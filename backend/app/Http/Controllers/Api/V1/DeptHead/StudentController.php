<?php

namespace App\Http\Controllers\Api\V1\DeptHead;

use App\Exports\DepartmentStudentExport;
use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\ExamAttempt;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class StudentController extends Controller
{
    /**
     * Resolve the department ID for the logged-in Department Head.
     */
    private function resolveDeptId(Request $request): ?int
    {
        $user = $request->user();
        if ($user->department_id) {
            return $user->department_id;
        }

        $dept = Department::where('head_id', $user->id)->first();
        if ($dept) {
            $user->update(['department_id' => $dept->id]);
            return $dept->id;
        }

        return null;
    }

    /**
     * Display a listing of students in the department with server-side
     * search, filtering, sorting, pagination, and real KPI metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $dept = $deptId ? Department::find($deptId) : null;

        $baseQuery = User::where('department_id', $deptId)
            ->where('role', 'student');

        // -------------------------------------------------------------
        // 1. Department-wide KPI Statistics (unfiltered by current query)
        // -------------------------------------------------------------
        $allDeptStudents = (clone $baseQuery)->withCount('examAttempts')->get();

        $totalStudents = $allDeptStudents->count();

        $activeStudents = $allDeptStudents->filter(function ($s) {
            return ($s->status ?? 'active') === 'active';
        })->count();

        $inactiveStudents = $allDeptStudents->filter(function ($s) {
            return ($s->status ?? '') === 'inactive';
        })->count();

        // Students enrolled / created in the past 6 months
        $newThisSemester = $allDeptStudents->filter(function ($s) {
            return $s->created_at && $s->created_at->greaterThanOrEqualTo(now()->subMonths(6));
        })->count();

        // Students with examination history
        $withExamRecords = $allDeptStudents->filter(function ($s) {
            return ($s->exam_attempts_count ?? 0) > 0;
        })->count();

        $stats = [
            'total'              => $totalStudents,
            'active'             => $activeStudents,
            'inactive'           => $inactiveStudents,
            'new_this_semester'  => $newThisSemester,
            'with_exam_records'  => $withExamRecords,
        ];

        // -------------------------------------------------------------
        // 2. Dynamic Available Filter Options for this Department
        // -------------------------------------------------------------
        $existingYears = $allDeptStudents->pluck('year_level')->filter()->unique()->values()->all();
        $defaultYears = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
        $availableYears = array_values(array_unique(array_merge($defaultYears, $existingYears)));

        $existingSections = $allDeptStudents->pluck('section')->filter()->unique()->values()->all();
        $defaultSections = ['Section A', 'Section B', 'Section C', 'Section D', 'A', 'B', 'C', 'D'];
        $availableSections = array_values(array_unique(array_merge($existingSections, $defaultSections)));

        $availableSemesters = $allDeptStudents->pluck('semester')->filter()->unique()->values()->all();
        if (empty($availableSemesters)) {
            $availableSemesters = ['First Semester', 'Second Semester', 'Summer'];
        }

        $availableAcademicYears = $allDeptStudents->pluck('academic_year')->filter()->unique()->values()->all();

        $filterOptions = [
            'year_levels'    => $availableYears,
            'sections'       => $availableSections,
            'semesters'      => $availableSemesters,
            'academic_years' => $availableAcademicYears,
            'statuses'       => ['active', 'inactive'],
        ];

        // -------------------------------------------------------------
        // 3. Search and Filtering
        // -------------------------------------------------------------
        $query = (clone $baseQuery)->withCount('examAttempts');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('id_no', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('year') && $request->year !== 'all') {
            $query->where('year_level', $request->year);
        }

        if ($request->filled('section') && $request->section !== 'all') {
            $query->where('section', $request->section);
        }

        if ($request->filled('semester') && $request->semester !== 'all') {
            $query->where('semester', $request->semester);
        }

        if ($request->filled('academic_year') && $request->academic_year !== 'all') {
            $query->where('academic_year', $request->academic_year);
        }

        // -------------------------------------------------------------
        // 4. Sorting
        // -------------------------------------------------------------
        $sortBy = $request->query('sort_by', 'created_at');
        $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['name', 'email', 'id_no', 'year_level', 'section', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->latest('created_at');
        }

        // -------------------------------------------------------------
        // 5. Pagination / Result
        // -------------------------------------------------------------
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min(100, $perPage));

        $paginated = $query->paginate($perPage);

        return response()->json([
            'data'           => $paginated->items(),
            'meta'           => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
            'stats'          => $stats,
            'filter_options' => $filterOptions,
            'department'     => $dept ? [
                'id'      => $dept->id,
                'name'    => $dept->name,
                'code'    => $dept->code,
                'college' => $dept->college,
            ] : null,
        ]);
    }

    /**
     * Display full details of a specific student, including real course
     * enrollments and exam attempts with scores and grades.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);

        $student = User::where('id', $id)
            ->where('department_id', $deptId)
            ->where('role', 'student')
            ->firstOrFail();

        // Load real exam attempts with exam relationships
        $attempts = ExamAttempt::where('user_id', $student->id)
            ->with(['exam' => function ($q) {
                $q->select('id', 'title', 'course_code', 'course_name', 'section', 'duration_minutes', 'total_marks', 'status');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // Compute real academic performance metrics
        $totalAttempts = $attempts->count();
        $submittedAttempts = $attempts->whereIn('status', ['published', 'submitted'])->count();
        $scoresWithPercentage = $attempts->filter(fn($a) => $a->percentage !== null && is_numeric($a->percentage));
        $avgPercentage = $scoresWithPercentage->isNotEmpty() ? round($scoresWithPercentage->avg('percentage'), 1) : null;
        $highestPercentage = $scoresWithPercentage->isNotEmpty() ? $scoresWithPercentage->max('percentage') : null;
        
        $passingGrades = ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'Pass'];
        $passedAttempts = $attempts->filter(function ($a) use ($passingGrades) {
            return in_array($a->grade, $passingGrades) || (is_numeric($a->percentage) && $a->percentage >= 50.0);
        })->count();

        // Relevant department courses matching student's level/section
        $enrolledCourses = Course::where('department_id', $deptId)
            ->when($student->year_level, function ($q) use ($student) {
                $q->where(function ($sub) use ($student) {
                    $sub->where('level', $student->year_level)
                        ->orWhereNull('level');
                });
            })
            ->with(['instructor' => function ($q) {
                $q->select('id', 'name', 'email');
            }])
            ->orderBy('code')
            ->get();

        $performance = [
            'total_attempts'     => $totalAttempts,
            'submitted_attempts' => $submittedAttempts,
            'passed_attempts'    => $passedAttempts,
            'average_percentage' => $avgPercentage,
            'highest_percentage' => $highestPercentage,
        ];

        return response()->json([
            'data'             => $student,
            'exam_attempts'    => $attempts,
            'performance'      => $performance,
            'enrolled_courses' => $enrolledCourses,
            'department'       => $student->department ? [
                'id'      => $student->department->id,
                'name'    => $student->department->name,
                'code'    => $student->department->code,
                'college' => $student->department->college,
            ] : null,
        ]);
    }

    /**
     * Update a student in the department.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);

        $student = User::where('id', $id)
            ->where('department_id', $deptId)
            ->where('role', 'student')
            ->firstOrFail();

        $validated = $request->validate([
            'name'            => 'sometimes|required|string|max:255',
            'email'           => 'sometimes|required|email|max:255|unique:users,email,' . $student->id,
            'id_no'           => 'sometimes|required|string|max:100|unique:users,id_no,' . $student->id,
            'phone'           => 'nullable|string|max:50',
            'gender'          => 'nullable|string|in:Male,Female,Other',
            'date_of_birth'   => 'nullable|date',
            'academic_year'   => 'nullable|string|max:50',
            'year_level'      => 'nullable|string|max:50',
            'semester'        => 'nullable|string|max:50',
            'section'         => 'nullable|string|max:50',
            'status'          => 'nullable|string|in:active,inactive',
            'username'        => 'nullable|string|max:100|unique:users,username,' . $student->id,
            'password'        => 'nullable|string|min:6',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        // Handle profile picture upload
        if ($request->hasFile('profile_picture')) {
            if ($student->profile_picture && Storage::disk('public')->exists($student->profile_picture)) {
                Storage::disk('public')->delete($student->profile_picture);
            }
            $path = $request->file('profile_picture')->store('avatars', 'public');
            $validated['profile_picture'] = $path;
        }

        // Handle password update
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $student->update($validated);

        LogActivity::record(
            'Updated',
            'Students',
            "Updated student profile \"{$student->name}\" (ID: {$student->id_no})"
        );

        return response()->json([
            'message' => 'Student updated successfully',
            'data'    => $student->fresh(),
        ]);
    }

    /**
     * Quick status update for a student (active / inactive).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);

        $student = User::where('id', $id)
            ->where('department_id', $deptId)
            ->where('role', 'student')
            ->firstOrFail();

        $validated = $request->validate([
            'status' => 'required|string|in:active,inactive',
        ]);

        $student->update(['status' => $validated['status']]);

        LogActivity::record(
            'Status Changed',
            'Students',
            "Changed student status of \"{$student->name}\" (ID: {$student->id_no}) to " . ucfirst($validated['status'])
        );

        return response()->json([
            'message' => 'Student status updated successfully',
            'data'    => $student->fresh(),
        ]);
    }

    /**
     * Remove a student from the department with academic integrity check.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);

        $student = User::where('id', $id)
            ->where('department_id', $deptId)
            ->where('role', 'student')
            ->firstOrFail();

        // Academic Integrity Check: prevent deletion if student has exam attempts
        $attemptCount = ExamAttempt::where('user_id', $student->id)->count();

        if ($attemptCount > 0 && !$request->boolean('force')) {
            return response()->json([
                'message'        => "Cannot permanently delete student \"{$student->name}\" because they have {$attemptCount} examination record(s) in the system. To preserve academic records and audit integrity, please deactivate the student instead.",
                'has_attempts'   => true,
                'attempts_count' => $attemptCount,
            ], 422);
        }

        $name = $student->name;
        $idNo = $student->id_no;

        if ($student->profile_picture && Storage::disk('public')->exists($student->profile_picture)) {
            Storage::disk('public')->delete($student->profile_picture);
        }

        $student->delete();

        LogActivity::record(
            'Deleted',
            'Students',
            "Removed student \"{$name}\" (ID: {$idNo}) from department records"
        );

        return response()->json([
            'message' => 'Student removed successfully',
        ]);
    }

    /**
     * Export department students as PDF or Excel (XLSX/CSV).
     */
    public function export(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $format = strtolower($request->query('format', 'excel'));

        $dept = Department::find($deptId);
        $deptName = $dept ? $dept->name : 'Department';

        $query = User::where('department_id', $deptId)
            ->where('role', 'student');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('year') && $request->year !== 'all') {
            $query->where('year_level', $request->year);
        }
        if ($request->filled('section') && $request->section !== 'all') {
            $query->where('section', $request->section);
        }
        if ($request->filled('semester') && $request->semester !== 'all') {
            $query->where('semester', $request->semester);
        }
        if ($request->filled('academic_year') && $request->academic_year !== 'all') {
            $query->where('academic_year', $request->academic_year);
        }
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('id_no', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $students = $query->with('department')->orderBy('name')->get();
        $dateStr = now()->format('Y-m-d');
        $sanitizedDept = preg_replace('/[^A-Za-z0-9_\-]/', '_', $deptName);
        $baseFileName = "{$sanitizedDept}_Students_{$dateStr}";

        if ($format === 'pdf') {
            return $this->exportStudentsPdf($students, $deptName, $baseFileName);
        }

        if ($format === 'csv') {
            return $this->exportStudentsCsv($students, $deptName, $baseFileName);
        }

        // Default: Excel (.xlsx)
        return $this->exportStudentsExcel($students, $deptName, $baseFileName);
    }

    /**
     * Export students as a formatted PDF using Dompdf.
     */
    private function exportStudentsPdf($students, string $deptName, string $baseFileName): JsonResponse
    {
        $generatedAt = now()->format('F j, Y  H:i');
        $totalRecords = $students->count();

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<title>' . htmlspecialchars($deptName) . ' — Students List</title>';
        $html .= '<style>
            @page { margin: 25px 25px 35px 25px; }
            body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 8px; color: #1e293b; }
            .header-table { width: 100%; border-bottom: 2px solid #5138ed; padding-bottom: 10px; margin-bottom: 12px; }
            .university-title { font-size: 15px; font-weight: bold; color: #1e1b4b; text-transform: uppercase; letter-spacing: 0.5px; }
            .dept-title { font-size: 11px; font-weight: bold; color: #5138ed; margin-top: 2px; }
            .meta { font-size: 8px; color: #64748b; text-align: right; line-height: 1.4; }
            table.data-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
            table.data-table th { background: #5138ed; color: #ffffff; padding: 6px 4px; text-align: left; font-size: 7.5px; font-weight: bold; text-transform: uppercase; }
            table.data-table td { padding: 5px 4px; border-bottom: 1px solid #e2e8f0; font-size: 7px; color: #334155; }
            table.data-table tr:nth-child(even) td { background: #f8fafc; }
            .badge-active { display: inline-block; padding: 2px 5px; border-radius: 4px; font-weight: bold; color: #15803d; background: #dcfce7; font-size: 6.5px; }
            .badge-inactive { display: inline-block; padding: 2px 5px; border-radius: 4px; font-weight: bold; color: #be123c; background: #ffe4e6; font-size: 6.5px; }
            .footer { position: fixed; bottom: 10px; left: 25px; right: 25px; font-size: 7.5px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 4px; }
        </style></head><body>';

        $html .= '<table class="header-table"><tr>';
        $html .= '<td><div class="university-title">Wollo University</div>';
        $html .= '<div class="dept-title">Department of ' . htmlspecialchars($deptName) . ' — Enrolled Students</div></td>';
        $html .= '<td class="meta"><strong>Date Generated:</strong> ' . $generatedAt . '<br><strong>Total Students:</strong> ' . $totalRecords . '</td>';
        $html .= '</tr></table>';

        $html .= '<table class="data-table"><thead><tr>';
        $html .= '<th style="width: 25px;">#</th>';
        $html .= '<th>Full Name</th>';
        $html .= '<th>Student ID</th>';
        $html .= '<th>Email Address</th>';
        $html .= '<th>Section</th>';
        $html .= '<th>Year Level</th>';
        $html .= '<th>Phone</th>';
        $html .= '<th>Gender</th>';
        $html .= '<th>Status</th>';
        $html .= '<th>Admission Date</th>';
        $html .= '</tr></thead><tbody>';

        if ($students->isEmpty()) {
            $html .= '<tr><td colspan="10" style="text-align: center; padding: 15px; color: #94a3b8;">No student records found in this department.</td></tr>';
        } else {
            foreach ($students as $i => $s) {
                $status = ($s->status ?? 'active') === 'active' ? 'active' : 'inactive';
                $statusBadge = $status === 'active'
                    ? '<span class="badge-active">Active</span>'
                    : '<span class="badge-inactive">Inactive</span>';

                $admissionDate = $s->created_at ? $s->created_at->format('M d, Y') : '—';
                $html .= '<tr>';
                $html .= '<td>' . ($i + 1) . '</td>';
                $html .= '<td><strong>' . htmlspecialchars($s->name ?? '') . '</strong></td>';
                $html .= '<td>' . htmlspecialchars($s->id_no ?? (string)$s->id) . '</td>';
                $html .= '<td>' . htmlspecialchars($s->email ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($s->section ?? '—') . '</td>';
                $html .= '<td>' . htmlspecialchars($s->year_level ?? '—') . '</td>';
                $html .= '<td>' . htmlspecialchars($s->phone ?? '—') . '</td>';
                $html .= '<td>' . htmlspecialchars($s->gender ?? '—') . '</td>';
                $html .= '<td>' . $statusBadge . '</td>';
                $html .= '<td>' . $admissionDate . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">Wollo University Online Examination System &bull; Confidential Department Document &bull; Generated by Department Head</div>';
        $html .= '</body></html>';

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $pdfOutput = $dompdf->output();

        LogActivity::record('Exported', 'Students', "Exported {$totalRecords} students list as PDF ({$deptName})");

        return response()->json([
            'file'     => base64_encode($pdfOutput),
            'filename' => $baseFileName . '.pdf',
            'format'   => 'pdf',
        ]);
    }

    /**
     * Export students as genuine Excel spreadsheet (.xlsx).
     */
    private function exportStudentsExcel($students, string $deptName, string $baseFileName): JsonResponse
    {
        $export = new DepartmentStudentExport($students);
        $xlsxBytes = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);

        LogActivity::record('Exported', 'Students', "Exported {$students->count()} students list as Excel ({$deptName})");

        return response()->json([
            'file'     => base64_encode($xlsxBytes),
            'filename' => $baseFileName . '.xlsx',
            'format'   => 'xlsx',
        ]);
    }

    /**
     * Export students as CSV (Excel-compatible UTF-8 BOM).
     */
    private function exportStudentsCsv($students, string $deptName, string $baseFileName): JsonResponse
    {
        ob_start();
        $handle = fopen('php://output', 'w');
        // UTF-8 BOM for Microsoft Excel auto-detect
        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            '#', 'Full Name', 'Student ID', 'Email', 'Section',
            'Year Level', 'Department', 'Phone', 'Gender', 'Status', 'Admission Date'
        ]);

        foreach ($students as $i => $s) {
            fputcsv($handle, [
                $i + 1,
                $s->name ?? '',
                $s->id_no ?? (string)$s->id,
                $s->email ?? '',
                $s->section ?? '—',
                $s->year_level ?? '—',
                $s->department ? $s->department->name : $deptName,
                $s->phone ?? '—',
                $s->gender ?? '—',
                ucfirst($s->status ?? 'active'),
                $s->created_at ? $s->created_at->format('M d, Y') : '',
            ]);
        }
        fclose($handle);
        $csvContent = ob_get_clean();

        LogActivity::record('Exported', 'Students', "Exported {$students->count()} students list as CSV ({$deptName})");

        return response()->json([
            'file'     => base64_encode($csvContent),
            'filename' => $baseFileName . '.csv',
            'format'   => 'csv',
        ]);
    }
}
