<?php

namespace App\Http\Controllers\Api\V1\DeptHead;

use App\Exports\DepartmentResultExport;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\SemesterSubmission;
use App\Models\SystemSetting;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ResultController extends Controller
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

    /**
     * Standard grade letter calculator according to Wollo University criteria.
     */
    private function calculateGrade(float $pct): string
    {
        if ($pct >= 90) return 'A+';
        if ($pct >= 85) return 'A';
        if ($pct >= 80) return 'A-';
        if ($pct >= 75) return 'B+';
        if ($pct >= 70) return 'B';
        if ($pct >= 65) return 'C+';
        if ($pct >= 60) return 'C';
        if ($pct >= 50) return 'D';
        return 'F';
    }

    /**
     * Convert percentage to 4.0 scale Grade Point.
     */
    private function calculateGradePoint(float $pct): float
    {
        if ($pct >= 90) return 4.0;
        if ($pct >= 85) return 4.0;
        if ($pct >= 80) return 3.75;
        if ($pct >= 75) return 3.5;
        if ($pct >= 70) return 3.0;
        if ($pct >= 65) return 2.5;
        if ($pct >= 60) return 2.0;
        if ($pct >= 50) return 1.0;
        return 0.0;
    }

    /**
     * Main results endpoint:
     * Returns department academic performance, KPIs, exam performance list,
     * individual student results list, grade distribution, course performance,
     * and semester submission monitoring.
     */
    public function index(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();
        $dept = Department::find($deptId);

        // Fetch real system academic period settings
        $settingsYear = SystemSetting::where('key', 'academicYear')->value('value') ?? '2028';
        $settingsSemester = SystemSetting::where('key', 'semester')->value('value') ?? 'Second Semester';

        // Department courses & students
        $courses = Course::where('department_id', $deptId)->get();
        $deptCourseCodes = $courses->pluck('code')->filter()->toArray();

        $deptStudents = User::where('role', 'student')
            ->where(function ($q) use ($deptId) {
                if ($deptId) {
                    $q->where('department_id', $deptId);
                }
            })->get();

        $totalStudentsCount = $deptStudents->count();
        if ($totalStudentsCount === 0) {
            $totalStudentsCount = User::where('role', 'student')->count();
        }

        // Department instructors
        $deptInstructors = User::where(function ($q) use ($deptId) {
            if ($deptId) {
                $q->where('department_id', $deptId);
            }
            $q->whereIn('role', ['instructor', 'dept_head']);
        })->get(['id', 'name', 'email']);

        // Base department exams query
        $allDeptExams = $this->buildDeptExamsQuery($deptId, $user->id)
            ->with(['instructor', 'course', 'attempts.student'])
            ->withCount(['questions', 'attempts'])
            ->latest('id')
            ->get();

        $allDeptExamIds = $allDeptExams->pluck('id');
        $allAttempts = ExamAttempt::whereIn('exam_id', $allDeptExamIds)
            ->with(['exam.course', 'student'])
            ->get();

        // ── Real KPI Metrics ──
        $completedExams = $allDeptExams->filter(function ($e) {
            return in_array(strtolower($e->status ?? ''), ['completed', 'published']) || $e->attempts_count > 0;
        })->count();

        $studentIdsWithResults = $allAttempts->pluck('user_id')->filter()->unique();
        $totalStudentsWithResults = $studentIdsWithResults->count();

        $publishedAttempts = $allAttempts->where('status', 'published')->count();
        $pendingAttempts = $allAttempts->filter(function ($a) {
            return in_array(strtolower($a->status ?? ''), ['submitted', 'in_progress', 'pending', 'graded']) && strtolower($a->status ?? '') !== 'published';
        })->count();

        $gradedAttempts = $allAttempts->filter(function ($a) {
            return $a->percentage !== null && in_array(strtolower($a->status ?? ''), ['graded', 'published', 'completed', 'submitted']);
        });

        $avgScore = $gradedAttempts->count() > 0 ? round($gradedAttempts->avg('percentage'), 1) : 0.0;
        $passedAttempts = $gradedAttempts->filter(fn($a) => (float)$a->percentage >= 50)->count();
        $failedAttempts = $gradedAttempts->filter(fn($a) => (float)$a->percentage < 50)->count();
        $passRate = $gradedAttempts->count() > 0 ? round(($passedAttempts / $gradedAttempts->count()) * 100, 1) : 0.0;

        // ── Real Grade Distribution ──
        $gradeDistribution = [
            'A+' => 0, 'A' => 0, 'A-' => 0,
            'B+' => 0, 'B' => 0, 'B-' => 0,
            'C+' => 0, 'C' => 0,
            'D'  => 0, 'F' => 0,
        ];

        foreach ($allAttempts as $att) {
            $g = trim(strtoupper($att->grade ?? ''));
            if (isset($gradeDistribution[$g])) {
                $gradeDistribution[$g]++;
            } elseif ($att->percentage !== null) {
                $gCalc = $this->calculateGrade((float)$att->percentage);
                if (isset($gradeDistribution[$gCalc])) {
                    $gradeDistribution[$gCalc]++;
                }
            }
        }

        // ── Real Course Performance Breakdown ──
        $coursePerformance = [];
        $attemptsByCourse = $allAttempts->groupBy(function ($att) {
            return $att->exam->course_code ?? ($att->exam->course->code ?? 'N/A');
        });

        foreach ($attemptsByCourse as $code => $courseAtts) {
            $firstExam = $courseAtts->first()?->exam;
            $courseTitle = $firstExam?->course_name ?: ($firstExam?->course?->title ?? $code);
            $cCredits = $firstExam?->course?->credits ?? 3;
            $courseGraded = $courseAtts->filter(fn($a) => $a->percentage !== null);
            $cAvg = $courseGraded->count() > 0 ? round($courseGraded->avg('percentage'), 1) : 0.0;
            $cPassCount = $courseGraded->filter(fn($a) => (float)$a->percentage >= 50)->count();
            $cPassRate = $courseGraded->count() > 0 ? round(($cPassCount / $courseGraded->count()) * 100, 1) : 0.0;
            $cMax = $courseGraded->max('score') ?? 0;
            $cMin = $courseGraded->min('score') ?? 0;
            $pendingInCourse = $courseAtts->where('status', '!=', 'published')->count();

            $coursePerformance[] = [
                'course_code'    => $code,
                'course_name'    => $courseTitle,
                'credits'        => $cCredits,
                'total_attempts' => $courseAtts->count(),
                'average_score'  => $cAvg,
                'pass_rate'      => $cPassRate,
                'highest_score'  => $cMax,
                'lowest_score'   => $cMin,
                'pending_count'  => $pendingInCourse,
                'status'         => $pendingInCourse === 0 && $courseAtts->count() > 0 ? 'Published' : ($courseAtts->count() > 0 ? 'In Review' : 'No Submissions'),
            ];
        }

        // ── Real Semester Submissions Summary ──
        $instructorIds = $deptInstructors->pluck('id');
        $deptSubmissions = SemesterSubmission::whereIn('instructor_id', $instructorIds)->with('instructor')->get();
        $subStats = [
            'total_instructors' => $deptInstructors->count(),
            'submitted'         => $deptSubmissions->whereIn('status', ['submitted', 'approved'])->count(),
            'approved'          => $deptSubmissions->where('status', 'approved')->count(),
            'pending'           => $deptSubmissions->where('status', 'pending')->count(),
            'reopened'          => $deptSubmissions->where('status', 'reopened')->count(),
        ];
        $recentSubmissions = $deptSubmissions->take(5)->map(function ($sub) {
            return [
                'id'              => $sub->id,
                'instructor_name' => $sub->instructor?->name ?? 'Faculty Member',
                'instructor_email'=> $sub->instructor?->email ?? '',
                'semester'        => $sub->semester,
                'academic_year'   => $sub->academic_year,
                'department'      => $sub->department,
                'section'         => $sub->section ?? 'A',
                'status'          => ucfirst($sub->status ?? 'Pending'),
                'submitted_at'    => $sub->submitted_at ? $sub->submitted_at->format('M d, Y') : 'Not submitted',
                'approved_at'     => $sub->approved_at ? $sub->approved_at->format('M d, Y') : null,
            ];
        });

        // ── Build Filtered Exams (Exam-Level View) ──
        $filteredExams = $allDeptExams->filter(function ($exam) use ($request) {
            $search = strtolower(trim((string)$request->get('search', '')));
            if ($search !== '') {
                $code = strtolower($exam->settings['exam_code'] ?? ('EXM-' . ($exam->scheduled_at ? $exam->scheduled_at->format('Y') : date('Y')) . '-' . str_pad((string)$exam->id, 3, '0', STR_PAD_LEFT)));
                $title = strtolower((string)$exam->title);
                $cName = strtolower((string)($exam->course_name ?: ($exam->course->title ?? '')));
                $cCode = strtolower((string)($exam->course_code ?: ($exam->course->code ?? '')));
                $iName = strtolower((string)($exam->instructor?->name ?? ''));

                if (!str_contains($title, $search) &&
                    !str_contains($code, $search) &&
                    !str_contains($cName, $search) &&
                    !str_contains($cCode, $search) &&
                    !str_contains($iName, $search)) {
                    return false;
                }
            }

            if ($request->filled('semester') && $request->semester !== 'all') {
                $sem = $exam->settings['semester'] ?? ($exam->course->semester ?? 'Semester 1');
                if (strtolower($sem) !== strtolower($request->semester)) {
                    return false;
                }
            }

            if ($request->filled('course_code') && $request->course_code !== 'all') {
                $cCode = $exam->course_code ?: ($exam->course->code ?? '');
                if (strtolower($cCode) !== strtolower($request->course_code)) {
                    return false;
                }
            }

            if ($request->filled('instructor_id') && $request->instructor_id !== 'all') {
                if ((string)$exam->user_id !== (string)$request->instructor_id) {
                    return false;
                }
            }

            if ($request->filled('status') && $request->status !== 'all') {
                $reqStatus = strtolower($request->status);
                $examAttempts = $exam->attempts;
                $subCount = $examAttempts->count();
                $pubCount = $examAttempts->where('status', 'published')->count();
                $gradCount = $examAttempts->whereIn('status', ['graded', 'published'])->count();

                $computedStatus = 'draft';
                if ($subCount > 0 && $pubCount === $subCount) {
                    $computedStatus = 'published';
                } elseif ($subCount > 0 && $gradCount === $subCount) {
                    $computedStatus = 'graded';
                } elseif ($subCount > 0) {
                    $computedStatus = 'pending';
                } elseif (in_array(strtolower($exam->status ?? ''), ['completed', 'published'])) {
                    $computedStatus = 'completed';
                }

                if ($computedStatus !== $reqStatus && strtolower($exam->status ?? '') !== $reqStatus) {
                    return false;
                }
            }

            return true;
        });

        // Map exam view results
        $resultsExams = $filteredExams->map(function ($exam) use ($totalStudentsCount) {
            $examAttempts = $exam->attempts;
            $submittedCount = $examAttempts->filter(fn($a) => $a->submitted_at !== null || $a->status !== 'in_progress')->count();
            $gradedCount = $examAttempts->whereIn('status', ['graded', 'published'])->count();
            $publishedCount = $examAttempts->where('status', 'published')->count();
            $avg = $examAttempts->count() > 0 ? round($examAttempts->avg('score'), 1) : null;
            $avgPct = $examAttempts->count() > 0 ? round($examAttempts->avg('percentage'), 1) : null;

            $status = 'Completed';
            if ($submittedCount === 0) {
                $status = ucfirst($exam->status ?: 'Draft');
            } elseif ($publishedCount === $submittedCount && $submittedCount > 0) {
                $status = 'Published';
            } elseif ($gradedCount === $submittedCount && $submittedCount > 0) {
                $status = 'Graded';
            } else {
                $status = 'Pending';
            }

            $passCount = $examAttempts->filter(fn($a) => (float)($a->percentage ?? 0) >= 50)->count();
            $failCount = $examAttempts->filter(fn($a) => (float)($a->percentage ?? 0) < 50 && $a->percentage !== null)->count();

            return [
                'id'              => $exam->id,
                'title'           => $exam->title,
                'code'            => $exam->settings['exam_code'] ?? ('EXM-' . ($exam->scheduled_at ? $exam->scheduled_at->format('Y') : date('Y')) . '-' . str_pad((string)$exam->id, 3, '0', STR_PAD_LEFT)),
                'course_id'       => $exam->course?->id,
                'course_name'     => $exam->course_name ?: ($exam->course->title ?? 'General Course'),
                'course_code'     => $exam->course_code ?: ($exam->course->code ?? 'N/A'),
                'instructor_id'   => $exam->user_id,
                'instructor_name' => $exam->instructor->name ?? 'Faculty Member',
                'instructor_email'=> $exam->instructor->email ?? '',
                'scheduled_at'    => $exam->scheduled_at ? $exam->scheduled_at->format('M d, Y') : 'TBD',
                'duration'        => $exam->duration_minutes . ' min',
                'total_marks'     => $exam->total_marks,
                'total_students'  => $exam->students_count > 0 ? $exam->students_count : $totalStudentsCount,
                'submitted_count' => $submittedCount,
                'graded_count'    => $gradedCount,
                'published_count' => $publishedCount,
                'passed_count'    => $passCount,
                'failed_count'    => $failCount,
                'average_score'   => $avg,
                'average_pct'     => $avgPct,
                'pass_rate'       => $examAttempts->count() > 0 ? round(($passCount / $examAttempts->count()) * 100, 1) : null,
                'status'          => $status,
                'semester'        => $exam->settings['semester'] ?? ($exam->course->semester ?? 'Semester 1'),
                'academic_year'   => $exam->settings['academic_year'] ?? '2025/2026',
            ];
        })->values();

        // ── Build Filtered Student Attempts (Student-Level View) ──
        $allStudentAttempts = $allAttempts->map(function ($att) {
            $student = $att->student;
            $exam = $att->exam;
            $courseCode = $exam?->course_code ?: ($exam?->course?->code ?? 'N/A');
            $courseName = $exam?->course_name ?: ($exam?->course?->title ?? 'General Course');
            $examCode = $exam?->settings['exam_code'] ?? ('EXM-' . ($exam?->scheduled_at ? $exam->scheduled_at->format('Y') : date('Y')) . '-' . str_pad((string)($exam?->id ?? 0), 3, '0', STR_PAD_LEFT));

            $pct = $att->percentage !== null ? (float)$att->percentage : null;
            $grade = $att->grade ?: ($pct !== null ? $this->calculateGrade($pct) : 'Pending');
            $gradePoint = $pct !== null ? $this->calculateGradePoint($pct) : 0.0;

            return [
                'id'              => $att->id,
                'attempt_id'      => $att->id,
                'exam_id'         => $att->exam_id,
                'exam_title'      => $exam?->title ?? 'Exam',
                'exam_code'       => $examCode,
                'course_name'     => $courseName,
                'course_code'     => $courseCode,
                'student_id'      => $att->user_id,
                'student_name'    => $student?->name ?? 'Student',
                'student_reg_no'  => $student?->student_id ?: ('UGR/' . str_pad((string)$att->user_id, 5, '0', STR_PAD_LEFT) . '/16'),
                'student_email'   => $student?->email ?? '',
                'student_year'    => $student?->year_level ?? '1st Year',
                'student_section' => $student?->section ?? 'A',
                'instructor_name' => $exam?->instructor?->name ?? 'Faculty Member',
                'instructor_id'   => $exam?->user_id,
                'score'           => $att->score,
                'total_marks'     => $att->total_marks ?? ($exam?->total_marks ?? 100),
                'percentage'      => $pct,
                'grade'           => $grade,
                'grade_point'     => $gradePoint,
                'status'          => ucfirst($att->status ?? 'submitted'),
                'submitted_at'    => $att->submitted_at ? $att->submitted_at->format('M d, Y h:i A') : ($att->created_at ? $att->created_at->format('M d, Y') : 'N/A'),
                'semester'        => $exam?->settings['semester'] ?? ($exam?->course?->semester ?? 'Semester 1'),
                'academic_year'   => $exam?->settings['academic_year'] ?? '2025/2026',
            ];
        });

        // Filter student attempts
        $filteredStudentAttempts = $allStudentAttempts->filter(function ($row) use ($request) {
            $search = strtolower(trim((string)$request->get('search', '')));
            if ($search !== '') {
                $sName = strtolower($row['student_name']);
                $sReg = strtolower($row['student_reg_no']);
                $cName = strtolower($row['course_name']);
                $cCode = strtolower($row['course_code']);
                $eTitle = strtolower($row['exam_title']);
                $eCode = strtolower($row['exam_code']);

                if (!str_contains($sName, $search) &&
                    !str_contains($sReg, $search) &&
                    !str_contains($cName, $search) &&
                    !str_contains($cCode, $search) &&
                    !str_contains($eTitle, $search) &&
                    !str_contains($eCode, $search)) {
                    return false;
                }
            }

            if ($request->filled('semester') && $request->semester !== 'all') {
                if (strtolower($row['semester']) !== strtolower($request->semester)) {
                    return false;
                }
            }

            if ($request->filled('course_code') && $request->course_code !== 'all') {
                if (strtolower($row['course_code']) !== strtolower($request->course_code)) {
                    return false;
                }
            }

            if ($request->filled('grade') && $request->grade !== 'all') {
                if (strtoupper($row['grade']) !== strtoupper($request->grade)) {
                    return false;
                }
            }

            if ($request->filled('status') && $request->status !== 'all') {
                if (strtolower($row['status']) !== strtolower($request->status)) {
                    return false;
                }
            }

            return true;
        })->values();

        // Server-side pagination
        $page = max(1, (int)$request->get('page', 1));
        $perPage = max(5, min(100, (int)$request->get('per_page', 10)));
        $viewMode = $request->get('view_mode', 'exams'); // 'exams' | 'students'

        $totalExamsCount = $resultsExams->count();
        $totalAttemptsCount = $filteredStudentAttempts->count();

        $paginatedExams = $resultsExams->slice(($page - 1) * $perPage, $perPage)->values();
        $paginatedAttempts = $filteredStudentAttempts->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => [
                'department' => [
                    'name'            => $dept->name ?? 'Computer Science',
                    'code'            => $dept->code ?? 'CS',
                    'head_name'       => $user->name,
                    'academic_year'   => $settingsYear,
                    'semester'        => $settingsSemester,
                    'total_courses'   => count($deptCourseCodes),
                    'total_students'  => $totalStudentsCount,
                ],
                'stats' => [
                    'total_exams'                => $allDeptExams->count(),
                    'completed_exams'            => $completedExams,
                    'total_students_with_results'=> $totalStudentsWithResults,
                    'total_attempts'             => $allAttempts->count(),
                    'published_results'          => $publishedAttempts,
                    'pending_results'            => $pendingAttempts,
                    'average_score'              => $avgScore,
                    'pass_rate'                  => $passRate,
                    'passed_count'               => $passedAttempts,
                    'failed_count'               => $failedAttempts,
                    'courses_evaluated'          => count($coursePerformance),
                ],
                'grade_distribution'            => $gradeDistribution,
                'course_performance'            => $coursePerformance,
                'semester_submissions_summary'  => [
                    'stats'   => $subStats,
                    'recent'  => $recentSubmissions,
                ],
                'results'                       => $paginatedExams,
                'all_exams_count'               => $totalExamsCount,
                'student_results'               => $paginatedAttempts,
                'all_students_count'            => $totalAttemptsCount,
                'pagination' => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total'        => $viewMode === 'students' ? $totalAttemptsCount : $totalExamsCount,
                    'last_page'    => max(1, (int)ceil(($viewMode === 'students' ? $totalAttemptsCount : $totalExamsCount) / $perPage)),
                ],
                'filter_options' => [
                    'courses' => $courses->map(fn($c) => ['code' => $c->code, 'title' => $c->title])->values(),
                    'instructors' => $deptInstructors->map(fn($i) => ['id' => $i->id, 'name' => $i->name, 'email' => $i->email])->values(),
                    'semesters' => ['First Semester', 'Second Semester'],
                    'academic_years' => [$settingsYear, '2027', '2026', '2025'],
                    'grades' => ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'D', 'F'],
                    'statuses' => ['published', 'graded', 'submitted', 'pending', 'in_progress'],
                ],
            ]
        ]);
    }

    /**
     * Show all student results for a specific examination.
     */
    public function showExamResults(Request $request, $examId): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();

        // Validate department ownership of this exam
        $exam = $this->buildDeptExamsQuery($deptId, $user->id)
            ->with(['instructor', 'course', 'questions'])
            ->findOrFail($examId);

        $attempts = ExamAttempt::where('exam_id', $examId)->with(['student', 'exam'])->get();

        // Get students relevant to department or exam
        $students = User::where('role', 'student')
            ->when($deptId, fn($q, $d) => $q->where('department_id', $d))
            ->get();

        if ($students->isEmpty()) {
            $students = User::where('role', 'student')->get();
        }

        $studentResults = $attempts->map(function ($attempt) use ($exam) {
            $stu = $attempt->student ?: User::find($attempt->user_id);
            $pct = $attempt->percentage !== null ? (float)$attempt->percentage : null;
            $grade = $attempt->grade ?: ($pct !== null ? $this->calculateGrade($pct) : 'Pending');

            return [
                'id'           => $stu?->id ?? $attempt->user_id,
                'attempt_id'   => $attempt->id,
                'name'         => $stu?->name ?? 'Student',
                'student_id'   => $stu?->student_id ?? ('UGR/' . str_pad((string)$attempt->user_id, 5, '0', STR_PAD_LEFT) . '/16'),
                'email'        => $stu?->email ?? '',
                'year_level'   => $stu?->year_level ?? '1st Year',
                'section'      => $stu?->section ?? 'A',
                'score'        => $attempt->score,
                'total_marks'  => $attempt->total_marks ?? ($exam->total_marks ?? 100),
                'percentage'   => $pct,
                'grade'        => $grade,
                'status'       => ucfirst($attempt->status ?? 'submitted'),
                'submitted_at' => $attempt->submitted_at ? $attempt->submitted_at->format('M d, Y h:i A') : 'N/A',
            ];
        });

        $passCount = $attempts->filter(fn($a) => (float)($a->percentage ?? 0) >= 50)->count();
        $failCount = $attempts->filter(fn($a) => (float)($a->percentage ?? 0) < 50 && $a->percentage !== null)->count();
        $avgScore = $attempts->count() > 0 ? round($attempts->avg('percentage'), 1) : 0.0;

        return response()->json([
            'data' => [
                'exam' => [
                    'id'              => $exam->id,
                    'title'           => $exam->title,
                    'code'            => $exam->settings['exam_code'] ?? ('EXM-' . ($exam->scheduled_at ? $exam->scheduled_at->format('Y') : date('Y')) . '-' . str_pad((string)$exam->id, 3, '0', STR_PAD_LEFT)),
                    'course_name'     => $exam->course_name ?: ($exam->course->title ?? 'General Course'),
                    'course_code'     => $exam->course_code ?: ($exam->course->code ?? 'N/A'),
                    'instructor_name' => $exam->instructor->name ?? 'Faculty Member',
                    'instructor_email'=> $exam->instructor->email ?? '',
                    'total_marks'     => $exam->total_marks,
                    'duration'        => $exam->duration_minutes . ' min',
                    'status'          => ucfirst($exam->status ?? 'Draft'),
                    'scheduled_at'    => $exam->scheduled_at ? $exam->scheduled_at->format('M d, Y') : 'TBD',
                    'semester'        => $exam->settings['semester'] ?? ($exam->course->semester ?? 'Semester 1'),
                ],
                'stats' => [
                    'total_attempts'  => $attempts->count(),
                    'passed_count'   => $passCount,
                    'failed_count'   => $failCount,
                    'pass_rate'      => $attempts->count() > 0 ? round(($passCount / $attempts->count()) * 100, 1) : 0.0,
                    'average_score'  => $avgScore,
                ],
                'students' => $studentResults,
            ]
        ]);
    }

    /**
     * Show detailed examination attempt breakdown for an individual student.
     */
    public function showAttemptDetails(Request $request, $attemptId): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();

        $attempt = ExamAttempt::with(['exam.course', 'exam.instructor', 'exam.questions', 'student'])
            ->findOrFail($attemptId);

        $exam = $attempt->exam;

        // Security check: Verify that this exam belongs to the Department Head's department
        $isAuthorized = $this->buildDeptExamsQuery($deptId, $user->id)
            ->where('id', $exam->id)
            ->exists();

        if (!$isAuthorized) {
            return response()->json(['message' => 'Unauthorized: Result attempt belongs to another department.'], 403);
        }

        $student = $attempt->student;
        $rawQuestions = $exam->questions ?? collect();
        $answers = $attempt->answers ?? [];
        if (is_string($answers)) {
            $answers = json_decode($answers, true) ?? [];
        }

        // Parse questions and compare student answers with correct answers
        $questionBreakdown = $rawQuestions->map(function ($q, $idx) use ($answers) {
            $qId = (string)$q->id;
            $studentAns = $answers[$qId] ?? null;
            $correct = $q->correct_answer;
            $isCorrect = false;

            if ($studentAns !== null && $correct !== null) {
                $isCorrect = strtolower(trim((string)$studentAns)) === strtolower(trim((string)$correct));
            }

            return [
                'id'             => $q->id,
                'number'         => $idx + 1,
                'type'           => $q->type,
                'instruction'    => $q->instruction,
                'text'           => strip_tags($q->text ?? ''),
                'options'        => $q->options,
                'student_answer' => $studentAns,
                'correct_answer' => $correct,
                'is_correct'     => $isCorrect,
                'awarded_marks'  => $isCorrect ? $q->marks : 0,
                'max_marks'      => $q->marks,
            ];
        });

        $pct = $attempt->percentage !== null ? (float)$attempt->percentage : 0.0;
        $grade = $attempt->grade ?: $this->calculateGrade($pct);
        $gradePoint = $this->calculateGradePoint($pct);

        return response()->json([
            'data' => [
                'attempt' => [
                    'id'           => $attempt->id,
                    'score'        => $attempt->score,
                    'total_marks'  => $attempt->total_marks ?? $exam->total_marks,
                    'percentage'   => $pct,
                    'grade'        => $grade,
                    'grade_point'  => $gradePoint,
                    'status'       => ucfirst($attempt->status ?? 'submitted'),
                    'started_at'   => $attempt->started_at ? $attempt->started_at->format('M d, Y h:i A') : 'N/A',
                    'submitted_at' => $attempt->submitted_at ? $attempt->submitted_at->format('M d, Y h:i A') : 'N/A',
                ],
                'student' => [
                    'id'         => $student?->id,
                    'name'       => $student?->name ?? 'Student',
                    'student_id' => $student?->student_id ?: ('UGR/' . str_pad((string)$attempt->user_id, 5, '0', STR_PAD_LEFT) . '/16'),
                    'email'      => $student?->email ?? '',
                    'year_level' => $student?->year_level ?? '1st Year',
                    'section'    => $student?->section ?? 'A',
                    'department' => $student?->department?->name ?? 'Computer Science',
                ],
                'exam' => [
                    'id'              => $exam->id,
                    'title'           => $exam->title,
                    'code'            => $exam->settings['exam_code'] ?? ('EXM-' . ($exam->scheduled_at ? $exam->scheduled_at->format('Y') : date('Y')) . '-' . str_pad((string)$exam->id, 3, '0', STR_PAD_LEFT)),
                    'course_name'     => $exam->course_name ?: ($exam->course->title ?? 'General Course'),
                    'course_code'     => $exam->course_code ?: ($exam->course->code ?? 'N/A'),
                    'instructor_name' => $exam->instructor->name ?? 'Faculty Member',
                    'instructor_email'=> $exam->instructor->email ?? '',
                    'total_marks'     => $exam->total_marks,
                    'duration'        => $exam->duration_minutes . ' min',
                    'semester'        => $exam->settings['semester'] ?? ($exam->course->semester ?? 'Semester 1'),
                ],
                'questions' => $questionBreakdown,
            ]
        ]);
    }

    /**
     * Publish examination results across the department.
     */
    public function publishExamResults(Request $request, $examId): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();

        // Validate department ownership
        $exam = $this->buildDeptExamsQuery($deptId, $user->id)->findOrFail($examId);

        // Update all attempts
        $updatedAttemptsCount = ExamAttempt::where('exam_id', $exam->id)
            ->whereIn('status', ['submitted', 'graded', 'completed'])
            ->update(['status' => 'published']);

        // Update exam status
        $exam->update([
            'status'       => 'published',
            'published_at' => now(),
        ]);

        ActivityLog::create([
            'department_id' => $deptId,
            'user_id'       => $user->id,
            'actor_role'    => 'dept_head',
            'action'        => "Published results for examination [{$exam->title}] ({$exam->course_code})",
            'type'          => 'exam_result',
            'module'        => 'Results',
            'details'       => "Department Head published results for {$updatedAttemptsCount} student submissions.",
            'log_status'    => 'success',
        ]);

        return response()->json([
            'message'               => "Results for examination '{$exam->title}' published successfully.",
            'published_attempts'   => $updatedAttemptsCount,
        ]);
    }

    /**
     * Institutional Multi-Format Export (Excel, PDF, CSV)
     */
    public function export(Request $request)
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();
        $dept = Department::find($deptId);
        $deptName = $dept ? $dept->name : 'Computer Science';

        $format = strtolower($request->get('format', 'excel'));

        $exams = $this->buildDeptExamsQuery($deptId, $user->id)
            ->with(['instructor', 'course', 'attempts.student'])
            ->get();

        $allAttempts = ExamAttempt::whereIn('exam_id', $exams->pluck('id'))
            ->with(['exam.course', 'student'])
            ->get();

        $rows = $allAttempts->map(function ($att) {
            $student = $att->student;
            $exam = $att->exam;
            $courseCode = $exam?->course_code ?: ($exam?->course?->code ?? 'N/A');
            $courseName = $exam?->course_name ?: ($exam?->course?->title ?? 'General Course');
            $examCode = $exam?->settings['exam_code'] ?? ('EXM-' . ($exam?->scheduled_at ? $exam->scheduled_at->format('Y') : date('Y')) . '-' . str_pad((string)($exam?->id ?? 0), 3, '0', STR_PAD_LEFT));
            $pct = $att->percentage !== null ? (float)$att->percentage : null;
            $grade = $att->grade ?: ($pct !== null ? $this->calculateGrade($pct) : 'Pending');

            return [
                'student_name'   => $student?->name ?? 'Student',
                'student_reg_no' => $student?->student_id ?: ('UGR/' . str_pad((string)$att->user_id, 5, '0', STR_PAD_LEFT) . '/16'),
                'student_email'  => $student?->email ?? '',
                'course_code'    => $courseCode,
                'course_name'    => $courseName,
                'exam_code'      => $examCode,
                'exam_title'     => $exam?->title ?? 'Exam',
                'score'          => $att->score,
                'total_marks'    => $att->total_marks ?? ($exam?->total_marks ?? 100),
                'percentage'     => $pct,
                'grade'          => $grade,
                'status'         => ucfirst($att->status ?? 'submitted'),
                'semester'       => $exam?->settings['semester'] ?? ($exam?->course->semester ?? 'Semester 1'),
                'academic_year'  => $exam?->settings['academic_year'] ?? '2025/2026',
                'submitted_at'   => $att->submitted_at ? $att->submitted_at->format('M d, Y h:i A') : 'N/A',
            ];
        });

        $timestamp = Carbon::now()->format('Y-m-d_His');
        $safeDept = preg_replace('/[^A-Za-z0-9_\-]/', '_', strtolower($deptName));
        $baseFileName = "{$safeDept}_department_results_{$timestamp}";

        // 1. PDF Export (Dompdf with inline institutional layout)
        if ($format === 'pdf') {
            $formattedDate = Carbon::now()->format('F d, Y \a\t h:i A');
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
            $html .= '<title>Wollo University - Academic Results Report</title>';
            $html .= '<style>
                @page { margin: 20px 25px; }
                body { font-family: "DejaVu Sans", sans-serif; font-size: 8px; color: #1e293b; line-height: 1.3; }
                .header { text-align: center; border-bottom: 2px solid #5138ed; padding-bottom: 10px; margin-bottom: 12px; }
                .logo-text { font-size: 15px; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; }
                .sub-title { font-size: 10px; color: #5138ed; font-weight: bold; margin-top: 3px; }
                .meta { font-size: 8px; color: #64748b; margin-top: 5px; }
                .dept-banner { background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 12px; margin-bottom: 12px; border-radius: 4px; }
                .dept-banner table { width: 100%; border: none; }
                .dept-banner td { padding: 2px 4px; font-size: 8.5px; }
                table.data { width: 100%; border-collapse: collapse; margin-top: 5px; }
                table.data th { background: #5138ed; color: #ffffff; font-weight: bold; padding: 6px 4px; text-align: left; font-size: 7.5px; text-transform: uppercase; }
                table.data td { padding: 5px 4px; border-bottom: 1px solid #e2e8f0; font-size: 7.5px; }
                table.data tr:nth-child(even) { background-color: #f8fafc; }
                .grade-a { color: #059669; font-weight: bold; }
                .grade-b { color: #2563eb; font-weight: bold; }
                .grade-c { color: #d97706; font-weight: bold; }
                .grade-f { color: #dc2626; font-weight: bold; }
                .badge-published { background: #dcfce7; color: #166534; padding: 2px 4px; border-radius: 3px; font-weight: bold; }
                .badge-pending { background: #fef3c7; color: #92400e; padding: 2px 4px; border-radius: 3px; font-weight: bold; }
                .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 7.5px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 4px; }
            </style></head><body>';

            $html .= '<div class="header">';
            $html .= '<div class="logo-text">Wollo University</div>';
            $html .= '<div class="sub-title">DEPARTMENT RESULTS & ACADEMIC PERFORMANCE REPORT</div>';
            $html .= '<div class="meta">Official Examination Results &bull; Generated: ' . $formattedDate . ' &bull; Department Head: ' . htmlspecialchars($user->name) . '</div>';
            $html .= '</div>';

            $html .= '<div class="dept-banner"><table><tr>';
            $html .= '<td><strong>Department:</strong> ' . htmlspecialchars($deptName) . '</td>';
            $html .= '<td><strong>Total Submissions:</strong> ' . $rows->count() . '</td>';
            $html .= '<td><strong>Exported By:</strong> ' . htmlspecialchars($user->name) . '</td>';
            $html .= '</tr></table></div>';

            $html .= '<table class="data"><thead><tr>';
            $html .= '<th style="width: 20px;">#</th>';
            $html .= '<th>Student Name</th>';
            $html .= '<th style="width: 65px;">Student ID</th>';
            $html .= '<th style="width: 45px;">Course</th>';
            $html .= '<th>Exam Title</th>';
            $html .= '<th style="width: 35px; text-align: center;">Score</th>';
            $html .= '<th style="width: 30px; text-align: center;">%</th>';
            $html .= '<th style="width: 25px; text-align: center;">Grade</th>';
            $html .= '<th style="width: 50px; text-align: center;">Status</th>';
            $html .= '<th style="width: 65px;">Submitted</th>';
            $html .= '</tr></thead><tbody>';

            if ($rows->isEmpty()) {
                $html .= '<tr><td colspan="10" style="text-align: center; padding: 15px; color: #94a3b8;">No student examination results found.</td></tr>';
            } else {
                foreach ($rows as $i => $r) {
                    $gradeClass = 'grade-f';
                    if (str_starts_with($r['grade'], 'A')) $gradeClass = 'grade-a';
                    elseif (str_starts_with($r['grade'], 'B')) $gradeClass = 'grade-b';
                    elseif (str_starts_with($r['grade'], 'C')) $gradeClass = 'grade-c';

                    $statusBadge = '<span class="badge-pending">' . strtoupper($r['status']) . '</span>';
                    if (strtolower($r['status']) === 'published') {
                        $statusBadge = '<span class="badge-published">PUBLISHED</span>';
                    }

                    $html .= '<tr>';
                    $html .= '<td>' . ($i + 1) . '</td>';
                    $html .= '<td><strong>' . htmlspecialchars($r['student_name']) . '</strong></td>';
                    $html .= '<td>' . htmlspecialchars($r['student_reg_no']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($r['course_code']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($r['exam_title']) . '</td>';
                    $html .= '<td style="text-align: center;">' . ($r['score'] !== null ? $r['score'] . '/' . $r['total_marks'] : '—') . '</td>';
                    $html .= '<td style="text-align: center;">' . ($r['percentage'] !== null ? $r['percentage'] . '%' : '—') . '</td>';
                    $html .= '<td style="text-align: center;" class="' . $gradeClass . '">' . htmlspecialchars($r['grade']) . '</td>';
                    $html .= '<td style="text-align: center;">' . $statusBadge . '</td>';
                    $html .= '<td>' . htmlspecialchars($r['submitted_at']) . '</td>';
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

            \App\Helpers\LogActivity::record('Exported', 'Results', "Exported {$rows->count()} results records as PDF ({$deptName})");

            return response()->json([
                'filename'  => $baseFileName . '.pdf',
                'mime_type' => 'application/pdf',
                'file'      => base64_encode($dompdf->output()),
            ]);
        }

        // 2. CSV Export
        if ($format === 'csv') {
            $filename = "{$baseFileName}.csv";
            $headers = ['#', 'Student Name', 'Student ID', 'Student Email', 'Course Code', 'Course Title', 'Exam Code', 'Exam Title', 'Score', 'Total Marks', 'Percentage', 'Grade', 'Status', 'Semester', 'Academic Year', 'Submitted Date'];
            
            $handle = fopen('php://memory', 'r+');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $idx => $r) {
                fputcsv($handle, [
                    $idx + 1,
                    $r['student_name'],
                    $r['student_reg_no'],
                    $r['student_email'],
                    $r['course_code'],
                    $r['course_name'],
                    $r['exam_code'],
                    $r['exam_title'],
                    $r['score'] !== null ? $r['score'] : 'N/A',
                    $r['total_marks'],
                    $r['percentage'] !== null ? ($r['percentage'] . '%') : 'N/A',
                    $r['grade'],
                    $r['status'],
                    $r['semester'],
                    $r['academic_year'],
                    $r['submitted_at'],
                ]);
            }

            rewind($handle);
            $csvContent = stream_get_contents($handle);
            fclose($handle);

            \App\Helpers\LogActivity::record('Exported', 'Results', "Exported {$rows->count()} results records as CSV ({$deptName})");

            return response()->json([
                'filename'  => $filename,
                'mime_type' => 'text/csv',
                'file'      => base64_encode($csvContent),
            ]);
        }

        // 3. Excel Export (Default)
        $filename = "{$baseFileName}.xlsx";
        $excelBinary = Excel::raw(new DepartmentResultExport($rows), \Maatwebsite\Excel\Excel::XLSX);

        \App\Helpers\LogActivity::record('Exported', 'Results', "Exported {$rows->count()} results records as Excel ({$deptName})");

        return response()->json([
            'filename'  => $filename,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file'      => base64_encode($excelBinary),
        ]);
    }
}
