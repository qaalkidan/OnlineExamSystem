<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    /**
     * List all departments with their head info, relationship counts, and real platform stats.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Department::with('head:id,name,email,role,phone')
            ->withCount([
                'users as instructors_count' => function ($q) {
                    $q->whereIn('role', ['instructor', 'dept_head']);
                },
                'users as students_count' => function ($q) {
                    $q->where('role', 'student');
                },
                'courses as courses_count'
            ]);

        // Search filter
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', "%{$s}%")
                  ->orWhere('code', 'LIKE', "%{$s}%")
                  ->orWhere('college', 'LIKE', "%{$s}%")
                  ->orWhereHas('head', function ($sub) use ($s) {
                      $sub->where('name', 'LIKE', "%{$s}%")
                          ->orWhere('email', 'LIKE', "%{$s}%");
                  });
            });
        }

        // Status filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // College filter
        if ($request->filled('college') && $request->college !== 'all') {
            $query->where('college', $request->college);
        }

        $departments = $query->orderBy('name')->get()->map(function ($department) {
            $courseCodes = Course::where('department_id', $department->id)->pluck('code')->filter()->toArray();
            $instructorIds = User::where('department_id', $department->id)->whereIn('role', ['instructor', 'dept_head'])->pluck('id')->toArray();
            $department->exams_count = Exam::where(function($q) use ($courseCodes, $instructorIds) {
                $q->whereIn('course_code', $courseCodes)
                  ->orWhereIn('user_id', $instructorIds);
            })->count();
            return $department;
        });

        // 100% Real Database Statistics
        $stats = [
            'total'         => Department::count(),
            'active'        => Department::where('status', 'active')->count(),
            'inactive'      => Department::where('status', 'inactive')->count(),
            'students'      => User::where('role', 'student')->count(),
            'instructors'   => User::whereIn('role', ['instructor', 'dept_head'])->count(),
            'courses'       => Course::count(),
            'exams'         => Exam::count(),
            'new_this_year' => Department::whereYear('created_at', date('Y'))->count(),
        ];

        // Unique dynamic list of colleges from DB
        $colleges = Department::whereNotNull('college')->where('college', '!=', '')->pluck('college')->unique()->values();

        return response()->json([
            'data'     => $departments,
            'stats'    => $stats,
            'colleges' => $colleges,
            'message'  => 'Departments retrieved successfully.',
        ]);
    }

    /**
     * Get details for a specific department (including instructors, students, courses, exams, activities).
     */
    public function show(Department $department): JsonResponse
    {
        $department->load([
            'head:id,name,email,created_at,role,department_id,phone',
            'users' => function($q) {
                $q->select('id', 'name', 'email', 'role', 'department_id', 'phone', 'created_at');
            },
            'courses' => function($q) {
                $q->with('instructor:id,name,email');
            }
        ]);

        $activityLogs = ActivityLog::with('user:id,name')
            ->where('department_id', $department->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Separate users into instructors and students
        $instructors = $department->users->filter(fn($u) => in_array($u->role, ['instructor', 'dept_head']))->values();
        $students = $department->users->filter(fn($u) => $u->role === 'student')->values();

        $courseCodes = $department->courses->pluck('code')->filter()->toArray();
        $instructorIds = $instructors->pluck('id')->toArray();

        // Real exams associated with this department's courses or faculty
        $exams = Exam::where(function($q) use ($courseCodes, $instructorIds) {
            $q->whereIn('course_code', $courseCodes)
              ->orWhereIn('user_id', $instructorIds);
        })->with('course:id,title,code')->latest()->take(20)->get();

        // Calculate counts
        $department->instructors_count = $instructors->count();
        $department->students_count = $students->count();
        $department->courses_count = $department->courses->count();
        $department->exams_count = $exams->count();

        return response()->json([
            'data' => [
                'department'  => $department,
                'instructors' => $instructors,
                'students'    => $students,
                'courses'     => $department->courses,
                'exams'       => $exams,
                'activities'  => $activityLogs,
            ],
            'message' => 'Department details retrieved successfully.',
        ]);
    }

    /**
     * Create a new department.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:20|unique:departments,code',
            'college'     => 'required|string|max:255',
            'established' => 'nullable|max:4',
            'status'      => 'nullable|in:active,inactive',
        ], [
            'name.required'    => 'Department Name is required.',
            'code.required'    => 'Department Code is required.',
            'code.unique'      => 'A department with this code already exists.',
            'college.required' => 'College/School is required.',
        ]);

        $department = Department::create([
            'name'        => $request->name,
            'code'        => strtoupper($request->code),
            'college'     => $request->college,
            'established' => $request->established,
            'status'      => $request->status ?? 'active',
        ]);

        LogActivity::record(
            'Created',
            'Departments',
            "Created a new department \"{$department->name}\"",
            'Success',
            $department->id
        );

        return response()->json([
            'data'    => $department->load('head:id,name,email'),
            'message' => 'Department created successfully.',
        ], 201);
    }

    /**
     * Assign an existing instructor as the department head.
     */
    public function assignHead(Request $request, Department $department): JsonResponse
    {
        $request->validate([
            'instructor_id' => 'required|exists:users,id',
        ]);

        $instructor = User::findOrFail($request->instructor_id);

        // Ensure the instructor belongs to this department
        if ($instructor->department_id != $department->id) {
            return response()->json([
                'message' => 'The selected instructor does not belong to this department.',
                'errors' => [
                    'instructor_id' => ['Only instructors belonging to this department can be assigned as Department Head.']
                ]
            ], 422);
        }

        DB::transaction(function () use ($department, $instructor) {
            // If there was a previous head, revert them to instructor role
            if ($department->head_id && $department->head_id !== $instructor->id) {
                User::where('id', $department->head_id)->update(['role' => 'instructor']);
            }

            // Promote the instructor to dept_head
            $instructor->update([
                'role'          => 'dept_head',
                'department_id' => $department->id,
            ]);

            // Link the head to the department
            $department->update(['head_id' => $instructor->id]);

            LogActivity::record(
                'Updated',
                'Departments',
                "Assigned department head for \"{$department->name}\" to \"{$instructor->name}\"",
                'Success',
                $department->id
            );
        });

        return response()->json([
            'data'    => $department->load('head:id,name,email'),
            'message' => 'Department head assigned successfully.',
        ]);
    }

    /**
     * Update a department.
     */
    public function update(Request $request, Department $department): JsonResponse
    {
        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'code'        => 'sometimes|string|max:20|unique:departments,code,' . $department->id,
            'college'     => 'nullable|string|max:255',
            'established' => 'nullable|max:4',
            'status'      => 'sometimes|in:active,inactive',
        ]);

        $data = $request->only(['name', 'code', 'college', 'established', 'status']);
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $department->update($data);

        if ($request->has('status')) {
            LogActivity::record(
                'Updated',
                'Departments',
                "Changed status of department \"{$department->name}\" to " . ucfirst($department->status),
                'Success',
                $department->id
            );
        } else {
            LogActivity::record(
                'Updated',
                'Departments',
                "Updated department \"{$department->name}\"",
                'Success',
                $department->id
            );
        }

        return response()->json([
            'data'    => $department->load('head:id,name,email'),
            'message' => 'Department updated successfully.',
        ]);
    }

    /**
     * Toggle status between active and inactive.
     */
    public function toggleStatus(Request $request, Department $department): JsonResponse
    {
        $newStatus = $request->filled('status') ? $request->status : ($department->status === 'active' ? 'inactive' : 'active');
        $department->status = $newStatus;
        $department->save();

        LogActivity::record(
            'Updated',
            'Departments',
            "Changed status of department \"{$department->name}\" to " . ucfirst($newStatus),
            'Success',
            $department->id
        );

        return response()->json([
            'data'    => $department->load('head:id,name,email'),
            'message' => "Department marked as " . ucfirst($newStatus) . " successfully.",
        ]);
    }

    /**
     * Delete a department.
     */
    public function destroy(Department $department): JsonResponse
    {
        $deptName = $department->name;
        
        DB::transaction(function () use ($department) {
            // Revert head back to instructor if exists
            if ($department->head_id) {
                User::where('id', $department->head_id)->update(['role' => 'instructor']);
            }
            $department->delete();
        });

        LogActivity::record(
            'Deleted',
            'Departments',
            "Deleted department \"$deptName\"",
            'Success',
            $department->id
        );

        return response()->json([
            'message' => 'Department deleted successfully.',
        ]);
    }

    /**
     * Export departments as CSV or printable PDF/HTML report.
     */
    public function export(Request $request)
    {
        $format = $request->query('format', 'csv');
        $query = Department::with('head:id,name,email')
            ->withCount([
                'users as instructors_count' => function ($q) {
                    $q->whereIn('role', ['instructor', 'dept_head']);
                },
                'users as students_count' => function ($q) {
                    $q->where('role', 'student');
                },
                'courses as courses_count'
            ]);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('college') && $request->college !== 'all') {
            $query->where('college', $request->college);
        }

        $departments = $query->orderBy('name')->get();
        $fileName = 'wollo_university_departments_' . date('Y-m-d');

        if ($format === 'pdf') {
            return $this->exportDepartmentsPdf($departments, $fileName);
        }

        return $this->exportDepartmentsCsv($departments, $fileName);
    }

    private function exportDepartmentsCsv($departments, string $fileName)
    {
        ob_start();
        $handle = fopen('php://output', 'w');
        fputs($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'ID', 'Department Name', 'Code', 'College/Faculty',
            'Department Head', 'Head Email', 'Students', 'Instructors', 'Courses',
            'Status', 'Established Year', 'Created Date'
        ]);

        foreach ($departments as $dept) {
            fputcsv($handle, [
                $dept->id,
                $dept->name,
                $dept->code,
                $dept->college ?: 'General',
                $dept->head?->name ?: 'Not Assigned',
                $dept->head?->email ?: '',
                $dept->students_count ?? 0,
                $dept->instructors_count ?? 0,
                $dept->courses_count ?? 0,
                ucfirst($dept->status ?? 'Active'),
                $dept->established ?: 'N/A',
                $dept->created_at ? $dept->created_at->format('Y-m-d') : '',
            ]);
        }

        fclose($handle);
        $csvContent = ob_get_clean();

        return response()->json([
            'file'     => base64_encode($csvContent),
            'filename' => $fileName . '.csv'
        ]);
    }

    private function exportDepartmentsPdf($departments, string $fileName)
    {
        $generatedAt = now()->format('F j, Y  H:i');

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 20px; }
            .header { border-bottom: 2px solid #4338ca; padding-bottom: 12px; margin-bottom: 16px; }
            .inst-name { font-size: 18px; font-weight: bold; color: #4338ca; text-transform: uppercase; letter-spacing: 0.5px; }
            .doc-title { font-size: 13px; font-weight: bold; color: #0f172a; margin-top: 4px; }
            .meta { font-size: 9px; color: #64748b; margin-top: 4px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background-color: #f1f5f9; color: #334155; font-size: 9px; font-weight: bold; text-align: left; padding: 6px 8px; border: 1px solid #cbd5e1; }
            td { padding: 5px 8px; border: 1px solid #e2e8f0; font-size: 9px; vertical-align: top; }
            tr:nth-child(even) td { background-color: #f8fafc; }
            .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: bold; text-transform: uppercase; }
            .badge-active { background: #ecfdf5; color: #047857; }
            .badge-inactive { background: #f1f5f9; color: #475569; }
            .footer { margin-top: 20px; border-top: 1px solid #cbd5e1; padding-top: 8px; font-size: 8px; color: #94a3b8; display: flex; justify-content: space-between; }
        </style></head><body>';

        $html .= '<div class="header">
            <div class="inst-name">Wollo University</div>
            <div class="doc-title">Office of Academic Affairs &bull; Academic Departments Official Register</div>
            <div class="meta">Generated: ' . $generatedAt . ' &bull; Total Departments: ' . $departments->count() . '</div>
        </div>';

        $html .= '<table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Department Name</th>
                    <th>College / Faculty</th>
                    <th>Department Head</th>
                    <th>Students</th>
                    <th>Faculty</th>
                    <th>Courses</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($departments as $dept) {
            $status = strtolower($dept->status ?? 'active');
            $html .= '<tr>
                <td style="font-family:monospace;font-weight:bold;">' . htmlspecialchars($dept->code) . '</td>
                <td><strong>' . htmlspecialchars($dept->name) . '</strong></td>
                <td>' . htmlspecialchars($dept->college ?: 'General') . '</td>
                <td>' . htmlspecialchars($dept->head?->name ?? 'Not Assigned') . '</td>
                <td>' . ($dept->students_count ?? 0) . '</td>
                <td>' . ($dept->instructors_count ?? 0) . '</td>
                <td>' . ($dept->courses_count ?? 0) . '</td>
                <td><span class="badge badge-' . $status . '">' . htmlspecialchars(ucfirst($status)) . '</span></td>
            </tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer"><div>Wollo University &bull; Academic Department Management Center</div><div>Confidential & Official Document</div></div>';
        $html .= '</body></html>';

        return response()->json([
            'file'     => base64_encode($html),
            'filename' => $fileName . '.html'
        ]);
    }
}
