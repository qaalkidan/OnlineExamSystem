<?php

namespace App\Http\Controllers\Api\V1\DeptHead;

use App\Exports\DepartmentCourseExport;
use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CourseController extends Controller
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

        // Fallback: look up department where this user is the head
        $dept = Department::where('head_id', $user->id)->first();
        if ($dept) {
            $user->update(['department_id' => $dept->id]);
            return $dept->id;
        }

        return null;
    }

    /**
     * Display a listing of department courses with server-side search,
     * filtering, sorting, pagination, and real KPI metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();
        $currentUserId = $user->id;
        $dept = $deptId ? Department::find($deptId) : null;

        // Base query strictly scoped to this department
        $baseQuery = Course::where('department_id', $deptId);

        // -------------------------------------------------------------
        // 1. Department-wide Real KPI Statistics (unfiltered)
        // -------------------------------------------------------------
        $allDeptCourses = (clone $baseQuery)
            ->with(['instructor', 'creator'])
            ->withCount('exams')
            ->get();

        $totalCourses = $allDeptCourses->count();
        $activeCourses = $allDeptCourses->where('status', 'active')->count();
        $inactiveCourses = $allDeptCourses->where('status', '!=', 'active')->count();
        $unassignedCourses = $allDeptCourses->whereNull('instructor_id')->count();

        // Total Exams associated with all department courses
        $deptCourseCodes = $allDeptCourses->pluck('code')->filter()->unique()->values();
        $totalExams = Exam::whereIn('course_code', $deptCourseCodes)->count();

        // Real students in this department
        $deptStudents = User::where('department_id', $deptId)
            ->where('role', 'student')
            ->get(['id', 'name', 'year_level', 'section', 'status']);
        
        $totalDeptStudentsCount = $deptStudents->count();

        // Calculate students per course level map
        $studentsByYearLevel = $deptStudents->groupBy('year_level');

        $stats = [
            'total'              => $totalCourses,
            'active'             => $activeCourses,
            'inactive'           => $inactiveCourses,
            'total_students'     => $totalDeptStudentsCount,
            'total_exams'        => $totalExams,
            'unassigned_courses' => $unassignedCourses,
        ];

        // -------------------------------------------------------------
        // 2. Dynamic Available Filter Options for this Department
        // -------------------------------------------------------------
        $existingLevels = $allDeptCourses->pluck('level')->filter()->unique()->values()->all();
        $defaultLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];
        $availableLevels = array_values(array_unique(array_merge($defaultLevels, $existingLevels)));

        $existingSemesters = $allDeptCourses->pluck('semester')->filter()->unique()->values()->all();
        $defaultSemesters = ['First Semester', 'Second Semester', 'Summer Term'];
        $availableSemesters = array_values(array_unique(array_merge($defaultSemesters, $existingSemesters)));

        $deptInstructors = User::where('department_id', $deptId)
            ->whereIn('role', ['instructor', 'dept_head'])
            ->select('id', 'name', 'email', 'id_no', 'role', 'status')
            ->orderBy('name')
            ->get();

        $filterOptions = [
            'year_levels' => $availableLevels,
            'semesters'   => $availableSemesters,
            'statuses'    => ['active', 'inactive'],
            'created_by'  => [
                ['value' => 'all', 'label' => 'All Creators'],
                ['value' => 'dept_head', 'label' => 'Department Head'],
                ['value' => 'admin', 'label' => 'Super Admin'],
            ],
            'instructors' => $deptInstructors,
        ];

        // -------------------------------------------------------------
        // 3. Search and Filtering
        // -------------------------------------------------------------
        $query = (clone $baseQuery)
            ->with(['instructor:id,name,email,profile_picture,role,id_no,status,phone', 'creator:id,name,email,role', 'coInstructor:id,name,email,profile_picture,role'])
            ->withCount('exams');

        // Search by title, code, or instructor name
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhereHas('instructor', function ($iq) use ($search) {
                      $iq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Status filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Year Level filter
        if ($request->filled('level') && $request->level !== 'all') {
            $query->where('level', $request->level);
        }

        // Semester filter
        if ($request->filled('semester') && $request->semester !== 'all') {
            $query->where('semester', $request->semester);
        }

        // Instructor filter
        if ($request->filled('instructor_id') && $request->instructor_id !== 'all') {
            if ($request->instructor_id === 'unassigned') {
                $query->whereNull('instructor_id');
            } else {
                $query->where('instructor_id', $request->instructor_id);
            }
        }

        // Created by filter
        if ($request->filled('created_by') && $request->created_by !== 'all') {
            if ($request->created_by === 'dept_head') {
                $query->where(function ($q) use ($currentUserId) {
                    $q->where('created_by', $currentUserId)
                      ->whereHas('creator', function ($cq) {
                          $cq->where('role', 'dept_head');
                      });
                });
            } elseif ($request->created_by === 'admin') {
                $query->where(function ($q) use ($currentUserId) {
                    $q->where('created_by', '!=', $currentUserId)
                      ->orWhereHas('creator', function ($cq) {
                          $cq->whereIn('role', ['admin', 'super_admin']);
                      });
                });
            }
        }

        // -------------------------------------------------------------
        // 4. Sorting
        // -------------------------------------------------------------
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = strtolower($request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['title', 'code', 'credits', 'level', 'semester', 'status', 'created_at', 'exams_count'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest('created_at');
        }

        // -------------------------------------------------------------
        // 5. Pagination & Transformation
        // -------------------------------------------------------------
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min(100, $perPage));

        $paginated = $query->paginate($perPage);

        $transformedItems = collect($paginated->items())->map(function ($course) use ($currentUserId, $studentsByYearLevel) {
            $creatorRole = $course->creator ? $course->creator->role : null;
            $isAdminCreated = in_array($creatorRole, ['super_admin', 'admin']) || ($course->created_by !== $currentUserId);
            $isCreatedByDeptHead = !$isAdminCreated && ($course->created_by === $currentUserId);

            // Compute real student count for this course's level
            $courseLevel = $course->level;
            $studentsCount = 0;
            if ($courseLevel && isset($studentsByYearLevel[$courseLevel])) {
                $studentsCount = $studentsByYearLevel[$courseLevel]->count();
            }

            $courseArray = $course->toArray();
            $courseArray['can_edit'] = $isCreatedByDeptHead;
            $courseArray['can_delete'] = $isCreatedByDeptHead;
            $courseArray['can_assign'] = true;
            $courseArray['is_admin_created'] = $isAdminCreated;
            $courseArray['creator_name'] = $course->creator?->name ?? ($isCreatedByDeptHead ? 'Department Head' : 'Super Admin');
            $courseArray['created_by_role'] = $isCreatedByDeptHead ? 'dept_head' : 'admin';
            $courseArray['students_count'] = $studentsCount;
            $courseArray['studentsEnrolled'] = $studentsCount;
            $courseArray['exams'] = $course->exams_count ?? 0;
            $courseArray['created_at_human'] = $course->created_at ? $course->created_at->format('M d, Y') : '—';

            return $courseArray;
        });

        return response()->json([
            'status'         => 'success',
            'data'           => $transformedItems,
            'department'     => [
                'id'      => $dept?->id,
                'name'    => ucwords($dept?->name ?? 'Department'),
                'code'    => $dept?->code ?? 'DEPT',
                'college' => $dept?->college ?? 'College of Computing and Informatics',
            ],
            'stats'          => $stats,
            'filter_options' => $filterOptions,
            'meta'           => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
        ]);
    }

    /**
     * Display comprehensive details of a single course including
     * assigned faculty workload, associated exams, and enrolled students cohort.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $currentUserId = $request->user()->id;

        $course = Course::where('department_id', $deptId)
            ->with([
                'instructor:id,name,email,phone,profile_picture,role,id_no,status,office,created_at',
                'coInstructor:id,name,email,phone,profile_picture,role,id_no,status',
                'creator:id,name,email,role',
                'department:id,name,code,college',
            ])
            ->withCount('exams')
            ->findOrFail($id);

        $creatorRole = $course->creator ? $course->creator->role : null;
        $isAdminCreated = in_array($creatorRole, ['super_admin', 'admin']) || ($course->created_by !== $currentUserId);
        $isCreatedByDeptHead = !$isAdminCreated && ($course->created_by === $currentUserId);

        // Associated Exams for this course code
        $exams = Exam::where('course_code', $course->code)
            ->with(['instructor:id,name,email'])
            ->withCount(['questions', 'attempts'])
            ->latest()
            ->get()
            ->map(function ($exam) {
                return [
                    'id'               => $exam->id,
                    'title'            => $exam->title,
                    'instructor_name'  => $exam->instructor?->name ?? 'Faculty Member',
                    'duration_minutes' => $exam->duration_minutes ?? 60,
                    'total_marks'      => $exam->total_marks ?? 0,
                    'status'           => $exam->status,
                    'scheduled_at'     => $exam->scheduled_at ? $exam->scheduled_at->format('M d, Y • h:i A') : 'Flexible Window',
                    'questions_count'  => $exam->questions_count ?? 0,
                    'attempts_count'   => $exam->attempts_count ?? 0,
                ];
            });

        // Enrolled Students matching this course's department and year level
        $studentsQuery = User::where('department_id', $deptId)
            ->where('role', 'student');

        if ($course->level) {
            $studentsQuery->where('year_level', $course->level);
        }

        if ($course->section && $course->section !== 'Both Sections' && $course->section !== 'All Sections') {
            $studentsQuery->where(function ($sq) use ($course) {
                $sq->where('section', $course->section)
                   ->orWhereNull('section');
            });
        }

        $enrolledStudents = $studentsQuery->select('id', 'name', 'email', 'id_no', 'year_level', 'section', 'status', 'created_at')
            ->orderBy('name')
            ->take(50)
            ->get();

        $totalEnrolledCount = $studentsQuery->count();

        // Primary instructor workload stats
        $instructorWorkload = null;
        if ($course->instructor_id) {
            $instCourses = Course::where('department_id', $deptId)
                ->where(function ($q) use ($course) {
                    $q->where('instructor_id', $course->instructor_id)
                      ->orWhere('co_instructor_id', $course->instructor_id);
                })
                ->get(['id', 'title', 'code', 'credits']);

            $instructorWorkload = [
                'courses_count' => $instCourses->count(),
                'total_credits' => $instCourses->sum('credits'),
                'courses'       => $instCourses,
            ];
        }

        $courseData = $course->toArray();
        $courseData['can_edit'] = $isCreatedByDeptHead;
        $courseData['can_delete'] = $isCreatedByDeptHead;
        $courseData['can_assign'] = true;
        $courseData['is_admin_created'] = $isAdminCreated;
        $courseData['creator_name'] = $course->creator?->name ?? ($isCreatedByDeptHead ? 'Department Head' : 'Super Admin');
        $courseData['created_by_role'] = $isCreatedByDeptHead ? 'dept_head' : 'admin';
        $courseData['students_count'] = $totalEnrolledCount;
        $courseData['exams_list'] = $exams;
        $courseData['enrolled_students'] = $enrolledStudents;
        $courseData['instructor_workload'] = $instructorWorkload;

        return response()->json([
            'status' => 'success',
            'data'   => $courseData,
        ]);
    }

    /**
     * Store a newly created course in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();

        $request->validate([
            'title'            => 'required|string|max:255',
            'code'             => 'required|string|max:50|unique:courses,code',
            'credits'          => 'required|integer|min:1|max:30',
            'semester'         => 'required|string|max:100',
            'level'            => 'required|string|max:100',
            'section'          => 'nullable|string|max:100',
            'instructor_id'    => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($deptId) {
                    $instructor = User::where('id', $value)
                        ->where('department_id', $deptId)
                        ->whereIn('role', ['instructor', 'dept_head'])
                        ->first();
                    if (!$instructor) {
                        $fail('The selected instructor is invalid or does not belong to your department.');
                    }
                },
            ],
            'co_instructor_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($deptId) {
                    $instructor = User::where('id', $value)
                        ->where('department_id', $deptId)
                        ->whereIn('role', ['instructor', 'dept_head'])
                        ->first();
                    if (!$instructor) {
                        $fail('The selected co-instructor is invalid or does not belong to your department.');
                    }
                },
            ],
        ]);

        $course = Course::create([
            'title'            => trim($request->title),
            'code'             => strtoupper(trim($request->code)),
            'credits'          => (int) $request->credits,
            'semester'         => trim($request->semester),
            'level'            => trim($request->level),
            'section'          => $request->section ? trim($request->section) : null,
            'department_id'    => $deptId,
            'instructor_id'    => $request->instructor_id ?: null,
            'co_instructor_id' => $request->co_instructor_id ?: null,
            'status'           => 'active',
            'created_by'       => $user->id,
        ]);

        // Sync instructor's assigned course profile fields
        if ($course->instructor_id) {
            User::where('id', $course->instructor_id)->update([
                'course_code' => $course->code,
                'course_name' => $course->title,
                'year_level'  => $course->level,
                'section'     => $course->section,
            ]);
        }

        LogActivity::record(
            'Created',
            'Courses',
            "Created course \"{$course->title}\" ({$course->code}) in Department ID {$deptId}"
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Course created successfully',
            'data'    => $course->load(['instructor', 'coInstructor', 'creator']),
        ], 201);
    }

    /**
     * Update the specified course in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $course = Course::where('department_id', $deptId)->findOrFail($id);

        $currentUserId = $request->user()->id;
        $creatorRole = $course->creator ? $course->creator->role : null;
        $isAdminCreated = in_array($creatorRole, ['super_admin', 'admin']) || ($course->created_by !== $currentUserId);

        // Security / Permission Check:
        // Courses created by Super Admin cannot have their academic properties changed by Dept Head.
        // Department Head CAN, however, assign instructors and section.
        if ($isAdminCreated) {
            if ($request->hasAny(['title', 'code', 'credits', 'semester', 'level', 'status'])) {
                return response()->json([
                    'message' => 'Unauthorized. Courses created by Super Admin cannot be edited by Department Head. You can only assign instructors and sections.'
                ], 403);
            }
        }

        $request->validate([
            'title'            => 'sometimes|string|max:255',
            'code'             => 'sometimes|string|max:50|unique:courses,code,' . $course->id,
            'credits'          => 'sometimes|integer|min:1|max:30',
            'semester'         => 'nullable|string|max:100',
            'level'            => 'nullable|string|max:100',
            'section'          => 'nullable|string|max:100',
            'status'           => 'sometimes|in:active,inactive',
            'instructor_id'    => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($deptId) {
                    if (!$value) return;
                    $instructor = User::where('id', $value)
                        ->where('department_id', $deptId)
                        ->whereIn('role', ['instructor', 'dept_head'])
                        ->first();
                    if (!$instructor) {
                        $fail('The selected instructor is invalid or does not belong to your department.');
                    }
                },
            ],
            'co_instructor_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($deptId) {
                    if (!$value) return;
                    $instructor = User::where('id', $value)
                        ->where('department_id', $deptId)
                        ->whereIn('role', ['instructor', 'dept_head'])
                        ->first();
                    if (!$instructor) {
                        $fail('The selected co-instructor is invalid or does not belong to your department.');
                    }
                },
            ],
        ]);

        if ($isAdminCreated) {
            // Only update assignment fields for courses created by Super Admin
            $course->update([
                'instructor_id'    => $request->has('instructor_id') ? ($request->instructor_id ?: null) : $course->instructor_id,
                'co_instructor_id' => $request->has('co_instructor_id') ? ($request->co_instructor_id ?: null) : $course->co_instructor_id,
                'section'          => $request->has('section') ? ($request->section ?: null) : $course->section,
            ]);
        } else {
            // Update all permitted fields for courses created by Department Head
            $updates = [];
            if ($request->has('title')) $updates['title'] = trim($request->title);
            if ($request->has('code')) $updates['code'] = strtoupper(trim($request->code));
            if ($request->has('credits')) $updates['credits'] = (int) $request->credits;
            if ($request->has('semester')) $updates['semester'] = trim($request->semester);
            if ($request->has('level')) $updates['level'] = trim($request->level);
            if ($request->has('section')) $updates['section'] = $request->section ? trim($request->section) : null;
            if ($request->has('status')) $updates['status'] = $request->status;
            if ($request->has('instructor_id')) $updates['instructor_id'] = $request->instructor_id ?: null;
            if ($request->has('co_instructor_id')) $updates['co_instructor_id'] = $request->co_instructor_id ?: null;

            $course->update($updates);
        }

        // Sync instructor's assigned course profile fields
        if ($course->instructor_id) {
            User::where('id', $course->instructor_id)->update([
                'course_code' => $course->code,
                'course_name' => $course->title,
                'year_level'  => $course->level,
                'section'     => $course->section,
            ]);
        }

        LogActivity::record(
            'Updated',
            'Courses',
            "Updated course \"{$course->title}\" ({$course->code})"
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Course updated successfully',
            'data'    => $course->load(['instructor', 'coInstructor', 'creator']),
        ]);
    }

    /**
     * Remove the specified course from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $course = Course::where('department_id', $deptId)->findOrFail($id);

        $currentUserId = $request->user()->id;
        $creatorRole = $course->creator ? $course->creator->role : null;
        $isAdminCreated = in_array($creatorRole, ['super_admin', 'admin']) || ($course->created_by !== $currentUserId);

        if ($isAdminCreated) {
            return response()->json([
                'message' => 'Unauthorized. Courses created by Super Admin cannot be deleted by Department Head.'
            ], 403);
        }

        // Integrity Guard: prevent deleting courses that already have exams
        $examsCount = Exam::where('course_code', $course->code)->count();
        if ($examsCount > 0) {
            return response()->json([
                'message' => "Cannot delete course \"{$course->title}\" because it is linked to {$examsCount} examination(s). Please deactivate the course instead to protect academic records."
            ], 422);
        }

        $courseTitle = $course->title;
        $courseCode = $course->code;
        $course->delete();

        LogActivity::record(
            'Deleted',
            'Courses',
            "Deleted course \"{$courseTitle}\" ({$courseCode})"
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Course deleted successfully'
        ]);
    }

    /**
     * Export department courses list to Excel, PDF, or CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $dept = $deptId ? Department::find($deptId) : null;
        $deptName = $dept ? $dept->name : 'Department';
        $currentUserId = $request->user()->id;

        $format = strtolower($request->query('format', 'excel'));

        $query = Course::where('department_id', $deptId)
            ->with(['instructor', 'coInstructor', 'creator', 'department'])
            ->withCount('exams');

        // Apply same filters as table
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhereHas('instructor', function ($iq) use ($search) {
                      $iq->where('name', 'like', "%{$search}%");
                  });
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

        if ($request->filled('instructor_id') && $request->instructor_id !== 'all') {
            if ($request->instructor_id === 'unassigned') {
                $query->whereNull('instructor_id');
            } else {
                $query->where('instructor_id', $request->instructor_id);
            }
        }

        if ($request->filled('created_by') && $request->created_by !== 'all') {
            if ($request->created_by === 'dept_head') {
                $query->where('created_by', $currentUserId);
            } elseif ($request->created_by === 'admin') {
                $query->where('created_by', '!=', $currentUserId);
            }
        }

        // Preload student counts by cohort
        $studentsByYearLevel = User::where('department_id', $deptId)
            ->where('role', 'student')
            ->get(['id', 'year_level'])
            ->groupBy('year_level');

        $courses = $query->orderBy('code')->get()->map(function ($course) use ($currentUserId, $studentsByYearLevel) {
            $creatorRole = $course->creator ? $course->creator->role : null;
            $course->is_admin_created = in_array($creatorRole, ['super_admin', 'admin']) || ($course->created_by !== $currentUserId);
            $course->students_count = isset($studentsByYearLevel[$course->level]) ? $studentsByYearLevel[$course->level]->count() : 0;
            return $course;
        });

        $dateStr = now()->format('Y-m-d');
        $sanitizedDept = preg_replace('/[^A-Za-z0-9_\-]/', '_', $deptName);
        $baseFileName = "{$sanitizedDept}_Courses_{$dateStr}";

        if ($format === 'pdf') {
            return $this->exportCoursesPdf($courses, $deptName, $baseFileName);
        }

        if ($format === 'csv') {
            return $this->exportCoursesCsv($courses, $deptName, $baseFileName);
        }

        return $this->exportCoursesExcel($courses, $deptName, $baseFileName);
    }

    /**
     * Export courses as a formatted PDF using Dompdf.
     */
    private function exportCoursesPdf($courses, string $deptName, string $baseFileName): JsonResponse
    {
        $generatedAt = now()->format('F j, Y  H:i');
        $totalRecords = $courses->count();

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<title>' . htmlspecialchars($deptName) . ' — Department Courses</title>';
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
            .badge-admin { display: inline-block; padding: 2px 4px; border-radius: 3px; font-weight: bold; color: #4338ca; background: #e0e7ff; font-size: 6px; }
            .footer { position: fixed; bottom: 10px; left: 25px; right: 25px; font-size: 7.5px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 4px; }
        </style></head><body>';

        $html .= '<table class="header-table"><tr>';
        $html .= '<td><div class="university-title">Wollo University</div>';
        $html .= '<div class="dept-title">Department of ' . htmlspecialchars($deptName) . ' — Curriculum Courses</div></td>';
        $html .= '<td class="meta"><strong>Date Generated:</strong> ' . $generatedAt . '<br><strong>Total Courses:</strong> ' . $totalRecords . '</td>';
        $html .= '</tr></table>';

        $html .= '<table class="data-table"><thead><tr>';
        $html .= '<th style="width: 25px;">#</th>';
        $html .= '<th style="width: 55px;">Code</th>';
        $html .= '<th>Course Title</th>';
        $html .= '<th style="width: 35px;">Credits</th>';
        $html .= '<th style="width: 55px;">Year Level</th>';
        $html .= '<th style="width: 70px;">Semester</th>';
        $html .= '<th>Assigned Instructor</th>';
        $html .= '<th style="width: 35px;">Exams</th>';
        $html .= '<th style="width: 45px;">Students</th>';
        $html .= '<th style="width: 45px;">Status</th>';
        $html .= '<th style="width: 60px;">Created By</th>';
        $html .= '</tr></thead><tbody>';

        if ($courses->isEmpty()) {
            $html .= '<tr><td colspan="11" style="text-align: center; padding: 15px; color: #94a3b8;">No courses found matching the specified criteria.</td></tr>';
        } else {
            foreach ($courses as $i => $c) {
                $statusBadge = ($c->status === 'active')
                    ? '<span class="badge-active">ACTIVE</span>'
                    : '<span class="badge-inactive">INACTIVE</span>';

                $instName = $c->instructor ? htmlspecialchars($c->instructor->name) : '<em style="color:#94a3b8;">Unassigned</em>';
                $creatorBadge = $c->is_admin_created
                    ? '<span class="badge-admin">SUPER ADMIN</span>'
                    : '<span style="color:#0f766e;font-weight:bold;">DEPT HEAD</span>';

                $html .= '<tr>';
                $html .= '<td>' . ($i + 1) . '</td>';
                $html .= '<td><strong>' . htmlspecialchars($c->code) . '</strong></td>';
                $html .= '<td>' . htmlspecialchars($c->title) . '</td>';
                $html .= '<td>' . ($c->credits ?? 0) . '</td>';
                $html .= '<td>' . htmlspecialchars($c->level ?? '—') . '</td>';
                $html .= '<td>' . htmlspecialchars($c->semester ?? '—') . '</td>';
                $html .= '<td>' . $instName . '</td>';
                $html .= '<td>' . ($c->exams_count ?? 0) . '</td>';
                $html .= '<td>' . ($c->students_count ?? 0) . '</td>';
                $html .= '<td>' . $statusBadge . '</td>';
                $html .= '<td>' . $creatorBadge . '</td>';
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

        LogActivity::record('Exported', 'Courses', "Exported {$totalRecords} courses list as PDF ({$deptName})");

        return response()->json([
            'file'     => base64_encode($pdfOutput),
            'filename' => $baseFileName . '.pdf',
            'format'   => 'pdf',
        ]);
    }

    /**
     * Export courses as genuine Excel spreadsheet (.xlsx).
     */
    private function exportCoursesExcel($courses, string $deptName, string $baseFileName): JsonResponse
    {
        $export = new DepartmentCourseExport($courses);
        $xlsxBytes = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);

        LogActivity::record('Exported', 'Courses', "Exported {$courses->count()} courses list as Excel ({$deptName})");

        return response()->json([
            'file'     => base64_encode($xlsxBytes),
            'filename' => $baseFileName . '.xlsx',
            'format'   => 'xlsx',
        ]);
    }

    /**
     * Export courses as CSV (Excel-compatible UTF-8 BOM).
     */
    private function exportCoursesCsv($courses, string $deptName, string $baseFileName): JsonResponse
    {
        ob_start();
        $handle = fopen('php://output', 'w');
        // UTF-8 BOM for Microsoft Excel auto-detect
        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            '#', 'Course Code', 'Course Title', 'Credits', 'Year Level', 'Semester',
            'Section', 'Department', 'Primary Instructor', 'Co-Instructor',
            'Exams Count', 'Enrolled Students', 'Status', 'Created By', 'Created At'
        ]);

        foreach ($courses as $i => $c) {
            $instName = $c->instructor ? $c->instructor->name : 'Unassigned';
            $coInstName = $c->coInstructor ? $c->coInstructor->name : '—';
            $creatorName = $c->is_admin_created ? 'Super Admin' : 'Department Head';

            fputcsv($handle, [
                $i + 1,
                $c->code,
                $c->title,
                $c->credits,
                $c->level ?? '—',
                $c->semester ?? '—',
                $c->section ?? 'All Sections',
                $deptName,
                $instName,
                $coInstName,
                $c->exams_count ?? 0,
                $c->students_count ?? 0,
                ucfirst($c->status ?? 'active'),
                $creatorName,
                $c->created_at ? $c->created_at->format('M d, Y') : '',
            ]);
        }
        fclose($handle);
        $csvContent = ob_get_clean();

        LogActivity::record('Exported', 'Courses', "Exported {$courses->count()} courses list as CSV ({$deptName})");

        return response()->json([
            'file'     => base64_encode($csvContent),
            'filename' => $baseFileName . '.csv',
            'format'   => 'csv',
        ]);
    }
}
