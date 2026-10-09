<?php

namespace App\Http\Controllers\Api\V1\DeptHead;

use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\SystemSetting;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
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
     * Get start and end dates based on the selected period.
     */
    private function resolvePeriodDates(string $period): array
    {
        $now = Carbon::now();
        return match (strtolower(trim($period))) {
            'last 30 days'        => [$now->copy()->subDays(30), $now->copy(), $now->copy()->subDays(60), $now->copy()->subDays(30)],
            'last semester'       => [$now->copy()->subMonths(12), $now->copy()->subMonths(6), $now->copy()->subMonths(18), $now->copy()->subMonths(12)],
            'this academic year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear(), $now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'this year'           => [$now->copy()->startOfYear(), $now->copy()->endOfYear(), $now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'all time'            => [null, null, null, null],
            default               => [$now->copy()->subMonths(6), $now->copy(), $now->copy()->subMonths(12), $now->copy()->subMonths(6)], // 'This Semester'
        };
    }

    /**
     * Get department reports data, KPIs, trends, and course performance.
     */
    public function index(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();
        $department = $deptId ? Department::find($deptId) : null;
        $deptName = $department ? $department->name : ($user->department?->name ?? 'Computer Science');
        $deptCode = $department->code ?? ($user->department?->code ?? 'CS');

        // Current System Term
        $currentAcademicYear = SystemSetting::where('key', 'academicYear')->value('value') ?? '2028';
        $currentSemester = SystemSetting::where('key', 'semester')->value('value') ?? 'Second Semester';

        // Filters
        $period = $request->input('period', 'This Semester');
        $filterYear = $request->input('academic_year', 'all');
        $filterSemester = $request->input('semester', 'all');
        $filterCourse = $request->input('course_code', 'all');

        [$startDate, $endDate, $prevStartDate, $prevEndDate] = $this->resolvePeriodDates($period);

        // 1. Fetch Department Courses
        $coursesQuery = Course::query();
        if ($deptId) {
            $coursesQuery->where('department_id', $deptId);
        }
        $deptCourses = (clone $coursesQuery)->with(['instructor'])->get();
        $deptCourseCodes = $deptCourses->pluck('code')->filter()->unique()->toArray();

        // 2. Fetch Department Exams
        $examsQuery = Exam::query();
        if ($deptId) {
            $examsQuery->where(function ($q) use ($deptId, $deptCourseCodes, $user) {
                $q->whereHas('instructor', function ($sub) use ($deptId) {
                    $sub->where('department_id', $deptId);
                })
                ->orWhereIn('course_code', $deptCourseCodes)
                ->orWhere('user_id', $user->id)
                ->orWhere('settings->department_id', $deptId);
            });
        }

        // Apply academic year or semester filters if specified
        if ($filterYear !== 'all' && !empty($filterYear)) {
            $examsQuery->where(function ($q) use ($filterYear) {
                $q->where('settings->academic_year', $filterYear)
                  ->orWhereYear('scheduled_at', $filterYear)
                  ->orWhereYear('created_at', $filterYear);
            });
        }
        if ($filterSemester !== 'all' && !empty($filterSemester)) {
            $examsQuery->where(function ($q) use ($filterSemester) {
                $q->where('settings->semester', $filterSemester)
                  ->orWhere('description', 'like', "%{$filterSemester}%");
            });
        }
        if ($filterCourse !== 'all' && !empty($filterCourse)) {
            $examsQuery->where('course_code', $filterCourse);
        }

        $allExams = (clone $examsQuery)->with(['instructor'])->get();
        $examIds = $allExams->pluck('id')->toArray();

        // Also fetch any course records matching the exam course codes
        $allExamCourseCodes = $allExams->pluck('course_code')->filter()->unique()->toArray();
        $allRelevantCourses = Course::where(function ($q) use ($deptId, $allExamCourseCodes) {
            if ($deptId) {
                $q->where('department_id', $deptId);
            }
            if (!empty($allExamCourseCodes)) {
                $q->orWhereIn('code', $allExamCourseCodes);
            }
        })->with(['instructor'])->get();

        // Period-filtered exams
        $periodExams = $allExams->filter(function ($e) use ($startDate, $endDate) {
            if (!$startDate) return true;
            $dt = $e->scheduled_at ?? $e->created_at;
            if (!$dt) return true;
            $c = Carbon::parse($dt);
            return $c->gte($startDate) && ($endDate ? $c->lte($endDate) : true);
        });

        $prevPeriodExams = $allExams->filter(function ($e) use ($prevStartDate, $prevEndDate) {
            if (!$prevStartDate || !$prevEndDate) return false;
            $dt = $e->scheduled_at ?? $e->created_at;
            if (!$dt) return false;
            $c = Carbon::parse($dt);
            return $c->gte($prevStartDate) && $c->lte($prevEndDate);
        });

        // 3. Fetch Student Attempts for Department Exams
        $attemptsQuery = ExamAttempt::whereIn('exam_id', $examIds);
        $allAttempts = (clone $attemptsQuery)->with(['exam', 'student'])->get();

        $periodAttempts = $allAttempts->filter(function ($a) use ($startDate, $endDate) {
            if (!$startDate) return true;
            $dt = $a->submitted_at ?? $a->created_at;
            if (!$dt) return true;
            $c = Carbon::parse($dt);
            return $c->gte($startDate) && ($endDate ? $c->lte($endDate) : true);
        });

        $prevPeriodAttempts = $allAttempts->filter(function ($a) use ($prevStartDate, $prevEndDate) {
            if (!$prevStartDate || !$prevEndDate) return false;
            $dt = $a->submitted_at ?? $a->created_at;
            if (!$dt) return false;
            $c = Carbon::parse($dt);
            return $c->gte($prevStartDate) && $c->lte($prevEndDate);
        });

        $activeAttempts = $startDate ? $periodAttempts : $allAttempts;
        $activeExams = $startDate ? $periodExams : $allExams;

        // 4. Department Students Count
        $studentsQuery = User::where('role', 'student');
        if ($deptId) {
            $studentsQuery->where('department_id', $deptId);
        }
        $totalDeptStudents = $studentsQuery->count();

        // 5. Calculate KPI Metrics
        $totalExamsCount = $activeExams->count();
        $prevExamsCount = $prevPeriodExams->count();
        if ($prevExamsCount > 0) {
            $diff = round((($totalExamsCount - $prevExamsCount) / $prevExamsCount) * 100, 1);
            $examsChange = ($diff >= 0 ? '+' : '') . $diff . '%';
            $examsTrend = $diff >= 0 ? 'up' : 'down';
        } else {
            $examsChange = $totalExamsCount > 0 ? "{$totalExamsCount} active" : "0 active";
            $examsTrend = 'up';
        }

        $totalAttemptsCount = $activeAttempts->count();
        $completedAttempts = $activeAttempts->filter(function ($a) {
            return $a->percentage !== null && $a->status !== 'in_progress';
        });
        $completedAttemptsCount = $completedAttempts->count();

        $passedCount = $completedAttempts->filter(fn($a) => (float)$a->percentage >= 50.0)->count();
        $failedCount = $completedAttempts->filter(fn($a) => (float)$a->percentage < 50.0)->count();
        $inProgressCount = $activeAttempts->filter(fn($a) => $a->status === 'in_progress' || $a->percentage === null)->count();

        // Department Pass Rate
        $passRate = $totalAttemptsCount > 0 ? round(($passedCount / $totalAttemptsCount) * 100, 1) : 0.0;

        $prevCompletedAttempts = $prevPeriodAttempts->filter(fn($a) => $a->percentage !== null && $a->status !== 'in_progress');
        $prevTotalAttempts = $prevPeriodAttempts->count();
        $prevPassedCount = $prevCompletedAttempts->filter(fn($a) => (float)$a->percentage >= 50.0)->count();
        $prevPassRate = $prevTotalAttempts > 0 ? round(($prevPassedCount / $prevTotalAttempts) * 100, 1) : 0.0;

        if ($prevPassRate > 0) {
            $diff = round($passRate - $prevPassRate, 1);
            $passRateChange = ($diff >= 0 ? '+' : '') . $diff . '%';
            $passRateTrend = $diff >= 0 ? 'up' : 'down';
        } else {
            $passRateChange = $totalAttemptsCount > 0 ? "{$passedCount}/{$totalAttemptsCount} passed" : "0 passed";
            $passRateTrend = 'up';
        }

        // Average Score
        $avgScore = $completedAttemptsCount > 0 ? round($completedAttempts->avg('percentage'), 1) : 0.0;
        $prevAvgScore = $prevCompletedAttempts->count() > 0 ? round($prevCompletedAttempts->avg('percentage'), 1) : 0.0;

        if ($prevAvgScore > 0) {
            $diff = round($avgScore - $prevAvgScore, 1);
            $avgScoreChange = ($diff >= 0 ? '+' : '') . $diff . '%';
            $avgScoreTrend = $diff >= 0 ? 'up' : 'down';
        } else {
            $gradeLetter = match (true) {
                $avgScore >= 90 => 'Grade A',
                $avgScore >= 80 => 'Grade B+',
                $avgScore >= 70 => 'Grade B',
                $avgScore >= 60 => 'Grade C',
                $avgScore >= 50 => 'Grade D',
                $avgScore > 0   => 'Grade F',
                default         => 'N/A',
            };
            $avgScoreChange = $avgScore > 0 ? $gradeLetter : "No graded tests";
            $avgScoreTrend = 'up';
        }

        // 4 Executive Summary KPI Cards
        $kpis = [
            [
                'id'     => 'students',
                'label'  => 'Total Students',
                'value'  => (string) $totalDeptStudents,
                'change' => "{$totalDeptStudents} enrolled",
                'trend'  => 'up',
                'bg'     => 'bg-indigo-50',
                'ic'     => 'text-[#5138ed]',
                'icon'   => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
                'route'  => '/dept-head/students',
            ],
            [
                'id'     => 'exams',
                'label'  => 'Total Exams Conducted',
                'value'  => (string) $totalExamsCount,
                'change' => $examsChange,
                'trend'  => $examsTrend,
                'bg'     => 'bg-sky-50',
                'ic'     => 'text-sky-500',
                'icon'   => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
                'route'  => '/dept-head/exams',
            ],
            [
                'id'     => 'pass_rate',
                'label'  => 'Department Pass Rate',
                'value'  => $passRate . '%',
                'change' => $passRateChange,
                'trend'  => $passRateTrend,
                'bg'     => 'bg-emerald-50',
                'ic'     => 'text-emerald-500',
                'icon'   => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                'route'  => '/dept-head/results',
            ],
            [
                'id'     => 'avg_score',
                'label'  => 'Average Score',
                'value'  => $avgScore . '%',
                'change' => $avgScoreChange,
                'trend'  => $avgScoreTrend,
                'bg'     => 'bg-amber-50',
                'ic'     => 'text-amber-500',
                'icon'   => 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z',
                'route'  => '/dept-head/results',
            ],
        ];

        // 6. Grade Breakdown (100% Real, zero mock data)
        $gradeDistribution = [
            'A'            => $completedAttempts->filter(fn($a) => (float)$a->percentage >= 85.0)->count(),
            'B'            => $completedAttempts->filter(fn($a) => (float)$a->percentage >= 70.0 && (float)$a->percentage < 85.0)->count(),
            'C'            => $completedAttempts->filter(fn($a) => (float)$a->percentage >= 60.0 && (float)$a->percentage < 70.0)->count(),
            'D'            => $completedAttempts->filter(fn($a) => (float)$a->percentage >= 50.0 && (float)$a->percentage < 60.0)->count(),
            'F'            => $completedAttempts->filter(fn($a) => (float)$a->percentage < 50.0)->count(),
            'in_progress'  => $inProgressCount,
            'passed_count' => $passedCount,
            'failed_count' => $failedCount,
            'total_graded' => $completedAttemptsCount,
            'total_all'    => $totalAttemptsCount,
            'pass_rate'    => $passRate,
            'avg_score'    => $avgScore,
        ];

        // 7. Monthly Exam Performance Trend (12 Months, Real Data Only)
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $attemptsByMonth = [];
        foreach ($activeAttempts as $attempt) {
            $date = $attempt->submitted_at ?? $attempt->created_at;
            if ($date) {
                $c = Carbon::parse($date);
                $m = $c->month - 1; // 0-indexed
                if ($attempt->percentage !== null) {
                    $attemptsByMonth[$m][] = (float)$attempt->percentage;
                }
            }
        }

        $trendData = [];
        $chartPoints = [];
        $hasAnyTrendData = false;

        for ($i = 0; $i < 12; $i++) {
            $month = $monthNames[$i];
            $monthAttemptsCount = isset($attemptsByMonth[$i]) ? count($attemptsByMonth[$i]) : 0;
            $hasData = $monthAttemptsCount > 0;
            if ($hasData) {
                $hasAnyTrendData = true;
                $monthAvg = round(array_sum($attemptsByMonth[$i]) / $monthAttemptsCount, 1);
            } else {
                $monthAvg = 0.0;
            }

            $trendData[] = [
                'month'          => $month,
                'avg_score'      => $monthAvg,
                'attempts_count' => $monthAttemptsCount,
                'has_data'       => $hasData,
            ];

            // Calculate SVG coordinates in 550x200 space
            $x = round($i * (550 / 11));
            // Map 0..100% to Y 180..30
            $y = $hasData ? round(180 - (($monthAvg / 100) * 140)) : 180;
            $chartPoints[] = [$x, $y];
        }

        // SVG path construction
        $dataMonths = array_keys(array_filter($trendData, fn($t) => $t['has_data']));
        if (!empty($dataMonths)) {
            $firstMonthIdx = min($dataMonths);
            $lastMonthIdx = max($dataMonths);

            $svgLinePoints = [];
            for ($i = 0; $i < 12; $i++) {
                $svgLinePoints[] = $chartPoints[$i];
            }
            $svgLine = collect($svgLinePoints)->map(fn($p, $idx) => ($idx === 0 ? 'M' : 'L') . "{$p[0]},{$p[1]}")->join(' ');
            $lastPoint = $chartPoints[11];
            $svgFill = $svgLine . " L{$lastPoint[0]},200 L0,200 Z";
        } else {
            $svgLine = "M0,180 L550,180";
            $svgFill = "M0,180 L550,180 L550,200 L0,200 Z";
        }

        // 8. Chronological Exam Performance Trend Points (Exams as data points)
        $chronologicalTrend = [];
        $sortedExams = $activeExams->sortBy(function ($e) {
            return $e->scheduled_at ?? $e->created_at;
        });

        foreach ($sortedExams as $e) {
            $eAttempts = $allAttempts->filter(fn($a) => $a->exam_id === $e->id);
            $eGraded = $eAttempts->filter(fn($a) => $a->percentage !== null && $a->status !== 'in_progress');
            if ($eAttempts->count() > 0) {
                $eAvg = $eGraded->count() > 0 ? round($eGraded->avg('percentage'), 1) : 0.0;
                $ePass = $eGraded->filter(fn($a) => (float)$a->percentage >= 50.0)->count();
                $ePassRate = $eAttempts->count() > 0 ? round(($ePass / $eAttempts->count()) * 100, 1) : 0.0;

                $dt = $e->scheduled_at ?? $e->created_at;
                $chronologicalTrend[] = [
                    'exam_id'        => $e->id,
                    'title'          => $e->title,
                    'course_code'    => $e->course_code,
                    'date'           => $dt ? Carbon::parse($dt)->format('M d') : 'N/A',
                    'full_date'      => $dt ? Carbon::parse($dt)->format('M d, Y') : 'N/A',
                    'avg_score'      => $eAvg,
                    'pass_rate'      => $ePassRate,
                    'attempts_count' => $eAttempts->count(),
                ];
            }
        }

        // 9. Course Performance Breakdown
        $coursePerformance = [];
        $coursesToProcess = $allRelevantCourses->isNotEmpty() ? $allRelevantCourses : $deptCourses;

        foreach ($coursesToProcess as $c) {
            $courseExams = $activeExams->filter(fn($e) => $e->course_code === $c->code || $e->course_id === $c->id);
            $cExamIds = $courseExams->pluck('id')->toArray();
            $cAttempts = $activeAttempts->filter(fn($a) => in_array($a->exam_id, $cExamIds));
            $cAttemptsCount = $cAttempts->count();

            $cGraded = $cAttempts->filter(fn($a) => $a->percentage !== null && $a->status !== 'in_progress');
            $cGradedCount = $cGraded->count();

            $cPassed = $cGraded->filter(fn($a) => (float)$a->percentage >= 50.0)->count();
            $cFailed = $cGraded->filter(fn($a) => (float)$a->percentage < 50.0)->count();

            $cPassRate = $cAttemptsCount > 0 ? round(($cPassed / $cAttemptsCount) * 100, 1) : 0.0;
            $cAvgScore = $cGradedCount > 0 ? round($cGraded->avg('percentage'), 1) : 0.0;

            $instructorName = $c->instructor?->name;
            if (!$instructorName) {
                $firstWithInst = $courseExams->first(fn($e) => !empty($e->instructor?->name));
                $instructorName = $firstWithInst?->instructor?->name ?? 'Unassigned';
            }

            // Skip dummy courses if plenty exist
            if ($coursesToProcess->count() > 4 && in_array(strtolower($c->title), ['hftdtrd', 'hgfgfd'])) {
                continue;
            }

            $coursePerformance[] = [
                'id'                => $c->id,
                'name'              => $c->title,
                'code'              => $c->code,
                'credits'           => $c->credits ?? 3,
                'semester'          => $c->semester ?? 'Second Semester',
                'instructor'        => $instructorName,
                'eligible_students' => $totalDeptStudents,
                'attempts'          => $cAttemptsCount,
                'graded_count'      => $cGradedCount,
                'passed'            => $cPassed,
                'failed'            => $cFailed,
                'passRate'          => $cPassRate,
                'avgScore'          => $cAvgScore,
                'examsCount'        => $courseExams->count(),
            ];
        }

        // Sort Course Performance: attempts desc, avgScore desc
        usort($coursePerformance, function ($a, $b) {
            if ($a['attempts'] !== $b['attempts']) {
                return $b['attempts'] <=> $a['attempts'];
            }
            return $b['avgScore'] <=> $a['avgScore'];
        });

        // 10. Detailed Examination Results Summary List
        $examResults = [];
        foreach ($activeExams as $e) {
            $eAttempts = $allAttempts->filter(fn($a) => $a->exam_id === $e->id);
            $eAttemptsCount = $eAttempts->count();
            $eGraded = $eAttempts->filter(fn($a) => $a->percentage !== null && $a->status !== 'in_progress');
            $ePassed = $eGraded->filter(fn($a) => (float)$a->percentage >= 50.0)->count();
            $eFailed = $eGraded->filter(fn($a) => (float)$a->percentage < 50.0)->count();
            $eAvg = $eGraded->count() > 0 ? round($eGraded->avg('percentage'), 1) : 0.0;
            $ePassRate = $eAttemptsCount > 0 ? round(($ePassed / $eAttemptsCount) * 100, 1) : 0.0;

            $examResults[] = [
                'id'               => $e->id,
                'title'            => $e->title,
                'code'             => 'EXM-' . str_pad((string)$e->id, 4, '0', STR_PAD_LEFT),
                'course_code'      => $e->course_code ?? 'N/A',
                'course_name'      => $e->course_name ?? 'Department Course',
                'instructor'       => $e->instructor?->name ?? 'Dept Instructor',
                'scheduled_at'     => $e->scheduled_at ? Carbon::parse($e->scheduled_at)->format('M d, Y h:i A') : 'Not scheduled',
                'duration_minutes' => $e->duration_minutes ?? 60,
                'total_marks'      => $e->total_marks ?? 100,
                'status'           => $e->status ?? 'published',
                'attempts_count'   => $eAttemptsCount,
                'passed_count'     => $ePassed,
                'failed_count'     => $eFailed,
                'avg_score'        => $eAvg,
                'pass_rate'        => $ePassRate,
            ];
        }

        // Sort Exam Results: latest scheduled first
        usort($examResults, fn($a, $b) => $b['id'] <=> $a['id']);

        // 11. Student Performance Analysis List
        $studentPerformance = [];
        foreach ($activeAttempts as $a) {
            $student = $a->student ?? User::find($a->user_id);
            $exam = $a->exam ?? Exam::find($a->exam_id);
            $pct = $a->percentage !== null ? (float)$a->percentage : ($a->total_marks > 0 && $a->score !== null ? round(($a->score / $a->total_marks) * 100, 1) : null);

            $grade = match (true) {
                $pct === null => 'PND',
                $pct >= 85.0  => 'A',
                $pct >= 75.0  => 'B+',
                $pct >= 70.0  => 'B',
                $pct >= 60.0  => 'C',
                $pct >= 50.0  => 'D',
                default       => 'F',
            };

            $studentPerformance[] = [
                'id'            => $a->id,
                'user_id'       => $a->user_id,
                'student_name'  => $student?->name ?? 'Unknown Student',
                'student_id'    => $student?->student_id ? $student->student_id : ('STU-' . ($student?->id ?? $a->user_id)),
                'email'         => $student?->email ?? '',
                'year_level'    => $student?->year_level ?? '1st Year',
                'section'       => $student?->section ?? 'A',
                'exam_id'       => $a->exam_id,
                'exam_title'    => $exam?->title ?? 'Examination',
                'course_code'   => $exam?->course_code ?? 'N/A',
                'score'         => $a->score !== null ? (float)$a->score : 0.0,
                'total_marks'   => $a->total_marks !== null ? (float)$a->total_marks : 100.0,
                'percentage'    => $pct,
                'grade'         => $grade,
                'status'        => $a->status ?? 'submitted',
                'submitted_at'  => $a->submitted_at ? Carbon::parse($a->submitted_at)->format('M d, Y h:i A') : ($a->created_at ? Carbon::parse($a->created_at)->format('M d, Y h:i A') : 'In Progress'),
            ];
        }

        // Sort Student Performance: latest attempt first
        usort($studentPerformance, fn($a, $b) => $b['id'] <=> $a['id']);

        // 12. Instructor Performance Summary
        $deptInstructors = User::where(function ($q) use ($deptId, $user) {
            if ($deptId) {
                $q->where('department_id', $deptId);
            }
            $q->orWhere('id', $user->id);
        })->whereIn('role', ['instructor', 'dept_head'])->get();

        $instructorSummary = [];
        foreach ($deptInstructors as $inst) {
            $instCourses = Course::where('instructor_id', $inst->id)->get();
            $instExams = Exam::where('user_id', $inst->id)->get();
            $instExamIds = $instExams->pluck('id')->toArray();
            $instAttempts = ExamAttempt::whereIn('exam_id', $instExamIds)->get();
            $instGraded = $instAttempts->filter(fn($a) => $a->percentage !== null && $a->status !== 'in_progress');
            $instPassed = $instGraded->filter(fn($a) => (float)$a->percentage >= 50.0)->count();

            $instAvg = $instGraded->count() > 0 ? round($instGraded->avg('percentage'), 1) : 0.0;
            $instPassRate = $instAttempts->count() > 0 ? round(($instPassed / $instAttempts->count()) * 100, 1) : 0.0;

            $instructorSummary[] = [
                'id'               => $inst->id,
                'name'             => $inst->name,
                'email'            => $inst->email,
                'role'             => $inst->role === 'dept_head' ? 'Department Head' : 'Instructor',
                'assigned_courses' => $instCourses->count(),
                'exams_conducted'  => $instExams->count(),
                'attempts_count'   => $instAttempts->count(),
                'avg_score'        => $instAvg,
                'pass_rate'        => $instPassRate,
            ];
        }

        // Available Filter Options
        $filterOptions = [
            'academic_years' => ['2028', '2027', '2026', '2025'],
            'semesters'      => ['First Semester', 'Second Semester'],
            'periods'        => ['This Semester', 'Last 30 Days', 'This Academic Year', 'All Time'],
            'report_types'   => [
                ['id' => 'overview', 'name' => 'Department Overview'],
                ['id' => 'courses', 'name' => 'Course Performance'],
                ['id' => 'exams', 'name' => 'Examination Results'],
                ['id' => 'students', 'name' => 'Student Performance'],
                ['id' => 'instructors', 'name' => 'Instructor Summary'],
            ],
        ];

        return response()->json([
            'status' => 'success',
            'data'   => [
                'department_name'      => ucwords(strtolower($deptName)),
                'department_code'      => $deptCode,
                'academic_year'        => $currentAcademicYear,
                'semester'             => $currentSemester,
                'period'               => $period,
                'kpis'                 => $kpis,
                'grade_distribution'   => $gradeDistribution,
                'trend'                => $trendData,
                'chart_points'         => $chartPoints,
                'has_trend_data'       => $hasAnyTrendData,
                'svg_line'             => $svgLine,
                'svg_fill'             => $svgFill,
                'chronological_trend'  => $chronologicalTrend,
                'course_performance'   => $coursePerformance,
                'exam_results'         => $examResults,
                'student_performance'  => $studentPerformance,
                'instructor_summary'   => $instructorSummary,
                'filter_options'       => $filterOptions,
            ]
        ]);
    }

    /**
     * Export Department Reports as PDF, Excel, or CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();
        $dept = $deptId ? Department::find($deptId) : null;
        $deptName = ucwords(strtolower($dept ? $dept->name : ($user->department?->name ?? 'Computer Science')));
        $format = strtolower($request->input('format', 'pdf'));
        $period = $request->input('period', 'This Semester');
        $dateStr = Carbon::now()->format('Y-m-d');
        $baseFileName = "Wollo_University_{$deptName}_Department_Report_{$dateStr}";

        // Get report data directly using same filters
        $reportResponse = $this->index($request);
        $reportData = $reportResponse->getData(true)['data'] ?? [];

        if ($format === 'pdf') {
            return $this->exportPdf($reportData, $deptName, $period, $baseFileName);
        }

        if ($format === 'excel' || $format === 'xlsx') {
            return $this->exportExcel($reportData, $deptName, $period, $baseFileName);
        }

        return $this->exportCsv($reportData, $deptName, $period, $baseFileName);
    }

    /**
     * Export as PDF using Dompdf.
     */
    private function exportPdf(array $data, string $deptName, string $period, string $baseFileName): JsonResponse
    {
        $generatedAt = Carbon::now()->format('M d, Y h:i A');
        $academicYear = $data['academic_year'] ?? '2028';
        $semester = $data['semester'] ?? 'Second Semester';
        $kpis = $data['kpis'] ?? [];
        $grades = $data['grade_distribution'] ?? [];
        $courses = $data['course_performance'] ?? [];
        $exams = $data['exam_results'] ?? [];
        $students = $data['student_performance'] ?? [];

        $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Wollo University Department Report</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 16px; }
  .header { border-bottom: 2.5px solid #5138ed; padding-bottom: 12px; margin-bottom: 16px; }
  .university-title { font-size: 18px; font-weight: bold; color: #1e1b4b; margin: 0 0 2px 0; letter-spacing: 0.5px; }
  .report-subtitle { font-size: 11px; color: #5138ed; font-weight: bold; margin: 0 0 4px 0; }
  .dept-title { font-size: 13px; font-weight: bold; color: #0f172a; margin: 0; }
  .meta-table { width: 100%; margin-top: 10px; font-size: 9.5px; color: #475569; }
  .meta-table td { padding: 2px 0; }
  
  .kpi-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin-bottom: 16px; }
  .kpi-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; text-align: left; }
  .kpi-title { font-size: 8px; text-transform: uppercase; color: #64748b; font-weight: bold; margin-bottom: 3px; }
  .kpi-val { font-size: 15px; font-weight: bold; color: #0f172a; }
  .kpi-change { font-size: 8px; color: #10b981; font-weight: bold; margin-left: 3px; }

  .section-title { font-size: 11.5px; font-weight: bold; color: #1e293b; margin: 16px 0 6px 0; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; text-transform: uppercase; letter-spacing: 0.3px; }

  table.data-table { width: 100%; border-collapse: collapse; margin-top: 4px; font-size: 9px; }
  table.data-table th { background: #f1f5f9; color: #334155; font-weight: bold; text-align: left; padding: 5px 6px; border: 1px solid #e2e8f0; }
  table.data-table td { padding: 4px 6px; border: 1px solid #e2e8f0; }
  table.data-table tr:nth-child(even) { background: #f8fafc; }

  .badge-pass { background: #ecfdf5; color: #059669; font-weight: bold; padding: 2px 5px; border-radius: 3px; }
  .badge-fail { background: #fef2f2; color: #dc2626; font-weight: bold; padding: 2px 5px; border-radius: 3px; }
  .badge-avg { background: #f5f3ff; color: #5138ed; font-weight: bold; padding: 2px 5px; border-radius: 3px; }
  .badge-neutral { background: #f1f5f9; color: #475569; font-weight: bold; padding: 2px 5px; border-radius: 3px; }
  .footer { margin-top: 25px; text-align: right; font-size: 8.5px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }
</style>
</head>
<body>
  <div class="header">
    <table style="width: 100%;">
      <tr>
        <td>
          <div class="university-title">WOLLO UNIVERSITY</div>
          <div class="report-subtitle">OFFICIAL DEPARTMENT ACADEMIC &amp; EXAMINATION REPORT</div>
          <div class="dept-title">Department of ' . htmlspecialchars($deptName) . '</div>
        </td>
      </tr>
    </table>
    <table class="meta-table">
      <tr>
        <td style="width: 33%;"><strong>Academic Term:</strong> ' . htmlspecialchars($academicYear . ' ' . $semester) . '</td>
        <td style="width: 33%; text-align: center;"><strong>Reporting Filter:</strong> ' . htmlspecialchars($period) . '</td>
        <td style="width: 33%; text-align: right;"><strong>Generated On:</strong> ' . $generatedAt . '</td>
      </tr>
    </table>
  </div>

  <!-- Key Metrics Summary -->
  <table class="kpi-table">
    <tr>';
        foreach ($kpis as $k) {
            $html .= '<td class="kpi-card" style="width: 25%;">
        <div class="kpi-title">' . htmlspecialchars($k['label']) . '</div>
        <div class="kpi-val">' . htmlspecialchars($k['value']) . ' <span class="kpi-change">' . htmlspecialchars($k['change']) . '</span></div>
      </td>';
        }
        $html .= '</tr>
  </table>

  <!-- Grade Distribution Summary -->
  <div class="section-title">Academic Performance &amp; Grade Breakdown</div>
  <table class="data-table" style="margin-bottom: 12px;">
    <thead>
      <tr>
        <th style="text-align: center;">Grade A (&ge;85%)</th>
        <th style="text-align: center;">Grade B (70-84%)</th>
        <th style="text-align: center;">Grade C (60-69%)</th>
        <th style="text-align: center;">Grade D (50-59%)</th>
        <th style="text-align: center;">Grade F (&lt;50%)</th>
        <th style="text-align: center;">In Progress</th>
        <th style="text-align: center;">Passed Total</th>
        <th style="text-align: center;">Department Pass Rate</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="text-align: center; font-weight: bold; color: #059669;">' . ($grades['A'] ?? 0) . '</td>
        <td style="text-align: center; font-weight: bold; color: #0284c7;">' . ($grades['B'] ?? 0) . '</td>
        <td style="text-align: center; font-weight: bold; color: #4f46e5;">' . ($grades['C'] ?? 0) . '</td>
        <td style="text-align: center; font-weight: bold; color: #d97706;">' . ($grades['D'] ?? 0) . '</td>
        <td style="text-align: center; font-weight: bold; color: #dc2626;">' . ($grades['F'] ?? 0) . '</td>
        <td style="text-align: center; color: #64748b;">' . ($grades['in_progress'] ?? 0) . '</td>
        <td style="text-align: center; font-weight: bold; color: #059669;">' . ($grades['passed_count'] ?? 0) . '</td>
        <td style="text-align: center; font-weight: bold; color: #5138ed;">' . ($grades['pass_rate'] ?? 0) . '%</td>
      </tr>
    </tbody>
  </table>

  <!-- Course Performance -->
  <div class="section-title">Course Examination Performance</div>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 5%;">#</th>
        <th style="width: 15%;">Course Code</th>
        <th style="width: 30%;">Course Name</th>
        <th style="width: 20%;">Instructor</th>
        <th style="width: 10%; text-align: center;">Attempts</th>
        <th style="width: 10%; text-align: center;">Avg Score</th>
        <th style="width: 10%; text-align: center;">Pass Rate</th>
      </tr>
    </thead>
    <tbody>';
        foreach ($courses as $idx => $c) {
            $html .= '<tr>
        <td>' . ($idx + 1) . '</td>
        <td style="font-weight: bold; font-family: monospace;">' . htmlspecialchars($c['code']) . '</td>
        <td style="font-weight: 500;">' . htmlspecialchars($c['name']) . '</td>
        <td>' . htmlspecialchars($c['instructor']) . '</td>
        <td style="text-align: center;">' . $c['attempts'] . '</td>
        <td style="text-align: center;"><span class="badge-avg">' . $c['avgScore'] . '%</span></td>
        <td style="text-align: center;"><span class="badge-pass">' . $c['passRate'] . '%</span></td>
      </tr>';
        }
        $html .= '</tbody>
  </table>

  <!-- Examination Results Summary -->
  <div class="section-title" style="margin-top: 16px;">Examination Summary</div>
  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 5%;">#</th>
        <th style="width: 15%;">Exam Code</th>
        <th style="width: 30%;">Exam Title</th>
        <th style="width: 15%;">Course</th>
        <th style="width: 10%; text-align: center;">Attempts</th>
        <th style="width: 10%; text-align: center;">Avg Score</th>
        <th style="width: 15%; text-align: center;">Status</th>
      </tr>
    </thead>
    <tbody>';
        foreach ($exams as $idx => $e) {
            $html .= '<tr>
        <td>' . ($idx + 1) . '</td>
        <td style="font-weight: bold; font-family: monospace;">' . htmlspecialchars($e['code']) . '</td>
        <td style="font-weight: 500;">' . htmlspecialchars($e['title']) . '</td>
        <td>' . htmlspecialchars($e['course_code']) . '</td>
        <td style="text-align: center;">' . $e['attempts_count'] . '</td>
        <td style="text-align: center;"><span class="badge-avg">' . $e['avg_score'] . '%</span></td>
        <td style="text-align: center;"><span class="badge-neutral">' . ucfirst($e['status']) . '</span></td>
      </tr>';
        }
        $html .= '</tbody>
  </table>

  <div class="footer">
    <p>Official Wollo University Online Examination System &bull; Confidential Academic Document &bull; Generated by Department Head</p>
  </div>
</body>
</html>';

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfBytes = $dompdf->output();

        LogActivity::record('Exported', 'Reports', "Exported {$deptName} Department Analytics Report as PDF");

        return response()->json([
            'file'     => base64_encode($pdfBytes),
            'filename' => $baseFileName . '.pdf',
            'format'   => 'pdf',
        ]);
    }

    /**
     * Export as CSV / Excel compatible with BOM.
     */
    private function exportCsv(array $data, string $deptName, string $period, string $baseFileName): JsonResponse
    {
        ob_start();
        $handle = fopen('php://output', 'w');
        // UTF-8 BOM
        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['WOLLO UNIVERSITY - DEPARTMENT ACADEMIC & EXAMINATION REPORT']);
        fputcsv($handle, ['Department:', $deptName]);
        fputcsv($handle, ['Academic Term:', ($data['academic_year'] ?? '') . ' ' . ($data['semester'] ?? '')]);
        fputcsv($handle, ['Reporting Filter:', $period]);
        fputcsv($handle, ['Generated At:', Carbon::now()->toDateTimeString()]);
        fputcsv($handle, []);

        // KPIs
        fputcsv($handle, ['KEY PERFORMANCE METRICS']);
        foreach ($data['kpis'] ?? [] as $k) {
            fputcsv($handle, [$k['label'], $k['value'], $k['change']]);
        }
        fputcsv($handle, []);

        // Grade Distribution
        fputcsv($handle, ['GRADE BREAKDOWN & PASS/FAIL ANALYSIS']);
        fputcsv($handle, ['Metric', 'Count', 'Percentage']);
        $grades = $data['grade_distribution'] ?? [];
        fputcsv($handle, ['Grade A (>=85%)', $grades['A'] ?? 0, '']);
        fputcsv($handle, ['Grade B (70-84%)', $grades['B'] ?? 0, '']);
        fputcsv($handle, ['Grade C (60-69%)', $grades['C'] ?? 0, '']);
        fputcsv($handle, ['Grade D (50-59%)', $grades['D'] ?? 0, '']);
        fputcsv($handle, ['Grade F (<50%)', $grades['F'] ?? 0, '']);
        fputcsv($handle, ['In Progress Attempts', $grades['in_progress'] ?? 0, '']);
        fputcsv($handle, ['Total Passed', $grades['passed_count'] ?? 0, ($grades['pass_rate'] ?? 0) . '%']);
        fputcsv($handle, ['Total Failed', $grades['failed_count'] ?? 0, '']);
        fputcsv($handle, []);

        // Course Performance
        fputcsv($handle, ['COURSE PERFORMANCE BREAKDOWN']);
        fputcsv($handle, ['#', 'Course Code', 'Course Title', 'Instructor', 'Attempts Count', 'Average Score (%)', 'Pass Rate (%)']);
        foreach ($data['course_performance'] ?? [] as $i => $c) {
            fputcsv($handle, [
                $i + 1,
                $c['code'],
                $c['name'],
                $c['instructor'],
                $c['attempts'],
                $c['avgScore'] . '%',
                $c['passRate'] . '%',
            ]);
        }
        fputcsv($handle, []);

        // Examination Results
        fputcsv($handle, ['EXAMINATION RESULTS SUMMARY']);
        fputcsv($handle, ['#', 'Exam Code', 'Exam Title', 'Course', 'Scheduled Date', 'Attempts', 'Passed', 'Failed', 'Avg Score (%)', 'Pass Rate (%)', 'Status']);
        foreach ($data['exam_results'] ?? [] as $i => $e) {
            fputcsv($handle, [
                $i + 1,
                $e['code'],
                $e['title'],
                $e['course_code'],
                $e['scheduled_at'],
                $e['attempts_count'],
                $e['passed_count'],
                $e['failed_count'],
                $e['avg_score'] . '%',
                $e['pass_rate'] . '%',
                $e['status'],
            ]);
        }
        fputcsv($handle, []);

        // Student Performance
        fputcsv($handle, ['STUDENT EXAMINATION ATTEMPTS']);
        fputcsv($handle, ['#', 'Student Name', 'Student ID', 'Exam Title', 'Course', 'Score', 'Total Marks', 'Percentage (%)', 'Grade', 'Status', 'Submitted At']);
        foreach ($data['student_performance'] ?? [] as $i => $s) {
            fputcsv($handle, [
                $i + 1,
                $s['student_name'],
                $s['student_id'],
                $s['exam_title'],
                $s['course_code'],
                $s['score'],
                $s['total_marks'],
                $s['percentage'] !== null ? $s['percentage'] . '%' : 'N/A',
                $s['grade'],
                $s['status'],
                $s['submitted_at'],
            ]);
        }

        fclose($handle);
        $csvContent = ob_get_clean();

        LogActivity::record('Exported', 'Reports', "Exported {$deptName} Department Analytics Report as CSV");

        return response()->json([
            'file'     => base64_encode($csvContent),
            'filename' => $baseFileName . '.csv',
            'format'   => 'csv',
        ]);
    }

    /**
     * Export as Excel.
     */
    private function exportExcel(array $data, string $deptName, string $period, string $baseFileName): JsonResponse
    {
        $csvResponse = $this->exportCsv($data, $deptName, $period, $baseFileName);
        $csvData = $csvResponse->getData(true);
        $csvData['filename'] = $baseFileName . '.xlsx';
        $csvData['format'] = 'xlsx';
        return response()->json($csvData);
    }
}
