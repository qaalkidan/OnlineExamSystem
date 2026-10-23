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

        // If user is admin and explicitly passes department_id for preview
        if ($user->role === 'admin' && $request->filled('department_id')) {
            return (int)$request->department_id;
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
        if ($pct >= 65) return 'B-';
        if ($pct >= 60) return 'C+';
        if ($pct >= 50) return 'C';
        if ($pct >= 40) return 'D';
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
        if ($pct >= 65) return 2.75;
        if ($pct >= 60) return 2.5;
        if ($pct >= 50) return 2.0;
        if ($pct >= 40) return 1.0;
        return 0.0;
    }

    /**
     * Compute auto-grade score for a specific question given student answers.
     * Accurately supports Multiple Choice, True/False, and Matching questions.
     */
    private function computeQuestionAutoScore($q, $studentAns): float
    {
        if ($studentAns === null || $studentAns === '') {
            return 0.0;
        }

        $type = strtolower($q->type ?? '');

        if ($type === 'matching') {
            $options = $q->options ?? [];
            if (is_string($options)) $options = json_decode($options, true) ?? [];
            if (empty($options)) return 0.0;

            // 1. Parse correct_answers map
            $correctAnswers = null;
            if (!empty($q->question_data['correct_answers'])) {
                $correctAnswers = $q->question_data['correct_answers'];
            } elseif (!empty($q->correct_answer)) {
                $correctAnswers = [];
                foreach (explode(',', $q->correct_answer) as $part) {
                    $p = explode('-', $part, 2);
                    if (count($p) === 2) {
                        $correctAnswers[trim($p[0])] = strtoupper(trim($p[1]));
                    }
                }
            }

            // 2. Parse student selections
            $studentPairs = [];
            if (is_string($studentAns)) {
                $decoded = json_decode($studentAns, true);
                if (is_array($decoded)) {
                    $studentPairs = $decoded;
                } else {
                    foreach (explode(',', $studentAns) as $part) {
                        $pieces = explode(':', $part, 2);
                        if (count($pieces) === 2) {
                            $studentPairs[trim($pieces[0])] = strtoupper(trim($pieces[1]));
                        }
                    }
                }
            } elseif (is_array($studentAns)) {
                $studentPairs = $studentAns;
            }

            $colACounter = 0;
            $correctMatchCount = 0;
            foreach ($options as $i => $opt) {
                $leftText = strip_tags(trim($opt['left'] ?? ''));
                if ($leftText === '') continue;

                $colACounter++;
                $colAKey = (string)$colACounter;
                $expectedLetter = isset($correctAnswers[$colAKey])
                    ? strtoupper($correctAnswers[$colAKey])
                    : ($correctAnswers[(string)$i] ?? chr(65 + $i));

                $studentMatch = $studentPairs[(string)$i]
                    ?? ($studentPairs[$colAKey]
                    ?? ($studentPairs[chr(65 + $i)]
                    ?? ''));

                if ($studentMatch !== '' && strtoupper(trim((string)$studentMatch)) === strtoupper(trim((string)$expectedLetter))) {
                    $correctMatchCount++;
                }
            }

            if ($colACounter === 0) $colACounter = count($options);
            $marksPerItem = (float)($q->marks_per_item ?? 0);
            if ($marksPerItem <= 0 && $colACounter > 0) {
                $marksPerItem = round($q->marks / $colACounter, 2);
            }

            return (float)round($correctMatchCount * $marksPerItem, 2);
        }

        if ($type === 'true_false' || $type === 'true-false') {
            $cRaw = strtolower(trim((string)$q->correct_answer));
            $isTrue = in_array($cRaw, ['true', 'a', '1', 'yes', 'correct']);
            $sRaw = strtolower(trim((string)$studentAns));
            $sTrue = in_array($sRaw, ['true', 'a', '1', 'yes', 'correct']);
            $sFalse = in_array($sRaw, ['false', 'b', '0', 'no', 'incorrect']);
            if (($isTrue && $sTrue) || (!$isTrue && $sFalse)) {
                return (float)$q->marks;
            }
            return 0.0;
        }

        if ($type === 'multiple_choice') {
            $cRaw = strtolower(trim((string)$q->correct_answer));
            $sRaw = strtolower(trim((string)$studentAns));
            if ($cRaw === $sRaw) {
                return (float)$q->marks;
            }
            $options = $q->options ?? [];
            if (is_string($options)) $options = json_decode($options, true) ?? [];
            if (strlen($cRaw) === 1 && ctype_alpha($cRaw)) {
                $letterIdx = ord($cRaw) - 97;
                if (isset($options[$letterIdx])) {
                    $optText = strtolower(trim(strip_tags((string)$options[$letterIdx])));
                    if ($sRaw === $optText) {
                        return (float)$q->marks;
                    }
                }
            }
            return 0.0;
        }

        return 0.0;
    }

    /**
     * Main results endpoint:
     * Returns department academic performance, dynamic KPIs, exam performance list,
     * individual student results list (with 14 columns metadata), grade distribution,
     * course performance, and semester submission monitoring.
     */
    public function index(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        if (!$deptId) {
            return response()->json([
                'message' => 'Unauthorized: Authenticated user is not assigned to manage any academic department.'
            ], 403);
        }

        $user = $request->user();
        $dept = Department::find($deptId);
        $deptName = $dept ? $dept->name : 'Department';
        $deptCode = $dept ? ($dept->code ?: 'DEPT') : 'DEPT';

        // Fetch real Super Admin assigned system settings
        $settingsYear = SystemSetting::where('key', 'academicYear')->value('value') ?? '2026';
        $assignedSemester = SystemSetting::where('key', 'semester')->value('value') ?? 'Second Semester';

        // Department courses & students
        $courses = Course::where('department_id', $deptId)->get();
        $deptCourseCodes = $courses->pluck('code')->filter()->unique()->toArray();

        $deptStudents = User::where('role', 'student')->where('department_id', $deptId)->get();
        $totalStudentsCount = $deptStudents->count();

        // Department instructors
        $deptInstructors = User::where('department_id', $deptId)
            ->whereIn('role', ['instructor', 'dept_head'])
            ->get(['id', 'name', 'email']);
        $deptInstructorIds = $deptInstructors->pluck('id');

        // Base department exams query (strictly scoped to this department)
        $allDeptExams = $this->buildDeptExamsQuery($deptId, $user->id)
            ->with(['instructor', 'course', 'questions'])
            ->withCount(['questions', 'attempts'])
            ->latest('id')
            ->get();

        $allDeptExamIds = $allDeptExams->pluck('id');

        // STRICT Department Attempts Query:
        // Excludes all attempts from students of unrelated departments
        $allAttempts = ExamAttempt::whereIn('exam_id', $allDeptExamIds)
            ->where(function ($q) use ($deptId) {
                $q->whereHas('student', function ($s) use ($deptId) {
                    $s->where('department_id', $deptId)->orWhereNull('department_id');
                });
            })
            ->with(['exam.course', 'exam.instructor', 'student.department'])
            ->latest('id')
            ->get();

        // Map all attempts into rich student result objects
        $allStudentRows = $allAttempts->map(function ($att) use ($settingsYear, $assignedSemester) {
            $student = $att->student;
            $exam = $att->exam;

            $courseCode = $exam?->course_code ?: ($exam?->course?->code ?? 'N/A');
            $courseName = $exam?->course_name ?: ($exam?->course?->title ?? 'General Course');
            $examCode = $exam?->settings['exam_code'] ?? ('EXM-' . ($exam?->scheduled_at ? $exam->scheduled_at->format('Y') : date('Y')) . '-' . str_pad((string)($exam?->id ?? 0), 3, '0', STR_PAD_LEFT));

            // Institutional Student ID
            $officialStudentId = $student?->id_no ?: ($student?->student_id ?: ('WU/' . str_pad((string)$att->user_id, 5, '0', STR_PAD_LEFT) . '/16'));

            // Section resolution
            $sec = $student?->section ?: ($exam?->section ?: ($exam?->settings['section'] ?? 'Section A'));
            if (strtolower($sec) === 'both sections' || strtolower($sec) === 'all') {
                $sec = $student?->section ?: 'Section A';
            }

            // Student Year Level (1st Year, 2nd Year, 3rd Year, 4th Year, 5th Year)
            $yearLevel = $student?->year_level ?: ($exam?->settings['year_level'] ?? '1st Year');

            // Academic Year resolution
            $academicYear = $exam?->settings['academic_year'] ?? ($student?->academic_year ?? $settingsYear);

            // Semester resolution: integrates with Super Admin assigned semester
            $semester = $exam?->settings['semester'] ?? ($exam?->course?->semester ?? ($student?->semester ?? $assignedSemester));

            // Score and grading calculation with instructor manual score support
            $totalMarks = (float)($att->total_marks ?? ($exam?->total_marks ?? 100));
            $rawStatus = strtolower($att->status ?? 'submitted');
            $score = $att->score !== null ? (float)$att->score : null;
            $pct = $att->percentage !== null ? (float)$att->percentage : null;

            $ans = $att->answers ?? [];
            if (is_string($ans)) $ans = json_decode($ans, true) ?? [];
            $manualScores = $ans['_manual_scores'] ?? ($ans['manual_scores'] ?? []);

            if (!empty($manualScores) && ($score === null || $pct === null)) {
                $manualTotal = (float)array_sum($manualScores);
                $score = $score !== null ? max($score, $manualTotal) : $manualTotal;
                $pct = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 1) : 0.0;
            } elseif ($score !== null && $pct === null) {
                $pct = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 1) : 0.0;
            }

            $isGraded = ($score !== null) && in_array($rawStatus, ['graded', 'published', 'completed', 'submitted']);
            $grade = $isGraded ? ($att->grade ?: $this->calculateGrade($pct ?? 0.0)) : 'Pending';
            $gradePoint = $isGraded ? $this->calculateGradePoint($pct ?? 0.0) : 0.0;

            // Result Display Status
            $displayStatus = 'Pending';
            if ($rawStatus === 'published') {
                $displayStatus = 'Published';
            } elseif ($rawStatus === 'graded' || ($isGraded && $rawStatus !== 'published')) {
                $displayStatus = 'Graded (Unpublished)';
            } elseif ($rawStatus === 'in_progress') {
                $displayStatus = 'In Progress';
            } elseif ($rawStatus === 'submitted') {
                $displayStatus = $isGraded ? 'Graded (Unpublished)' : 'Pending';
            }

            return [
                'id'              => $att->id,
                'attempt_id'      => $att->id,
                'student_id'      => $officialStudentId, // Column 2
                'student_db_id'   => $att->user_id,
                'student_name'    => $student?->name ?? 'Student', // Column 3
                'student_reg_no'  => $officialStudentId,
                'student_email'   => $student?->email ?? '',
                'student_year'    => $yearLevel,
                'year_level'      => $yearLevel, // Column 7: Year Level
                'course_code'     => $courseCode, // Column 4
                'course_name'     => $courseName, // Column 5
                'section'         => $sec, // Column 6
                'academic_year'   => $academicYear,
                'semester'        => $semester, // Column 8
                'exam_title'      => $exam?->title ?? 'Examination', // Column 9
                'exam_code'       => $examCode,
                'exam_id'         => $exam?->id,
                'total_marks'     => $totalMarks, // Column 10
                'obtained_marks'  => $score, // Column 11: score when graded, null if pending
                'score'           => $score,
                'percentage'      => $pct,
                'grade'           => $grade, // Column 12: letter grade
                'grade_point'     => $gradePoint,
                'status'          => $displayStatus, // Column 13: status badge
                'raw_status'      => $rawStatus,
                'is_graded'       => $isGraded,
                'is_published'    => $rawStatus === 'published',
                'instructor_name' => $exam?->instructor?->name ?? 'Faculty Member',
                'instructor_id'   => $exam?->user_id,
                'submitted_at'    => $att->submitted_at ? $att->submitted_at->format('M d, Y h:i A') : ($att->created_at ? $att->created_at->format('M d, Y') : 'N/A'),
            ];
        });

        // ── Apply Comprehensive Filters to Student Results ──
        $filteredStudentResults = $allStudentRows->filter(function ($row) use ($request) {
            // General query search
            $search = strtolower(trim((string)$request->get('search', '')));
            if ($search !== '') {
                $fields = [
                    $row['student_name'],
                    $row['student_id'],
                    $row['student_email'],
                    $row['course_code'],
                    $row['course_name'],
                    $row['exam_title'],
                    $row['exam_code'],
                    $row['section'],
                    $row['year_level'],
                ];
                $matched = false;
                foreach ($fields as $f) {
                    if (str_contains(strtolower((string)$f), $search)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) return false;
            }

            // Student ID filter
            if ($request->filled('student_id') && strtolower($request->student_id) !== 'all') {
                $reqId = strtolower(trim((string)$request->student_id));
                if (!str_contains(strtolower($row['student_id']), $reqId) && (string)$row['student_db_id'] !== $reqId) {
                    return false;
                }
            }

            // Student Name filter
            if ($request->filled('student_name') && strtolower($request->student_name) !== 'all') {
                $reqName = strtolower(trim((string)$request->student_name));
                if (!str_contains(strtolower($row['student_name']), $reqName)) {
                    return false;
                }
            }

            // Year Level filter (1st Year, 2nd Year, 3rd Year, 4th Year, 5th Year)
            $reqYearLevel = $request->get('year_level') ?? $request->get('academic_year');
            if ($reqYearLevel && strtolower($reqYearLevel) !== 'all') {
                $reqNorm = strtolower(trim((string)$reqYearLevel));
                $rowNorm = strtolower(trim((string)$row['year_level']));
                if ($rowNorm !== $reqNorm && !str_contains($rowNorm, $reqNorm)) {
                    return false;
                }
            }

            // Semester filter (integrated with Super Admin assigned semester)
            if ($request->filled('semester') && strtolower($request->semester) !== 'all') {
                if (strtolower($row['semester']) !== strtolower(trim((string)$request->semester))) {
                    return false;
                }
            }

            // Section filter
            if ($request->filled('section') && strtolower($request->section) !== 'all') {
                $reqSec = strtolower(trim((string)$request->section));
                $rowSec = strtolower(trim((string)$row['section']));
                $normReq = str_replace('section ', '', $reqSec);
                $normRow = str_replace('section ', '', $rowSec);
                if ($normReq !== $normRow && $rowSec !== $reqSec) {
                    return false;
                }
            }

            // Course filter
            if ($request->filled('course_code') && strtolower($request->course_code) !== 'all') {
                if (strtolower($row['course_code']) !== strtolower(trim((string)$request->course_code))) {
                    return false;
                }
            }

            // Result Status filter
            if ($request->filled('status') && strtolower($request->status) !== 'all') {
                $reqStatus = strtolower(trim((string)$request->status));
                $rowStatus = strtolower($row['status']);
                $rawStatus = strtolower($row['raw_status']);

                if ($reqStatus === 'published') {
                    if ($rowStatus !== 'published') return false;
                } elseif ($reqStatus === 'graded') {
                    if (!str_contains($rowStatus, 'graded') && $row['is_graded'] !== true) return false;
                } elseif ($reqStatus === 'pending') {
                    if ($rowStatus !== 'pending' && $rowStatus !== 'submitted' && $row['is_graded'] === true && $rowStatus === 'published') return false;
                } elseif ($reqStatus === 'in_progress') {
                    if ($rawStatus !== 'in_progress' && $rowStatus !== 'in progress') return false;
                } else {
                    if (!str_contains($rowStatus, $reqStatus) && !str_contains($rawStatus, $reqStatus)) return false;
                }
            }

            // Grade filter
            if ($request->filled('grade') && strtolower($request->grade) !== 'all') {
                if (strtoupper($row['grade']) !== strtoupper(trim((string)$request->grade))) {
                    return false;
                }
            }

            // Exam ID filter
            if ($request->filled('exam_id') && strtolower($request->exam_id) !== 'all') {
                if ((string)$row['exam_id'] !== (string)$request->exam_id) {
                    return false;
                }
            }

            return true;
        })->values();

        // ── Real Dynamic KPIs Reflecting Active Filter Scope ──
        $scopedEvaluatedStudents = $filteredStudentResults->pluck('student_db_id')->unique()->count();
        $scopedTotalSubmissions = $filteredStudentResults->count();
        $scopedPublishedResults = $filteredStudentResults->where('status', 'Published')->count();
        $scopedPendingGrading = $filteredStudentResults->where('status', '!=', 'Published')->count();

        $scopedGraded = $filteredStudentResults->filter(fn($r) => $r['is_graded'] && $r['percentage'] !== null);
        $scopedAvgScore = $scopedGraded->count() > 0 ? round($scopedGraded->avg('percentage'), 1) : 0.0;
        $scopedPassed = $scopedGraded->filter(fn($r) => (float)$r['percentage'] >= 50)->count();
        $scopedFailed = $scopedGraded->filter(fn($r) => (float)$r['percentage'] < 50)->count();
        $scopedPassRate = $scopedGraded->count() > 0 ? round(($scopedPassed / $scopedGraded->count()) * 100, 1) : 0.0;

        // Dynamic Grade Distribution reflecting active filter scope
        $scopedGradeDistribution = [
            'A+' => 0, 'A' => 0, 'A-' => 0,
            'B+' => 0, 'B' => 0, 'B-' => 0,
            'C+' => 0, 'C' => 0,
            'D'  => 0, 'F' => 0,
        ];
        foreach ($filteredStudentResults as $r) {
            $g = trim(strtoupper($r['grade']));
            if (isset($scopedGradeDistribution[$g])) {
                $scopedGradeDistribution[$g]++;
            }
        }

        // Dynamic Course Performance breakdown reflecting active filter scope
        $scopedCoursePerformance = [];
        $byCourse = $filteredStudentResults->groupBy('course_code');
        foreach ($byCourse as $cCode => $items) {
            $first = $items->first();
            $cGraded = $items->filter(fn($r) => $r['is_graded'] && $r['percentage'] !== null);
            $cAvg = $cGraded->count() > 0 ? round($cGraded->avg('percentage'), 1) : 0.0;
            $cPassCount = $cGraded->filter(fn($r) => (float)$r['percentage'] >= 50)->count();
            $cPass = $cGraded->count() > 0 ? round(($cPassCount / $cGraded->count()) * 100, 1) : 0.0;
            $cMax = $cGraded->count() > 0 ? $cGraded->max('score') : 0;
            $cMin = $cGraded->count() > 0 ? $cGraded->min('score') : 0;
            $pendingInCourse = $items->where('status', '!=', 'Published')->count();

            $scopedCoursePerformance[] = [
                'course_code'    => $cCode,
                'course_name'    => $first['course_name'] ?? $cCode,
                'credits'        => 3,
                'total_attempts' => $items->count(),
                'average_score'  => $cAvg,
                'pass_rate'      => $cPass,
                'highest_score'  => $cMax,
                'lowest_score'   => $cMin,
                'pending_count'  => $pendingInCourse,
                'status'         => $pendingInCourse === 0 && $items->count() > 0 ? 'Published' : ($items->count() > 0 ? 'In Review' : 'No Submissions'),
            ];
        }

        // If no filter matched courses but department has courses, list them with zero counts
        if (empty($scopedCoursePerformance) && $courses->isNotEmpty() && !$request->filled('course_code')) {
            foreach ($courses as $c) {
                $scopedCoursePerformance[] = [
                    'course_code'    => $c->code,
                    'course_name'    => $c->title,
                    'credits'        => $c->credits ?? 3,
                    'total_attempts' => 0,
                    'average_score'  => 0.0,
                    'pass_rate'      => 0.0,
                    'highest_score'  => 0,
                    'lowest_score'   => 0,
                    'pending_count'  => 0,
                    'status'         => 'No Submissions',
                ];
            }
        }

        // ── Real Semester Submissions Summary ──
        $deptSubmissions = SemesterSubmission::whereIn('instructor_id', $deptInstructorIds)->with('instructor')->get();
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

        // ── Filtered Exams (Exam-Level View) ──
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

        // ── Real Distinct Filter Options from Department Database ──
        $studentSections = User::where('role', 'student')->where('department_id', $deptId)->whereNotNull('section')->pluck('section');
        $examSections = $allDeptExams->map(fn($e) => $e->section ?: ($e->settings['section'] ?? null))->filter();
        $courseSections = $courses->pluck('section')->filter();
        $distinctSections = $studentSections->merge($examSections)->merge($courseSections)
            ->map(fn($s) => trim((string)$s))
            ->filter(fn($s) => $s !== '' && strtolower($s) !== 'both sections' && strtolower($s) !== 'all')
            ->unique()->sort()->values()->toArray();
        if (empty($distinctSections)) {
            $distinctSections = ['Section A', 'Section B'];
        }

        // ── Server-Side Pagination ──
        $page = max(1, (int)$request->get('page', 1));
        $perPage = max(5, min(100, (int)$request->get('per_page', 10)));
        $viewMode = $request->get('view_mode', 'students'); // 'students' is default primary

        $totalAttemptsCount = $filteredStudentResults->count();
        $totalExamsCount = $resultsExams->count();

        $paginatedStudentSlice = $filteredStudentResults->slice(($page - 1) * $perPage, $perPage)->values();
        // Number sequentially based on page
        $paginatedStudents = $paginatedStudentSlice->map(function ($row, $i) use ($page, $perPage) {
            $row['no'] = ($page - 1) * $perPage + $i + 1;
            return $row;
        });

        $paginatedExams = $resultsExams->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => [
                'department' => [
                    'name'            => $deptName,
                    'code'            => $deptCode,
                    'head_name'       => $user->name,
                    'academic_year'   => $settingsYear,
                    'semester'        => $assignedSemester,
                    'total_courses'   => count($deptCourseCodes),
                    'total_students'  => $totalStudentsCount,
                ],
                'stats' => [
                    'total_exams'                => $allDeptExams->count(),
                    'completed_exams'            => $allDeptExams->filter(fn($e) => in_array(strtolower($e->status ?? ''), ['completed', 'published']))->count(),
                    'total_students_with_results'=> $scopedEvaluatedStudents,
                    'total_attempts'             => $scopedTotalSubmissions,
                    'published_results'          => $scopedPublishedResults,
                    'pending_results'            => $scopedPendingGrading,
                    'average_score'              => $scopedAvgScore,
                    'pass_rate'                  => $scopedPassRate,
                    'passed_count'               => $scopedPassed,
                    'failed_count'               => $scopedFailed,
                    'courses_evaluated'          => count($scopedCoursePerformance),
                ],
                'grade_distribution'            => $scopedGradeDistribution,
                'course_performance'            => $scopedCoursePerformance,
                'semester_submissions_summary'  => [
                    'stats'   => $subStats,
                    'recent'  => $recentSubmissions,
                ],
                'student_results'               => $paginatedStudents,
                'all_students_count'            => $totalAttemptsCount,
                'results'                       => $paginatedExams,
                'all_exams_count'               => $totalExamsCount,
                'pagination' => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total'        => $viewMode === 'exams' ? $totalExamsCount : $totalAttemptsCount,
                    'last_page'    => max(1, (int)ceil(($viewMode === 'exams' ? $totalExamsCount : $totalAttemptsCount) / $perPage)),
                ],
                'filter_options' => [
                    'courses'           => $courses->map(fn($c) => ['code' => $c->code, 'title' => $c->title])->values(),
                    'sections'          => $distinctSections,
                    'year_levels'       => ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'],
                    'assigned_semester' => $assignedSemester,
                    'semesters'         => [$assignedSemester],
                    'grades'            => ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'D', 'F'],
                    'statuses'          => ['published', 'graded', 'submitted', 'pending', 'in_progress'],
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

        $attempts = ExamAttempt::where('exam_id', $examId)
            ->where(function ($q) use ($deptId) {
                $q->whereHas('student', function ($s) use ($deptId) {
                    $s->where('department_id', $deptId)->orWhereNull('department_id');
                });
            })
            ->with(['student', 'exam'])
            ->get();

        $studentResults = $attempts->map(function ($attempt, $idx) use ($exam) {
            $stu = $attempt->student ?: User::find($attempt->user_id);
            $totalMarks = (float)($attempt->total_marks ?? ($exam->total_marks ?? 100));
            $rawStatus = strtolower($attempt->status ?? 'submitted');
            $score = $attempt->score !== null ? (float)$attempt->score : null;
            $pct = $attempt->percentage !== null ? (float)$attempt->percentage : null;

            $ans = $attempt->answers ?? [];
            if (is_string($ans)) $ans = json_decode($ans, true) ?? [];
            $manualScores = $ans['_manual_scores'] ?? ($ans['manual_scores'] ?? []);

            if (!empty($manualScores) && ($score === null || $pct === null)) {
                $manualTotal = (float)array_sum($manualScores);
                $score = $score !== null ? max($score, $manualTotal) : $manualTotal;
                $pct = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 1) : 0.0;
            } elseif ($score !== null && $pct === null) {
                $pct = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 1) : 0.0;
            }

            $isGraded = ($score !== null) && in_array($rawStatus, ['graded', 'published', 'completed', 'submitted']);
            $grade = $isGraded ? ($attempt->grade ?: $this->calculateGrade($pct ?? 0.0)) : 'Pending';

            $displayStatus = 'Pending';
            if ($rawStatus === 'published') {
                $displayStatus = 'Published';
            } elseif ($rawStatus === 'graded' || ($isGraded && $rawStatus !== 'published')) {
                $displayStatus = 'Graded (Unpublished)';
            } elseif ($rawStatus === 'in_progress') {
                $displayStatus = 'In Progress';
            }

            return [
                'no'           => $idx + 1,
                'id'           => $stu?->id ?? $attempt->user_id,
                'attempt_id'   => $attempt->id,
                'name'         => $stu?->name ?? 'Student',
                'student_id'   => $stu?->id_no ?: ($stu?->student_id ?: ('WU/' . str_pad((string)$attempt->user_id, 5, '0', STR_PAD_LEFT) . '/16')),
                'email'        => $stu?->email ?? '',
                'year_level'   => $stu?->year_level ?? '1st Year',
                'section'      => $stu?->section ?? 'Section A',
                'score'        => $isGraded ? $attempt->score : null,
                'total_marks'  => $attempt->total_marks ?? ($exam->total_marks ?? 100),
                'percentage'   => $pct,
                'grade'        => $grade,
                'status'       => $displayStatus,
                'is_published' => $rawStatus === 'published',
                'submitted_at' => $attempt->submitted_at ? $attempt->submitted_at->format('M d, Y h:i A') : 'N/A',
            ];
        });

        $graded = $attempts->filter(fn($a) => $a->percentage !== null);
        $passCount = $graded->filter(fn($a) => (float)($a->percentage ?? 0) >= 50)->count();
        $failCount = $graded->filter(fn($a) => (float)($a->percentage ?? 0) < 50)->count();
        $avgScore = $graded->count() > 0 ? round($graded->avg('percentage'), 1) : 0.0;

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
                    'passed_count'    => $passCount,
                    'failed_count'    => $failCount,
                    'pass_rate'       => $attempts->count() > 0 ? round(($passCount / $attempts->count()) * 100, 1) : 0.0,
                    'average_score'   => $avgScore,
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

        $attempt = ExamAttempt::with(['exam.course', 'exam.instructor', 'exam.questions', 'student.department'])
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
        if ($student && $student->department_id && $deptId && $student->department_id != $deptId) {
            return response()->json(['message' => 'Unauthorized: Student belongs to another department.'], 403);
        }

        $rawQuestions = $exam->questions ?? collect();
        $answers = $attempt->answers ?? [];
        if (is_string($answers)) {
            $answers = json_decode($answers, true) ?? [];
        }

        // Real manual scores assigned by instructor: stored in _manual_scores or manual_scores
        $manualScores = $answers['_manual_scores'] ?? ($answers['manual_scores'] ?? []);

        // Parse questions, accurately factoring in instructor manual scores and question types
        $questionBreakdown = $rawQuestions->map(function ($q, $idx) use ($answers, $manualScores) {
            $qId = (string)$q->id;
            $studentAns = $answers[$qId] ?? ($answers[$q->id] ?? null);
            $correct = $q->correct_answer ?: ($q->sample_answer ?? '');
            $maxMarks = (float)($q->marks ?? 0);
            $type = strtolower($q->type ?? 'multiple_choice');

            // Check if instructor assigned a manual score for this question (fill_blank, short_answer, etc.)
            $hasManualScore = isset($manualScores[$qId]) || isset($manualScores[$q->id]);
            $manualScoreVal = $hasManualScore ? (float)($manualScores[$qId] ?? $manualScores[$q->id]) : null;

            $awardedMarks = 0.0;
            $isManualGraded = false;

            if ($hasManualScore) {
                // Directly award the instructor-assigned manual score
                $awardedMarks = (float)$manualScoreVal;
                $isManualGraded = true;
            } elseif (in_array($type, ['multiple_choice', 'true_false', 'true-false', 'matching'])) {
                // Auto-grade objective questions accurately
                $awardedMarks = $this->computeQuestionAutoScore($q, $studentAns);
            } elseif (in_array($type, ['fill_blank', 'fill_in_blank', 'fill_in_the_blank', 'blank_space'])) {
                // Exact string match check for fill in blank if not manually overridden
                if ($studentAns !== null && !empty($correct)) {
                    $cNorm = strtolower(trim(strip_tags((string)$correct)));
                    $sNorm = strtolower(trim(strip_tags((string)$studentAns)));
                    if ($cNorm !== '' && $cNorm === $sNorm) {
                        $awardedMarks = $maxMarks;
                    }
                }
            }

            // Cap awarded marks between 0 and max question marks
            $awardedMarks = min($maxMarks, max(0.0, round($awardedMarks, 2)));
            $isCorrect = ($maxMarks > 0 && $awardedMarks >= $maxMarks);
            $isPartial = ($maxMarks > 0 && $awardedMarks > 0 && $awardedMarks < $maxMarks);

            $statusLabel = 'Incorrect';
            if ($isCorrect) {
                $statusLabel = 'Correct';
            } elseif ($isPartial) {
                $statusLabel = 'Partial Credit';
            } elseif ($hasManualScore) {
                $statusLabel = 'Incorrect';
            } elseif (in_array($type, ['short_answer', 'essay', 'fill_blank', 'fill_in_blank', 'fill_in_the_blank', 'blank_space', 'code'])) {
                $statusLabel = 'Pending Review';
            }

            return [
                'id'               => $q->id,
                'number'           => $idx + 1,
                'type'             => $q->type ?? 'Multiple Choice',
                'instruction'      => $q->instruction ?? '',
                'text'             => strip_tags($q->text ?? ($q->title ?? 'Question')),
                'options'          => $q->options,
                'student_answer'   => is_array($studentAns) ? json_encode($studentAns) : ($studentAns ?? 'No answer submitted'),
                'correct_answer'   => $correct ?: 'Pending evaluation',
                'is_correct'       => $isCorrect,
                'is_partial'       => $isPartial,
                'has_manual_score' => $hasManualScore,
                'manual_score'     => $manualScoreVal,
                'is_manual_graded' => $isManualGraded,
                'awarded_marks'    => $awardedMarks,
                'max_marks'        => $maxMarks,
                'status_label'     => $statusLabel,
            ];
        });

        // Dynamic accurate total score calculation based on all awarded marks
        $totalCalculatedScore = round($questionBreakdown->sum('awarded_marks'), 2);
        $totalMarks = (float)($attempt->total_marks ?? ($exam->total_marks ?? 100));
        $pct = $totalMarks > 0 ? round(($totalCalculatedScore / $totalMarks) * 100, 1) : 0.0;
        $grade = $this->calculateGrade($pct);
        $gradePoint = $this->calculateGradePoint($pct);

        // Keep attempt score in DB permanently in sync if it was null or differed
        if ($attempt->score === null || (float)$attempt->score !== $totalCalculatedScore || $attempt->percentage === null) {
            $attempt->update([
                'score'      => $totalCalculatedScore,
                'percentage' => $pct,
                'grade'      => $grade,
            ]);
        }

        $displayStatus = ucfirst($attempt->status ?? 'submitted');
        if ($attempt->status === 'graded') {
            $displayStatus = 'Graded (Unpublished)';
        } elseif ($attempt->status === 'published') {
            $displayStatus = 'Published';
        }

        $officialStudentId = $student?->id_no ?: ($student?->student_id ?: ('WU/' . str_pad((string)$attempt->user_id, 5, '0', STR_PAD_LEFT) . '/16'));

        return response()->json([
            'data' => [
                'attempt' => [
                    'id'           => $attempt->id,
                    'score'        => $totalCalculatedScore,
                    'total_marks'  => $totalMarks,
                    'percentage'   => $pct,
                    'grade'        => $grade,
                    'grade_point'  => $gradePoint,
                    'status'       => $displayStatus,
                    'started_at'   => $attempt->started_at ? $attempt->started_at->format('M d, Y h:i A') : 'N/A',
                    'submitted_at' => $attempt->submitted_at ? $attempt->submitted_at->format('M d, Y h:i A') : 'N/A',
                ],
                'student' => [
                    'id'         => $student?->id,
                    'name'       => $student?->name ?? 'Student',
                    'student_id' => $officialStudentId,
                    'email'      => $student?->email ?? '',
                    'year_level' => $student?->year_level ?? '1st Year',
                    'section'    => $student?->section ?? 'Section A',
                    'department' => $student?->department?->name ?? ($exam->department?->name ?? 'Department'),
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
                    'academic_year'   => $exam->settings['academic_year'] ?? '2025/2026',
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
     * strictly scoped to Department Head's department and respecting active filters.
     */
    public function export(Request $request)
    {
        $deptId = $this->resolveDeptId($request);
        if (!$deptId) {
            return response()->json(['message' => 'Unauthorized: No department assigned.'], 403);
        }

        $user = $request->user();
        $dept = Department::find($deptId);
        $deptName = $dept ? $dept->name : 'Department';
        $deptCode = $dept ? ($dept->code ?: 'DEPT') : 'DEPT';
        $assignedSemester = SystemSetting::where('key', 'semester')->value('value') ?? 'Second Semester';

        $format = strtolower($request->get('format', 'excel'));

        $exams = $this->buildDeptExamsQuery($deptId, $user->id)
            ->with(['instructor', 'course'])
            ->get();

        $allAttempts = ExamAttempt::whereIn('exam_id', $exams->pluck('id'))
            ->where(function ($q) use ($deptId) {
                $q->whereHas('student', function ($s) use ($deptId) {
                    $s->where('department_id', $deptId)->orWhereNull('department_id');
                });
            })
            ->with(['exam.course', 'student.department'])
            ->latest('id')
            ->get();

        // Map and filter identically to index()
        $rows = $allAttempts->map(function ($att) use ($assignedSemester) {
            $student = $att->student;
            $exam = $att->exam;
            $courseCode = $exam?->course_code ?: ($exam?->course?->code ?? 'N/A');
            $courseName = $exam?->course_name ?: ($exam?->course?->title ?? 'General Course');
            $examCode = $exam?->settings['exam_code'] ?? ('EXM-' . ($exam?->scheduled_at ? $exam->scheduled_at->format('Y') : date('Y')) . '-' . str_pad((string)($exam?->id ?? 0), 3, '0', STR_PAD_LEFT));

            $officialStudentId = $student?->id_no ?: ($student?->student_id ?: ('WU/' . str_pad((string)$att->user_id, 5, '0', STR_PAD_LEFT) . '/16'));

            $sec = $student?->section ?: ($exam?->section ?: ($exam?->settings['section'] ?? 'Section A'));
            if (strtolower($sec) === 'both sections' || strtolower($sec) === 'all') {
                $sec = $student?->section ?: 'Section A';
            }

            $yearLevel = $student?->year_level ?: ($exam?->settings['year_level'] ?? '1st Year');
            $semester = $exam?->settings['semester'] ?? ($exam?->course?->semester ?? ($student?->semester ?? $assignedSemester));

            $totalMarks = $att->total_marks ?? ($exam?->total_marks ?? 100);
            $rawStatus = strtolower($att->status ?? 'submitted');
            $score = $att->score !== null ? (float)$att->score : null;
            $pct = $att->percentage !== null ? (float)$att->percentage : null;

            $ans = $att->answers ?? [];
            if (is_string($ans)) $ans = json_decode($ans, true) ?? [];
            $manualScores = $ans['_manual_scores'] ?? ($ans['manual_scores'] ?? []);

            if (!empty($manualScores) && ($score === null || $pct === null)) {
                $manualTotal = (float)array_sum($manualScores);
                $score = $score !== null ? max($score, $manualTotal) : $manualTotal;
                $pct = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 1) : 0.0;
            } elseif ($score !== null && $pct === null) {
                $pct = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 1) : 0.0;
            }

            $isGraded = ($score !== null) && in_array($rawStatus, ['graded', 'published', 'completed', 'submitted']);
            $grade = $isGraded ? ($att->grade ?: $this->calculateGrade($pct ?? 0.0)) : 'Pending';

            $displayStatus = 'Pending';
            if ($rawStatus === 'published') {
                $displayStatus = 'Published';
            } elseif ($rawStatus === 'graded' || ($isGraded && $rawStatus !== 'published')) {
                $displayStatus = 'Graded (Unpublished)';
            } elseif ($rawStatus === 'in_progress') {
                $displayStatus = 'In Progress';
            }

            return [
                'student_name'   => $student?->name ?? 'Student',
                'student_id'     => $officialStudentId,
                'student_reg_no' => $officialStudentId,
                'student_email'  => $student?->email ?? '',
                'course_code'    => $courseCode,
                'course_name'    => $courseName,
                'section'        => $sec,
                'year_level'     => $yearLevel,
                'semester'       => $semester,
                'exam_code'      => $examCode,
                'exam_title'     => $exam?->title ?? 'Exam',
                'score'          => $score,
                'total_marks'    => $totalMarks,
                'percentage'     => $pct,
                'grade'          => $grade,
                'status'         => $displayStatus,
                'raw_status'     => $rawStatus,
                'is_graded'      => $isGraded,
                'submitted_at'   => $att->submitted_at ? $att->submitted_at->format('M d, Y h:i A') : 'N/A',
            ];
        });

        // Apply filters
        $filteredRows = $rows->filter(function ($row) use ($request) {
            $search = strtolower(trim((string)$request->get('search', '')));
            if ($search !== '') {
                $matched = str_contains(strtolower($row['student_name']), $search) ||
                           str_contains(strtolower($row['student_id']), $search) ||
                           str_contains(strtolower($row['course_code']), $search) ||
                           str_contains(strtolower($row['course_name']), $search) ||
                           str_contains(strtolower($row['exam_title']), $search);
                if (!$matched) return false;
            }

            if ($request->filled('student_id') && strtolower($request->student_id) !== 'all') {
                if (!str_contains(strtolower($row['student_id']), strtolower(trim((string)$request->student_id)))) {
                    return false;
                }
            }

            $reqYearLevel = $request->get('year_level') ?? $request->get('academic_year');
            if ($reqYearLevel && strtolower($reqYearLevel) !== 'all') {
                $reqNorm = strtolower(trim((string)$reqYearLevel));
                $rowNorm = strtolower(trim((string)$row['year_level']));
                if ($rowNorm !== $reqNorm && !str_contains($rowNorm, $reqNorm)) {
                    return false;
                }
            }

            if ($request->filled('semester') && strtolower($request->semester) !== 'all') {
                if (strtolower($row['semester']) !== strtolower(trim((string)$request->semester))) {
                    return false;
                }
            }

            if ($request->filled('section') && strtolower($request->section) !== 'all') {
                $reqSec = str_replace('section ', '', strtolower(trim((string)$request->section)));
                $rowSec = str_replace('section ', '', strtolower(trim((string)$row['section'])));
                if ($reqSec !== $rowSec) return false;
            }

            if ($request->filled('course_code') && strtolower($request->course_code) !== 'all') {
                if (strtolower($row['course_code']) !== strtolower(trim((string)$request->course_code))) {
                    return false;
                }
            }

            if ($request->filled('status') && strtolower($request->status) !== 'all') {
                $reqStatus = strtolower(trim((string)$request->status));
                if (!str_contains(strtolower($row['status']), $reqStatus) && !str_contains(strtolower($row['raw_status']), $reqStatus)) {
                    return false;
                }
            }

            if ($request->filled('grade') && strtolower($request->grade) !== 'all') {
                if (strtoupper($row['grade']) !== strtoupper(trim((string)$request->grade))) {
                    return false;
                }
            }

            return true;
        })->values();

        $timestamp = Carbon::now()->format('Y-m-d_His');
        $safeDept = preg_replace('/[^A-Za-z0-9_\-]/', '_', strtolower($deptName));
        $baseFileName = "{$safeDept}_department_results_{$timestamp}";

        // Active filters description for export banner
        $activeFilterDesc = [];
        $reqYearLevel = $request->get('year_level') ?? $request->get('academic_year');
        if ($reqYearLevel && $reqYearLevel !== 'all') $activeFilterDesc[] = "Year Level: {$reqYearLevel}";
        if ($request->filled('semester') && $request->semester !== 'all') $activeFilterDesc[] = "Semester: {$request->semester}";
        if ($request->filled('section') && $request->section !== 'all') $activeFilterDesc[] = "Section: {$request->section}";
        if ($request->filled('course_code') && $request->course_code !== 'all') $activeFilterDesc[] = "Course: {$request->course_code}";
        if ($request->filled('status') && $request->status !== 'all') $activeFilterDesc[] = "Status: {$request->status}";
        if ($request->filled('grade') && $request->grade !== 'all') $activeFilterDesc[] = "Grade: {$request->grade}";
        $filterSummaryText = !empty($activeFilterDesc) ? implode(' | ', $activeFilterDesc) : 'All Department Records';

        // 1. PDF Export (Dompdf with official university layout and Department Head sign-off)
        if ($format === 'pdf') {
            $formattedDate = Carbon::now()->format('F d, Y \a\t h:i A');
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
            $html .= '<title>Wollo University - Official Results Report</title>';
            $html .= '<style>
                @page { margin: 20px 25px; }
                body { font-family: "DejaVu Sans", sans-serif; font-size: 8px; color: #1e293b; line-height: 1.3; }
                .header { text-align: center; border-bottom: 2px solid #5138ed; padding-bottom: 8px; margin-bottom: 10px; }
                .logo-text { font-size: 15px; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; }
                .sub-title { font-size: 10px; color: #5138ed; font-weight: bold; margin-top: 2px; }
                .meta { font-size: 7.5px; color: #64748b; margin-top: 4px; }
                .dept-banner { background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 12px; margin-bottom: 10px; border-radius: 4px; }
                .dept-banner table { width: 100%; border: none; }
                .dept-banner td { padding: 2px 4px; font-size: 8px; }
                table.data { width: 100%; border-collapse: collapse; margin-top: 5px; }
                table.data th { background: #5138ed; color: #ffffff; font-weight: bold; padding: 5px 3px; text-align: left; font-size: 7px; text-transform: uppercase; }
                table.data td { padding: 4px 3px; border-bottom: 1px solid #e2e8f0; font-size: 7px; }
                table.data tr:nth-child(even) { background-color: #f8fafc; }
                .grade-a { color: #059669; font-weight: bold; }
                .grade-b { color: #2563eb; font-weight: bold; }
                .grade-c { color: #d97706; font-weight: bold; }
                .grade-f { color: #dc2626; font-weight: bold; }
                .badge-published { background: #dcfce7; color: #166534; padding: 1.5px 3px; border-radius: 3px; font-weight: bold; }
                .badge-pending { background: #fef3c7; color: #92400e; padding: 1.5px 3px; border-radius: 3px; font-weight: bold; }
                .sign-off { margin-top: 18px; border-top: 1px dashed #cbd5e1; padding-top: 12px; }
                .sign-off table { width: 100%; border: none; }
                .sign-off td { font-size: 8px; padding: 4px 8px; vertical-align: top; }
                .seal-box { border: 1px dashed #94a3b8; width: 85px; height: 50px; text-align: center; line-height: 50px; color: #94a3b8; font-size: 7px; text-transform: uppercase; font-weight: bold; margin: 0 auto; border-radius: 4px; }
                .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 7px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 3px; }
            </style></head><body>';

            $html .= '<div class="header">';
            $html .= '<div class="logo-text">Wollo University</div>';
            $html .= '<div class="sub-title">DEPARTMENT OF ' . strtoupper(htmlspecialchars($deptName)) . ' (' . htmlspecialchars($deptCode) . ')</div>';
            $html .= '<div class="meta">OFFICIAL EXAMINATION RESULTS REPORT &bull; Generated: ' . $formattedDate . ' &bull; Department Head: ' . htmlspecialchars($user->name) . '</div>';
            $html .= '</div>';

            $html .= '<div class="dept-banner"><table><tr>';
            $html .= '<td><strong>Department:</strong> ' . htmlspecialchars($deptName) . ' (' . htmlspecialchars($deptCode) . ')</td>';
            $html .= '<td><strong>Active Filters:</strong> ' . htmlspecialchars($filterSummaryText) . '</td>';
            $html .= '<td><strong>Total Records:</strong> ' . $filteredRows->count() . '</td>';
            $html .= '</tr></table></div>';

            $html .= '<table class="data"><thead><tr>';
            $html .= '<th style="width: 15px;">#</th>';
            $html .= '<th style="width: 60px;">Student ID</th>';
            $html .= '<th>Student Name</th>';
            $html .= '<th style="width: 45px;">Course</th>';
            $html .= '<th style="width: 35px;">Section</th>';
            $html .= '<th style="width: 45px;">Year Level</th>';
            $html .= '<th style="width: 50px;">Semester</th>';
            $html .= '<th>Exam Title</th>';
            $html .= '<th style="width: 35px; text-align: center;">Score</th>';
            $html .= '<th style="width: 25px; text-align: center;">%</th>';
            $html .= '<th style="width: 25px; text-align: center;">Grade</th>';
            $html .= '<th style="width: 55px; text-align: center;">Status</th>';
            $html .= '</tr></thead><tbody>';

            if ($filteredRows->isEmpty()) {
                $html .= '<tr><td colspan="12" style="text-align: center; padding: 15px; color: #94a3b8;">No examination results found matching the selected filter criteria.</td></tr>';
            } else {
                foreach ($filteredRows as $i => $r) {
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
                    $html .= '<td style="font-family: monospace;">' . htmlspecialchars($r['student_id']) . '</td>';
                    $html .= '<td><strong>' . htmlspecialchars($r['student_name']) . '</strong></td>';
                    $html .= '<td>' . htmlspecialchars($r['course_code']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($r['section']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($r['year_level']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($r['semester']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($r['exam_title']) . '</td>';
                    $html .= '<td style="text-align: center;">' . ($r['score'] !== null ? $r['score'] . '/' . $r['total_marks'] : '—') . '</td>';
                    $html .= '<td style="text-align: center;">' . ($r['percentage'] !== null ? $r['percentage'] . '%' : '—') . '</td>';
                    $html .= '<td style="text-align: center;" class="' . $gradeClass . '">' . htmlspecialchars($r['grade']) . '</td>';
                    $html .= '<td style="text-align: center;">' . $statusBadge . '</td>';
                    $html .= '</tr>';
                }
            }

            $html .= '</tbody></table>';

            // Official Department Head sign-off footer
            $html .= '<div class="sign-off"><table><tr>';
            $html .= '<td style="width: 35%;"><strong>Prepared & Verified By:</strong><br><br>' . htmlspecialchars($user->name) . '<br>Head, Department of ' . htmlspecialchars($deptName) . '<br>Signature: __________________________</td>';
            $html .= '<td style="width: 30%; text-align: center;"><strong>Institutional Stamp:</strong><br><br><div class="seal-box">Official Seal</div></td>';
            $html .= '<td style="width: 35%; text-align: right;"><strong>Academic Commission Approval:</strong><br><br>Date: __________________________<br>Registrar Endorsement: ____________</td>';
            $html .= '</tr></table></div>';

            $html .= '<div class="footer">Wollo University Examination Management Portal &bull; Confidential Department Document &bull; Authorized Access Only</div>';
            $html .= '</body></html>';

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            ActivityLog::create([
                'department_id' => $deptId,
                'user_id'       => $user->id,
                'actor_role'    => 'dept_head',
                'action'        => "Exported {$filteredRows->count()} examination results as PDF ({$deptName})",
                'type'          => 'export',
                'module'        => 'Results',
                'details'       => "Department Head generated PDF results report with filters: {$filterSummaryText}",
                'log_status'    => 'success',
            ]);

            return response()->json([
                'filename'  => $baseFileName . '.pdf',
                'mime_type' => 'application/pdf',
                'file'      => base64_encode($dompdf->output()),
            ]);
        }

        // 2. CSV Export
        if ($format === 'csv') {
            $filename = "{$baseFileName}.csv";
            $headers = ['#', 'Student ID', 'Student Name', 'Course Code', 'Course Title', 'Section', 'Year Level', 'Semester', 'Exam Title', 'Score Earned', 'Total Marks', 'Percentage (%)', 'Grade', 'Result Status', 'Submitted Date'];

            $handle = fopen('php://memory', 'r+');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($filteredRows as $idx => $r) {
                fputcsv($handle, [
                    $idx + 1,
                    $r['student_id'],
                    $r['student_name'],
                    $r['course_code'],
                    $r['course_name'],
                    $r['section'],
                    $r['year_level'],
                    $r['semester'],
                    $r['exam_title'],
                    $r['score'] !== null ? $r['score'] : 'N/A',
                    $r['total_marks'],
                    $r['percentage'] !== null ? ($r['percentage'] . '%') : 'N/A',
                    $r['grade'],
                    $r['status'],
                    $r['submitted_at'],
                ]);
            }

            rewind($handle);
            $csvContent = stream_get_contents($handle);
            fclose($handle);

            ActivityLog::create([
                'department_id' => $deptId,
                'user_id'       => $user->id,
                'actor_role'    => 'dept_head',
                'action'        => "Exported {$filteredRows->count()} examination results as CSV ({$deptName})",
                'type'          => 'export',
                'module'        => 'Results',
                'details'       => "Department Head exported CSV results spreadsheet with filters: {$filterSummaryText}",
                'log_status'    => 'success',
            ]);

            return response()->json([
                'filename'  => $filename,
                'mime_type' => 'text/csv',
                'file'      => base64_encode($csvContent),
            ]);
        }

        // 3. Excel Export (Default)
        $filename = "{$baseFileName}.xlsx";
        $excelBinary = Excel::raw(new DepartmentResultExport($filteredRows), \Maatwebsite\Excel\Excel::XLSX);

        ActivityLog::create([
            'department_id' => $deptId,
            'user_id'       => $user->id,
            'actor_role'    => 'dept_head',
            'action'        => "Exported {$filteredRows->count()} examination results as Excel ({$deptName})",
            'type'          => 'export',
            'module'        => 'Results',
            'details'       => "Department Head exported Excel results workbook with filters: {$filterSummaryText}",
            'log_status'    => 'success',
        ]);

        return response()->json([
            'filename'  => $filename,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file'      => base64_encode($excelBinary),
        ]);
    }
}
