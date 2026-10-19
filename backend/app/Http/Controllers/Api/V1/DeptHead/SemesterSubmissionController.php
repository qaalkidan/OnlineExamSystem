<?php

namespace App\Http\Controllers\Api\V1\DeptHead;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\SemesterSubmission;
use App\Models\User;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Helpers\LogActivity;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SemesterSubmissionController extends Controller
{
    private function resolveDeptId(Request $request): ?int
    {
        $user = $request->user();
        if ($user->department_id) return $user->department_id;
        $dept = Department::where('head_id', $user->id)->first();
        if ($dept) {
            $user->update(['department_id' => $dept->id]);
            return $dept->id;
        }
        return null;
    }

    /**
     * Get the year-level summary for SemesterSubmissions.vue
     */
    public function index(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $departmentName = Department::find($deptId)?->name ?? 'Unknown Department';

        $yearLabels = [
            '1st year' => '1st Year',
            '2nd year' => '2nd Year',
            '3rd year' => '3rd Year',
            '4th year' => '4th Year',
            '5th year' => '5th Year',
        ];

        $allInstructors = User::where('department_id', $deptId)
            ->whereIn('role', ['instructor', 'dept_head'])
            ->get(['id', 'year_level']);

        $allCourses = Course::where('department_id', $deptId)
            ->get(['id', 'level', 'instructor_id']);

        $instructorIds = $allInstructors->pluck('id');
        $submissions = SemesterSubmission::whereIn('instructor_id', $instructorIds)->get();

        $normalizeLevel = function ($level) {
            return strtolower(trim($level ?? ''));
        };

        $groups = [];
        foreach ($yearLabels as $key => $label) {
            $instCount = $allInstructors->filter(function ($i) use ($key, $normalizeLevel) {
                return $normalizeLevel($i->year_level) === $key;
            })->count();

            $courseCount = $allCourses->filter(function ($c) use ($key, $normalizeLevel) {
                return $normalizeLevel($c->level) === $key;
            })->count();

            $groups[$key] = [
                'id'                   => base64_encode($key),
                'academicYear'         => $label,
                'semester'             => 'Second Semester',
                'department'           => $departmentName,
                'coursesCount'         => $courseCount,
                'instructorsCount'     => $instCount,
                'submittedInstructors' => [],
                'totalCount'           => $instCount,
                'status'               => 'Not Submitted',
            ];
        }

        $instructorYearMap = [];
        foreach ($allInstructors as $inst) {
            $instructorYearMap[$inst->id] = $normalizeLevel($inst->year_level);
        }

        $academicYearFallback = [
            '2025/2026' => '1st year',
            '2024/2025' => '2nd year',
            '2023/2024' => '3rd year',
            '2022/2023' => '4th year',
            '2021/2022' => '5th year',
        ];

        foreach ($submissions as $sub) {
            if (!in_array($sub->status, ['submitted', 'approved'])) continue;

            $yearKey = $instructorYearMap[$sub->instructor_id] ?? null;
            if (!$yearKey || !isset($groups[$yearKey])) {
                $yearKey = $academicYearFallback[$sub->academic_year] ?? null;
            }

            if ($yearKey && isset($groups[$yearKey])) {
                if (!in_array($sub->instructor_id, $groups[$yearKey]['submittedInstructors'])) {
                    $groups[$yearKey]['submittedInstructors'][] = $sub->instructor_id;
                }
            }
        }

        foreach ($groups as $key => &$group) {
            $group['submittedCount'] = count($group['submittedInstructors']);
            unset($group['submittedInstructors']);

            if ($group['submittedCount'] === 0) {
                $group['status'] = 'Not Submitted';
            } elseif ($group['totalCount'] > 0 && $group['submittedCount'] >= $group['totalCount']) {
                $group['status'] = 'Submitted';
            } elseif ($group['submittedCount'] > 0) {
                $group['status'] = 'Pending';
            }
        }

        $submissionsList = array_values($groups);

        $stats = [
            'totalAcademicYears' => count($groups),
            'totalDepartments'   => Department::count(),
            'totalCourses'       => $allCourses->count(),
            'totalInstructors'   => $allInstructors->count(),
        ];

        return response()->json([
            'stats'       => $stats,
            'submissions' => $submissionsList,
        ]);
    }

    /**
     * Get detailed instructor semester submissions for SemesterSubmissionDetail.vue
     */
    /**
     * Get detailed instructor semester submissions for SemesterSubmissionDetail.vue
     */
    public function details(Request $request, $id = null): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $userDept = Department::find($deptId);

        $academicYear = $request->query('academic_year', 'All Academic Terms');
        $semester = $request->query('semester', 'All Semesters');
        $deptFilter = $request->query('department');
        $statusFilter = $request->query('status');
        $searchQuery = strtolower(trim($request->query('search', '')));

        $isAllTerms = (!$academicYear || $academicYear === 'All Academic Terms' || strtolower($academicYear) === 'all');

        // Decode year level from $id or query param
        $yearLevelFilter = $request->query('year_level');
        if (!$yearLevelFilter && $id && $id !== 'details' && $id !== 'all') {
            $decoded = base64_decode($id, true);
            $yearLevelFilter = ($decoded !== false && ctype_print($decoded)) ? $decoded : $id;
        }

        // Query instructors
        $instructorsQuery = User::whereIn('role', ['instructor', 'dept_head'])
            ->with(['department', 'assignedCourses']);

        // Department filtering
        if ($deptFilter && $deptFilter !== 'All Departments') {
            $instructorsQuery->whereHas('department', function ($q) use ($deptFilter) {
                $q->where('name', $deptFilter);
            });
        } elseif (!$deptFilter && $deptId) {
            // Include instructors belonging to this department OR assigned to courses in this dept
            $courseInstIds = Course::where('department_id', $deptId)
                ->whereNotNull('instructor_id')
                ->pluck('instructor_id')
                ->toArray();

            $instructorsQuery->where(function ($q) use ($deptId, $courseInstIds) {
                $q->where('department_id', $deptId)
                  ->orWhereIn('id', $courseInstIds);
            });
        }

        // Year level filtering if specified
        if ($yearLevelFilter && strtolower($yearLevelFilter) !== 'all') {
            $normalizedLevel = strtolower(trim($yearLevelFilter));
            $instructorsQuery->where(function ($q) use ($normalizedLevel) {
                $q->whereRaw('LOWER(TRIM(year_level)) = ?', [$normalizedLevel])
                  ->orWhereNull('year_level');
            });
        }

        $instructors = $instructorsQuery->get();

        $colorPalettes = [
            'bg-purple-100 text-purple-700',
            'bg-rose-100 text-rose-700',
            'bg-sky-100 text-sky-700',
            'bg-amber-100 text-amber-700',
            'bg-emerald-100 text-emerald-700',
            'bg-indigo-100 text-indigo-700',
            'bg-orange-100 text-orange-700',
            'bg-teal-100 text-teal-700',
        ];

        // Query REAL submissions from database
        $instructorIds = $instructors->pluck('id');
        $subsQuery = SemesterSubmission::whereIn('instructor_id', $instructorIds);
        if (!$isAllTerms) {
            $subsQuery->where('academic_year', $academicYear);
            if ($semester && strtolower($semester) !== 'all' && $semester !== 'All Semesters') {
                $subsQuery->where('semester', $semester);
            }
        }
        $existingSubmissions = $subsQuery->latest('updated_at')->get();

        $rows = [];
        $counts = [
            'pending'             => 0,
            'approved'            => 0,
            'correction_required' => 0,
            'rejected'            => 0,
            'reopened'            => 0,
            'not_submitted'       => 0,
            'total'               => 0,
        ];

        // If All Terms is selected, we can show all real submissions for the department
        // If an instructor has submitted in multiple terms, show each submission
        if ($isAllTerms && $existingSubmissions->isNotEmpty()) {
            foreach ($existingSubmissions as $sub) {
                $inst = $instructors->firstWhere('id', $sub->instructor_id);
                if (!$inst) {
                    $inst = User::with('department')->find($sub->instructor_id);
                    if (!$inst) continue;
                }

                $rawStatus = strtolower($sub->status ?? 'submitted');
                $displayStatus = match ($rawStatus) {
                    'approved' => 'Approved',
                    'correction_required' => 'Correction Required',
                    'rejected' => 'Rejected',
                    'reopened' => 'Reopened',
                    'submitted', 'under_review', 'pending' => 'Pending',
                    default => 'Pending',
                };
                $counts['total']++;
                if ($rawStatus === 'approved') {
                    $counts['approved']++;
                } elseif ($rawStatus === 'correction_required') {
                    $counts['correction_required']++;
                } elseif ($rawStatus === 'rejected') {
                    $counts['rejected']++;
                } elseif ($rawStatus === 'reopened') {
                    $counts['reopened']++;
                } else {
                    $counts['pending']++;
                }

                // Initials calculation
                $nameParts = preg_split('/\s+/', trim($inst->name));
                $initials = '';
                if (count($nameParts) >= 2) {
                    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                } elseif (count($nameParts) === 1 && strlen($nameParts[0]) > 0) {
                    $initials = strtoupper(substr($nameParts[0], 0, min(2, strlen($nameParts[0]))));
                } else {
                    $initials = 'IN';
                }

                $color = $colorPalettes[$inst->id % count($colorPalettes)];

                // REAL Course lookup from database courses table
                $assignedCourse = Course::where('instructor_id', $inst->id)->first();
                if (!$assignedCourse && $inst->assignedCourses && $inst->assignedCourses->isNotEmpty()) {
                    $assignedCourse = $inst->assignedCourses->first();
                }

                $courseTitle = $assignedCourse ? $assignedCourse->title : ($inst->department?->name . ' Instruction');
                $courseCode = $assignedCourse ? $assignedCourse->code : 'BO-0';
                $credit = $assignedCourse ? ($assignedCourse->credits ?: 4) : 4;
                $section = $sub->section ?: ($assignedCourse?->section ?: ($inst->section ?: 'Section A'));

                $submittedDate = $sub->submitted_at ? $sub->submitted_at->format('M d, Y') : ($sub->created_at ? $sub->created_at->format('M d, Y') : Carbon::now()->format('M d, Y'));
                $submittedTime = $sub->submitted_at ? $sub->submitted_at->format('h:i A') : ($sub->created_at ? $sub->created_at->format('h:i A') : Carbon::now()->format('h:i A'));
                $submittedStr = "{$submittedDate}\n{$submittedTime}";

                // Real exam & attempt stats for instructor
                $instExams = Exam::where('user_id', $inst->id)
                    ->when($assignedCourse, fn($q) => $q->orWhere('course_code', $assignedCourse->code))
                    ->get();
                $examsCount = $instExams->count();
                $examIds = $instExams->pluck('id')->toArray();
                $attempts = ExamAttempt::whereIn('exam_id', $examIds)->get();
                $resultsSubmitted = $attempts->count();
                $avgScore = $resultsSubmitted > 0 ? round($attempts->avg('percentage'), 1) : 0;
                $passedAttempts = $attempts->filter(fn($a) => ($a->percentage ?? 0) >= 50)->count();
                $passRate = $resultsSubmitted > 0 ? round(($passedAttempts / $resultsSubmitted) * 100, 1) : 0;

                $studentsCount = User::where('role', 'student')
                    ->where('department_id', $inst->department_id)
                    ->count();

                $rowItem = [
                    'id'            => $sub->id,
                    'submission_id' => $sub->id,
                    'instructor_id' => $inst->id,
                    'name'          => $inst->name,
                    'email'         => $inst->email,
                    'initials'      => $initials,
                    'color'         => $color,
                    'department'    => $inst->department?->name ?? ($userDept?->name ?? 'Computer Science'),
                    'course'        => $courseTitle,
                    'course_code'   => $courseCode,
                    'section'       => $section,
                    'courses'       => $credit,
                    'credit'        => $credit,
                    'students'      => $studentsCount,
                    'submitted'     => $submittedStr,
                    'submitted_date'=> $submittedDate,
                    'submitted_time'=> $submittedTime,
                    'status'        => $displayStatus,
                    'raw_status'    => $rawStatus,
                    'is_submitted'  => true,
                    'is_locked'     => $sub->isLocked(),
                    'reopened_at'   => $sub->reopened_at?->format('M d, Y h:i A'),
                    'reopen_reason' => $sub->reopen_reason,
                    'locked_at'     => $sub->locked_at?->format('M d, Y h:i A'),
                    'approved_at'   => $sub->approved_at?->format('M d, Y h:i A'),
                    'remarks'       => $sub->remarks ?? '',
                    'year_level'    => $inst->year_level ?? '1st Year',
                    'academic_year' => $sub->academic_year,
                    'semester'      => $sub->semester,
                    'exams_count'       => $examsCount,
                    'results_submitted' => $resultsSubmitted,
                    'avg_score'         => $avgScore,
                    'pass_rate'         => $passRate,
                    'checklist'         => [
                        'academic_schedule' => [
                            'completed' => true,
                            'label'     => 'Academic Schedule Verified',
                            'detail'    => "Class schedule verified for {$sub->academic_year}",
                        ],
                        'exams' => [
                            'completed' => $examsCount > 0,
                            'label'     => 'Examinations Completed',
                            'detail'    => "{$examsCount} Exams Created & Conducted",
                        ],
                        'students' => [
                            'completed' => $studentsCount > 0,
                            'label'     => 'Student Enrollment Verified',
                            'detail'    => "{$studentsCount} Students Enrolled in Department",
                        ],
                        'results' => [
                            'completed' => $resultsSubmitted > 0,
                            'label'     => 'Grades & Results Processed',
                            'detail'    => "{$resultsSubmitted} Submissions Graded ({$passRate}% Pass Rate)",
                        ],
                    ],
                ];

                $matchesStatus = false;
                if (!$statusFilter || $statusFilter === 'All Statuses' || $statusFilter === 'All Submissions') {
                    $matchesStatus = true;
                } elseif ($statusFilter === 'All Instructors') {
                    $matchesStatus = true;
                } elseif ($statusFilter === 'Not Submitted') {
                    $matchesStatus = false;
                } else {
                    $matchesStatus = (strtolower($displayStatus) === strtolower($statusFilter));
                }

                $matchesSearch = true;
                if ($searchQuery) {
                    $matchesSearch = (
                        str_contains(strtolower($inst->name), $searchQuery) ||
                        str_contains(strtolower($inst->email), $searchQuery) ||
                        str_contains(strtolower($rowItem['department']), $searchQuery) ||
                        str_contains(strtolower($courseTitle), $searchQuery) ||
                        str_contains(strtolower($section), $searchQuery)
                    );
                }

                if ($matchesStatus && $matchesSearch) {
                    $rows[] = $rowItem;
                }
            }
        } else {
            // Specific Term or no submissions found under All Terms: evaluate each instructor
            $subsKeyed = $existingSubmissions->keyBy('instructor_id');

            foreach ($instructors as $inst) {
                $sub = $subsKeyed->get($inst->id);
                $isSubmitted = ($sub && in_array(strtolower($sub->status ?? ''), [
                    'submitted', 'pending', 'under_review', 'approved', 'correction_required', 'rejected', 'reopened'
                ]));

                if ($isSubmitted) {
                    $rawStatus = strtolower($sub->status ?? 'submitted');
                    $displayStatus = match ($rawStatus) {
                        'approved' => 'Approved',
                        'correction_required' => 'Correction Required',
                        'rejected' => 'Rejected',
                        'reopened' => 'Reopened',
                        'submitted', 'under_review', 'pending' => 'Pending',
                        default => 'Pending',
                    };
                    $counts['total']++;
                    if ($rawStatus === 'approved') {
                        $counts['approved']++;
                    } elseif ($rawStatus === 'correction_required') {
                        $counts['correction_required']++;
                    } elseif ($rawStatus === 'rejected') {
                        $counts['rejected']++;
                    } elseif ($rawStatus === 'reopened') {
                        $counts['reopened']++;
                    } else {
                        $counts['pending']++;
                    }
                } else {
                    $rawStatus = 'not_submitted';
                    $displayStatus = 'Not Submitted';
                    $counts['not_submitted']++;
                }

                // Initials calculation
                $nameParts = preg_split('/\s+/', trim($inst->name));
                $initials = '';
                if (count($nameParts) >= 2) {
                    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                } elseif (count($nameParts) === 1 && strlen($nameParts[0]) > 0) {
                    $initials = strtoupper(substr($nameParts[0], 0, min(2, strlen($nameParts[0]))));
                } else {
                    $initials = 'IN';
                }

                $color = $colorPalettes[$inst->id % count($colorPalettes)];

                // REAL Course lookup from database courses table
                $assignedCourse = Course::where('instructor_id', $inst->id)->first();
                if (!$assignedCourse && $inst->assignedCourses && $inst->assignedCourses->isNotEmpty()) {
                    $assignedCourse = $inst->assignedCourses->first();
                }

                $courseTitle = $assignedCourse ? $assignedCourse->title : ($inst->department?->name . ' Instruction');
                $courseCode = $assignedCourse ? $assignedCourse->code : 'BO-0';
                $credit = $assignedCourse ? ($assignedCourse->credits ?: 4) : 4;
                $section = $sub?->section ?: ($assignedCourse?->section ?: ($inst->section ?: 'Section A'));

                if ($isSubmitted && $sub) {
                    $submittedDate = $sub->submitted_at ? $sub->submitted_at->format('M d, Y') : ($sub->created_at ? $sub->created_at->format('M d, Y') : Carbon::now()->format('M d, Y'));
                    $submittedTime = $sub->submitted_at ? $sub->submitted_at->format('h:i A') : ($sub->created_at ? $sub->created_at->format('h:i A') : Carbon::now()->format('h:i A'));
                    $submittedStr = "{$submittedDate}\n{$submittedTime}";
                } else {
                    $submittedDate = '—';
                    $submittedTime = 'Not Submitted';
                    $submittedStr = '—';
                }

                // Real exam & attempt stats for instructor
                $instExams = Exam::where('user_id', $inst->id)
                    ->when($assignedCourse, fn($q) => $q->orWhere('course_code', $assignedCourse->code))
                    ->get();
                $examsCount = $instExams->count();
                $examIds = $instExams->pluck('id')->toArray();
                $attempts = ExamAttempt::whereIn('exam_id', $examIds)->get();
                $resultsSubmitted = $attempts->count();
                $avgScore = $resultsSubmitted > 0 ? round($attempts->avg('percentage'), 1) : 0;
                $passedAttempts = $attempts->filter(fn($a) => ($a->percentage ?? 0) >= 50)->count();
                $passRate = $resultsSubmitted > 0 ? round(($passedAttempts / $resultsSubmitted) * 100, 1) : 0;

                $studentsCount = User::where('role', 'student')
                    ->where('department_id', $inst->department_id)
                    ->count();

                $rowItem = [
                    'id'            => $sub?->id ?? $inst->id,
                    'submission_id' => $sub?->id,
                    'instructor_id' => $inst->id,
                    'name'          => $inst->name,
                    'email'         => $inst->email,
                    'initials'      => $initials,
                    'color'         => $color,
                    'department'    => $inst->department?->name ?? ($userDept?->name ?? 'Computer Science'),
                    'course'        => $courseTitle,
                    'course_code'   => $courseCode,
                    'section'       => $section,
                    'courses'       => $credit,
                    'credit'        => $credit,
                    'students'      => $studentsCount,
                    'submitted'     => $submittedStr,
                    'submitted_date'=> $submittedDate,
                    'submitted_time'=> $submittedTime,
                    'status'        => $displayStatus,
                    'raw_status'    => $rawStatus,
                    'is_submitted'  => $isSubmitted,
                    'is_locked'     => $sub ? $sub->isLocked() : false,
                    'reopened_at'   => $sub?->reopened_at?->format('M d, Y h:i A'),
                    'reopen_reason' => $sub?->reopen_reason,
                    'locked_at'     => $sub?->locked_at?->format('M d, Y h:i A'),
                    'approved_at'   => $sub?->approved_at?->format('M d, Y h:i A'),
                    'remarks'       => $sub?->remarks ?? '',
                    'year_level'    => $inst->year_level ?? '1st Year',
                    'academic_year' => $sub?->academic_year ?? $academicYear,
                    'semester'      => $sub?->semester ?? $semester,
                    'exams_count'       => $examsCount,
                    'results_submitted' => $resultsSubmitted,
                    'avg_score'         => $avgScore,
                    'pass_rate'         => $passRate,
                    'checklist'         => [
                        'academic_schedule' => [
                            'completed' => true,
                            'label'     => 'Academic Schedule Verified',
                            'detail'    => "Class schedule verified for " . ($sub?->academic_year ?? $academicYear),
                        ],
                        'exams' => [
                            'completed' => $examsCount > 0,
                            'label'     => 'Examinations Completed',
                            'detail'    => "{$examsCount} Exams Created & Conducted",
                        ],
                        'students' => [
                            'completed' => $studentsCount > 0,
                            'label'     => 'Student Enrollment Verified',
                            'detail'    => "{$studentsCount} Students Enrolled in Department",
                        ],
                        'results' => [
                            'completed' => $resultsSubmitted > 0,
                            'label'     => 'Grades & Results Processed',
                            'detail'    => "{$resultsSubmitted} Submissions Graded ({$passRate}% Pass Rate)",
                        ],
                    ],
                ];

                $matchesStatus = false;
                if (!$statusFilter || $statusFilter === 'All Statuses' || $statusFilter === 'All Submissions') {
                    $matchesStatus = $isSubmitted;
                } elseif ($statusFilter === 'All Instructors') {
                    $matchesStatus = true;
                } elseif ($statusFilter === 'Not Submitted') {
                    $matchesStatus = !$isSubmitted;
                } else {
                    $matchesStatus = (strtolower($displayStatus) === strtolower($statusFilter));
                }

                $matchesSearch = true;
                if ($searchQuery) {
                    $matchesSearch = (
                        str_contains(strtolower($inst->name), $searchQuery) ||
                        str_contains(strtolower($inst->email), $searchQuery) ||
                        str_contains(strtolower($rowItem['department']), $searchQuery) ||
                        str_contains(strtolower($courseTitle), $searchQuery) ||
                        str_contains(strtolower($section), $searchQuery)
                    );
                }

                if ($matchesStatus && $matchesSearch) {
                    $rows[] = $rowItem;
                }
            }
        }

        $allDepartments = Department::orderBy('name')->pluck('name')->unique()->values();

        // Build dynamic list of available semesters
        $dbTerms = SemesterSubmission::select('academic_year', 'semester')
            ->distinct()
            ->get()
            ->map(function ($t) {
                if ($t->academic_year && $t->semester) {
                    return "{$t->academic_year} — {$t->semester}";
                }
                return null;
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $semestersList = array_values(array_unique(array_merge(
            ['All Academic Terms'],
            $dbTerms,
            [
                '2025/2026 — First Semester',
                '2025/2026 — Second Semester',
                '2024/2025 — Second Semester',
                '2024/2025 — First Semester',
            ]
        )));

        return response()->json([
            'semester_info' => [
                'academicYear'       => $isAllTerms ? 'All Academic Years' : $academicYear,
                'semester'           => $isAllTerms ? 'All Semesters' : $semester,
                'department'         => $userDept?->name ?? 'Computer Science',
                'department_code'    => $userDept?->code ?? 'CS',
                'pendingReview'      => $counts['pending'],
                'approved'           => $counts['approved'],
                'correctionRequired' => $counts['correction_required'],
                'rejected'           => $counts['rejected'],
                'reopened'           => $counts['reopened'],
                'notSubmitted'       => $counts['not_submitted'],
                'total'              => $counts['total'],
            ],
            'instructors'   => $rows,
            'departments'   => $allDepartments,
            'semesters'     => $semestersList,
        ]);
    }

    /**
     * Synchronize and recalculate live semester submissions for the department.
     */
    public function sync(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $userDept = Department::find($deptId);
        $deptName = $userDept?->name ?? 'Computer Science';

        $academicYear = $request->input('academic_year', '2025/2026');
        $semester = $request->input('semester', 'Second Semester');

        if ($academicYear === 'All Academic Terms' || strtolower($academicYear) === 'all') {
            $academicYear = '2025/2026';
            $semester = 'Second Semester';
        }

        $instructors = User::where(function ($q) use ($deptId) {
                $q->where('department_id', $deptId);
            })
            ->whereIn('role', ['instructor', 'dept_head'])
            ->get();

        $courseInstIds = Course::where('department_id', $deptId)
            ->whereNotNull('instructor_id')
            ->pluck('instructor_id')
            ->toArray();

        if (!empty($courseInstIds)) {
            $extra = User::whereIn('id', $courseInstIds)->whereIn('role', ['instructor', 'dept_head'])->get();
            $instructors = $instructors->concat($extra)->unique('id');
        }

        foreach ($instructors as $inst) {
            $assignedCourse = Course::where('instructor_id', $inst->id)->first();
            $section = $assignedCourse?->section ?: ($inst->section ?: 'Section A');

            $submission = SemesterSubmission::where('instructor_id', $inst->id)
                ->where('academic_year', $academicYear)
                ->where('semester', $semester)
                ->first();

            if (!$submission) {
                SemesterSubmission::create([
                    'instructor_id' => $inst->id,
                    'academic_year' => $academicYear,
                    'semester'      => $semester,
                    'department'    => $deptName,
                    'section'       => $section,
                    'status'        => 'pending',
                    'submitted_at'  => now(),
                ]);
            } else {
                if (!$submission->submitted_at && in_array($submission->status, ['pending', 'submitted', 'under_review'])) {
                    $submission->submitted_at = now();
                    $submission->save();
                }
            }
        }

        LogActivity::record(
            'Synchronized',
            'Semester Submissions',
            "Synchronized live semester submissions for Department of {$deptName} ({$academicYear} {$semester})"
        );

        return $this->details($request);
    }

    /**
     * Calculate letter grade from percentage
     */
    private function calculateGrade(?float $percentage): string
    {
        if ($percentage === null) return 'NG';
        if ($percentage >= 90) return 'A+';
        if ($percentage >= 85) return 'A';
        if ($percentage >= 80) return 'A-';
        if ($percentage >= 75) return 'B+';
        if ($percentage >= 70) return 'B';
        if ($percentage >= 65) return 'B-';
        if ($percentage >= 60) return 'C+';
        if ($percentage >= 50) return 'C';
        if ($percentage >= 45) return 'C-';
        if ($percentage >= 40) return 'D';
        return 'F';
    }

    /**
     * Get comprehensive record details for a single submission/instructor.
     * Includes overview, real enrolled students, exams, attempts, scores, and checklist.
     */
    public function recordDetails(Request $request, $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $userDept = $deptId ? Department::find($deptId) : null;

        // 1. Locate submission
        $submission = SemesterSubmission::with(['instructor.department', 'approvedBy', 'reopenedBy'])->find($id);
        if (!$submission) {
            $submission = SemesterSubmission::with(['instructor.department', 'approvedBy', 'reopenedBy'])
                ->where('instructor_id', $id)
                ->latest('updated_at')
                ->first();
        }

        // 2. Locate instructor
        $instructor = $submission?->instructor;
        if (!$instructor) {
            $instructor = User::with(['department', 'assignedCourses'])->find($id);
        }

        if (!$instructor) {
            return response()->json(['message' => 'Instructor or semester submission record not found.'], 404);
        }

        $academicYear = $submission?->academic_year ?? $request->query('academic_year', '2025/2026');
        $semester = $submission?->semester ?? $request->query('semester', 'Second Semester');

        if (!$submission) {
            $submission = SemesterSubmission::firstOrCreate([
                'instructor_id' => $instructor->id,
                'academic_year' => $academicYear,
                'semester'      => $semester,
            ], [
                'department'    => $instructor->department?->name ?? ($userDept?->name ?? 'Computer Science'),
                'section'       => $instructor->section ?? 'Section A',
                'status'        => 'pending',
            ]);
            $submission->load(['approvedBy', 'reopenedBy']);
        }

        // Color & initials
        $nameParts = preg_split('/\s+/', trim($instructor->name));
        $initials = count($nameParts) >= 2 
            ? strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1))
            : strtoupper(substr($nameParts[0], 0, min(2, strlen($nameParts[0]))));
        $colorPalettes = [
            'bg-purple-100 text-purple-700',
            'bg-rose-100 text-rose-700',
            'bg-sky-100 text-sky-700',
            'bg-amber-100 text-amber-700',
            'bg-emerald-100 text-emerald-700',
            'bg-indigo-100 text-indigo-700',
            'bg-orange-100 text-orange-700',
            'bg-teal-100 text-teal-700',
        ];
        $color = $colorPalettes[$instructor->id % count($colorPalettes)];

        // Course info
        $assignedCourse = Course::where('instructor_id', $instructor->id)->first();
        if (!$assignedCourse && $instructor->assignedCourses && $instructor->assignedCourses->isNotEmpty()) {
            $assignedCourse = $instructor->assignedCourses->first();
        }
        $courseTitle = $assignedCourse ? $assignedCourse->title : ($instructor->department?->name . ' Instruction');
        $courseCode = $assignedCourse ? $assignedCourse->code : ($instructor->course_code ?: 'BO-0');
        $credit = $assignedCourse ? ($assignedCourse->credits ?: 4) : 4;
        $section = $submission->section ?: ($assignedCourse?->section ?: ($instructor->section ?: 'Section A'));
        $level = $assignedCourse?->level ?: ($instructor->year_level ?: '3rd Year');

        // Real exams conducted by this instructor or for this course
        $exams = Exam::where(function ($q) use ($instructor, $courseCode) {
                $q->where('user_id', $instructor->id);
                if ($courseCode && $courseCode !== 'BO-0') {
                    $q->orWhere('course_code', $courseCode);
                }
            })
            ->get();

        $examIds = $exams->pluck('id')->toArray();

        // Exam Attempts
        $attempts = ExamAttempt::whereIn('exam_id', $examIds)->with('student')->get();

        // Enrolled Students in section / department
        $studentsQuery = User::where('role', 'student');
        if ($instructor->department_id) {
            $studentsQuery->where('department_id', $instructor->department_id);
        }
        $deptStudents = $studentsQuery->orderBy('name')->get();

        // Also add students who took attempts
        $attemptStudentIds = $attempts->pluck('user_id')->unique();
        $attemptStudents = User::whereIn('id', $attemptStudentIds)->get();

        $allStudents = $deptStudents->concat($attemptStudents)->unique('id')->values();

        // Map exams details
        $examsList = $exams->map(function ($ex) use ($attempts) {
            $exAttempts = $attempts->where('exam_id', $ex->id);
            $count = $exAttempts->count();
            $avg = $count > 0 ? round($exAttempts->avg('percentage'), 1) : 0;
            $passed = $exAttempts->filter(fn($a) => ($a->percentage ?? 0) >= 50)->count();
            $passRate = $count > 0 ? round(($passed / $count) * 100, 1) : 0;

            return [
                'id'               => $ex->id,
                'title'            => $ex->title,
                'course_code'      => $ex->course_code,
                'section'          => $ex->section ?: 'All Sections',
                'duration_minutes' => $ex->duration_minutes,
                'total_marks'      => $ex->total_marks ?? 100,
                'status'           => $ex->status,
                'scheduled_at'     => $ex->scheduled_at?->format('M d, Y h:i A'),
                'published_at'     => $ex->published_at?->format('M d, Y h:i A'),
                'attempts_count'   => $count,
                'avg_score'        => $avg,
                'pass_rate'        => $passRate,
            ];
        })->values();

        // Map student examination information
        $studentsList = [];
        $totalMaxMarks = $exams->sum('total_marks') ?: 100;
        $totalScoresArr = [];

        foreach ($allStudents as $idx => $st) {
            $stAttempts = $attempts->where('user_id', $st->id);
            $attemptsMap = [];
            $stTotalScore = 0;
            $gradedExamsCount = 0;
            $hasPublished = false;

            foreach ($exams as $ex) {
                $att = $stAttempts->firstWhere('exam_id', $ex->id);
                if ($att) {
                    $score = (float)($att->score ?? 0);
                    $tot = (float)($att->total_marks ?? $ex->total_marks ?? 100);
                    $pct = (float)($att->percentage ?? ($tot > 0 ? round(($score / $tot) * 100, 1) : 0));
                    $stTotalScore += $score;
                    $gradedExamsCount++;
                    if ($att->status === 'published') $hasPublished = true;

                    $attemptsMap[] = [
                        'exam_id'      => $ex->id,
                        'exam_title'   => $ex->title,
                        'score'        => $score,
                        'total_marks'  => $tot,
                        'percentage'   => $pct,
                        'grade'        => $att->grade ?: $this->calculateGrade($pct),
                        'status'       => $att->status === 'published' ? 'Published' : ($att->submitted_at ? 'Graded' : 'Attempted'),
                        'submitted_at' => $att->submitted_at?->format('M d, Y h:i A') ?? '—',
                    ];
                } else {
                    $attemptsMap[] = [
                        'exam_id'      => $ex->id,
                        'exam_title'   => $ex->title,
                        'score'        => null,
                        'total_marks'  => (float)($ex->total_marks ?? 100),
                        'percentage'   => null,
                        'grade'        => 'NG',
                        'status'       => 'Not Attempted',
                        'submitted_at' => '—',
                    ];
                }
            }

            $overallPct = $totalMaxMarks > 0 ? round(($stTotalScore / $totalMaxMarks) * 100, 1) : 0;
            if ($gradedExamsCount > 0) {
                $totalScoresArr[] = $overallPct;
            }

            $evalStatus = 'Not Attempted';
            if ($exams->isNotEmpty()) {
                if ($gradedExamsCount === $exams->count()) {
                    $evalStatus = 'Completed';
                } elseif ($gradedExamsCount > 0) {
                    $evalStatus = 'Partially Graded';
                }
            }

            $studentsList[] = [
                'id'                 => $st->id,
                'name'               => $st->name,
                'id_no'              => $st->id_no ?: ('WU/' . str_pad($st->id, 5, '0', STR_PAD_LEFT)),
                'email'              => $st->email,
                'section'            => $st->section ?: $section,
                'year_level'         => $st->year_level ?: $level,
                'attempts'           => $attemptsMap,
                'total_score'        => round($stTotalScore, 1),
                'max_marks'          => $totalMaxMarks,
                'overall_percentage' => $overallPct,
                'letter_grade'       => $gradedExamsCount > 0 ? $this->calculateGrade($overallPct) : 'NG',
                'status'             => $evalStatus,
                'is_published'       => $hasPublished,
                'publication_status' => $hasPublished ? 'Published' : 'Pending',
            ];
        }

        // KPIs computation
        $totalStudentsCount = count($studentsList);
        $totalGraded = count($totalScoresArr);
        $classAvg = $totalGraded > 0 ? round(array_sum($totalScoresArr) / $totalGraded, 1) : 0;
        $passedCount = count(array_filter($totalScoresArr, fn($p) => $p >= 50));
        $passRate = $totalGraded > 0 ? round(($passedCount / $totalGraded) * 100, 1) : 0;
        $highestScore = !empty($totalScoresArr) ? max($totalScoresArr) : 0;
        $lowestScore = !empty($totalScoresArr) ? min($totalScoresArr) : 0;

        $rawStatus = strtolower($submission->status ?? 'pending');
        $displayStatus = match ($rawStatus) {
            'approved' => 'Approved',
            'correction_required' => 'Correction Required',
            'rejected' => 'Rejected',
            'reopened' => 'Reopened',
            'submitted', 'under_review' => 'Pending',
            default => 'Pending',
        };

        $isLocked = $submission->isLocked();

        return response()->json([
            'submission' => [
                'id'            => $submission->id,
                'instructor_id' => $instructor->id,
                'academic_year' => $submission->academic_year,
                'semester'      => $submission->semester,
                'department'    => $submission->department ?: ($instructor->department?->name ?? 'Computer Science'),
                'section'       => $section,
                'status'        => $displayStatus,
                'raw_status'    => $rawStatus,
                'is_locked'     => $isLocked,
                'submitted_at'  => $submission->submitted_at?->format('M d, Y h:i A'),
                'approved_at'   => $submission->approved_at?->format('M d, Y h:i A'),
                'approved_by'   => $submission->approvedBy ? [
                    'id'    => $submission->approvedBy->id,
                    'name'  => $submission->approvedBy->name,
                    'email' => $submission->approvedBy->email,
                    'role'  => $submission->approvedBy->role,
                ] : null,
                'reopened_at'   => $submission->reopened_at?->format('M d, Y h:i A'),
                'reopened_by'   => $submission->reopenedBy ? [
                    'id'    => $submission->reopenedBy->id,
                    'name'  => $submission->reopenedBy->name,
                    'email' => $submission->reopenedBy->email,
                ] : null,
                'reopen_reason' => $submission->reopen_reason,
                'remarks'       => $submission->remarks,
                'locked_at'     => $submission->locked_at?->format('M d, Y h:i A'),
                'created_at'    => $submission->created_at?->format('M d, Y h:i A'),
            ],
            'instructor' => [
                'id'              => $instructor->id,
                'name'            => $instructor->name,
                'email'           => $instructor->email,
                'initials'        => $initials,
                'color'           => $color,
                'department'      => $instructor->department?->name ?? ($userDept?->name ?? 'Computer Science'),
                'phone'           => $instructor->phone ?? '—',
                'role'            => $instructor->role,
                'office'          => $instructor->office ?? 'Staff Office',
                'year_level'      => $instructor->year_level ?? $level,
                'section'         => $section,
            ],
            'course' => [
                'id'         => $assignedCourse?->id,
                'title'      => $courseTitle,
                'code'       => $courseCode,
                'credits'    => $credit,
                'section'    => $section,
                'level'      => $level,
                'department' => $assignedCourse?->department?->name ?? ($instructor->department?->name ?? 'Computer Science'),
            ],
            'exams'      => $examsList,
            'students'   => $studentsList,
            'kpis'       => [
                'total_enrolled' => $totalStudentsCount,
                'total_exams'    => $exams->count(),
                'total_graded'   => $totalGraded,
                'avg_score'      => $classAvg,
                'pass_rate'      => $passRate,
                'highest_score'  => $highestScore,
                'lowest_score'   => $lowestScore,
            ],
            'checklist'  => [
                'academic_schedule' => [
                    'completed' => true,
                    'label'     => 'Academic Schedule Verified',
                    'detail'    => "Term {$submission->academic_year} ({$submission->semester}) verified",
                ],
                'exams' => [
                    'completed' => $exams->count() > 0,
                    'label'     => 'Examinations Completed',
                    'detail'    => $exams->count() . ' Examinations configured and conducted',
                ],
                'students' => [
                    'completed' => $totalStudentsCount > 0,
                    'label'     => 'Student Enrollment Verified',
                    'detail'    => $totalStudentsCount . ' Students active in section ' . $section,
                ],
                'results' => [
                    'completed' => $totalGraded > 0,
                    'label'     => 'Grades & Results Processed',
                    'detail'    => $totalGraded . ' Graded student submissions (' . $passRate . '% Pass Rate)',
                ],
            ],
        ]);
    }

    /**
     * Show detail for a specific year-level / id
     */
    public function show(Request $request, $id): JsonResponse
    {
        // If $id is base64 encoded year-level string (e.g. from SemesterSubmissions.vue overview cards)
        $decoded = base64_decode($id, true);
        if ($decoded !== false && ctype_print($decoded) && str_contains(strtolower($decoded), 'year')) {
            return $this->details($request, $id);
        }

        return $this->recordDetails($request, $id);
    }

    /**
     * Update status of an instructor semester submission
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status'  => 'required|string',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $normalizedStatus = strtolower(str_replace(' ', '_', trim($request->status)));

        // Valid statuses
        $allowed = ['pending', 'submitted', 'under_review', 'approved', 'rejected', 'correction_required', 'reopened'];
        if (!in_array($normalizedStatus, $allowed)) {
            $normalizedStatus = 'pending';
        }

        // Validate mandatory remarks for correction or rejection
        if ($normalizedStatus === 'correction_required' && empty(trim($request->remarks ?? ''))) {
            return response()->json(['message' => 'Please provide feedback or remarks describing what needs correction.'], 422);
        }

        if ($normalizedStatus === 'rejected' && empty(trim($request->remarks ?? ''))) {
            return response()->json(['message' => 'Please provide a reason for rejecting the submission.'], 422);
        }

        $user = $request->user();

        return DB::transaction(function () use ($request, $id, $normalizedStatus, $user) {
            // Find submission by ID
            $submission = SemesterSubmission::with(['instructor', 'approvedBy', 'reopenedBy'])->find($id);

            if (!$submission) {
                // Find by instructor_id
                $submission = SemesterSubmission::with(['instructor', 'approvedBy', 'reopenedBy'])->where('instructor_id', $id)->first();
            }

            if (!$submission) {
                $inst = User::find($id);
                if ($inst) {
                    $submission = SemesterSubmission::create([
                        'instructor_id' => $inst->id,
                        'academic_year' => $request->input('academic_year', '2025/2026'),
                        'semester'      => $request->input('semester', 'Second Semester'),
                        'department'    => $inst->department?->name ?? 'Computer Science',
                        'section'       => $inst->section ?? 'Section A',
                        'status'        => $normalizedStatus,
                        'remarks'       => $request->remarks,
                        'submitted_at'  => now(),
                    ]);
                    $submission->load(['instructor', 'approvedBy', 'reopenedBy']);
                }
            }

            if (!$submission) {
                return response()->json(['message' => 'Submission record not found.'], 404);
            }

            $submission->status = $normalizedStatus;

            if ($normalizedStatus === 'approved') {
                $submission->approved_at = now();
                $submission->approved_by = $user->id;
                // Approval does NOT lock the semester: semester access remains Open
            } elseif ($normalizedStatus === 'submitted') {
                // Newly submitted semester access state remains Open (locked_at remains null)
            } elseif ($normalizedStatus === 'reopened') {
                $submission->locked_at = null;
                $submission->reopened_at = now();
                $submission->reopened_by = $user->id;
                $submission->reopen_reason = $request->reason ?? $request->remarks ?? 'Reopened by Department Head';
            } elseif ($normalizedStatus === 'correction_required') {
                $submission->approved_at = null;
                $submission->approved_by = null;
                $submission->locked_at = null; // Unlocked for correction
            } elseif ($normalizedStatus === 'rejected') {
                $submission->approved_at = null;
                $submission->approved_by = null;
                $submission->locked_at = null;
            } elseif ($normalizedStatus === 'pending') {
                $submission->approved_at = null;
                $submission->approved_by = null;
                $submission->locked_at = null;
            }

            if ($request->has('remarks')) {
                $submission->remarks = $request->remarks;
            }

            $submission->save();

            $displayStatus = match ($normalizedStatus) {
                'approved' => 'Approved',
                'correction_required' => 'Correction Required',
                'rejected' => 'Rejected',
                'reopened' => 'Reopened',
                default => 'Pending',
            };

            // Record Activity Log
            $instName = $submission->instructor?->name ?? 'Instructor';
            $activityAction = match ($normalizedStatus) {
                'approved' => 'Approved',
                'correction_required' => 'Correction Requested',
                'rejected' => 'Rejected',
                'reopened' => 'Reopened',
                default => 'Updated',
            };

            LogActivity::record(
                $activityAction,
                'Semester Submissions',
                "{$displayStatus} semester submission for {$instName}" . ($submission->remarks ? ": {$submission->remarks}" : '')
            );

            return response()->json([
                'message'    => "Semester submission updated to {$displayStatus} successfully.",
                'submission' => [
                    'id'            => $submission->id,
                    'status'        => $displayStatus,
                    'raw_status'    => $submission->status,
                    'is_locked'     => $submission->isLocked(),
                    'remarks'       => $submission->remarks,
                    'approved_at'   => $submission->approved_at?->format('M d, Y h:i A'),
                    'approved_by'   => $user->name,
                    'reopened_at'   => $submission->reopened_at?->format('M d, Y h:i A'),
                    'reopened_by'   => $submission->reopenedBy?->name ?? null,
                    'reopen_reason' => $submission->reopen_reason,
                    'locked_at'     => $submission->locked_at?->format('M d, Y h:i A'),
                ]
            ]);
        });
    }

    /**
     * Explicitly close and lock semester submission / workspace for an instructor.
     * Enforces department authorization, records activity log, and marks locked_at timestamp.
     */
    public function lock(Request $request, $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();

        $submission = SemesterSubmission::with(['instructor.department', 'approvedBy', 'reopenedBy'])->find($id);
        if (!$submission) {
            $submission = SemesterSubmission::with(['instructor.department', 'approvedBy', 'reopenedBy'])->where('instructor_id', $id)->first();
        }

        if (!$submission) {
            return response()->json([
                'message' => 'Semester submission record not found. The submission must be submitted and approved first.'
            ], 404);
        }

        // Authorization check: Dept Head can only lock instructors in their department
        $inst = $submission->instructor;
        if ($deptId && $inst && $inst->department_id !== $deptId) {
            $hasAssignedCourse = Course::where('instructor_id', $inst->id)->where('department_id', $deptId)->exists();
            if (!$hasAssignedCourse && $user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized: Instructor does not belong to your department.'], 403);
            }
        }

        // Enforce: Submission must be approved first before it can be closed (locked)
        $isApproved = ($submission->status === 'approved' || !empty($submission->approved_at));
        if (!$isApproved) {
            return response()->json([
                'message' => 'Cannot close and lock semester: submission must be approved first.',
                'status'  => $submission->status
            ], 422);
        }

        // Verify that the submission is not already locked
        if ($submission->isLocked()) {
            return response()->json([
                'message' => 'Semester workspace is already closed and locked.',
                'status'  => $submission->status
            ], 422);
        }

        return DB::transaction(function () use ($submission, $user, $request) {
            $submission->status = 'approved';
            $submission->locked_at = now();
            if (!$submission->approved_at) {
                $submission->approved_at = now();
                $submission->approved_by = $user->id;
            }
            $submission->save();

            // Revoke active sessions ONLY for the instructor workspace, never the current Department Head session
            if ($submission->instructor_id) {
                $currentAccessTokenId = $request->user()?->currentAccessToken()?->id;

                $tokensQuery = \Laravel\Sanctum\PersonalAccessToken::where('tokenable_type', \App\Models\User::class)
                    ->where('tokenable_id', $submission->instructor_id);

                if ($currentAccessTokenId) {
                    $tokensQuery->where('id', '!=', $currentAccessTokenId);
                }

                // Specifically protect dept_head and admin tokens under all circumstances
                $tokensQuery->where('name', 'not like', '%dept_head%')
                    ->where('abilities', 'not like', '%role:dept_head%')
                    ->where('name', 'not like', '%admin%')
                    ->where('abilities', 'not like', '%role:admin%');

                // If the Department Head is locking their own assigned course,
                // only revoke instructor-specific tokens so the Dept Head session stays active.
                if ($submission->instructor_id === $user->id) {
                    $tokensQuery->where(function ($q) {
                        $q->where('name', 'like', '%instructor%')
                          ->orWhere('abilities', 'like', '%role:instructor%');
                    });
                } else {
                    $tokensQuery->where(function ($q) {
                        $q->where('name', 'like', '%instructor%')
                          ->orWhere('abilities', 'like', '%role:instructor%')
                          ->orWhere('name', 'auth_token');
                    });
                }

                $tokensQuery->delete();
            }

            $instName = $submission->instructor?->name ?? 'Instructor';
            $termStr = "{$submission->academic_year} — {$submission->semester}";

            LogActivity::record(
                'Locked',
                'Semester Submissions',
                "Closed and locked semester workspace for instructor {$instName} ({$termStr}). Write access restricted."
            );

            $displayStatus = 'Approved';

            return response()->json([
                'message' => "Semester workspace for {$instName} has been closed and locked successfully. Instructor access is restricted to read-only.",
                'submission' => [
                    'id'            => $submission->id,
                    'status'        => $displayStatus,
                    'raw_status'    => $submission->status,
                    'is_locked'     => true,
                    'locked_at'     => $submission->locked_at->format('M d, Y h:i A'),
                    'academic_year' => $submission->academic_year,
                    'semester'      => $submission->semester,
                    'approved_at'   => $submission->approved_at?->format('M d, Y h:i A'),
                    'approved_by'   => $submission->approvedBy?->name,
                    'reopened_at'   => $submission->reopened_at?->format('M d, Y h:i A'),
                    'reopened_by'   => $submission->reopenedBy?->name,
                ]
            ]);
        });
    }

    /**
     * Explicitly reopen a locked semester submission for an instructor.
     * Enforces department authorization, clears locked_at timestamp, and restores edit access.
     */
    public function reopen(Request $request, $id): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $deptId = $this->resolveDeptId($request);
        $user = $request->user();

        $submission = SemesterSubmission::with(['instructor.department', 'approvedBy', 'reopenedBy'])->find($id);
        if (!$submission) {
            $submission = SemesterSubmission::with(['instructor.department', 'approvedBy', 'reopenedBy'])->where('instructor_id', $id)->first();
        }

        if (!$submission) {
            return response()->json(['message' => 'Semester submission record not found.'], 404);
        }

        // Authorization check: Dept Head can only reopen instructors in their department
        $inst = $submission->instructor;
        if ($deptId && $inst && $inst->department_id !== $deptId) {
            $hasAssignedCourse = Course::where('instructor_id', $inst->id)->where('department_id', $deptId)->exists();
            if (!$hasAssignedCourse && $user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized: Instructor does not belong to your department.'], 403);
            }
        }

        // Verify that the submission is currently locked
        if (!$submission->isLocked()) {
            return response()->json([
                'message' => 'Cannot unlock semester: submission is not currently locked.',
                'status'  => $submission->status
            ], 422);
        }

        $reason = $request->input('reason', 'Reopened by Department Head for modifications');

        return DB::transaction(function () use ($submission, $reason, $user) {
            $submission->status = 'approved'; // Submission status remains Approved
            $submission->reopened_at = now();
            $submission->reopened_by = $user->id;
            $submission->reopen_reason = $reason;
            $submission->locked_at = null; // Access state becomes Open!
            $submission->save();

            $instName = $submission->instructor?->name ?? 'Instructor';
            $termStr = "{$submission->academic_year} — {$submission->semester}";
            LogActivity::record(
                'Reopened',
                'Semester Submissions',
                "Reopened semester workspace for {$instName} ({$termStr}). Reason: {$reason}. Edit access restored."
            );

            return response()->json([
                'message' => "Semester submission for {$instName} has been reopened successfully. Academic edit access is restored.",
                'submission' => [
                    'id'            => $submission->id,
                    'status'        => 'Approved',
                    'raw_status'    => 'approved',
                    'is_locked'     => false,
                    'locked_at'     => null,
                    'reopened_at'   => $submission->reopened_at->format('M d, Y h:i A'),
                    'reopened_by'   => $user->name,
                    'reopen_reason' => $submission->reopen_reason,
                    'approved_at'   => $submission->approved_at?->format('M d, Y h:i A'),
                    'academic_year' => $submission->academic_year,
                    'semester'      => $submission->semester,
                ]
            ]);
        });
    }

    /**
     * Export Semester Submissions as PDF, Excel, or CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $userDept = $deptId ? Department::find($deptId) : null;
        $deptName = ucwords(strtolower($userDept ? $userDept->name : 'Computer Science'));
        $format = strtolower($request->input('format', 'pdf'));
        $dateStr = Carbon::now()->format('Y-m-d');
        $baseFileName = "Semester_Submissions_Report_{$dateStr}";

        // Get details data
        $detailsResponse = $this->details($request);
        $detailsData = $detailsResponse->getData(true);
        $semesterInfo = $detailsData['semester_info'] ?? [];
        $instructors = $detailsData['instructors'] ?? [];

        if ($format === 'pdf') {
            return $this->exportPdf($instructors, $semesterInfo, $deptName, $baseFileName);
        }

        return $this->exportCsv($instructors, $semesterInfo, $deptName, $baseFileName, $format === 'excel' || $format === 'xlsx');
    }

    private function exportPdf(array $instructors, array $info, string $deptName, string $baseFileName): JsonResponse
    {
        $generatedAt = Carbon::now()->format('M d, Y h:i A');
        $acadYear = $info['academicYear'] ?? '2025/2026';
        $semester = $info['semester'] ?? 'Second Semester';

        $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 15px; }
  .header { border-bottom: 2px solid #5138ed; padding-bottom: 10px; margin-bottom: 14px; }
  .title { font-size: 17px; font-weight: bold; color: #1e1b4b; margin: 0 0 3px 0; }
  .subtitle { font-size: 11px; color: #64748b; margin: 0; }
  .meta-table { width: 100%; margin-top: 6px; font-size: 9.5px; color: #475569; }
  
  .kpi-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin-bottom: 16px; }
  .kpi-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; text-align: left; }
  .kpi-title { font-size: 8.5px; text-transform: uppercase; color: #64748b; font-weight: bold; margin-bottom: 3px; }
  .kpi-val { font-size: 15px; font-weight: bold; color: #0f172a; }

  table.data-table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 9.5px; }
  table.data-table th { background: #f1f5f9; color: #334155; font-weight: bold; text-align: left; padding: 6px 7px; border: 1px solid #e2e8f0; }
  table.data-table td { padding: 5px 7px; border: 1px solid #e2e8f0; }
  table.data-table tr:nth-child(even) { background: #f8fafc; }

  .status-approved { color: #059669; font-weight: bold; background: #ecfdf5; padding: 2px 5px; border-radius: 3px; }
  .status-pending { color: #d97706; font-weight: bold; background: #fffbeb; padding: 2px 5px; border-radius: 3px; }
  .status-correction { color: #ea580c; font-weight: bold; background: #fff7ed; padding: 2px 5px; border-radius: 3px; }
  .status-rejected { color: #dc2626; font-weight: bold; background: #fef2f2; padding: 2px 5px; border-radius: 3px; }
  .footer { margin-top: 20px; text-align: right; font-size: 8.5px; color: #94a3b8; }
</style>
</head>
<body>
  <div class="header">
    <h1 class="title">Wollo University &mdash; Semester Submissions Report</h1>
    <p class="subtitle">Instructor semester records and grading approval summary for Department of ' . htmlspecialchars($deptName) . '</p>
    <table class="meta-table">
      <tr>
        <td style="width: 50%;"><strong>Academic Term:</strong> ' . htmlspecialchars($acadYear) . ' &bull; ' . htmlspecialchars($semester) . '</td>
        <td style="width: 50%; text-align: right;"><strong>Generated On:</strong> ' . $generatedAt . '</td>
      </tr>
    </table>
  </div>

  <table class="kpi-table">
    <tr>
      <td class="kpi-card" style="width: 25%;">
        <div class="kpi-title">Pending Review</div>
        <div class="kpi-val" style="color: #d97706;">' . ($info['pendingReview'] ?? 0) . '</div>
      </td>
      <td class="kpi-card" style="width: 25%;">
        <div class="kpi-title">Approved</div>
        <div class="kpi-val" style="color: #059669;">' . ($info['approved'] ?? 0) . '</div>
      </td>
      <td class="kpi-card" style="width: 25%;">
        <div class="kpi-title">Correction Required</div>
        <div class="kpi-val" style="color: #ea580c;">' . ($info['correctionRequired'] ?? 0) . '</div>
      </td>
      <td class="kpi-card" style="width: 25%;">
        <div class="kpi-title">Total Submissions</div>
        <div class="kpi-val" style="color: #5138ed;">' . ($info['total'] ?? 0) . '</div>
      </td>
    </tr>
  </table>

  <table class="data-table">
    <thead>
      <tr>
        <th style="width: 4%;">#</th>
        <th style="width: 22%;">Instructor</th>
        <th style="width: 22%;">Course & Code</th>
        <th style="width: 10%;">Section</th>
        <th style="width: 6%; text-align: center;">Credit</th>
        <th style="width: 14%;">Submitted</th>
        <th style="width: 12%;">Status</th>
        <th style="width: 10%;">Remarks</th>
      </tr>
    </thead>
    <tbody>';
        foreach ($instructors as $idx => $inst) {
            $stClass = match(strtolower($inst['status'] ?? 'pending')) {
                'approved' => 'status-approved',
                'correction required' => 'status-correction',
                'rejected' => 'status-rejected',
                default => 'status-pending'
            };
            $html .= '<tr>
        <td>' . ($idx + 1) . '</td>
        <td><strong>' . htmlspecialchars($inst['name']) . '</strong><br/><span style="color:#64748b;">' . htmlspecialchars($inst['email']) . '</span></td>
        <td><strong>' . htmlspecialchars($inst['course']) . '</strong><br/><span style="color:#64748b; font-family:monospace;">' . htmlspecialchars($inst['course_code']) . '</span></td>
        <td>' . htmlspecialchars($inst['section']) . '</td>
        <td style="text-align: center;">' . ($inst['credit'] ?? 4) . '</td>
        <td>' . htmlspecialchars($inst['submitted_date']) . '<br/><span style="color:#64748b;">' . htmlspecialchars($inst['submitted_time']) . '</span></td>
        <td><span class="' . $stClass . '">' . htmlspecialchars($inst['status']) . '</span></td>
        <td>' . htmlspecialchars($inst['remarks'] ?? '—') . '</td>
      </tr>';
        }
        $html .= '</tbody>
  </table>

  <div class="footer">
    <p>Official Wollo University Online Examination System &bull; Confidential Document &bull; Page 1 of 1</p>
  </div>
</body>
</html>';

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $pdfBytes = $dompdf->output();

        LogActivity::record('Exported', 'Semester Submissions', "Exported semester submissions report as PDF ({$deptName})");

        return response()->json([
            'file'     => base64_encode($pdfBytes),
            'filename' => $baseFileName . '.pdf',
            'format'   => 'pdf',
        ]);
    }

    private function exportCsv(array $instructors, array $info, string $deptName, string $baseFileName, bool $asXlsx = false): JsonResponse
    {
        ob_start();
        $handle = fopen('php://output', 'w');
        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['WOLLO UNIVERSITY - SEMESTER SUBMISSIONS REPORT']);
        fputcsv($handle, ['Department:', $deptName]);
        fputcsv($handle, ['Academic Year:', $info['academicYear'] ?? '2025/2026']);
        fputcsv($handle, ['Semester:', $info['semester'] ?? 'Second Semester']);
        fputcsv($handle, ['Generated At:', Carbon::now()->toDateTimeString()]);
        fputcsv($handle, []);

        fputcsv($handle, ['#', 'Instructor Name', 'Email', 'Department', 'Course', 'Course Code', 'Section', 'Credit', 'Submitted Date', 'Status', 'Remarks']);
        foreach ($instructors as $i => $inst) {
            fputcsv($handle, [
                $i + 1,
                $inst['name'],
                $inst['email'],
                $inst['department'],
                $inst['course'],
                $inst['course_code'],
                $inst['section'],
                $inst['credit'],
                $inst['submitted_date'] . ' ' . $inst['submitted_time'],
                $inst['status'],
                $inst['remarks'] ?? '',
            ]);
        }

        fclose($handle);
        $csvContent = ob_get_clean();

        $format = $asXlsx ? 'xlsx' : 'csv';
        LogActivity::record('Exported', 'Semester Submissions', "Exported semester submissions report as {$format} ({$deptName})");

        return response()->json([
            'file'     => base64_encode($csvContent),
            'filename' => $baseFileName . '.' . $format,
            'format'   => $format,
        ]);
    }
}
