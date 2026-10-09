<?php

namespace App\Http\Controllers\Api\V1\DeptHead;

use App\Exports\DepartmentExamExport;
use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\AcademicEvent;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\SystemSetting;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ExamController extends Controller
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
     * Authoritative department exam query boundary:
     * strictly restricts exams to those belonging to courses, instructors,
     * or explicit department ownership within this department.
     */
    private function buildDeptExamsQuery(?int $deptId, int $headId)
    {
        $deptCourseCodes = [];
        if ($deptId) {
            $deptCourseCodes = Course::where('department_id', $deptId)->pluck('code')->filter()->toArray();
        }

        return Exam::where(function ($q) use ($deptId, $headId, $deptCourseCodes) {
            if ($deptId) {
                $q->whereHas('instructor', function ($sub) use ($deptId) {
                    $sub->where('department_id', $deptId);
                });
                if (!empty($deptCourseCodes)) {
                    $q->orWhereIn('course_code', $deptCourseCodes);
                }
                $q->orWhere('user_id', $headId)
                  ->orWhere('settings->department_id', $deptId);
            } else {
                $q->where('user_id', $headId);
            }
        });
    }

    private function formatDuration(?int $minutes): string
    {
        if (!$minutes || $minutes <= 0) return '60m';
        $hours = intdiv($minutes, 60);
        $remMinutes = $minutes % 60;
        if ($hours > 0) {
            return $hours . 'h ' . ($remMinutes > 0 ? str_pad((string)$remMinutes, 2, '0', STR_PAD_LEFT) . 'm' : '');
        }
        return $remMinutes . 'm';
    }

    private function determineExamType($exam): string
    {
        if (!empty($exam->settings['exam_type'])) {
            return ucfirst($exam->settings['exam_type']);
        }
        $title = strtolower($exam->title ?? '');
        if (str_contains($title, 'final')) return 'Final';
        if (str_contains($title, 'quiz')) return 'Quiz';
        if (str_contains($title, 'practical') || str_contains($title, 'lab')) return 'Practical';
        if (str_contains($title, 'assignment') || str_contains($title, 'test')) return 'Quiz';
        return 'Midterm';
    }

    private function formatExamCode($exam): string
    {
        if (!empty($exam->settings['exam_code'])) {
            return $exam->settings['exam_code'];
        }
        $year = $exam->scheduled_at ? $exam->scheduled_at->format('Y') : ($exam->created_at ? $exam->created_at->format('Y') : date('Y'));
        return 'EXM-' . $year . '-' . str_pad((string)$exam->id, 3, '0', STR_PAD_LEFT);
    }

    private function determineComputedStatus($exam): string
    {
        $raw = strtolower($exam->status ?? '');
        if ($raw === 'completed') return 'Completed';
        if (in_array($raw, ['cancelled', 'canceled']) || $exam->cancelled_at !== null) return 'Cancelled';
        if ($raw === 'draft') return 'Draft';
        if ($raw === 'paused' || $exam->paused_at !== null) return 'Paused';

        $now = Carbon::now();
        $scheduledAt = $exam->scheduled_at;
        $duration = $exam->duration_minutes ?? 60;

        if ($scheduledAt) {
            $endAt = $scheduledAt->copy()->addMinutes($duration);
            if ($now->between($scheduledAt, $endAt)) {
                return 'Active';
            }
            if ($now->gt($endAt)) {
                return 'Completed';
            }
            return 'Scheduled';
        }

        return $raw === 'published' ? 'Published' : 'Scheduled';
    }

    /**
     * Display a listing of exams with server-side search, filtering,
     * sorting, pagination, and real departmental statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $headId = $request->user()->id;
        $dept = $deptId ? Department::find($deptId) : null;

        // Base scoped query
        $baseQuery = $this->buildDeptExamsQuery($deptId, $headId);

        // -------------------------------------------------------------
        // 1. Department-wide Real KPI Statistics (unfiltered)
        // -------------------------------------------------------------
        $allDeptExams = (clone $baseQuery)
            ->with(['course'])
            ->withCount(['attempts', 'questions'])
            ->get();

        $conflictsMap = $this->detectExamConflicts($allDeptExams);
        $now = Carbon::now();
        $totalExams = $allDeptExams->count();

        $activeExams = 0;
        $upcomingExams = 0;
        $scheduledExams = 0;
        $completedExams = 0;
        $cancelledExams = 0;
        $draftExams = 0;

        foreach ($allDeptExams as $e) {
            $status = $this->determineComputedStatus($e);
            if ($status === 'Active') {
                $activeExams++;
            } elseif ($status === 'Scheduled' || $status === 'Published') {
                $upcomingExams++;
                $scheduledExams++;
            } elseif ($status === 'Completed') {
                $completedExams++;
            } elseif ($status === 'Cancelled') {
                $cancelledExams++;
            } elseif ($status === 'Draft') {
                $draftExams++;
            }
        }

        $totalSubmissions = $allDeptExams->sum('attempts_count');
        $coursesWithExams = $allDeptExams->pluck('course_code')->filter()->unique()->count();

        $stats = [
            'total'              => $totalExams,
            'scheduled'          => $scheduledExams,
            'upcoming'           => $upcomingExams,
            'active'             => $activeExams,
            'completed'          => $completedExams,
            'cancelled'          => $cancelledExams,
            'draft'              => $draftExams,
            'conflicts'          => count($conflictsMap),
            'total_submissions'  => $totalSubmissions,
            'courses_with_exams' => $coursesWithExams,
        ];

        // -------------------------------------------------------------
        // 2. Dynamic Available Filter Options for this Department
        // -------------------------------------------------------------
        $deptCourses = Course::where('department_id', $deptId)
            ->select('id', 'title', 'code', 'credits', 'level', 'semester')
            ->orderBy('title')
            ->get();

        $deptInstructors = User::where('department_id', $deptId)
            ->whereIn('role', ['instructor', 'dept_head'])
            ->select('id', 'name', 'email', 'id_no')
            ->orderBy('name')
            ->get();

        $availableSemesters = collect(['First Semester', 'Second Semester', 'Summer Term'])
            ->concat($deptCourses->pluck('semester'))
            ->concat($allDeptExams->map(fn($e) => $e->settings['semester'] ?? null))
            ->filter()->unique()->values()->all();

        $availableYears = collect(['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'])
            ->concat($deptCourses->pluck('level'))
            ->concat($allDeptExams->map(fn($e) => $e->settings['year_level'] ?? null))
            ->filter()->unique()->values()->all();

        $filterOptions = [
            'semesters'   => $availableSemesters,
            'year_levels' => $availableYears,
            'courses'     => $deptCourses,
            'instructors' => $deptInstructors,
            'exam_types'  => ['Midterm', 'Final', 'Quiz', 'Practical', 'Assignment'],
            'statuses'    => [
                ['value' => 'all',       'label' => 'All Statuses'],
                ['value' => 'upcoming',  'label' => 'Upcoming'],
                ['value' => 'active',    'label' => 'Active / In Progress'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'scheduled', 'label' => 'Scheduled'],
                ['value' => 'published', 'label' => 'Published'],
                ['value' => 'draft',     'label' => 'Draft'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
        ];

        // -------------------------------------------------------------
        // 3. Search and Filtering
        // -------------------------------------------------------------
        $query = (clone $baseQuery)
            ->with(['instructor:id,name,email,profile_picture,role,id_no', 'course:id,title,code,credits,level,semester'])
            ->withCount(['questions', 'attempts']);

        // Search term
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('course_code', 'like', "%{$search}%")
                  ->orWhere('course_name', 'like', "%{$search}%")
                  ->orWhere('settings->exam_code', 'like', "%{$search}%")
                  ->orWhereHas('instructor', function ($iq) use ($search) {
                      $iq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Semester filter
        if ($request->filled('semester') && $request->semester !== 'all') {
            $sem = $request->semester;
            $query->where(function ($q) use ($sem) {
                $q->where('settings->semester', $sem)
                  ->orWhereHas('course', function ($cq) use ($sem) {
                      $cq->where('semester', $sem);
                  });
            });
        }

        // Academic year / year level filter
        if ($request->filled('year') && $request->year !== 'all') {
            $yr = $request->year;
            $query->where(function ($q) use ($yr) {
                $q->where('settings->year_level', $yr)
                  ->orWhere('settings->academic_year', $yr)
                  ->orWhereHas('course', function ($cq) use ($yr) {
                      $cq->where('level', $yr);
                  });
            });
        }

        // Course filter
        if ($request->filled('course_code') && $request->course_code !== 'all') {
            $query->where('course_code', $request->course_code);
        }

        // Exam type filter
        if ($request->filled('exam_type') && $request->exam_type !== 'all') {
            $type = strtolower($request->exam_type);
            $query->where(function ($q) use ($type) {
                $q->where('settings->exam_type', $type)
                  ->orWhere('title', 'like', "%{$type}%");
            });
        }

        // Instructor filter
        if ($request->filled('instructor_id') && $request->instructor_id !== 'all') {
            $query->where('user_id', $request->instructor_id);
        }

        // Status filter
        if ($request->filled('status') && $request->status !== 'all') {
            $st = strtolower($request->status);
            if ($st === 'upcoming') {
                $query->whereIn('status', ['scheduled', 'published'])
                      ->where(function ($q) use ($now) {
                          $q->whereNull('scheduled_at')
                            ->orWhere('scheduled_at', '>=', $now->toDateString());
                      });
            } elseif ($st === 'active') {
                $query->whereIn('status', ['published', 'scheduled'])
                      ->whereNotNull('scheduled_at')
                      ->where('scheduled_at', '<=', $now);
            } elseif ($st === 'completed') {
                $query->where(function ($q) use ($now) {
                    $q->where('status', 'completed')
                      ->orWhere(function ($sub) use ($now) {
                          $sub->whereNotNull('scheduled_at')
                              ->where('scheduled_at', '<', $now->copy()->subHours(2));
                      });
                });
            } elseif ($st === 'cancelled') {
                $query->where(function ($q) {
                    $q->whereIn('status', ['cancelled', 'canceled'])
                      ->orWhereNotNull('cancelled_at');
                });
            } else {
                $query->where('status', $st);
            }
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('scheduled_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('scheduled_at', '<=', $request->date_to);
        }

        // -------------------------------------------------------------
        // 4. Sorting
        // -------------------------------------------------------------
        $sortBy = $request->query('sort_by', 'scheduled_at');
        $sortOrder = strtolower($request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['title', 'course_code', 'scheduled_at', 'duration_minutes', 'total_marks', 'status', 'created_at', 'id'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest('id');
        }

        // -------------------------------------------------------------
        // 5. Pagination & Transformation
        // -------------------------------------------------------------
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min(100, $perPage));

        $paginated = $query->paginate($perPage);

        // Preload department students grouped by year level for participation
        $deptStudentsByLevel = User::where('department_id', $deptId)
            ->where('role', 'student')
            ->get(['id', 'year_level'])
            ->groupBy('year_level');

        $totalDeptStudentsCount = User::where('department_id', $deptId)->where('role', 'student')->count();

        $transformed = collect($paginated->items())->map(function ($exam) use ($deptStudentsByLevel, $totalDeptStudentsCount, $conflictsMap) {
            $duration = $exam->duration_minutes ?? 60;
            $scheduledAt = $exam->scheduled_at;
            $endTime = $scheduledAt ? $scheduledAt->copy()->addMinutes($duration)->format('h:i A') : 'TBD';

            $courseLevel = $exam->course?->level ?? ($exam->settings['year_level'] ?? null);
            $eligibleCount = $courseLevel && isset($deptStudentsByLevel[$courseLevel])
                ? $deptStudentsByLevel[$courseLevel]->count()
                : $totalDeptStudentsCount;

            $displayStatus = $this->determineComputedStatus($exam);

            return [
                'id'                      => $exam->id,
                'title'                   => $exam->title,
                'code'                    => $this->formatExamCode($exam),
                'course'                  => $exam->course_name ?: ($exam->course->title ?? 'General Course'),
                'courseName'              => $exam->course_name ?: ($exam->course->title ?? 'General Course'),
                'courseCode'              => $exam->course_code ?: ($exam->course->code ?? 'N/A'),
                'courseCredits'           => $exam->course->credits ?? null,
                'type'                    => $this->determineExamType($exam),
                'scheduled_at'            => $scheduledAt ? $scheduledAt->toIso8601String() : null,
                'date'                    => $scheduledAt ? $scheduledAt->format('M d, Y') : 'Unscheduled',
                'time'                    => $scheduledAt ? $scheduledAt->format('h:i A') : 'Flexible Window',
                'endTime'                 => $endTime,
                'duration'                => $this->formatDuration($duration),
                'duration_minutes'        => $duration,
                'questions'               => $exam->questions_count ?? 0,
                'marks'                   => $exam->total_marks ?? 100,
                'status'                  => $displayStatus,
                'raw_status'              => strtolower($exam->status ?? ''),
                'instructor_name'         => $exam->instructor->name ?? 'Unassigned Faculty',
                'instructor_email'        => $exam->instructor->email ?? '',
                'instructor_id'           => $exam->user_id,
                'instructor'              => $exam->instructor ? [
                    'id'                  => $exam->instructor->id,
                    'name'                => $exam->instructor->name,
                    'email'               => $exam->instructor->email,
                    'profile_picture_url' => $exam->instructor->profile_picture_url ?? null,
                ] : null,
                'attempts_count'          => $exam->attempts_count ?? 0,
                'eligible_students_count' => $eligibleCount,
                'semester'                => $exam->settings['semester'] ?? ($exam->course?->semester ?? 'Semester 1'),
                'year'                    => $exam->settings['year_level'] ?? ($exam->course?->level ?? 'Year 1'),
                'room'                    => $exam->settings['room'] ?? 'Room 101',
                'has_conflict'            => isset($conflictsMap[$exam->id]),
                'conflict_reason'         => $conflictsMap[$exam->id] ?? null,
                'can_edit'                => !in_array($displayStatus, ['Completed', 'Cancelled']),
                'can_cancel'              => $displayStatus !== 'Cancelled',
                'settings'                => $exam->settings ?? [],
            ];
        });

        return response()->json([
            'status'         => 'success',
            'data'           => $transformed,
            'stats'          => $stats,
            'department'     => [
                'id'      => $dept?->id,
                'name'    => ucwords($dept?->name ?? 'Department'),
                'code'    => $dept?->code ?? 'DEPT',
                'college' => $dept?->college ?? 'College of Computing and Informatics',
            ],
            'filter_options' => $filterOptions,
            'semesters'      => $availableSemesters,
            'years'          => $availableYears,
            'exam_types'     => $filterOptions['exam_types'],
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
     * Display the specified exam details with full questions preview,
     * student participation breakdown, settings, and attempts list.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $headId = $request->user()->id;

        // Scoped lookup to prevent cross-department inspection
        $exam = $this->buildDeptExamsQuery($deptId, $headId)
            ->with([
                'instructor.department',
                'course',
                'questions',
                'attempts.student:id,name,email,id_no,section,year_level',
                'cancelledBy:id,name',
            ])
            ->findOrFail($id);

        $settings = $exam->settings ?? [];
        $duration = $exam->duration_minutes ?? 60;
        $endTime = $exam->scheduled_at ? $exam->scheduled_at->copy()->addMinutes($duration)->format('h:i A') : 'TBD';

        // Questions preview
        $questionsList = $exam->questions->values()->map(function ($q, $index) {
            $rawType = $q->type ?: 'multiple_choice';
            $displayType = match($rawType) {
                'multiple_choice' => 'MCQ',
                'true_false'      => 'True/False',
                'short_answer'    => 'Short Answer',
                'matching'        => 'Matching',
                'fill_in_the_blank', 'fill_in_blank' => 'Fill in the Blanks',
                default           => ucfirst(str_replace('_', ' ', $rawType)),
            };

            return [
                'id'              => $q->id,
                'number'          => $index + 1,
                'type'            => $displayType,
                'full_type'       => $displayType,
                'raw_type'        => $rawType,
                'text'            => $q->text ?: ($q->title ?: 'Question #' . ($index + 1)),
                'marks'           => $q->marks ?? 5,
                'instruction'     => $q->instruction ?: ($rawType === 'multiple_choice' ? 'Choose the correct answer from options below.' : ''),
                'options'         => $q->options ?? [],
                'correct_answer'  => $q->correct_answer,
                'expected_answer' => $q->correct_answer ?: 'SELECT',
            ];
        });

        // Student participation analytics
        $attempts = $exam->attempts;
        $totalAttempts = $attempts->count();
        $submittedAttempts = $attempts->whereIn('status', ['submitted', 'graded', 'published'])->count();
        $passedAttempts = $attempts->where('percentage', '>=', 50)->count();
        $avgScore = $totalAttempts > 0 ? round($attempts->avg('percentage') ?? 0, 1) : 0;
        $passRate = $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100, 1) : 0;

        // Cohort eligible students
        $courseLevel = $exam->course?->level ?? ($settings['year_level'] ?? null);
        $eligibleStudentsQuery = User::where('department_id', $deptId)->where('role', 'student');
        if ($courseLevel) {
            $eligibleStudentsQuery->where('year_level', $courseLevel);
        }
        $eligibleCount = $eligibleStudentsQuery->count();

        // Attempts list preview
        $attemptsList = $attempts->map(function ($att) {
            return [
                'id'           => $att->id,
                'student_name' => $att->student?->name ?? 'Student',
                'student_id'   => $att->student?->id_no ?? (string)$att->user_id,
                'email'        => $att->student?->email ?? '',
                'score'        => $att->score ?? 0,
                'total_marks'  => $att->total_marks ?? 100,
                'percentage'   => $att->percentage ?? 0,
                'grade'        => $att->grade ?? '—',
                'status'       => $att->status ?? 'started',
                'submitted_at' => $att->submitted_at ? $att->submitted_at->format('M d, Y • h:i A') : 'In progress',
            ];
        });

        $createdByName = $exam->instructor?->name ?? 'Department Head';
        $displayStatus = $this->determineComputedStatus($exam);

        $formattedDetail = [
            'id'                 => $exam->id,
            'title'              => $exam->title,
            'code'               => $this->formatExamCode($exam),
            'courseName'         => $exam->course_name ?: ($exam->course->title ?? 'General Course'),
            'courseCode'         => $exam->course_code ?: ($exam->course->code ?? 'N/A'),
            'courseCredits'      => $exam->course->credits ?? null,
            'type'               => $this->determineExamType($exam),
            'date'               => $exam->scheduled_at ? $exam->scheduled_at->format('M d, Y') : 'Unscheduled',
            'time'               => $exam->scheduled_at ? $exam->scheduled_at->format('h:i A') : 'Flexible Window',
            'endTime'            => $endTime,
            'duration'           => $this->formatDuration($duration),
            'duration_minutes'   => $duration,
            'questions'          => $exam->questions->count(),
            'marks'              => $exam->total_marks ?? 100,
            'status'             => $displayStatus,
            'raw_status'         => strtolower($exam->status ?? ''),
            'instructorName'     => $exam->instructor->name ?? 'Unassigned Faculty',
            'instructorEmail'    => $exam->instructor->email ?? '',
            'instructorId'       => $exam->user_id,
            'createdByName'      => $createdByName,
            'createdAtFormatted' => $exam->created_at ? $exam->created_at->format('M d, Y h:i A') : '',
            'updatedAtFormatted' => $exam->updated_at ? $exam->updated_at->format('M d, Y h:i A') : '',
            'cancelledAt'        => $exam->cancelled_at ? $exam->cancelled_at->format('M d, Y h:i A') : null,
            'cancelledByName'    => $exam->cancelledBy?->name ?? null,
            'cancellationReason' => $exam->cancellation_reason,
            'totalAttempts'      => $totalAttempts,
            'submittedAttempts'  => $submittedAttempts,
            'eligibleStudents'   => $eligibleCount,
            'averageScore'       => $avgScore,
            'passRate'           => $passRate,
            'can_edit'           => !in_array($displayStatus, ['Completed', 'Cancelled']),
            'can_cancel'         => $displayStatus !== 'Cancelled',
            'settings'           => [
                'shuffleQuestions'           => (bool)($settings['shuffleQuestions'] ?? $settings['shuffle_questions'] ?? true),
                'showReviewScreen'           => (bool)($settings['showReviewScreen'] ?? $settings['examReviewGroup'] ?? true),
                'examReviewGroup'            => (bool)($settings['showReviewScreen'] ?? $settings['examReviewGroup'] ?? true),
                'shuffleAnswers'             => (bool)($settings['shuffleAnswers'] ?? $settings['shuffleOptions'] ?? true),
                'shuffleOptions'             => (bool)($settings['shuffleAnswers'] ?? $settings['shuffleOptions'] ?? true),
                'allowBacktracking'          => (bool)($settings['allowBacktracking'] ?? true),
                'showOneQuestionAtATime'     => (bool)($settings['showOneQuestionAtATime'] ?? $settings['showOneQuestion'] ?? false),
                'showOneQuestion'            => (bool)($settings['showOneQuestionAtATime'] ?? $settings['showOneQuestion'] ?? false),
                'autoSubmit'                 => (bool)($settings['autoSubmit'] ?? true),
                'enableFullscreenMode'       => (bool)($settings['enableFullscreenMode'] ?? true),
                'enableBrowserTabMonitoring' => (bool)($settings['enableBrowserTabMonitoring'] ?? true),
                'disableRightClick'          => (bool)($settings['disableRightClick'] ?? true),
                'allowCalculator'            => (bool)($settings['allowCalculator'] ?? false),
                'disableCopyPaste'           => (bool)($settings['disableCopyPaste'] ?? true),
                'webcamMonitoring'           => (bool)($settings['webcamMonitoring'] ?? false),
                'room'                       => $settings['room'] ?? null,
                'invigilator'                => $settings['invigilator'] ?? null,
                'notes'                      => $settings['notes'] ?? null,
            ],
            'questionsList'      => $questionsList,
            'attemptsList'       => $attemptsList,
        ];

        return response()->json([
            'status' => 'success',
            'data'   => $formattedDetail
        ]);
    }

    /**
     * Detect temporal and venue collisions among an exam collection.
     */
    private function detectExamConflicts($examCollection): array
    {
        $conflictsMap = [];
        $items = $examCollection->values();
        $count = $items->count();

        for ($i = 0; $i < $count; $i++) {
            $a = $items[$i];
            if (!$a->scheduled_at || in_array(strtolower($a->status ?? ''), ['cancelled', 'draft'])) continue;
            $startA = Carbon::parse($a->scheduled_at);
            $endA = $startA->copy()->addMinutes($a->duration_minutes ?? 60);
            $roomA = trim($a->settings['room'] ?? '');

            for ($j = $i + 1; $j < $count; $j++) {
                $b = $items[$j];
                if (!$b->scheduled_at || in_array(strtolower($b->status ?? ''), ['cancelled', 'draft'])) continue;
                $startB = Carbon::parse($b->scheduled_at);
                $endB = $startB->copy()->addMinutes($b->duration_minutes ?? 60);
                $roomB = trim($b->settings['room'] ?? '');

                // Temporal Overlap: Start A < End B && End A > Start B
                if ($startA->lt($endB) && $endA->gt($startB)) {
                    $reason = null;
                    if ($a->course_code === $b->course_code) {
                        $reason = "Course time conflict ({$a->course_code})";
                    } elseif (!empty($roomA) && !empty($roomB) && strcasecmp($roomA, $roomB) === 0) {
                        $reason = "Room double-booking ({$roomA})";
                    }

                    if ($reason) {
                        $conflictsMap[$a->id] = $reason;
                        $conflictsMap[$b->id] = $reason;
                    }
                }
            }
        }

        return $conflictsMap;
    }

    /**
     * Schedule conflict detection helper for pre-flight and submission validation.
     */
    private function checkSchedulingConflict(?int $deptId, string $courseCode, ?string $scheduledAt, int $durationMinutes, $excludeExamId = null, ?string $room = null): ?array
    {
        if (!$scheduledAt) {
            return null;
        }

        $newStart = Carbon::parse($scheduledAt);
        $newEnd = $newStart->copy()->addMinutes($durationMinutes);
        $cleanRoom = trim((string)$room);

        // Fetch active/scheduled exams across the department
        $exams = Exam::whereIn('status', ['published', 'scheduled'])
            ->when($excludeExamId, function ($query) use ($excludeExamId) {
                return $query->where('id', '!=', $excludeExamId);
            })
            ->get();

        foreach ($exams as $exam) {
            if (!$exam->scheduled_at) continue;
            $existingStart = Carbon::parse($exam->scheduled_at);
            $existingEnd = $existingStart->copy()->addMinutes($exam->duration_minutes ?? 60);

            // Overlap: Start A < End B && End A > Start B
            if ($newStart->lt($existingEnd) && $newEnd->gt($existingStart)) {
                if ($exam->course_code === $courseCode) {
                    return [
                        'conflict_type' => 'course',
                        'exam_id'       => $exam->id,
                        'title'         => $exam->title,
                        'course_code'   => $exam->course_code,
                        'scheduled_at'  => $existingStart->format('M d, Y • h:i A'),
                        'message'       => "Course Schedule Collision: Course {$courseCode} is already scheduled on {$existingStart->format('M d, Y')} at {$existingStart->format('h:i A')} (\"{$exam->title}\").",
                    ];
                }

                $existingRoom = trim($exam->settings['room'] ?? '');
                if (!empty($cleanRoom) && !empty($existingRoom) && strcasecmp($cleanRoom, $existingRoom) === 0) {
                    return [
                        'conflict_type' => 'room',
                        'exam_id'       => $exam->id,
                        'title'         => $exam->title,
                        'room'          => $cleanRoom,
                        'scheduled_at'  => $existingStart->format('M d, Y • h:i A'),
                        'message'       => "Room Double-Booking: {$cleanRoom} is already assigned to \"{$exam->title}\" ({$exam->course_code}) at {$existingStart->format('h:i A')}.",
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Pre-flight conflict check endpoint for frontend scheduling / rescheduling modal.
     */
    public function checkConflict(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);

        $request->validate([
            'course_code'      => 'required|string',
            'scheduled_at'     => 'required|date',
            'duration_minutes' => 'required|integer|min:5|max:360',
            'room'             => 'nullable|string',
            'exclude_id'       => 'nullable|integer',
        ]);

        $conflict = $this->checkSchedulingConflict(
            $deptId,
            $request->course_code,
            $request->scheduled_at,
            (int)$request->duration_minutes,
            $request->exclude_id,
            $request->room
        );

        if ($conflict) {
            return response()->json([
                'has_conflict' => true,
                'conflict'     => $conflict,
                'message'      => $conflict['message'],
            ]);
        }

        return response()->json([
            'has_conflict' => false,
            'message'      => 'No scheduling conflicts detected for this time window.',
        ]);
    }

    /**
     * Unified calendar feed: Department-scoped exams + University academic events.
     */
    public function calendarEvents(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $headId = $request->user()->id;
        $dept = $deptId ? Department::find($deptId) : null;

        // Active term from SystemSetting
        $activeYear = SystemSetting::where('key', 'academicYear')->value('value') ?: '2028';
        $activeSemester = SystemSetting::where('key', 'semester')->value('value') ?: 'Second Semester';

        // 1. Department Exams (strictly scoped)
        $baseQuery = $this->buildDeptExamsQuery($deptId, $headId);
        $allDeptExams = (clone $baseQuery)
            ->with(['course', 'instructor:id,name,email'])
            ->get();

        $now = Carbon::now();
        $totalExams = $allDeptExams->count();
        $scheduledCount = 0;
        $upcomingCount = 0;
        $completedCount = 0;

        foreach ($allDeptExams as $e) {
            $computed = $this->determineComputedStatus($e);
            if (in_array($computed, ['Scheduled', 'Published'])) {
                $scheduledCount++;
            }
            if ($e->scheduled_at && Carbon::parse($e->scheduled_at)->gte($now) && !in_array($computed, ['Cancelled', 'Completed'])) {
                $upcomingCount++;
            }
            if ($computed === 'Completed') {
                $completedCount++;
            }
        }

        $conflictsMap = $this->detectExamConflicts($allDeptExams);

        $examEvents = $allDeptExams->map(function ($e) use ($conflictsMap, $activeYear, $activeSemester) {
            $duration = $e->duration_minutes ?? 60;
            $start = $e->scheduled_at ? Carbon::parse($e->scheduled_at) : null;
            $end = $start ? $start->copy()->addMinutes($duration) : null;
            $computedStatus = $this->determineComputedStatus($e);

            return [
                'id'               => 'exam-' . $e->id,
                'exam_id'          => $e->id,
                'type'             => 'exam',
                'title'            => $e->title,
                'code'             => $this->formatExamCode($e),
                'course_code'      => $e->course_code ?: ($e->course?->code ?? 'N/A'),
                'course_name'      => $e->course_name ?: ($e->course?->title ?? 'Course'),
                'instructor_name'  => $e->instructor?->name ?? 'Unassigned Faculty',
                'instructor_email' => $e->instructor?->email ?? '',
                'room'             => $e->settings['room'] ?? 'Room 101',
                'section'          => $e->section ?? '',
                'exam_type'        => $this->determineExamType($e),
                'start'            => $start ? $start->toIso8601String() : null,
                'end'              => $end ? $end->toIso8601String() : null,
                'date'             => $start ? $start->format('Y-m-d') : null,
                'date_formatted'   => $start ? $start->format('M d, Y') : 'Unscheduled',
                'start_time'       => $start ? $start->format('h:i A') : 'TBD',
                'end_time'         => $end ? $end->format('h:i A') : 'TBD',
                'duration'         => $this->formatDuration($duration),
                'duration_minutes' => $duration,
                'total_marks'      => $e->total_marks ?? 100,
                'status'           => $computedStatus,
                'raw_status'       => strtolower($e->status ?? ''),
                'has_conflict'     => isset($conflictsMap[$e->id]),
                'conflict_reason'  => $conflictsMap[$e->id] ?? null,
                'semester'         => $e->settings['semester'] ?? ($e->course?->semester ?? $activeSemester),
                'academic_year'    => $e->settings['academic_year'] ?? $activeYear,
                'color'            => isset($conflictsMap[$e->id]) ? '#ef4444' : '#5138ed',
                'can_edit'         => !in_array($computedStatus, ['Completed', 'Cancelled']),
                'can_cancel'       => $computedStatus !== 'Cancelled',
            ];
        });

        // 2. University Academic Calendar Events
        $academicEvents = AcademicEvent::with('category')->get()->map(function ($ev) {
            return [
                'id'             => 'event-' . $ev->id,
                'event_id'       => $ev->id,
                'type'           => 'academic_event',
                'title'          => $ev->title,
                'description'    => $ev->description,
                'category'       => $ev->category?->name ?? 'University Date',
                'category_color' => $ev->category?->color ?? '#3b82f6',
                'start_date'     => $ev->start_date ? $ev->start_date->toDateString() : null,
                'end_date'       => $ev->end_date ? $ev->end_date->toDateString() : null,
                'start'          => $ev->start_date ? $ev->start_date->toIso8601String() : null,
                'end'            => $ev->end_date ? $ev->end_date->toIso8601String() : null,
                'all_day'        => (bool)$ev->all_day,
                'start_time'     => $ev->start_time,
                'end_time'       => $ev->end_time,
                'status'         => $ev->status,
                'academic_year'  => $ev->academic_year,
                'semester'       => $ev->semester,
                'color'          => $ev->color ?: ($ev->category?->color ?? '#3b82f6'),
            ];
        });

        // Available filter options
        $deptCourses = Course::where('department_id', $deptId)
            ->select('id', 'title', 'code', 'credits', 'level', 'semester')
            ->orderBy('title')
            ->get();

        $deptInstructors = User::where('department_id', $deptId)
            ->whereIn('role', ['instructor', 'dept_head'])
            ->select('id', 'name', 'email', 'id_no')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status'          => 'success',
            'exams'           => $examEvents,
            'academic_events' => $academicEvents,
            'stats'           => [
                'total'     => $totalExams,
                'scheduled' => $scheduledCount,
                'upcoming'  => $upcomingCount,
                'conflicts' => count($conflictsMap),
                'completed' => $completedCount,
            ],
            'active_term'     => [
                'academic_year' => $activeYear,
                'semester'      => $activeSemester,
            ],
            'department'      => [
                'id'      => $dept?->id,
                'name'    => ucwords($dept?->name ?? 'Department'),
                'code'    => $dept?->code ?? 'CS',
                'college' => $dept?->college ?? 'College of Informatics',
            ],
            'courses'         => $deptCourses,
            'instructors'     => $deptInstructors,
        ]);
    }

    /**
     * Create / Schedule a new examination.
     */
    public function store(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $headId = $request->user()->id;

        $request->validate([
            'title'            => 'required|string|max:255',
            'course_code'      => 'required|string|max:50',
            'scheduled_at'     => 'required|date',
            'duration_minutes' => 'required|integer|min:5|max:360',
            'total_marks'      => 'required|integer|min:1|max:500',
            'exam_type'        => 'nullable|string|max:50',
            'instructor_id'    => 'nullable|exists:users,id',
            'room'             => 'nullable|string|max:100',
            'section'          => 'nullable|string|max:100',
            'description'      => 'nullable|string',
        ]);

        // Security check: Verify that course belongs to this department
        $course = Course::where('department_id', $deptId)
            ->where('code', $request->course_code)
            ->first();

        if (!$course) {
            return response()->json([
                'message' => 'The selected course code does not belong to your department.'
            ], 422);
        }

        // Conflict check
        $conflict = $this->checkSchedulingConflict($deptId, $request->course_code, $request->scheduled_at, (int)$request->duration_minutes, null, $request->room);
        if ($conflict) {
            return response()->json([
                'message' => $conflict['message']
            ], 422);
        }

        $instructorId = $request->instructor_id ?: ($course->instructor_id ?: $headId);

        $exam = Exam::create([
            'user_id'          => $instructorId,
            'course_code'      => $course->code,
            'course_name'      => $course->title,
            'section'          => $request->section ?: $course->section,
            'title'            => trim($request->title),
            'duration_minutes' => (int) $request->duration_minutes,
            'total_marks'      => (int) $request->total_marks,
            'status'           => 'published',
            'scheduled_at'     => Carbon::parse($request->scheduled_at),
            'published_at'     => now(),
            'description'      => $request->description,
            'settings'         => [
                'department_id' => $deptId,
                'exam_type'     => $request->exam_type ?: 'Midterm',
                'semester'      => $course->semester,
                'year_level'    => $course->level,
                'room'          => $request->room ?: null,
            ],
        ]);

        LogActivity::record(
            'Created',
            'Examinations',
            "Scheduled exam \"{$exam->title}\" ({$exam->course_code}) for Department ID {$deptId}"
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Examination scheduled successfully',
            'data'    => $exam
        ], 201);
    }

    /**
     * Update an examination schedule / properties.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $headId = $request->user()->id;

        $exam = $this->buildDeptExamsQuery($deptId, $headId)->findOrFail($id);

        if (in_array(strtolower($exam->status), ['completed', 'cancelled'])) {
            return response()->json([
                'message' => 'This examination has already been completed or cancelled and cannot be modified.'
            ], 422);
        }

        $request->validate([
            'title'            => 'sometimes|string|max:255',
            'scheduled_at'     => 'sometimes|date',
            'duration_minutes' => 'sometimes|integer|min:5|max:360',
            'total_marks'      => 'sometimes|integer|min:1|max:500',
            'instructor_id'    => 'nullable|exists:users,id',
            'room'             => 'nullable|string|max:100',
            'section'          => 'nullable|string|max:100',
            'exam_type'        => 'nullable|string|max:50',
            'status'           => 'sometimes|string|in:published,scheduled,draft,completed,cancelled',
        ]);

        // Conflict check if scheduled_at, duration or room changed
        if ($request->has('scheduled_at') || $request->has('room')) {
            $schedAt = $request->scheduled_at ?? ($exam->scheduled_at ? $exam->scheduled_at->toIso8601String() : null);
            $duration = $request->duration_minutes ?? $exam->duration_minutes;
            $room = $request->has('room') ? $request->room : ($exam->settings['room'] ?? null);
            $conflict = $this->checkSchedulingConflict($deptId, $exam->course_code, $schedAt, (int)$duration, $exam->id, $room);
            if ($conflict) {
                return response()->json([
                    'message' => $conflict['message']
                ], 422);
            }
        }

        $updates = [];
        if ($request->has('title')) $updates['title'] = trim($request->title);
        if ($request->has('scheduled_at')) $updates['scheduled_at'] = Carbon::parse($request->scheduled_at);
        if ($request->has('duration_minutes')) $updates['duration_minutes'] = (int) $request->duration_minutes;
        if ($request->has('total_marks')) $updates['total_marks'] = (int) $request->total_marks;
        if ($request->has('section')) $updates['section'] = $request->section;
        if ($request->has('status')) $updates['status'] = $request->status;
        if ($request->has('instructor_id')) $updates['user_id'] = $request->instructor_id ?: $exam->user_id;

        $settings = $exam->settings ?? [];
        if ($request->has('room')) $settings['room'] = $request->room;
        if ($request->has('exam_type')) $settings['exam_type'] = $request->exam_type;
        $updates['settings'] = $settings;

        $exam->update($updates);

        LogActivity::record(
            'Updated',
            'Examinations',
            "Updated exam \"{$exam->title}\" ({$exam->course_code})"
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Examination updated successfully',
            'data'    => $exam
        ]);
    }

    /**
     * Cancel an examination with authoritative reason.
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $headId = $request->user()->id;

        $exam = $this->buildDeptExamsQuery($deptId, $headId)->findOrFail($id);

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $exam->update([
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancelled_by'        => $headId,
            'cancellation_reason' => $request->reason ?: 'Cancelled by Department Head',
        ]);

        LogActivity::record(
            'Cancelled',
            'Examinations',
            "Cancelled exam \"{$exam->title}\" ({$exam->course_code})"
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Examination cancelled successfully',
            'data'    => $exam
        ]);
    }

    /**
     * Assign / Reassign primary instructor to an exam.
     */
    public function assignInstructor(Request $request, $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $headId = $request->user()->id;

        $exam = $this->buildDeptExamsQuery($deptId, $headId)->findOrFail($id);

        $request->validate([
            'instructor_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($deptId) {
                    $inst = User::where('id', $value)
                        ->where('department_id', $deptId)
                        ->whereIn('role', ['instructor', 'dept_head'])
                        ->first();
                    if (!$inst) {
                        $fail('The selected instructor does not belong to your department.');
                    }
                }
            ],
        ]);

        $exam->update(['user_id' => $request->instructor_id]);

        LogActivity::record(
            'Updated',
            'Examinations',
            "Assigned instructor ID {$request->instructor_id} to exam \"{$exam->title}\""
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Instructor assigned successfully',
            'data'    => $exam->load('instructor')
        ]);
    }

    /**
     * Delete an exam schedule (protected if attempts exist).
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $headId = $request->user()->id;

        $exam = $this->buildDeptExamsQuery($deptId, $headId)->findOrFail($id);

        // Security / Integrity Guard
        $attemptsCount = $exam->attempts()->count();
        if ($attemptsCount > 0) {
            return response()->json([
                'message' => "Cannot delete examination \"{$exam->title}\" because {$attemptsCount} student attempt(s) are recorded. Please cancel or archive the examination instead."
            ], 422);
        }

        $title = $exam->title;
        $exam->delete();

        LogActivity::record(
            'Deleted',
            'Examinations',
            "Deleted exam \"{$title}\""
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Examination deleted successfully'
        ]);
    }

    /**
     * Multi-format export (PDF, Excel, CSV) scoped strictly to department.
     */
    public function export(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $headId = $request->user()->id;
        $dept = $deptId ? Department::find($deptId) : null;
        $deptName = $dept ? $dept->name : 'Department';

        $format = strtolower($request->query('format', 'excel'));

        $query = $this->buildDeptExamsQuery($deptId, $headId)
            ->with(['instructor', 'course'])
            ->withCount(['questions', 'attempts']);

        // Apply filters
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('course_code', 'like', "%{$search}%")
                  ->orWhere('course_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('course_code') && $request->course_code !== 'all') {
            $query->where('course_code', $request->course_code);
        }

        $exams = $query->orderBy('scheduled_at', 'desc')->get()->map(function ($e) {
            $e->code = $this->formatExamCode($e);
            $e->type = $this->determineExamType($e);
            return $e;
        });

        $dateStr = now()->format('Y-m-d');
        $sanitizedDept = preg_replace('/[^A-Za-z0-9_\-]/', '_', $deptName);
        $baseFileName = "{$sanitizedDept}_Exams_{$dateStr}";

        if ($format === 'pdf') {
            return $this->exportExamsPdf($exams, $deptName, $baseFileName);
        }

        if ($format === 'csv') {
            return $this->exportExamsCsv($exams, $deptName, $baseFileName);
        }

        return $this->exportExamsExcel($exams, $deptName, $baseFileName);
    }

    /**
     * Export exams as PDF using Dompdf.
     */
    private function exportExamsPdf($exams, string $deptName, string $baseFileName): JsonResponse
    {
        $generatedAt = now()->format('F j, Y  H:i');
        $totalRecords = $exams->count();

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<title>' . htmlspecialchars($deptName) . ' — Department Examinations</title>';
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
            .badge-scheduled { display: inline-block; padding: 2px 5px; border-radius: 4px; font-weight: bold; color: #0369a1; background: #e0f2fe; font-size: 6.5px; }
            .badge-cancelled { display: inline-block; padding: 2px 5px; border-radius: 4px; font-weight: bold; color: #be123c; background: #ffe4e6; font-size: 6.5px; }
            .footer { position: fixed; bottom: 10px; left: 25px; right: 25px; font-size: 7.5px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 4px; }
        </style></head><body>';

        $html .= '<table class="header-table"><tr>';
        $html .= '<td><div class="university-title">Wollo University</div>';
        $html .= '<div class="dept-title">Department of ' . htmlspecialchars($deptName) . ' — Examinations Register</div></td>';
        $html .= '<td class="meta"><strong>Date Generated:</strong> ' . $generatedAt . '<br><strong>Total Exams:</strong> ' . $totalRecords . '</td>';
        $html .= '</tr></table>';

        $html .= '<table class="data-table"><thead><tr>';
        $html .= '<th style="width: 25px;">#</th>';
        $html .= '<th style="width: 60px;">Code</th>';
        $html .= '<th>Exam Title</th>';
        $html .= '<th style="width: 50px;">Course</th>';
        $html .= '<th style="width: 50px;">Type</th>';
        $html .= '<th style="width: 65px;">Date</th>';
        $html .= '<th style="width: 50px;">Time</th>';
        $html .= '<th style="width: 45px;">Duration</th>';
        $html .= '<th style="width: 35px;">Marks</th>';
        $html .= '<th>Instructor</th>';
        $html .= '<th style="width: 45px;">Attempts</th>';
        $html .= '<th style="width: 55px;">Status</th>';
        $html .= '</tr></thead><tbody>';

        if ($exams->isEmpty()) {
            $html .= '<tr><td colspan="12" style="text-align: center; padding: 15px; color: #94a3b8;">No examinations found.</td></tr>';
        } else {
            foreach ($exams as $i => $e) {
                $statusBadge = '<span class="badge-scheduled">' . strtoupper($e->status ?? 'SCHEDULED') . '</span>';
                if ($e->status === 'completed') {
                    $statusBadge = '<span class="badge-active">COMPLETED</span>';
                } elseif ($e->status === 'cancelled') {
                    $statusBadge = '<span class="badge-cancelled">CANCELLED</span>';
                }

                $instName = $e->instructor ? htmlspecialchars($e->instructor->name) : 'Unassigned';
                $schedDate = $e->scheduled_at ? $e->scheduled_at->format('M d, Y') : '—';
                $schedTime = $e->scheduled_at ? $e->scheduled_at->format('h:i A') : '—';

                $html .= '<tr>';
                $html .= '<td>' . ($i + 1) . '</td>';
                $html .= '<td><strong>' . htmlspecialchars($e->code) . '</strong></td>';
                $html .= '<td>' . htmlspecialchars($e->title) . '</td>';
                $html .= '<td>' . htmlspecialchars($e->course_code) . '</td>';
                $html .= '<td>' . htmlspecialchars($e->type) . '</td>';
                $html .= '<td>' . $schedDate . '</td>';
                $html .= '<td>' . $schedTime . '</td>';
                $html .= '<td>' . ($e->duration_minutes ?? 60) . 'm</td>';
                $html .= '<td>' . ($e->total_marks ?? 100) . '</td>';
                $html .= '<td>' . $instName . '</td>';
                $html .= '<td>' . ($e->attempts_count ?? 0) . '</td>';
                $html .= '<td>' . $statusBadge . '</td>';
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

        LogActivity::record('Exported', 'Examinations', "Exported {$totalRecords} exams list as PDF ({$deptName})");

        return response()->json([
            'file'     => base64_encode($pdfOutput),
            'filename' => $baseFileName . '.pdf',
            'format'   => 'pdf',
        ]);
    }

    /**
     * Export exams as Excel spreadsheet (.xlsx).
     */
    private function exportExamsExcel($exams, string $deptName, string $baseFileName): JsonResponse
    {
        $export = new DepartmentExamExport($exams);
        $xlsxBytes = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);

        LogActivity::record('Exported', 'Examinations', "Exported {$exams->count()} exams list as Excel ({$deptName})");

        return response()->json([
            'file'     => base64_encode($xlsxBytes),
            'filename' => $baseFileName . '.xlsx',
            'format'   => 'xlsx',
        ]);
    }

    /**
     * Export exams as CSV (Excel-compatible UTF-8 BOM).
     */
    private function exportExamsCsv($exams, string $deptName, string $baseFileName): JsonResponse
    {
        ob_start();
        $handle = fopen('php://output', 'w');
        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            '#', 'Exam Code', 'Exam Title', 'Course Code', 'Course Title',
            'Exam Type', 'Scheduled Date', 'Start Time', 'Duration',
            'Total Marks', 'Questions', 'Assigned Instructor', 'Student Submissions', 'Status', 'Created Date'
        ]);

        foreach ($exams as $i => $e) {
            $instName = $e->instructor ? $e->instructor->name : 'Unassigned';
            $schedDate = $e->scheduled_at ? $e->scheduled_at->format('M d, Y') : '—';
            $schedTime = $e->scheduled_at ? $e->scheduled_at->format('h:i A') : '—';

            fputcsv($handle, [
                $i + 1,
                $e->code,
                $e->title,
                $e->course_code,
                $e->course_name,
                $e->type,
                $schedDate,
                $schedTime,
                ($e->duration_minutes ?? 60) . ' mins',
                $e->total_marks ?? 100,
                $e->questions_count ?? 0,
                $instName,
                $e->attempts_count ?? 0,
                ucfirst($e->status ?? 'Draft'),
                $e->created_at ? $e->created_at->format('M d, Y') : '',
            ]);
        }
        fclose($handle);
        $csvContent = ob_get_clean();

        LogActivity::record('Exported', 'Examinations', "Exported {$exams->count()} exams list as CSV ({$deptName})");

        return response()->json([
            'file'     => base64_encode($csvContent),
            'filename' => $baseFileName . '.csv',
            'format'   => 'csv',
        ]);
    }
}
