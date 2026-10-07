<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Models\Department;
use App\Helpers\LogActivity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Dompdf\Dompdf;
use Dompdf\Options;
use Smalot\PdfParser\Parser;

class AdminCourseController extends Controller
{
    /**
     * Display a listing of courses.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Course::with(['department', 'instructor', 'coInstructor', 'creator', 'assignedInstructors']);

        if ($request->filled('department_id') && $request->department_id !== 'all') {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('level') && $request->level !== 'all') {
            $query->where('level', $request->level);
        }
        if ($request->filled('semester') && $request->semester !== 'all') {
            $query->where('semester', $request->semester);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        $courses = $query->latest()->get();

        // 100% Real Database Metrics
        $total = Course::count();
        $active = Course::where('status', 'active')->count();
        $inactive = Course::where('status', 'inactive')->count();
        $currentSemCount = Course::where(function($q) {
            $q->where('semester', 'Second Semester')
              ->orWhere('semester', 'like', '%Second%')
              ->orWhere('semester', 'like', '%2%');
        })->count();
        $assigned = Course::where(function($q) {
            $q->whereNotNull('instructor_id')->orWhereNotNull('co_instructor_id');
        })->count();
        $unassigned = Course::whereNull('instructor_id')->whereNull('co_instructor_id')->count();
        $totalCredits = (int) Course::sum('credits');

        $thirtyDaysAgo = now()->subDays(30);
        $newCourses = Course::where('created_at', '>=', $thirtyDaysAgo)->count();

        $previousPeriodStart = now()->subDays(60);
        $previousNew = Course::whereBetween('created_at', [$previousPeriodStart, $thirtyDaysAgo])->count();
        $growth = null;
        if ($previousNew > 0) {
            $diff = $newCourses - $previousNew;
            $rate = round(($diff / $previousNew) * 100, 1);
            $growth = [
                'rate' => $rate,
                'formatted' => ($rate >= 0 ? '+' : '') . $rate . '%',
                'trend' => $rate >= 0 ? 'up' : 'down',
            ];
        }

        return response()->json([
            'data' => $courses,
            'stats' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'this_semester' => $currentSemCount,
                'assigned' => $assigned,
                'unassigned' => $unassigned,
                'total_credits' => $totalCredits,
                'new_courses' => $newCourses,
                'growth' => $growth,
            ],
        ]);
    }

    /**
     * Store a newly created course.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'code' => 'required|string|unique:courses,code',
            'credits' => 'required|integer',
            'semester' => 'nullable|string',
            'department_id' => 'required|exists:departments,id',
            'instructor_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($request) {
                    $instructor = User::where('id', $value)->where('department_id', $request->department_id)->where('role', 'instructor')->first();
                    if (!$instructor) {
                        $fail('The selected instructor is invalid or does not belong to the selected department.');
                    }
                },
            ],
            'level' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $course = Course::create([
            'title' => $request->title,
            'code' => $request->code,
            'credits' => $request->credits,
            'semester' => $request->semester,
            'department_id' => $request->department_id,
            'instructor_id' => $request->instructor_id,
            'level' => $request->level,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        LogActivity::record(
            'Created',
            'Courses',
            "Created a new course \"{$course->title}\""
        );

        return response()->json([
            'message' => 'Course created successfully',
            'data' => $course->load(['department', 'instructor', 'coInstructor', 'assignedInstructors'])
        ], 201);
    }

    /**
     * Display the specified course.
     */
    public function show(string $id): JsonResponse
    {
        $course = Course::with(['department', 'instructor', 'coInstructor', 'creator', 'assignedInstructors'])->findOrFail($id);
        return response()->json(['data' => $course]);
    }

    /**
     * Update the specified course.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $course = Course::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:courses,code,' . $course->id,
            'credits' => 'sometimes|integer',
            'semester' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
            'department_id' => 'sometimes|exists:departments,id',
            'section' => 'nullable|string',
            'instructor_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($request, $course) {
                    $deptId = $request->input('department_id', $course->department_id);
                    $instructor = User::where('id', $value)->where('department_id', $deptId)->whereIn('role', ['instructor', 'dept_head'])->first();
                    if (!$instructor) {
                        $fail('The selected instructor is invalid or does not belong to the department.');
                    }
                },
            ],
            'co_instructor_id' => 'nullable|exists:users,id',
            'level' => 'sometimes|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $course->update($request->only(['title', 'code', 'credits', 'semester', 'level', 'section', 'start_date', 'end_date', 'status', 'department_id', 'instructor_id', 'co_instructor_id']));

        // Sync instructor's section and year_level when assigning
        if ($request->has('instructor_id') && $request->instructor_id) {
            $instSection = $request->section === 'Both Sections' ? 'Section A' : ($request->section ?: ($course->section ?: 'Section A'));
            User::where('id', $request->instructor_id)->update([
                'section'    => $instSection,
                'year_level' => $course->level,
                'course_code' => $course->code,
                'course_name' => $course->title,
            ]);
        }
        if ($request->has('co_instructor_id') && $request->co_instructor_id) {
            $coSection = ($request->section === 'Both Sections' || $request->section === 'Section A') ? 'Section B' : ($request->co_instructor_section ?? 'Section B');
            User::where('id', $request->co_instructor_id)->update([
                'section'    => $coSection,
                'year_level' => $course->level,
                'course_code' => $course->code,
                'course_name' => $course->title,
            ]);
        }

        LogActivity::record(
            'Updated',
            'Courses',
            "Updated course \"{$course->title}\""
        );

        return response()->json([
            'message' => 'Course updated successfully',
            'data' => $course->load(['department', 'instructor', 'coInstructor', 'creator', 'assignedInstructors'])
        ]);
    }

    /**
     * Delete a course.
     */
    public function destroy(Course $course): JsonResponse
    {
        $courseTitle = $course->title;
        $course->delete();

        LogActivity::record(
            'Deleted',
            'Courses',
            "Deleted course \"$courseTitle\""
        );

        return response()->json(['message' => 'Course deleted successfully.']);
    }

/**
     * Export courses as CSV (Excel-compatible) or PDF.
     * Pure PHP — no ZipArchive needed.
     */
    public function export(Request $request)
    {
        $format = $request->query('format', 'csv');
        $query = Course::with('department')->latest();

        // Apply filters
        if ($request->filled('department') && $request->department !== 'all') {
            $query->whereHas('department', function ($q) use ($request) {
                $q->where('name', $request->department);
            });
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('level') && $request->level !== 'all') {
            $query->where('level', $request->level);
        }
        if ($request->filled('semester') && $request->semester !== 'all') {
            $query->where('semester', $request->semester);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        $courses = $query->get();
        $fileName = 'courses_export_' . date('Y-m-d');

        if ($format === 'pdf') {
            return $this->exportCoursesPdf($courses, $fileName);
        }

        return $this->exportCoursesCsv($courses, $fileName);
    }

    private function exportCoursesCsv($courses, string $fileName)
    {
        ob_start();
        $handle = fopen('php://output', 'w');
        fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
        fputcsv($handle, [
            'ID', 'Course Name', 'Course Code', 'Description',
            'Department', 'Credits', 'Level', 'Semester',
            'Start Date', 'End Date', 'Status', 'Created Date'
        ]);
        foreach ($courses as $course) {
            fputcsv($handle, [
                $course->id,
                $course->title ?? $course->name ?? '',
                $course->code ?? '',
                $course->description ?? '',
                $course->department ? $course->department->name : '',
                $course->credits ?? '',
                $course->level ?? '',
                $course->semester ?? '',
                $course->start_date ?? '',
                $course->end_date ?? '',
                $course->status ?? 'active',
                $course->created_at ? $course->created_at->format('Y-m-d') : '',
            ]);
        }
        fclose($handle);
        $csvContent = ob_get_clean();

        return response()->json([
            'file' => base64_encode($csvContent),
            'filename' => $fileName . '.csv'
        ]);
    }

    private function exportCoursesPdf($courses, string $fileName)
    {
        $generatedAt = now()->format('F j, Y  H:i');

        $html = '<html><head><style>
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #1e293b; }
            h2 { color: #4338ca; margin-bottom: 4px; font-size: 14px; }
            .meta { color: #64748b; font-size: 8px; margin-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #4338ca; color: #fff; padding: 5px 4px; text-align: left; font-size: 7.5px; }
            td { padding: 4px; border-bottom: 1px solid #e2e8f0; font-size: 7px; }
            tr:nth-child(even) td { background: #f8fafc; }
            .badge-active { color: #10b981; font-weight: bold; }
            .badge-inactive { color: #ef4444; font-weight: bold; }
        </style></head><body>';
        $html .= '<h2>Course Export</h2>';
        $html .= '<div class="meta">Generated: ' . $generatedAt . ' &nbsp;|&nbsp; Total records: ' . $courses->count() . '</div>';
        $html .= '<table><thead><tr>
            <th>#</th><th>Course Name</th><th>Code</th><th>Department</th>
            <th>Credits</th><th>Level</th><th>Semester</th>
            <th>Start</th><th>End</th><th>Status</th>
        </tr></thead><tbody>';
        foreach ($courses as $i => $course) {
            $statusClass = ($course->status === 'active') ? 'badge-active' : 'badge-inactive';
            $html .= '<tr>
                <td>' . ($i + 1) . '</td>
                <td>' . htmlspecialchars($course->title ?? $course->name ?? '') . '</td>
                <td>' . htmlspecialchars($course->code ?? '') . '</td>
                <td>' . htmlspecialchars($course->department ? $course->department->name : '') . '</td>
                <td>' . htmlspecialchars($course->credits ?? '') . '</td>
                <td>' . htmlspecialchars($course->level ?? '') . '</td>
                <td>' . htmlspecialchars($course->semester ?? '') . '</td>
                <td>' . htmlspecialchars($course->start_date ?? '') . '</td>
                <td>' . htmlspecialchars($course->end_date ?? '') . '</td>
                <td class="' . $statusClass . '">' . ucfirst($course->status ?? 'active') . '</td>
            </tr>';
        }
        $html .= '</tbody></table></body></html>';

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $pdfContent = $dompdf->output();

        return response()->json([
            'file' => base64_encode($pdfContent),
            'filename' => $fileName . '.pdf'
        ]);
    }

    /**
     * Import courses from a CSV or PDF file.
     * Validates that the file fulfills the Add Course form requirements.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();

        if (!in_array($ext, ['csv', 'pdf', 'txt']) && !in_array($mime, ['text/csv', 'application/pdf', 'text/plain'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file format. Please upload a CSV (.csv) or PDF (.pdf) file.',
                'errors' => ['Only .csv and .pdf file extensions are supported for course import.']
            ], 422);
        }

        $filePath = $file->getPathname();
        $fileName = $file->getClientOriginalName();
        $rows = [];

        try {
            if ($ext === 'pdf' || $mime === 'application/pdf') {
                $rows = $this->parsePdfRows($filePath);
            } else {
                $rows = $this->parseCsvRows($filePath);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse file: ' . $e->getMessage(),
                'errors' => ['The file could not be parsed. Please verify the file is not corrupted and is a valid CSV or PDF document.']
            ], 422);
        }

        if (empty($rows)) {
            return response()->json([
                'success' => false,
                'message' => 'The uploaded file is empty or contains no readable course table data.',
                'errors' => ['No data rows could be extracted from the uploaded file. Please ensure the file has a valid table with headers.']
            ], 422);
        }

        // Cache departments for smart matching
        $departments = Department::all(['id', 'name', 'code'])->toArray();
        $availableDeptNames = array_map(fn($d) => $d['name'] . ($d['code'] ? " ({$d['code']})" : ''), $departments);

        // Normalize rows
        $normalizedRows = [];
        foreach ($rows as $row) {
            $norm = $this->normalizeCourseRowKeys($row);
            $normalizedRows[] = $norm;
        }

        // Check for required column headers across the file
        $hasCode = false;
        $hasTitle = false;
        $hasDept = false;

        foreach ($normalizedRows as $r) {
            if (!empty($r['code'])) $hasCode = true;
            if (!empty($r['title'])) $hasTitle = true;
            if (!empty($r['department'])) $hasDept = true;
        }

        $missingHeaders = [];
        if (!$hasCode) $missingHeaders[] = 'Course Code';
        if (!$hasTitle) $missingHeaders[] = 'Course Title';
        if (!$hasDept) $missingHeaders[] = 'Department';

        if (!empty($missingHeaders)) {
            return response()->json([
                'success' => false,
                'message' => 'The file format does not fulfill the Add Course form requirements. Missing column(s): ' . implode(', ', $missingHeaders),
                'errors' => [
                    'Missing required column(s): ' . implode(', ', $missingHeaders) . '.',
                    'The course file MUST fulfill the Add Course form format with these columns:',
                    '• Course Code (required, e.g. "CS-301", "C-57")',
                    '• Course Title / Name (required, e.g. "Compiler Design", "Machine Learning")',
                    '• Department (required, e.g. "Software Engineering", "Computer Science")',
                    'Optional columns: Academic Year Level (defaults to 1st Year), Credits (defaults to 3), Semester, Section, Status, Start Date, End Date, Description'
                ],
                'format_guide' => [
                    'required_columns' => ['Course Code', 'Course Title', 'Department'],
                    'optional_columns' => ['Academic Year Level', 'Credits', 'Semester', 'Section', 'Status', 'Start Date', 'End Date', 'Description'],
                    'available_departments' => $availableDeptNames,
                    'valid_levels' => ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'],
                ]
            ], 422);
        }

        // Active term default
        $defaultSemester = '1st Semester';
        try {
            $settings = \App\Models\SystemSetting::first();
            if ($settings && !empty($settings->semester)) {
                $defaultSemester = $settings->semester;
            }
        } catch (\Exception $e) {}

        // Row-by-row validation
        $rowErrors = [];
        $validCourses = [];
        $seenCodesInFile = [];

        foreach ($normalizedRows as $idx => $r) {
            $rowNum = $idx + 2; // account for header line

            $code = trim($r['code'] ?? '');
            $title = trim($r['title'] ?? '');
            $deptName = trim($r['department'] ?? '');
            $level = $this->normalizeLevel($r['level'] ?? null);
            $credits = $this->normalizeCredits($r['credits'] ?? null);
            $semester = trim($r['semester'] ?? '') ?: $defaultSemester;
            $section = trim($r['section'] ?? '') ?: null;
            $status = $this->normalizeStatus($r['status'] ?? null);
            $startDate = trim($r['start_date'] ?? '') ?: null;
            $endDate = trim($r['end_date'] ?? '') ?: null;
            $description = trim($r['description'] ?? '') ?: null;

            // 1. Check Course Code
            if (empty($code)) {
                $rowErrors[] = "Row {$rowNum}: Course Code is required.";
                continue;
            }

            $cleanCodeUpper = strtoupper($code);

            // Duplicate in uploaded file
            if (isset($seenCodesInFile[$cleanCodeUpper])) {
                $rowErrors[] = "Row {$rowNum}: Duplicate Course Code '{$code}' found in file (already used on row {$seenCodesInFile[$cleanCodeUpper]}).";
                continue;
            }
            $seenCodesInFile[$cleanCodeUpper] = $rowNum;

            // Duplicate in database
            if (Course::where('code', $code)->exists()) {
                $rowErrors[] = "Row {$rowNum}: Course Code '{$code}' already exists in the system database.";
                continue;
            }

            // 2. Check Course Title
            if (empty($title)) {
                $rowErrors[] = "Row {$rowNum}: Course Title is required.";
                continue;
            }

            // 3. Check Department
            if (empty($deptName)) {
                $rowErrors[] = "Row {$rowNum}: Department is required for course '{$title}'.";
                continue;
            }

            $deptId = $this->findDepartmentId($deptName, $departments);
            if (!$deptId) {
                $rowErrors[] = "Row {$rowNum}: Department '{$deptName}' was not recognized. Please use one of: " . implode(', ', array_slice($availableDeptNames, 0, 5)) . '...';
                continue;
            }

            // 4. Validate Dates if provided
            if ($startDate && !strtotime($startDate)) {
                $rowErrors[] = "Row {$rowNum}: Invalid Start Date format '{$startDate}'.";
                continue;
            }
            if ($endDate && !strtotime($endDate)) {
                $rowErrors[] = "Row {$rowNum}: Invalid End Date format '{$endDate}'.";
                continue;
            }
            if ($startDate && $endDate && strtotime($endDate) < strtotime($startDate)) {
                $rowErrors[] = "Row {$rowNum}: End Date ({$endDate}) cannot be before Start Date ({$startDate}).";
                continue;
            }

            $validCourses[] = [
                'code'          => $code,
                'title'         => $title,
                'department_id' => $deptId,
                'level'         => $level,
                'credits'       => $credits,
                'semester'      => $semester,
                'section'       => $section,
                'status'        => $status,
                'start_date'    => $startDate ? date('Y-m-d', strtotime($startDate)) : null,
                'end_date'      => $endDate ? date('Y-m-d', strtotime($endDate)) : null,
                'description'   => $description,
                'created_by'    => $request->user()?->id ?? 1,
            ];
        }

        // If any rows have errors, reject the import and present all issues to admin
        if (!empty($rowErrors)) {
            $errCount = count($rowErrors);
            return response()->json([
                'success' => false,
                'message' => "The uploaded file has {$errCount} issue(s) that need to be resolved before importing.",
                'errors'  => $rowErrors,
                'format_guide' => [
                    'required_columns' => ['Course Code', 'Course Title', 'Department'],
                    'optional_columns' => ['Academic Year Level', 'Credits', 'Semester', 'Section', 'Status', 'Start Date', 'End Date', 'Description'],
                    'available_departments' => $availableDeptNames,
                    'valid_levels' => ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'],
                ]
            ], 422);
        }

        if (empty($validCourses)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid course records found in the file to import.',
                'errors'  => ['No valid course records could be extracted. Please check the file formatting.']
            ], 422);
        }

        // Execute batch insertion in transaction
        $importedCourses = [];
        DB::beginTransaction();
        try {
            foreach ($validCourses as $courseData) {
                $created = Course::create($courseData);
                $importedCourses[] = [
                    'id'         => $created->id,
                    'title'      => $created->title,
                    'code'       => $created->code,
                    'department' => $created->department?->name ?? 'N/A',
                    'level'      => $created->level,
                    'credits'    => $created->credits,
                    'semester'   => $created->semester,
                    'status'     => $created->status,
                ];
            }

            LogActivity::record(
                'Imported',
                'Courses',
                "Batch imported " . count($importedCourses) . " course(s) from file \"{$fileName}\""
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Database error while saving imported courses: ' . $e->getMessage(),
                'errors'  => ['A database error occurred during import. All changes were rolled back safely.']
            ], 500);
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Successfully imported ' . count($importedCourses) . ' course(s).',
            'imported' => count($importedCourses),
            'courses'  => $importedCourses,
        ]);
    }

    /**
     * Parse table rows from CSV file.
     */
    private function parseCsvRows(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) return [];

        $firstLine = fgets($handle);
        if (!$firstLine) {
            fclose($handle);
            return [];
        }

        $delimiters = [',', ';', "\t"];
        $bestDelim = ',';
        $maxCount = 0;
        foreach ($delimiters as $d) {
            $count = substr_count($firstLine, $d);
            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelim = $d;
            }
        }
        rewind($handle);

        // Strip UTF-8 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headings = fgetcsv($handle, 0, $bestDelim);
        if (!$headings) {
            fclose($handle);
            return [];
        }

        $headings = array_map(fn($h) => strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', (string)$h), '_')), $headings);

        $rows = [];
        while (($data = fgetcsv($handle, 0, $bestDelim)) !== false) {
            if (empty(array_filter($data, fn($v) => trim((string)$v) !== ''))) continue;
            $row = [];
            foreach ($headings as $i => $h) {
                if ($h === '') continue;
                $row[$h] = isset($data[$i]) ? trim((string)$data[$i]) : '';
            }
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Parse table rows from PDF file.
     */
    private function parsePdfRows(string $filePath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $allRows = [];

        foreach ($pdf->getPages() as $page) {
            $tm = $page->getDataTm();
            if (!empty($tm)) {
                $rowsByY = [];
                foreach ($tm as $item) {
                    $x = $item[0][4];
                    $y = $item[0][5];
                    $text = trim($item[1]);
                    if ($text === '') continue;

                    $foundY = null;
                    foreach (array_keys($rowsByY) as $existingY) {
                        if (abs($existingY - $y) <= 4.0) {
                            $foundY = $existingY;
                            break;
                        }
                    }
                    if ($foundY === null) {
                        $foundY = $y;
                        $rowsByY[$foundY] = [];
                    }
                    $rowsByY[$foundY][] = ['x' => $x, 'text' => $text];
                }
                krsort($rowsByY);

                $headerRow = null;
                $dataRows = [];

                foreach ($rowsByY as $y => $cells) {
                    usort($cells, fn($a, $b) => $a['x'] <=> $b['x']);
                    $rowText = strtolower(implode(' ', array_map(fn($c) => $c['text'], $cells)));

                    if (!$headerRow && count($cells) >= 3 && (str_contains($rowText, 'course') || str_contains($rowText, 'code') || str_contains($rowText, 'title') || str_contains($rowText, 'department'))) {
                        $headerRow = $cells;
                        continue;
                    }
                    if ($headerRow) {
                        if (count($cells) >= 3 && (str_contains($rowText, 'course name') || (str_contains($rowText, 'code') && str_contains($rowText, 'department')))) {
                            continue; // repeated header on multi-page
                        }
                        $dataRows[] = $cells;
                    }
                }

                if ($headerRow && !empty($dataRows)) {
                    $colsCount = count($headerRow);
                    $colDefs = [];
                    for ($i = 0; $i < $colsCount; $i++) {
                        $currentX = $headerRow[$i]['x'];
                        $prevMid = ($i === 0) ? -9999 : ($headerRow[$i - 1]['x'] + $currentX) / 2;
                        $nextMid = ($i === $colsCount - 1) ? 9999 : ($currentX + $headerRow[$i + 1]['x']) / 2;
                        $rawName = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $headerRow[$i]['text']), '_'));
                        if ($rawName === '') {
                            $rawName = ($headerRow[$i]['text'] === '#') ? 'row_index' : 'col_' . $i;
                        }
                        $colDefs[] = [
                            'name'  => $rawName,
                            'min_x' => $prevMid,
                            'max_x' => $nextMid,
                        ];
                    }

                    $coordRows = [];
                    foreach ($dataRows as $cells) {
                        $record = array_fill_keys(array_column($colDefs, 'name'), '');
                        foreach ($cells as $cell) {
                            foreach ($colDefs as $col) {
                                if ($cell['x'] >= $col['min_x'] && $cell['x'] < $col['max_x']) {
                                    $record[$col['name']] = trim($record[$col['name']] . ' ' . $cell['text']);
                                    break;
                                }
                            }
                        }
                        if (!empty(array_filter($record))) {
                            $coordRows[] = $record;
                        }
                    }

                    if (!empty($coordRows)) {
                        $allRows = array_merge($allRows, $coordRows);
                        continue;
                    }
                }
            }

            // Strategy 2: Text array matching fallback
            $textArray = array_values(array_filter(array_map('trim', $page->getTextArray()), fn($v) => $v !== ''));
            if (!empty($textArray)) {
                $headerStartIndex = null;
                foreach ($textArray as $idx => $item) {
                    $lower = strtolower($item);
                    if ($lower === '#' || $lower === 'course name' || $lower === 'course' || $lower === 'code' || $lower === 'course code' || $lower === 'course title') {
                        $headerStartIndex = $idx;
                        break;
                    }
                }

                if ($headerStartIndex !== null) {
                    $headers = [];
                    $dataStartIndex = null;
                    for ($i = $headerStartIndex; $i < count($textArray); $i++) {
                        $item = $textArray[$i];
                        if (!empty($headers) && ($item === '1' || str_contains($item, '-') || preg_match('/^[A-Z]{2,}/', $item))) {
                            $dataStartIndex = $i;
                            break;
                        }
                        $normHeader = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $item), '_'));
                        if ($normHeader === '' && $item === '#') {
                            $normHeader = 'row_index';
                        }
                        if ($normHeader !== '') {
                            $headers[] = $normHeader;
                        }
                    }

                    $numCols = count($headers);
                    if ($dataStartIndex !== null && $numCols >= 3) {
                        $dataSlice = array_slice($textArray, $dataStartIndex);
                        $chunks = array_chunk($dataSlice, $numCols);
                        $pageRows = [];
                        foreach ($chunks as $chunk) {
                            if (count($chunk) === $numCols) {
                                $row = array_combine($headers, $chunk);
                                $pageRows[] = $row;
                            }
                        }
                        if (!empty($pageRows)) {
                            $allRows = array_merge($allRows, $pageRows);
                        }
                    }
                }
            }
        }

        return $allRows;
    }

    /**
     * Map arbitrary column keys to canonical course keys.
     */
    private function normalizeCourseRowKeys(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $val) {
            $k = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', (string)$key), '_'));
            $val = trim((string)$val);

            // Course Code
            if (in_array($k, ['code', 'course_code', 'c_code', 'course_no', 'course_id', 'id_code'])) {
                $normalized['code'] = $val;
            }
            // Course Title / Name
            elseif (in_array($k, ['title', 'course_title', 'name', 'course_name', 'course', 'subject', 'course_subject'])) {
                $normalized['title'] = $val;
            }
            // Department
            elseif (in_array($k, ['department', 'dept', 'department_name', 'dept_name', 'department_id', 'faculty'])) {
                $normalized['department'] = $val;
            }
            // Academic Year Level
            elseif (in_array($k, ['level', 'year_level', 'academic_year_level', 'year', 'year_of_study', 'study_year', 'academic_level'])) {
                $normalized['level'] = $val;
            }
            // Credits
            elseif (in_array($k, ['credits', 'credit', 'credit_hours', 'credit_hour', 'cr', 'ch'])) {
                $normalized['credits'] = $val;
            }
            // Semester
            elseif (in_array($k, ['semester', 'sem', 'academic_term', 'term'])) {
                $normalized['semester'] = $val;
            }
            // Section
            elseif (in_array($k, ['section', 'sec', 'class_section'])) {
                $normalized['section'] = $val;
            }
            // Status
            elseif (in_array($k, ['status', 'state', 'course_status'])) {
                $normalized['status'] = $val;
            }
            // Start Date
            elseif (in_array($k, ['start_date', 'start', 'startdate', 'starts'])) {
                $normalized['start_date'] = $val;
            }
            // End Date
            elseif (in_array($k, ['end_date', 'end', 'enddate', 'ends'])) {
                $normalized['end_date'] = $val;
            }
            // Description
            elseif (in_array($k, ['description', 'desc', 'details', 'about'])) {
                $normalized['description'] = $val;
            }
            else {
                $normalized[$k] = $val;
            }
        }
        return $normalized;
    }

    /**
     * Normalize Academic Year Level.
     */
    private function normalizeLevel(?string $rawLevel): string
    {
        if (empty($rawLevel)) return '1st Year';
        $l = strtolower(trim($rawLevel));
        if (str_contains($l, '1') || str_contains($l, 'first') || str_contains($l, 'one')) return '1st Year';
        if (str_contains($l, '2') || str_contains($l, 'second') || str_contains($l, 'two')) return '2nd Year';
        if (str_contains($l, '3') || str_contains($l, 'third') || str_contains($l, 'three')) return '3rd Year';
        if (str_contains($l, '4') || str_contains($l, 'fourth') || str_contains($l, 'four')) return '4th Year';
        if (str_contains($l, '5') || str_contains($l, 'fifth') || str_contains($l, 'five')) return '5th Year';
        return '1st Year';
    }

    /**
     * Normalize Course Status.
     */
    private function normalizeStatus(?string $rawStatus): string
    {
        if (empty($rawStatus)) return 'active';
        $s = strtolower(trim($rawStatus));
        if ($s === 'inactive' || $s === 'disabled' || $s === 'draft' || $s === '0' || $s === 'archived') {
            return 'inactive';
        }
        return 'active';
    }

    /**
     * Normalize Credit Hours.
     */
    private function normalizeCredits($raw): int
    {
        if (empty($raw)) return 3;
        $val = intval(preg_replace('/[^0-9]/', '', (string)$raw));
        return ($val > 0 && $val <= 30) ? $val : 3;
    }

    /**
     * Smart department lookup.
     */
    private function findDepartmentId(string $deptName, array $departments): ?int
    {
        $clean = strtolower(trim($deptName));
        if ($clean === '') return null;

        // 1. Exact match by name
        foreach ($departments as $d) {
            if (strtolower(trim($d['name'])) === $clean) return $d['id'];
        }

        // 2. Exact match by code
        foreach ($departments as $d) {
            if (!empty($d['code']) && strtolower(trim($d['code'])) === $clean) return $d['id'];
        }

        // 3. Normalized alphanumeric match
        $alphanumeric = preg_replace('/[^a-z0-9]/', '', $clean);
        foreach ($departments as $d) {
            $dClean = preg_replace('/[^a-z0-9]/', '', strtolower($d['name']));
            if ($dClean === $alphanumeric) return $d['id'];
            if (!empty($d['code']) && preg_replace('/[^a-z0-9]/', '', strtolower($d['code'])) === $alphanumeric) return $d['id'];
        }

        // 4. Substring / contains match
        foreach ($departments as $d) {
            $dNameLower = strtolower($d['name']);
            if (str_contains($dNameLower, $clean) || str_contains($clean, $dNameLower)) return $d['id'];
        }

        return null;
    }
}
