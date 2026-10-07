<?php

namespace App\Http\Controllers\Api\V1\DeptHead;

use App\Exports\DepartmentInstructorExport;
use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\SystemSetting;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class InstructorController extends Controller
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
     * Display a listing of the instructors in the department with server-side
     * search, filtering, sorting, pagination, and real KPI metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $currentUserId = $request->user()->id;
        $department = $deptId ? Department::find($deptId) : null;

        // Base department instructors query
        $baseQuery = User::where('department_id', $deptId)
            ->whereIn('role', ['instructor', 'dept_head']);

        // -------------------------------------------------------------
        // 1. Department-wide KPI Statistics (unfiltered by current page)
        // -------------------------------------------------------------
        $allDeptInstructors = (clone $baseQuery)->with(['assignedCourses', 'coInstructorCourses'])->get();

        $totalInstructors = $allDeptInstructors->count();

        $fullTimeCount = $allDeptInstructors->filter(function ($i) {
            $et = strtolower($i->employment_type ?? 'full_time');
            $st = strtolower($i->status ?? 'active');
            return ($et === 'full_time' || $et === 'full time') && $st !== 'on_leave';
        })->count();

        $partTimeCount = $allDeptInstructors->filter(function ($i) {
            $et = strtolower($i->employment_type ?? '');
            $st = strtolower($i->status ?? '');
            return $et === 'part_time' || $et === 'part time' || $st === 'part time';
        })->count();

        $onLeaveCount = $allDeptInstructors->filter(function ($i) {
            $st = strtolower($i->status ?? '');
            return $st === 'on_leave' || $st === 'on leave' || $st === 'leave';
        })->count();

        $activeCount = $allDeptInstructors->filter(function ($i) {
            $st = strtolower($i->status ?? 'active');
            return $st === 'active';
        })->count();

        $inactiveCount = $allDeptInstructors->filter(function ($i) {
            $st = strtolower($i->status ?? '');
            return $st === 'inactive' || $st === 'suspended';
        })->count();

        // Instructors who currently have assigned courses
        $withCoursesCount = $allDeptInstructors->filter(function ($i) {
            return $i->assignedCourses->isNotEmpty() || $i->coInstructorCourses->isNotEmpty();
        })->count();

        $withoutCoursesCount = max(0, $totalInstructors - $withCoursesCount);

        // -------------------------------------------------------------
        // 2. Dynamic Available Filter Options for this Department
        // -------------------------------------------------------------
        $deptCourses = Course::where('department_id', $deptId)
            ->get(['id', 'title', 'code', 'credits', 'section', 'level', 'semester']);

        $availableCourses = $deptCourses->map(function ($c) {
            return [
                'id'      => $c->id,
                'title'   => $c->title,
                'code'    => $c->code,
                'credits' => $c->credits,
            ];
        })->unique('id')->values()->all();

        $availableYears = $allDeptInstructors->pluck('year_level')
            ->concat($deptCourses->pluck('level'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $currentSemester = SystemSetting::where('key', 'semester')->value('value') ?? 'Second Semester';
        $availableSemesters = collect([$currentSemester, 'First Semester', 'Second Semester', 'Summer Term'])
            ->concat($allDeptInstructors->pluck('semester'))
            ->concat($deptCourses->pluck('semester'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $availableSections = $allDeptInstructors->pluck('section')
            ->concat($deptCourses->pluck('section'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // -------------------------------------------------------------
        // 3. Apply Filters & Search to Query
        // -------------------------------------------------------------
        $query = (clone $baseQuery)->with(['assignedCourses', 'coInstructorCourses', 'creator', 'department']);

        // Search across name, email, username, employee ID
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('id_no', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Status Filter
        if ($request->filled('status') && $request->status !== 'all') {
            $status = strtolower($request->status);
            if ($status === 'active') {
                $query->where(function ($q) {
                    $q->where('status', 'active')->orWhereNull('status');
                });
            } elseif ($status === 'on_leave') {
                $query->whereIn('status', ['on_leave', 'on leave', 'leave']);
            } else {
                $query->where('status', $status);
            }
        }

        // Employment Type Filter
        if ($request->filled('employment_type') && $request->employment_type !== 'all') {
            $et = strtolower($request->employment_type);
            if ($et === 'full_time' || $et === 'full-time') {
                $query->where(function ($q) {
                    $q->where('employment_type', 'full_time')
                      ->orWhere('employment_type', 'full time')
                      ->orWhereNull('employment_type');
                });
            } elseif ($et === 'part_time' || $et === 'part-time') {
                $query->where(function ($q) {
                    $q->where('employment_type', 'part_time')
                      ->orWhere('employment_type', 'part time');
                });
            }
        }

        // Year Level Filter
        if ($request->filled('year') && $request->year !== 'all') {
            $query->where('year_level', $request->year);
        }

        // Semester Filter
        if ($request->filled('semester') && $request->semester !== 'all') {
            $query->where('semester', $request->semester);
        }

        // Section Filter
        if ($request->filled('section') && $request->section !== 'all') {
            $query->where('section', $request->section);
        }

        // Course Filter: Instructors teaching a specific course
        if ($request->filled('course_id') && $request->course_id !== 'all') {
            $courseId = (int) $request->course_id;
            $query->where(function ($q) use ($courseId) {
                $q->whereHas('assignedCourses', function ($cq) use ($courseId) {
                    $cq->where('id', $courseId);
                })->orWhereHas('coInstructorCourses', function ($cq) use ($courseId) {
                    $cq->where('id', $courseId);
                });
            });
        }

        // Teaching Assignment Status Filter
        if ($request->filled('assignment') && $request->assignment !== 'all') {
            if ($request->assignment === 'assigned') {
                $query->where(function ($q) {
                    $q->has('assignedCourses')->orHas('coInstructorCourses');
                });
            } elseif ($request->assignment === 'unassigned') {
                $query->whereDoesntHave('assignedCourses')->whereDoesntHave('coInstructorCourses');
            }
        }

        // -------------------------------------------------------------
        // 4. Sorting
        // -------------------------------------------------------------
        $sortBy = $request->query('sort_by', 'name');
        $sortOrder = strtolower($request->query('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';

        switch ($sortBy) {
            case 'id_no':
                $query->orderBy('id_no', $sortOrder);
                break;
            case 'email':
                $query->orderBy('email', $sortOrder);
                break;
            case 'status':
                $query->orderBy('status', $sortOrder);
                break;
            case 'employment_type':
                $query->orderBy('employment_type', $sortOrder);
                break;
            case 'created_at':
                $query->orderBy('created_at', $sortOrder);
                break;
            case 'name':
            default:
                $query->orderBy('name', $sortOrder);
                break;
        }

        // -------------------------------------------------------------
        // 5. Pagination
        // -------------------------------------------------------------
        $perPage = (int) $request->query('per_page', 10);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 10;
        }

        $paginator = $query->paginate($perPage);

        // -------------------------------------------------------------
        // 6. Map Output Data
        // -------------------------------------------------------------
        $items = collect($paginator->items())->map(function ($instructor) use ($currentUserId, $deptId) {
            $isSelf = ($instructor->id === $currentUserId);
            $isCreatedByDeptHead = ($instructor->created_by === $currentUserId);

            $assignedCoursesList = $instructor->assignedCourses->map(function ($c) {
                return [
                    'id'       => $c->id,
                    'title'    => $c->title,
                    'code'     => $c->code,
                    'credits'  => (int) ($c->credits ?? 3),
                    'level'    => $c->level,
                    'semester' => $c->semester,
                    'section'  => $c->section,
                    'status'   => $c->status ?? 'active',
                ];
            });

            $coCoursesList = $instructor->coInstructorCourses->map(function ($c) {
                return [
                    'id'       => $c->id,
                    'title'    => $c->title,
                    'code'     => $c->code,
                    'credits'  => (int) ($c->credits ?? 3),
                    'level'    => $c->level,
                    'semester' => $c->semester,
                    'section'  => $c->section,
                    'status'   => $c->status ?? 'active',
                ];
            });

            $totalCredits = $assignedCoursesList->sum('credits');
            $hasCourses = $assignedCoursesList->isNotEmpty() || $coCoursesList->isNotEmpty();

            // Safety rules for delete & edit:
            // 1. Department Head can edit instructors in their department
            // 2. Department Head CANNOT delete their own account
            // 3. Instructors with courses or exams should be deactivated, not permanently deleted
            $canDelete = !$isSelf && !$hasCourses;
            $canEdit = true;

            return [
                'id'                  => $instructor->id,
                'name'                => $instructor->name,
                'email'               => $instructor->email,
                'username'            => $instructor->username,
                'phone'               => $instructor->phone ?: '—',
                'gender'              => $instructor->gender ?: 'Not specified',
                'id_no'               => $instructor->id_no ?: 'INS-' . str_pad((string)$instructor->id, 4, '0', STR_PAD_LEFT),
                'role'                => $instructor->role,
                'department_id'       => $instructor->department_id,
                'department_name'     => $instructor->department?->name ?? 'Department',
                'course_code'         => $instructor->course_code,
                'course_name'         => $instructor->course_name,
                'year_level'          => $instructor->year_level ?: '—',
                'semester'            => $instructor->semester ?: '—',
                'section'             => $instructor->section ?: '—',
                'status'              => $instructor->status ?? 'active',
                'employment_type'     => $instructor->employment_type ?? 'full_time',
                'office'              => $instructor->office ?: 'Department Office',
                'created_by'          => $instructor->created_by,
                'creator_name'        => $instructor->creator?->name ?? ($isCreatedByDeptHead ? 'Department Head' : 'Super Admin'),
                'is_admin_created'    => !$isCreatedByDeptHead,
                'is_self'             => $isSelf,
                'can_edit'            => $canEdit,
                'can_delete'          => $canDelete,
                'can_deactivate'      => !$isSelf,
                'profile_picture'     => $instructor->profile_picture,
                'profile_picture_url' => $instructor->profile_picture_url,
                'created_at'          => $instructor->created_at ? $instructor->created_at->toIso8601String() : null,
                'joined_formatted'    => $instructor->created_at ? $instructor->created_at->format('M d, Y') : '—',
                'assigned_courses'    => $assignedCoursesList,
                'co_instructor_courses'=> $coCoursesList,
                'courses_count'       => $assignedCoursesList->count(),
                'total_credits'       => $totalCredits,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $items,
            'stats'  => [
                'total'             => $totalInstructors,
                'full_time'         => $fullTimeCount,
                'part_time'         => $partTimeCount,
                'on_leave'          => $onLeaveCount,
                'active'            => $activeCount,
                'inactive'          => $inactiveCount,
                'with_courses'      => $withCoursesCount,
                'without_courses'   => $withoutCoursesCount,
            ],
            'filter_options' => [
                'courses'   => $availableCourses,
                'years'     => $availableYears,
                'semesters' => $availableSemesters,
                'sections'  => $availableSections,
            ],
            'department' => [
                'id'      => $department?->id,
                'name'    => ucwords($department?->name ?? 'Department'),
                'code'    => $department?->code ?? 'DEPT',
                'college' => $department?->college ?? 'College of Computing and Informatics',
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Display detailed profile, teaching assignments, and activity of an instructor.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $currentUserId = $request->user()->id;

        $instructor = User::where('department_id', $deptId)
            ->whereIn('role', ['instructor', 'dept_head'])
            ->with(['assignedCourses', 'coInstructorCourses', 'creator', 'department'])
            ->findOrFail($id);

        $isSelf = ($instructor->id === $currentUserId);
        $isCreatedByDeptHead = ($instructor->created_by === $currentUserId);

        // Teaching Assignments
        $assignedCourses = $instructor->assignedCourses->map(function ($c) {
            return [
                'id'       => $c->id,
                'title'    => $c->title,
                'code'     => $c->code,
                'credits'  => (int) ($c->credits ?? 3),
                'level'    => $c->level,
                'semester' => $c->semester,
                'section'  => $c->section,
                'status'   => $c->status ?? 'active',
            ];
        });

        $coCourses = $instructor->coInstructorCourses->map(function ($c) {
            return [
                'id'       => $c->id,
                'title'    => $c->title,
                'code'     => $c->code,
                'credits'  => (int) ($c->credits ?? 3),
                'level'    => $c->level,
                'semester' => $c->semester,
                'section'  => $c->section,
                'status'   => $c->status ?? 'active',
            ];
        });

        // Exams created by this instructor
        $exams = Exam::where('user_id', $instructor->id)
            ->latest('created_at')
            ->take(10)
            ->get(['id', 'title', 'course_code', 'duration_minutes', 'status', 'scheduled_at', 'created_at'])
            ->map(function ($e) {
                return [
                    'id'               => $e->id,
                    'title'            => $e->title,
                    'course_code'      => $e->course_code,
                    'duration_minutes' => $e->duration_minutes,
                    'status'           => $e->status,
                    'scheduled_human'  => $e->scheduled_at ? $e->scheduled_at->format('M d, Y • h:i A') : 'Flexible Window',
                    'created_human'    => $e->created_at ? $e->created_at->format('M d, Y') : '—',
                ];
            });

        // Recent Activity Logs by this instructor
        $activities = ActivityLog::where('user_id', $instructor->id)
            ->latest('created_at')
            ->take(8)
            ->get()
            ->map(function ($log) {
                return [
                    'id'      => $log->id,
                    'action'  => $log->action,
                    'details' => $log->details,
                    'module'  => $log->module,
                    'time'    => $log->created_at ? $log->created_at->diffForHumans() : 'Recently',
                    'date'    => $log->created_at ? $log->created_at->format('M d, Y h:i A') : '',
                ];
            });

        $totalCredits = $assignedCourses->sum('credits');

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'                  => $instructor->id,
                'name'                => $instructor->name,
                'email'               => $instructor->email,
                'username'            => $instructor->username,
                'phone'               => $instructor->phone ?: '—',
                'gender'              => $instructor->gender ?: 'Not specified',
                'id_no'               => $instructor->id_no ?: 'INS-' . str_pad((string)$instructor->id, 4, '0', STR_PAD_LEFT),
                'role'                => $instructor->role,
                'department_id'       => $instructor->department_id,
                'department_name'     => $instructor->department?->name ?? 'Department',
                'year_level'          => $instructor->year_level ?: '—',
                'semester'            => $instructor->semester ?: '—',
                'section'             => $instructor->section ?: '—',
                'status'              => $instructor->status ?? 'active',
                'employment_type'     => $instructor->employment_type ?? 'full_time',
                'office'              => $instructor->office ?: 'Department Office',
                'created_by'          => $instructor->created_by,
                'creator_name'        => $instructor->creator?->name ?? ($isCreatedByDeptHead ? 'Department Head' : 'Super Admin'),
                'is_admin_created'    => !$isCreatedByDeptHead,
                'is_self'             => $isSelf,
                'can_edit'            => true,
                'can_delete'          => !$isSelf && $assignedCourses->isEmpty(),
                'can_deactivate'      => !$isSelf,
                'profile_picture_url' => $instructor->profile_picture_url,
                'joined_formatted'    => $instructor->created_at ? $instructor->created_at->format('M d, Y') : '—',
                'assigned_courses'    => $assignedCourses,
                'co_instructor_courses'=> $coCourses,
                'courses_count'       => $assignedCourses->count(),
                'total_credits'       => $totalCredits,
                'exams'               => $exams,
                'exams_count'         => $exams->count(),
                'activities'          => $activities,
            ]
        ]);
    }

    /**
     * Store a newly created instructor in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);

        $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'phone'           => 'required|string|max:255',
            'gender'          => 'required|string|max:255',
            'id_no'           => 'required|string|max:255',
            'year_level'      => 'nullable|string|max:255',
            'semester'        => 'nullable|string|max:255',
            'section'         => 'nullable|string|max:255',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'username'        => 'required|string|unique:users,username',
            'password'        => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?`~]/',
            ],
            'employment_type' => 'nullable|string|in:full_time,part_time,full time,part time',
            'status'          => 'nullable|string|in:active,on_leave,inactive,suspended',
        ], [
            'password.min'   => 'Password must be at least 8 characters long.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ]);

        $profilePicturePath = null;
        if ($request->hasFile('profile_picture')) {
            $profilePicturePath = $request->file('profile_picture')->store('avatars', 'public');
        }

        $instructor = User::create([
            'name'            => trim($request->name),
            'email'           => trim(strtolower($request->email)),
            'phone'           => trim($request->phone),
            'gender'          => $request->gender,
            'id_no'           => trim($request->id_no),
            'year_level'      => $request->year_level,
            'semester'        => $request->semester,
            'section'         => $request->section,
            'username'        => trim($request->username),
            'password'        => Hash::make($request->password),
            'role'            => 'instructor',
            'department_id'   => $deptId,
            'status'          => $request->status ?? 'active',
            'employment_type' => $request->employment_type ?? 'full_time',
            'created_by'      => $request->user()->id,
            'profile_picture' => $profilePicturePath,
        ]);

        LogActivity::record(
            'Created',
            'Instructors',
            "Created a new Instructor \"{$instructor->name}\""
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Instructor created successfully',
            'data'    => $instructor
        ], 201);
    }

    /**
     * Update the specified instructor in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $instructor = User::where('department_id', $deptId)
            ->whereIn('role', ['instructor', 'dept_head'])
            ->findOrFail($id);

        $request->validate([
            'name'            => 'sometimes|string|max:255',
            'email'           => 'sometimes|email|unique:users,email,' . $instructor->id,
            'phone'           => 'nullable|string|max:255',
            'gender'          => 'nullable|string|max:255',
            'id_no'           => 'nullable|string|max:255',
            'year_level'      => 'nullable|string|max:255',
            'semester'        => 'nullable|string|max:255',
            'section'         => 'nullable|string|max:255',
            'status'          => 'nullable|string|in:active,on_leave,inactive,suspended',
            'employment_type' => 'nullable|string|in:full_time,part_time,full time,part time',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'password'        => [
                'nullable',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?`~]/',
            ],
        ], [
            'password.min'   => 'Password must be at least 8 characters long.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ]);

        $updateData = $request->only([
            'name',
            'email',
            'phone',
            'gender',
            'id_no',
            'year_level',
            'semester',
            'section',
            'status',
            'employment_type',
        ]);

        if ($request->hasFile('profile_picture')) {
            $updateData['profile_picture'] = $request->file('profile_picture')->store('avatars', 'public');
        }

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $instructor->update($updateData);

        LogActivity::record(
            'Updated',
            'Instructors',
            "Updated Instructor \"{$instructor->name}\""
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Instructor updated successfully',
            'data'    => $instructor
        ]);
    }

    /**
     * Update the status of an instructor (active, on_leave, inactive).
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $instructor = User::where('department_id', $deptId)
            ->whereIn('role', ['instructor', 'dept_head'])
            ->findOrFail($id);

        $request->validate([
            'status' => 'required|string|in:active,on_leave,inactive,suspended',
        ]);

        $newStatus = $request->input('status');

        if ($instructor->id === $request->user()->id && $newStatus !== 'active') {
            return response()->json([
                'message' => 'You cannot change your own Department Head account status away from Active.'
            ], 422);
        }

        $oldStatus = $instructor->status ?? 'active';
        $instructor->update(['status' => $newStatus]);

        LogActivity::record(
            'Status Changed',
            'Instructors',
            "Changed status of Instructor \"{$instructor->name}\" from {$oldStatus} to {$newStatus}"
        );

        return response()->json([
            'status'  => 'success',
            'message' => "Instructor status updated to " . ucfirst(str_replace('_', ' ', $newStatus)),
            'data'    => $instructor
        ]);
    }

    /**
     * Remove the specified instructor from storage with safety checks.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $instructor = User::where('department_id', $deptId)
            ->whereIn('role', ['instructor', 'dept_head'])
            ->findOrFail($id);

        // Security check: cannot delete oneself
        if ($instructor->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own Department Head account.'
            ], 403);
        }

        // Academic integrity check: cannot delete if active courses or exams exist
        $coursesCount = Course::where('instructor_id', $instructor->id)->count();
        $examsCount = Exam::where('user_id', $instructor->id)->count();

        if ($coursesCount > 0 || $examsCount > 0) {
            return response()->json([
                'message' => "Cannot permanently delete this instructor because they have {$coursesCount} assigned course(s) and {$examsCount} examination record(s). To preserve academic records, please set their status to Inactive instead."
            ], 422);
        }

        $instructorName = $instructor->name;
        $instructor->delete();

        LogActivity::record(
            'Deleted',
            'Instructors',
            "Deleted Instructor \"$instructorName\""
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Instructor deleted successfully'
        ]);
    }

    /**
     * Export department instructors as PDF, Excel (.xlsx), or CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $format = strtolower($request->query('format', 'excel'));

        $dept = Department::find($deptId);
        $deptName = $dept ? $dept->name : 'Department';

        $query = User::where('department_id', $deptId)
            ->whereIn('role', ['instructor', 'dept_head'])
            ->with(['assignedCourses', 'coInstructorCourses', 'creator', 'department']);

        if ($request->filled('status') && $request->status !== 'all') {
            $status = strtolower($request->status);
            if ($status === 'active') {
                $query->where(function ($q) {
                    $q->where('status', 'active')->orWhereNull('status');
                });
            } elseif ($status === 'on_leave') {
                $query->whereIn('status', ['on_leave', 'on leave', 'leave']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('employment_type') && $request->employment_type !== 'all') {
            $et = strtolower($request->employment_type);
            if ($et === 'full_time' || $et === 'full-time') {
                $query->where(function ($q) {
                    $q->where('employment_type', 'full_time')
                      ->orWhere('employment_type', 'full time')
                      ->orWhereNull('employment_type');
                });
            } elseif ($et === 'part_time' || $et === 'part-time') {
                $query->where(function ($q) {
                    $q->where('employment_type', 'part_time')
                      ->orWhere('employment_type', 'part time');
                });
            }
        }

        if ($request->filled('year') && $request->year !== 'all') {
            $query->where('year_level', $request->year);
        }

        if ($request->filled('semester') && $request->semester !== 'all') {
            $query->where('semester', $request->semester);
        }

        if ($request->filled('section') && $request->section !== 'all') {
            $query->where('section', $request->section);
        }

        if ($request->filled('course_id') && $request->course_id !== 'all') {
            $courseId = (int) $request->course_id;
            $query->where(function ($q) use ($courseId) {
                $q->whereHas('assignedCourses', function ($cq) use ($courseId) {
                    $cq->where('id', $courseId);
                })->orWhereHas('coInstructorCourses', function ($cq) use ($courseId) {
                    $cq->where('id', $courseId);
                });
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('id_no', 'like', "%{$search}%");
            });
        }

        $instructors = $query->orderBy('name')->get();
        $dateStr = now()->format('Y-m-d');
        $sanitizedDept = preg_replace('/[^A-Za-z0-9_\-]/', '_', $deptName);
        $baseFileName = "{$sanitizedDept}_Instructors_{$dateStr}";

        if ($format === 'pdf') {
            return $this->exportInstructorsPdf($instructors, $deptName, $baseFileName);
        }

        if ($format === 'csv') {
            return $this->exportInstructorsCsv($instructors, $deptName, $baseFileName);
        }

        return $this->exportInstructorsExcel($instructors, $deptName, $baseFileName);
    }

    /**
     * Export instructors as PDF using Dompdf.
     */
    private function exportInstructorsPdf($instructors, string $deptName, string $baseFileName): JsonResponse
    {
        $generatedAt = now()->format('F j, Y  H:i');
        $totalRecords = $instructors->count();

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<title>' . htmlspecialchars($deptName) . ' — Department Instructors</title>';
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
            .badge-leave { display: inline-block; padding: 2px 5px; border-radius: 4px; font-weight: bold; color: #b45309; background: #fef3c7; font-size: 6.5px; }
            .badge-inactive { display: inline-block; padding: 2px 5px; border-radius: 4px; font-weight: bold; color: #be123c; background: #ffe4e6; font-size: 6.5px; }
            .badge-full { display: inline-block; padding: 2px 5px; border-radius: 4px; font-weight: bold; color: #4338ca; background: #e0e7ff; font-size: 6.5px; }
            .badge-part { display: inline-block; padding: 2px 5px; border-radius: 4px; font-weight: bold; color: #0284c7; background: #e0f2fe; font-size: 6.5px; }
            .footer { position: fixed; bottom: 10px; left: 25px; right: 25px; font-size: 7.5px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 4px; }
        </style></head><body>';

        $html .= '<table class="header-table"><tr>';
        $html .= '<td><div class="university-title">Wollo University</div>';
        $html .= '<div class="dept-title">Department of ' . htmlspecialchars($deptName) . ' — Instructors & Academic Staff</div></td>';
        $html .= '<td class="meta"><strong>Date Generated:</strong> ' . $generatedAt . '<br><strong>Total Instructors:</strong> ' . $totalRecords . '</td>';
        $html .= '</tr></table>';

        $html .= '<table class="data-table"><thead><tr>';
        $html .= '<th style="width: 25px;">#</th>';
        $html .= '<th>Instructor Name</th>';
        $html .= '<th>Employee ID</th>';
        $html .= '<th>Email Address</th>';
        $html .= '<th>Phone</th>';
        $html .= '<th>Assigned Courses</th>';
        $html .= '<th>Credits</th>';
        $html .= '<th>Type</th>';
        $html .= '<th>Status</th>';
        $html .= '<th>Joined Date</th>';
        $html .= '</tr></thead><tbody>';

        if ($instructors->isEmpty()) {
            $html .= '<tr><td colspan="10" style="text-align: center; padding: 15px; color: #94a3b8;">No instructor records found in this department.</td></tr>';
        } else {
            foreach ($instructors as $i => $inst) {
                $status = strtolower($inst->status ?? 'active');
                if ($status === 'on_leave' || $status === 'on leave') {
                    $statusBadge = '<span class="badge-leave">On Leave</span>';
                } elseif ($status === 'inactive') {
                    $statusBadge = '<span class="badge-inactive">Inactive</span>';
                } else {
                    $statusBadge = '<span class="badge-active">Active</span>';
                }

                $et = strtolower($inst->employment_type ?? 'full_time');
                $typeBadge = ($et === 'part_time' || $et === 'part time')
                    ? '<span class="badge-part">Part-Time</span>'
                    : '<span class="badge-full">Full-Time</span>';

                $courses = $inst->assignedCourses?->pluck('title')->filter()->join(', ');
                if (empty($courses)) {
                    $courses = 'No Courses';
                }
                $credits = $inst->assignedCourses?->sum('credits') ?? 0;
                $joinedDate = $inst->created_at ? $inst->created_at->format('M d, Y') : '—';

                $html .= '<tr>';
                $html .= '<td>' . ($i + 1) . '</td>';
                $html .= '<td><strong>' . htmlspecialchars($inst->name ?? '') . '</strong></td>';
                $html .= '<td>' . htmlspecialchars($inst->id_no ?? (string)$inst->id) . '</td>';
                $html .= '<td>' . htmlspecialchars($inst->email ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($inst->phone ?? '—') . '</td>';
                $html .= '<td>' . htmlspecialchars($courses) . '</td>';
                $html .= '<td>' . $credits . '</td>';
                $html .= '<td>' . $typeBadge . '</td>';
                $html .= '<td>' . $statusBadge . '</td>';
                $html .= '<td>' . $joinedDate . '</td>';
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

        LogActivity::record('Exported', 'Instructors', "Exported {$totalRecords} instructors list as PDF ({$deptName})");

        return response()->json([
            'file'     => base64_encode($pdfOutput),
            'filename' => $baseFileName . '.pdf',
            'format'   => 'pdf',
        ]);
    }

    /**
     * Export instructors as Excel (.xlsx).
     */
    private function exportInstructorsExcel($instructors, string $deptName, string $baseFileName): JsonResponse
    {
        $export = new DepartmentInstructorExport($instructors);
        $xlsxBytes = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);

        LogActivity::record('Exported', 'Instructors', "Exported {$instructors->count()} instructors list as Excel ({$deptName})");

        return response()->json([
            'file'     => base64_encode($xlsxBytes),
            'filename' => $baseFileName . '.xlsx',
            'format'   => 'xlsx',
        ]);
    }

    /**
     * Export instructors as CSV (Excel-compatible UTF-8 BOM).
     */
    private function exportInstructorsCsv($instructors, string $deptName, string $baseFileName): JsonResponse
    {
        ob_start();
        $handle = fopen('php://output', 'w');
        // UTF-8 BOM for Microsoft Excel auto-detect
        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            '#', 'Full Name', 'Employee ID', 'Email Address', 'Phone', 'Gender',
            'Department', 'Assigned Courses', 'Total Credits', 'Employment Type',
            'Status', 'Academic Year', 'Section', 'Created By', 'Joined Date'
        ]);

        foreach ($instructors as $i => $inst) {
            $courses = $inst->assignedCourses?->pluck('title')->filter()->join(', ');
            if (empty($courses)) {
                $courses = 'No Courses';
            }
            $credits = $inst->assignedCourses?->sum('credits') ?? 0;
            $employment = ucfirst(str_replace('_', ' ', $inst->employment_type ?? 'full_time'));
            $creatorName = $inst->creator ? $inst->creator->name : 'Super Admin';

            fputcsv($handle, [
                $i + 1,
                $inst->name ?? '',
                $inst->id_no ?? (string)$inst->id,
                $inst->email ?? '',
                $inst->phone ?? '—',
                $inst->gender ?? '—',
                $inst->department ? $inst->department->name : $deptName,
                $courses,
                $credits,
                $employment,
                ucfirst($inst->status ?? 'active'),
                $inst->year_level ?? '—',
                $inst->section ?? '—',
                $creatorName,
                $inst->created_at ? $inst->created_at->format('M d, Y') : '',
            ]);
        }
        fclose($handle);
        $csvContent = ob_get_clean();

        LogActivity::record('Exported', 'Instructors', "Exported {$instructors->count()} instructors list as CSV ({$deptName})");

        return response()->json([
            'file'     => base64_encode($csvContent),
            'filename' => $baseFileName . '.csv',
            'format'   => 'csv',
        ]);
    }
}
