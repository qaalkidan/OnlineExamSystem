<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Models\User;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Models\AcademicEvent;

class AdminDashboardController extends Controller
{
    /**
     * Provide comprehensive, real database-driven dashboard statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (!$admin || $admin->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Super Admin access required.'], 403);
        }

        $period = $request->query('period', '30_days');
        [$startDate, $prevStartDate, $periodDays] = $this->resolvePeriodDates($period);

        $now = Carbon::now();

        // ── 1. KPI Counts & Real Growth Calculations ─────────────────────────
        $totalUsers = User::count();
        $currUsersCount = User::where('created_at', '>=', $startDate)->count();
        $prevUsersCount = User::whereBetween('created_at', [$prevStartDate, $startDate])->count();
        $usersGrowth = $this->calculateGrowth($currUsersCount, $prevUsersCount);

        $instructors = User::where('role', 'instructor')->count();
        $activeInstructors = User::where('role', 'instructor')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'inactive');
            })->count();
        $currInstructorsCount = User::where('role', 'instructor')->where('created_at', '>=', $startDate)->count();
        $prevInstructorsCount = User::where('role', 'instructor')->whereBetween('created_at', [$prevStartDate, $startDate])->count();
        $instructorsGrowth = $this->calculateGrowth($currInstructorsCount, $prevInstructorsCount);

        $students = User::where('role', 'student')->count();
        $activeStudents = User::where('role', 'student')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'inactive');
            })->count();
        $currStudentsCount = User::where('role', 'student')->where('created_at', '>=', $startDate)->count();
        $prevStudentsCount = User::where('role', 'student')->whereBetween('created_at', [$prevStartDate, $startDate])->count();
        $studentsGrowth = $this->calculateGrowth($currStudentsCount, $prevStudentsCount);

        $deptHeads = User::where('role', 'dept_head')->count();
        $admins = User::where('role', 'admin')->count();

        $courses = Course::count();
        $currCoursesCount = Course::where('created_at', '>=', $startDate)->count();
        $prevCoursesCount = Course::whereBetween('created_at', [$prevStartDate, $startDate])->count();
        $coursesGrowth = $this->calculateGrowth($currCoursesCount, $prevCoursesCount);

        $exams = Exam::count();
        $activeExams = Exam::whereIn('status', ['published', 'scheduled'])->count();
        $currExamsCount = Exam::where('created_at', '>=', $startDate)->count();
        $prevExamsCount = Exam::whereBetween('created_at', [$prevStartDate, $startDate])->count();
        $examsGrowth = $this->calculateGrowth($currExamsCount, $prevExamsCount);

        // ── 2. Academic Overview (Filtered by Selected Period) ───────────────
        $totalAttempts = ExamAttempt::whereBetween('created_at', [$startDate, $now])->count();
        $prevTotalAttempts = ExamAttempt::whereBetween('created_at', [$prevStartDate, $startDate])->count();
        $attemptsGrowth = $this->calculateGrowth($totalAttempts, $prevTotalAttempts);

        $examsConducted = ExamAttempt::whereBetween('created_at', [$startDate, $now])
            ->distinct('exam_id')
            ->count('exam_id');
        $prevExamsConducted = ExamAttempt::whereBetween('created_at', [$prevStartDate, $startDate])
            ->distinct('exam_id')
            ->count('exam_id');
        $examsConductedGrowth = $this->calculateGrowth($examsConducted, $prevExamsConducted);

        $averageScore = ExamAttempt::whereBetween('created_at', [$startDate, $now])
            ->whereNotNull('percentage')
            ->avg('percentage');
        $averageScore = $averageScore !== null ? round((float) $averageScore, 1) : 0;

        $passedAttempts = ExamAttempt::whereBetween('created_at', [$startDate, $now])
            ->whereNotNull('percentage')
            ->where('percentage', '>=', 50)
            ->count();
        $passRate = $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100, 1) : 0;

        // ── 3. Real Chart Points Grouped by Period ───────────────────────────
        $chart = $this->generateChartData($startDate, $now, $period);

        // ── 4. Academic Semester & Calendar Period ───────────────────────────
        $academicYear = SystemSetting::where('key', 'academicYear')->value('value') ?? '2026';
        $semester = SystemSetting::where('key', 'semester')->value('value') ?? 'Second Semester';
        $formattedTerm = trim("{$academicYear} {$semester}");

        $currentEvent = AcademicEvent::where(function ($q) use ($academicYear, $semester) {
            $q->where('academic_year', $academicYear)
              ->orWhere('semester', $semester);
        })
        ->orderBy('start_date', 'desc')
        ->first();

        // ── 5. User Distribution ─────────────────────────────────────────────
        $userTotalForDist = max(1, $totalUsers);
        $userDistribution = [
            [
                'label' => 'Students',
                'count' => $students,
                'pct'   => round(($students / $userTotalForDist) * 100, 1) . '%',
                'color' => '#6366f1', // Indigo
            ],
            [
                'label' => 'Instructors',
                'count' => $instructors,
                'pct'   => round(($instructors / $userTotalForDist) * 100, 1) . '%',
                'color' => '#10b981', // Emerald
            ],
            [
                'label' => 'Department Heads',
                'count' => $deptHeads,
                'pct'   => round(($deptHeads / $userTotalForDist) * 100, 1) . '%',
                'color' => '#f59e0b', // Amber
            ],
            [
                'label' => 'Administrators',
                'count' => $admins,
                'pct'   => round(($admins / $userTotalForDist) * 100, 1) . '%',
                'color' => '#ec4899', // Pink
            ],
        ];

        // ── 6. Recent System Activity Logs ───────────────────────────────────
        $recentActivities = ActivityLog::with('user:id,name,role,profile_picture')
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($log) {
                return [
                    'id'          => $log->id,
                    'action'      => $log->action,
                    'type'        => $log->type ?: 'System Event',
                    'module'      => $log->module ?: 'General',
                    'details'     => $log->details ?: $log->action,
                    'actor_name'  => $log->user->name ?? 'System',
                    'actor_role'  => $log->actor_role ?: ($log->user->role ?? 'admin'),
                    'time_ago'    => $log->created_at ? $log->created_at->diffForHumans() : 'Recently',
                    'timestamp'   => $log->created_at ? $log->created_at->toIso8601String() : null,
                    'date'        => $log->created_at ? $log->created_at->format('M d, H:i') : '',
                ];
            });

        // ── 7. Recent Exams ──────────────────────────────────────────────────
        $recentExams = Exam::withCount('attempts')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($exam) {
                return [
                    'id'             => $exam->id,
                    'title'          => $exam->title,
                    'course'         => $exam->course_name ? ($exam->course_name . ($exam->course_code ? ' (' . $exam->course_code . ')' : '')) : 'No Course',
                    'date'           => $exam->created_at ? $exam->created_at->format('M d, Y') : '—',
                    'status'         => ucfirst($exam->status ?? 'Draft'),
                    'attempts_count' => $exam->attempts_count,
                    'duration'       => $exam->duration_minutes . ' mins',
                ];
            });

        // ── 8. Real System Health Check ──────────────────────────────────────
        $systemHealth = $this->performHealthChecks();

        return response()->json([
            'status' => 'success',
            'period' => $period,
            'kpi' => [
                'totalUsers' => [
                    'value'       => $totalUsers,
                    'label'       => 'Total Users',
                    'sub'         => 'All registered users',
                    'growth'      => $usersGrowth,
                    'in_period'   => $currUsersCount,
                ],
                'instructors' => [
                    'value'       => $instructors,
                    'active'      => $activeInstructors,
                    'label'       => 'Instructors',
                    'sub'         => $activeInstructors . ' active instructors',
                    'growth'      => $instructorsGrowth,
                    'in_period'   => $currInstructorsCount,
                ],
                'students' => [
                    'value'       => $students,
                    'active'      => $activeStudents,
                    'label'       => 'Students',
                    'sub'         => $activeStudents . ' active students',
                    'growth'      => $studentsGrowth,
                    'in_period'   => $currStudentsCount,
                ],
                'courses' => [
                    'value'       => $courses,
                    'label'       => 'Courses',
                    'sub'         => 'Offered academic courses',
                    'growth'      => $coursesGrowth,
                    'in_period'   => $currCoursesCount,
                ],
                'exams' => [
                    'value'       => $exams,
                    'active'      => $activeExams,
                    'label'       => 'Exams',
                    'sub'         => $activeExams . ' published & scheduled',
                    'growth'      => $examsGrowth,
                    'in_period'   => $currExamsCount,
                ],
            ],
            // Backward-compatible structure for existing frontend bindings
            'stats' => [
                'totalUsers'  => $totalUsers,
                'instructors' => $instructors,
                'students'    => $students,
                'deptHeads'   => $deptHeads,
                'courses'     => $courses,
                'exams'       => $exams,
            ],
            'academicOverview' => [
                'examsConducted' => [
                    'value'  => $examsConducted,
                    'growth' => $examsConductedGrowth,
                ],
                'totalAttempts' => [
                    'value'  => $totalAttempts,
                    'growth' => $attemptsGrowth,
                ],
                'passRate' => [
                    'value' => $passRate,
                ],
                'averageScore' => [
                    'value' => $averageScore,
                ],
            ],
            'overview' => [
                'examsConducted' => $examsConducted,
                'totalAttempts'  => $totalAttempts,
                'passRate'       => $passRate,
                'averageScore'   => $averageScore,
            ],
            'chart'              => $chart,
            'academicPeriod'     => [
                'academicYear'   => $academicYear,
                'semester'       => $semester,
                'formattedTerm'  => $formattedTerm,
                'eventTitle'     => $currentEvent?->title ?? 'Semester Period',
                'startDate'      => $currentEvent?->start_date?->format('M d, Y') ?? null,
                'endDate'        => $currentEvent?->end_date?->format('M d, Y') ?? null,
                'status'         => $currentEvent?->status ?? 'Active',
            ],
            'userDistribution'   => $userDistribution,
            'recentActivities'   => $recentActivities,
            'recentExams'        => $recentExams,
            'systemHealth'       => $systemHealth,
            'lastUpdated'        => now()->toIso8601String(),
        ]);
    }

    /**
     * Dedicated live endpoint to re-probe system services on demand.
     */
    public function systemStatus(): JsonResponse
    {
        $health = $this->performHealthChecks();
        return response()->json([
            'status' => 'success',
            'health' => $health,
            'checked_at' => now()->toIso8601String(),
            'checked_at_human' => now()->diffForHumans(),
        ]);
    }

    /**
     * Resolve date bounds based on requested period filter.
     */
    private function resolvePeriodDates(string $period): array
    {
        $now = Carbon::now();
        switch ($period) {
            case '7_days':
                return [$now->copy()->subDays(7), $now->copy()->subDays(14), 7];
            case '90_days':
                return [$now->copy()->subDays(90), $now->copy()->subDays(180), 90];
            case 'semester':
                return [$now->copy()->subMonths(6), $now->copy()->subMonths(12), 180];
            case 'year':
                return [$now->copy()->subYear(), $now->copy()->subYears(2), 365];
            case '30_days':
            default:
                return [$now->copy()->subDays(30), $now->copy()->subDays(60), 30];
        }
    }

    /**
     * Calculate honest percentage growth between current and prior periods.
     */
    private function calculateGrowth(int $current, int $previous): ?array
    {
        if ($previous === 0 && $current === 0) {
            return null;
        }

        if ($previous === 0) {
            return [
                'rate'      => 100.0,
                'formatted' => '+' . $current . ' new',
                'trend'     => 'up',
                'count'     => $current,
            ];
        }

        $difference = $current - $previous;
        $rate = round(($difference / $previous) * 100, 1);

        return [
            'rate'      => $rate,
            'formatted' => ($rate >= 0 ? '+' : '') . $rate . '%',
            'trend'     => $rate >= 0 ? 'up' : 'down',
            'count'     => $current,
        ];
    }

    /**
     * Generate dynamic chart data buckets for the requested date window.
     */
    private function generateChartData(Carbon $startDate, Carbon $endDate, string $period): array
    {
        $labels = [];
        $attemptsSeries = [];
        $examsSeries = [];
        $fullDates = [];

        if ($period === '7_days') {
            // 7 daily points
            for ($i = 6; $i >= 0; $i--) {
                $pointDate = Carbon::now()->subDays($i);
                $dateStr = $pointDate->toDateString();
                $labels[] = $pointDate->format('M d');
                $fullDates[] = $pointDate->format('D, M d, Y');
                $attemptsSeries[] = ExamAttempt::whereDate('created_at', $dateStr)->count();
                $examsSeries[] = Exam::whereDate('created_at', $dateStr)->count();
            }
        } elseif ($period === '30_days') {
            // 7 interval points across 30 days
            $stepDays = 5;
            for ($i = 6; $i >= 0; $i--) {
                $sliceEnd = Carbon::now()->subDays($i * $stepDays);
                $sliceStart = $sliceEnd->copy()->subDays($stepDays - 1)->startOfDay();
                $labels[] = $sliceEnd->format('M d');
                $fullDates[] = $sliceStart->format('M d') . ' - ' . $sliceEnd->format('M d, Y');
                $attemptsSeries[] = ExamAttempt::whereBetween('created_at', [$sliceStart, $sliceEnd->endOfDay()])->count();
                $examsSeries[] = Exam::whereBetween('created_at', [$sliceStart, $sliceEnd->endOfDay()])->count();
            }
        } elseif ($period === '90_days') {
            // 7 interval points across 90 days (every 13 days)
            $stepDays = 13;
            for ($i = 6; $i >= 0; $i--) {
                $sliceEnd = Carbon::now()->subDays($i * $stepDays);
                $sliceStart = $sliceEnd->copy()->subDays($stepDays - 1)->startOfDay();
                $labels[] = $sliceEnd->format('M d');
                $fullDates[] = $sliceStart->format('M d') . ' - ' . $sliceEnd->format('M d, Y');
                $attemptsSeries[] = ExamAttempt::whereBetween('created_at', [$sliceStart, $sliceEnd->endOfDay()])->count();
                $examsSeries[] = Exam::whereBetween('created_at', [$sliceStart, $sliceEnd->endOfDay()])->count();
            }
        } else {
            // Monthly points for semester/year
            $months = $period === 'year' ? 12 : 6;
            for ($i = $months - 1; $i >= 0; $i--) {
                $pointDate = Carbon::now()->subMonths($i);
                $labels[] = $pointDate->format('M Y');
                $fullDates[] = $pointDate->format('F Y');
                $attemptsSeries[] = ExamAttempt::whereYear('created_at', $pointDate->year)
                    ->whereMonth('created_at', $pointDate->month)
                    ->count();
                $examsSeries[] = Exam::whereYear('created_at', $pointDate->year)
                    ->whereMonth('created_at', $pointDate->month)
                    ->count();
            }
        }

        return [
            'labels'    => $labels,
            'fullDates' => $fullDates,
            'data'      => $attemptsSeries, // primary series for backward-compatibility
            'attempts'  => $attemptsSeries,
            'exams'     => $examsSeries,
        ];
    }

    /**
     * Probe actual server, database, file storage, and email configuration.
     */
    private function performHealthChecks(): array
    {
        // 1. Database Connectivity & Ping
        $dbStatus = 'Operational';
        $dbLatency = '0ms';
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $dbLatency = round((microtime(true) - $start) * 1000, 1) . 'ms';
        } catch (\Throwable $e) {
            $dbStatus = 'Unavailable';
            $dbLatency = 'Timeout';
        }

        // 2. Application Server Status & Memory
        $serverStatus = 'Operational';
        $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 1) . ' MB';

        // 3. File Storage Disk Accessibility
        $storageStatus = 'Operational';
        try {
            $storagePath = storage_path('app/public');
            if (!is_dir($storagePath) && !file_exists($storagePath)) {
                @mkdir($storagePath, 0755, true);
            }
            $isWritable = is_writable($storagePath);
            if (!$isWritable) {
                $storageStatus = 'Degraded';
            }
        } catch (\Throwable $e) {
            $storageStatus = 'Unavailable';
        }

        // 4. Email Service Configuration
        $emailStatus = 'Operational';
        $mailDriver = config('mail.default');
        if (empty($mailDriver) || $mailDriver === 'array') {
            $emailStatus = 'Degraded';
        }

        return [
            'server' => [
                'name'        => 'Application Server',
                'status'      => $serverStatus,
                'details'     => "PHP " . phpversion() . " • Memory: {$memoryUsage}",
                'icon'        => 'server',
            ],
            'database' => [
                'name'        => 'Database System',
                'status'      => $dbStatus,
                'details'     => "MySQL/MariaDB • Latency: {$dbLatency}",
                'icon'        => 'database',
            ],
            'storage' => [
                'name'        => 'File Storage',
                'status'      => $storageStatus,
                'details'     => 'Local/Public Disk Storage',
                'icon'        => 'folder',
            ],
            'email' => [
                'name'        => 'Email Notification Service',
                'status'      => $emailStatus,
                'details'     => 'Driver: ' . ($mailDriver ?: 'Not configured'),
                'icon'        => 'mail',
            ],
            'last_checked' => now()->toIso8601String(),
            'last_checked_human' => 'Just now',
        ];
    }
}
