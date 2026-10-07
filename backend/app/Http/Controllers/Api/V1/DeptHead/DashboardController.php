<?php

namespace App\Http\Controllers\Api\V1\DeptHead;

use App\Http\Controllers\Controller;
use App\Models\AcademicEvent;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\SemesterSubmission;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
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
     * Return comprehensive real statistics for the Department Head dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $deptId = $this->resolveDeptId($request);
        $department = $deptId ? Department::find($deptId) : null;

        // Current system active academic term
        $currentYear = SystemSetting::where('key', 'academicYear')->value('value') ?? '2026';
        $currentSemester = SystemSetting::where('key', 'semester')->value('value') ?? 'Second Semester';

        // Filter parameters
        $filterYear = $request->input('academic_year');
        $filterSemester = $request->input('semester');

        // Available periods
        $availableYears = collect([$currentYear, '2026/2027', '2025/2026'])
            ->concat(AcademicEvent::pluck('academic_year'))
            ->concat(SemesterSubmission::pluck('academic_year'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $availableSemesters = ['First Semester', 'Second Semester', 'Summer Term'];

        // -------------------------------------------------------------
        // 1. Core KPIs Scoped to Department
        // -------------------------------------------------------------
        $studentsQuery = User::where('role', 'student');
        if ($deptId) {
            $studentsQuery->where('department_id', $deptId);
        } else {
            $studentsQuery->whereRaw('1=0');
        }
        $totalStudents = $studentsQuery->count();

        $instructorsQuery = User::whereIn('role', ['instructor', 'dept_head']);
        if ($deptId) {
            $instructorsQuery->where('department_id', $deptId);
        } else {
            $instructorsQuery->whereRaw('1=0');
        }
        $totalInstructors = $instructorsQuery->count();

        $coursesQuery = Course::query();
        if ($deptId) {
            $coursesQuery->where('department_id', $deptId);
        } else {
            $coursesQuery->whereRaw('1=0');
        }
        $totalCourses = $coursesQuery->count();
        $coursesList = (clone $coursesQuery)->get(['id', 'title', 'code', 'status']);
        $coursesByCode = $coursesList->keyBy('code');

        // Exam query scoped strictly to department courses or department instructors
        $courseCodes = $coursesList->pluck('code')->filter();
        $examsQuery = Exam::query();
        if ($deptId) {
            $examsQuery->where(function ($q) use ($deptId, $courseCodes, $user) {
                $q->whereHas('instructor', function ($iq) use ($deptId) {
                    $iq->where('department_id', $deptId);
                });
                if ($courseCodes->isNotEmpty()) {
                    $q->orWhereIn('course_code', $courseCodes);
                }
                $q->orWhere('user_id', $user->id);
            });
        } else {
            $examsQuery->whereRaw('1=0');
        }

        $allDeptExams = (clone $examsQuery)->get();
        $totalExams = $allDeptExams->count();
        $activeExams = (clone $examsQuery)->whereIn('status', ['published', 'scheduled'])->count();

        // Pending semester submissions for this department
        $deptName = $department?->name ?? '';
        $submissionsQuery = SemesterSubmission::query();
        if ($deptName) {
            $submissionsQuery->where(function ($q) use ($deptName) {
                $q->where('department', $deptName)
                  ->orWhere('department', 'like', "%{$deptName}%");
            });
        }
        if ($filterYear && $filterYear !== 'all') {
            $submissionsQuery->where('academic_year', $filterYear);
        }
        if ($filterSemester && $filterSemester !== 'all') {
            $submissionsQuery->where('semester', $filterSemester);
        }
        $pendingSubmissionsCount = (clone $submissionsQuery)->where('status', 'pending')->count();
        $totalSubmissionsCount = (clone $submissionsQuery)->count();

        // Exam attempts
        $examIds = $allDeptExams->pluck('id');
        $attempts = ExamAttempt::whereIn('exam_id', $examIds);
        $totalAttempts = (clone $attempts)->count();

        $kpis = [
            [
                'id'     => 'students',
                'label'  => 'Total Students',
                'value'  => (string) $totalStudents,
                'change' => 'Enrolled in department',
                'bg'     => 'bg-indigo-50',
                'ic'     => 'text-indigo-600',
                'icon'   => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                'color'  => 'text-slate-500',
            ],
            [
                'id'     => 'instructors',
                'label'  => 'Department Faculty',
                'value'  => (string) $totalInstructors,
                'change' => 'Active instructors & head',
                'bg'     => 'bg-emerald-50',
                'ic'     => 'text-emerald-600',
                'icon'   => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
                'color'  => 'text-slate-500',
            ],
            [
                'id'     => 'courses',
                'label'  => 'Curriculum Courses',
                'value'  => (string) $totalCourses,
                'change' => 'Offered curriculum units',
                'bg'     => 'bg-sky-50',
                'ic'     => 'text-sky-600',
                'icon'   => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                'color'  => 'text-slate-500',
            ],
            [
                'id'     => 'active_exams',
                'label'  => 'Active & Scheduled Exams',
                'value'  => (string) $activeExams,
                'change' => 'Department assessments',
                'bg'     => 'bg-amber-50',
                'ic'     => 'text-amber-600',
                'icon'   => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
                'color'  => 'text-slate-500',
            ],
            [
                'id'     => 'submissions',
                'label'  => 'Pending Submissions',
                'value'  => (string) $pendingSubmissionsCount,
                'change' => 'Awaiting sign-off',
                'bg'     => 'bg-purple-50',
                'ic'     => 'text-purple-600',
                'icon'   => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                'color'  => $pendingSubmissionsCount > 0 ? 'text-amber-600 font-bold' : 'text-slate-500',
            ],
            [
                'id'     => 'attempts',
                'label'  => 'Exam Submissions',
                'value'  => (string) $totalAttempts,
                'change' => 'Student attempts recorded',
                'bg'     => 'bg-rose-50',
                'ic'     => 'text-rose-600',
                'icon'   => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                'color'  => 'text-slate-500',
            ],
        ];

        // -------------------------------------------------------------
        // 2. "Requires Your Attention" Action Center
        // -------------------------------------------------------------
        $requiresAttention = [];

        if ($pendingSubmissionsCount > 0) {
            $requiresAttention[] = [
                'id'       => 'semester_submissions',
                'title'    => 'Instructor Semester Submissions',
                'count'    => $pendingSubmissionsCount,
                'desc'     => $pendingSubmissionsCount === 1
                    ? '1 instructor package is pending your review and approval.'
                    : "{$pendingSubmissionsCount} instructor packages await your review and approval.",
                'route'    => '/dept-head/semester-submissions',
                'action'   => 'Review Package',
                'severity' => 'warning',
                'icon'     => 'document',
            ];
        }

        if ($activeExams > 0) {
            $requiresAttention[] = [
                'id'       => 'active_exams',
                'title'    => 'Published & Scheduled Exams',
                'count'    => $activeExams,
                'desc'     => "{$activeExams} examination(s) active in the department curriculum.",
                'route'    => '/dept-head/exams',
                'action'   => 'Inspect Exams',
                'severity' => 'info',
                'icon'     => 'calendar',
            ];
        }

        $upcomingAcademicEvents = AcademicEvent::where('end_date', '>=', Carbon::now()->toDateString())
            ->orderBy('start_date')
            ->take(3)
            ->get();

        if ($upcomingAcademicEvents->isNotEmpty()) {
            $requiresAttention[] = [
                'id'       => 'academic_milestones',
                'title'    => 'Upcoming Academic Deadlines',
                'count'    => $upcomingAcademicEvents->count(),
                'desc'     => "Institutional milestones active in the academic calendar.",
                'route'    => '/dept-head/schedule',
                'action'   => 'View Schedule',
                'severity' => 'info',
                'icon'     => 'clock',
            ];
        }

        // -------------------------------------------------------------
        // 3. Upcoming Exams List (Scoped to Department)
        // -------------------------------------------------------------
        $upcomingExamsList = (clone $examsQuery)
            ->with(['instructor:id,name,email'])
            ->latest('scheduled_at')
            ->take(5)
            ->get()
            ->map(function ($exam) use ($coursesByCode) {
                $course = $coursesByCode->get($exam->course_code);
                $courseTitle = $course ? $course->title : ($exam->course_code ?: 'General Assessment');

                return [
                    'id'               => $exam->id,
                    'title'            => $exam->title,
                    'course_code'      => $exam->course_code ?: 'N/A',
                    'course_title'     => $courseTitle,
                    'instructor_name'  => $exam->instructor?->name ?? 'Faculty Member',
                    'scheduled_at'     => $exam->scheduled_at ? $exam->scheduled_at->toIso8601String() : null,
                    'scheduled_human'  => $exam->scheduled_at ? $exam->scheduled_at->format('M d, Y • h:i A') : 'Flexible Window',
                    'duration_minutes' => $exam->duration_minutes ?? 60,
                    'status'           => $exam->status,
                ];
            });

        // -------------------------------------------------------------
        // 4. Department Overview Chart Data (Monthly Trend)
        // -------------------------------------------------------------
        $chartLabels = [];
        $studentsTrend = [];
        $coursesTrend = [];

        for ($i = 6; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $chartLabels[] = $monthDate->format('M Y');
            $endOfMonth = $monthDate->copy()->endOfMonth();

            $stCount = $deptId ? User::where('department_id', $deptId)
                ->where('role', 'student')
                ->where('created_at', '<=', $endOfMonth)
                ->count() : 0;

            $crCount = $deptId ? Course::where('department_id', $deptId)
                ->where('created_at', '<=', $endOfMonth)
                ->count() : 0;

            $studentsTrend[] = $stCount;
            $coursesTrend[] = $crCount;
        }

        // -------------------------------------------------------------
        // 5. Exams Breakdown (Doughnut Chart)
        // -------------------------------------------------------------
        $now = Carbon::now();
        $upcomingCount = 0;
        $ongoingCount = 0;
        $completedCount = 0;
        $draftCount = 0;

        foreach ($allDeptExams as $exam) {
            if ($exam->status === 'draft') {
                $draftCount++;
            } elseif ($exam->status === 'completed') {
                $completedCount++;
            } elseif ($exam->status === 'scheduled') {
                $upcomingCount++;
            } elseif ($exam->status === 'published') {
                $sched = $exam->scheduled_at;
                $dur = $exam->duration_minutes ?? 60;
                if ($sched) {
                    $end = $sched->copy()->addMinutes($dur);
                    if ($now->lt($sched)) {
                        $upcomingCount++;
                    } elseif ($now->between($sched, $end)) {
                        $ongoingCount++;
                    } else {
                        $completedCount++;
                    }
                } else {
                    $upcomingCount++;
                }
            } else {
                $draftCount++;
            }
        }

        $examStats = [
            ['label' => 'Upcoming', 'val' => $upcomingCount, 'color' => 'bg-indigo-600'],
            ['label' => 'Ongoing', 'val' => $ongoingCount, 'color' => 'bg-sky-500'],
            ['label' => 'Completed', 'val' => $completedCount, 'color' => 'bg-emerald-500'],
            ['label' => 'Drafts', 'val' => $draftCount, 'color' => 'bg-slate-400'],
        ];

        // -------------------------------------------------------------
        // 6. Department Performance (From Exam Attempts)
        // -------------------------------------------------------------
        $passedAttempts = (clone $attempts)->where('percentage', '>=', 50)->count();
        $passRate = $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100, 1) : 0;

        $avgScore = (clone $attempts)->avg('percentage') ?? 0;
        $gpa = round(($avgScore / 100) * 4.0, 2);

        $submittedAttempts = (clone $attempts)->whereIn('status', ['submitted', 'graded', 'published'])->count();
        $attendancePct = $totalAttempts > 0 ? round(($submittedAttempts / $totalAttempts) * 100) : 0;

        $activeCoursesCount = $coursesList->where('status', 'active')->count();
        $courseCompletionPct = $totalCourses > 0 ? round(($activeCoursesCount / $totalCourses) * 100) : 0;

        $performances = [
            [
                'label' => 'Exam Participation Rate',
                'val'   => $totalAttempts > 0 ? "{$attendancePct}%" : '0%',
                'pct'   => $totalAttempts > 0 ? $attendancePct : 0,
                'color' => 'bg-emerald-500',
            ],
            [
                'label' => 'Assessment Pass Rate',
                'val'   => $totalAttempts > 0 ? "{$passRate}%" : '0%',
                'pct'   => $totalAttempts > 0 ? (int) round($passRate) : 0,
                'color' => 'bg-indigo-600',
            ],
            [
                'label' => 'Average Grade Point (GPA)',
                'val'   => $totalAttempts > 0 ? number_format($gpa, 2) . ' / 4.00' : '0.00 / 4.00',
                'pct'   => $totalAttempts > 0 ? min(100, (int) round($avgScore)) : 0,
                'color' => 'bg-sky-500',
            ],
            [
                'label' => 'Active Course Delivery',
                'val'   => "{$courseCompletionPct}%",
                'pct'   => $courseCompletionPct,
                'color' => 'bg-amber-500',
            ],
        ];

        // -------------------------------------------------------------
        // 7. Recent Department Activities
        // -------------------------------------------------------------
        $logsQuery = ActivityLog::query();
        if ($deptId) {
            $logsQuery->where(function ($q) use ($deptId) {
                $q->where('department_id', $deptId)
                  ->orWhereHas('user', function ($uq) use ($deptId) {
                      $uq->where('department_id', $deptId);
                  });
            });
        }

        $logs = $logsQuery->latest()->take(6)->get();

        if ($logs->isEmpty()) {
            $logs = ActivityLog::latest()->take(5)->get();
        }

        $formattedActivities = $logs->map(function ($log) {
            $icon = 'user';
            $bg = 'bg-indigo-50';
            $color = 'text-indigo-600';

            $mod = strtolower($log->module ?? '');
            $type = strtolower($log->type ?? '');

            if (str_contains($mod, 'exam')) {
                $icon = 'exam';
                $bg = 'bg-amber-50';
                $color = 'text-amber-600';
            } elseif (str_contains($mod, 'course')) {
                $icon = 'course';
                $bg = 'bg-purple-50';
                $color = 'text-purple-600';
            } elseif (str_contains($type, 'delete')) {
                $icon = 'trash';
                $bg = 'bg-rose-50';
                $color = 'text-rose-600';
            } elseif (str_contains($type, 'create') || str_contains($type, 'store')) {
                $icon = 'plus';
                $bg = 'bg-emerald-50';
                $color = 'text-emerald-600';
            }

            return [
                'id'     => $log->id,
                'title'  => $log->action ?: ($log->module . ' ' . $log->type),
                'desc'   => $log->details ?: $log->action,
                'module' => $log->module ?: 'General',
                'time'   => $log->created_at ? $log->created_at->diffForHumans() : 'Recently',
                'date'   => $log->created_at ? $log->created_at->format('M d, Y h:i A') : '',
                'icon'   => $icon,
                'bg'     => $bg,
                'color'  => $color,
            ];
        });

        // -------------------------------------------------------------
        // 8. Academic Events / Announcements
        // -------------------------------------------------------------
        $eventsQuery = AcademicEvent::with('category')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'cancelled');
            });

        if ($filterYear && $filterYear !== 'all') {
            $eventsQuery->where('academic_year', $filterYear);
        }

        $events = (clone $eventsQuery)->orderBy('start_date')->take(4)->get();
        if ($events->isEmpty()) {
            $events = AcademicEvent::with('category')
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'cancelled');
                })
                ->orderBy('start_date')
                ->take(4)
                ->get();
        }

        $formattedAnnouncements = $events->map(function ($event) {
            $catName = strtolower($event->category?->name ?? '');
            $badgeBg = 'bg-emerald-50 text-emerald-700 border-emerald-200';

            if (str_contains($catName, 'exam')) {
                $badgeBg = 'bg-amber-50 text-amber-700 border-amber-200';
            } elseif (str_contains($catName, 'holiday')) {
                $badgeBg = 'bg-rose-50 text-rose-700 border-rose-200';
            }

            return [
                'id'            => $event->id,
                'title'         => $event->title,
                'desc'          => $event->description ?: ($event->academic_year . ' ' . $event->semester),
                'category_name' => $event->category?->name ?? 'Academic Milestone',
                'category_color'=> $event->category?->color ?? '#4338ca',
                'badge_bg'      => $badgeBg,
                'date_start'    => $event->start_date ? $event->start_date->format('M d, Y') : '',
                'date_end'      => $event->end_date ? $event->end_date->format('M d, Y') : '',
                'date_formatted'=> $event->start_date ? ($event->end_date && $event->end_date->gt($event->start_date) ? $event->start_date->format('M d') . ' – ' . $event->end_date->format('M d, Y') : $event->start_date->format('M d, Y')) : '',
                'status'        => $event->status ?? 'upcoming',
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => [
                'department' => [
                    'id'       => $department?->id,
                    'name'     => ucwords($department?->name ?? 'Department'),
                    'raw_name' => $department?->name ?? 'Department',
                    'code'     => $department?->code ?? 'DEPT',
                    'college'  => $department?->college ?? 'College of Computing and Informatics',
                ],
                'active_term' => [
                    'academic_year' => $currentYear,
                    'semester'      => $currentSemester,
                ],
                'available_periods' => [
                    'years'     => $availableYears,
                    'semesters' => $availableSemesters,
                ],
                'stats'              => $kpis,
                'requires_attention' => $requiresAttention,
                'upcoming_exams'     => $upcomingExamsList,
                'lineChart'          => [
                    'labels'   => $chartLabels,
                    'students' => $studentsTrend,
                    'courses'  => $coursesTrend,
                ],
                'doughnutChart'      => [
                    'totalExams' => $totalExams,
                    'data'       => [$upcomingCount, $ongoingCount, $completedCount, $draftCount],
                    'stats'      => $examStats,
                ],
                'performances'       => $performances,
                'activities'         => $formattedActivities,
                'announcements'      => $formattedAnnouncements,
            ]
        ]);
    }
}
