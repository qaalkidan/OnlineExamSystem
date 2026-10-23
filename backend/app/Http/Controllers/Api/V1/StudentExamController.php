<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StudentExamController extends Controller
{
    /**
     * Auto-finalize any in-progress attempts belonging to the student that have expired.
     */
    protected function autoFinalizeExpiredAttempts(?User $student): void
    {
        if (!$student) {
            return;
        }

        $inProgressAttempts = ExamAttempt::where('user_id', $student->id)
            ->where('status', 'in_progress')
            ->with(['exam.questions'])
            ->get();

        foreach ($inProgressAttempts as $att) {
            if ($att->getRemainingSeconds() <= 0) {
                $this->finalizeAttempt($att);
            }
        }
    }

    /**
     * Dashboard stats for the student.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $student = $request->user();
        $this->autoFinalizeExpiredAttempts($student);

        // Count completed attempts with published results
        $completedAttempts = ExamAttempt::where('user_id', $student->id)
            ->where('status', 'published')
            ->count();

        // Average score across published attempts
        $avgScore = ExamAttempt::where('user_id', $student->id)
            ->where('status', 'published')
            ->whereNotNull('percentage')
            ->avg('percentage');

        // Count upcoming published/scheduled exams for student's department & year_level that haven't been completed
        $completedAttemptExamIds = ExamAttempt::where('user_id', $student->id)
            ->where(function($q) {
                $q->whereIn('status', ['submitted', 'graded', 'published'])
                  ->orWhereNotNull('submitted_at');
            })
            ->pluck('exam_id');

        $upcomingCount = $this->getAvailableExamsQuery($student)
            ->whereNotIn('id', $completedAttemptExamIds)
            ->count();

        return response()->json([
            'data' => [
                'completed_exams' => $completedAttempts,
                'upcoming_exams'  => $upcomingCount,
                'average_score'   => round($avgScore ?? 0, 1),
            ]
        ]);
    }

    /**
     * List all published/scheduled exams for the student's course.
     * Returns ALL non-submitted exams in "upcoming_exams" with an attemptStatus field.
     * The frontend Ready Card decides what to show based on time window + attemptStatus.
     */
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();
        $this->autoFinalizeExpiredAttempts($student);

        // Get all published/scheduled exams for the student's department, year_level & section
        $exams = $this->getAvailableExamsQuery($student)
            ->with(['instructor:id,name,email', 'course:id,title,code,credits,level,semester'])
            ->orderBy('scheduled_at', 'asc')
            ->get();

        // Get all attempts by this student, keyed by exam_id
        $attempts = ExamAttempt::where('user_id', $student->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->get()
            ->keyBy('exam_id');

        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay();
        $upcomingExams = [];

        foreach ($exams as $exam) {
            $rawStatus = strtolower($exam->status ?? '');
            if ($rawStatus === 'cancelled' || $exam->cancelled_at !== null) {
                continue;
            }

            $attempt = $attempts->get($exam->id);
            $attemptStatus = $attempt ? $attempt->status : null; // null | 'in_progress' | 'submitted' | 'graded' | 'published'

            $duration = $exam->duration_minutes ?? 60;
            $start = $exam->scheduled_at ? Carbon::parse($exam->scheduled_at) : null;
            $end = $start ? $start->copy()->addMinutes($duration) : null;

            $isSubmitted = $attempt && (in_array($attempt->status, ['submitted', 'graded', 'published']) || $attempt->submitted_at !== null);
            $isExpired = $end && $now->gte($end) && $attemptStatus !== 'in_progress';

            // Strictly exclude completed exams and closed/expired exams from active upcoming exams
            if ($isSubmitted || ($isExpired && $attemptStatus !== 'in_progress')) {
                continue;
            }

            // Determine authoritative display status for student UI
            if ($attemptStatus === 'in_progress') {
                $status = 'In Progress';
            } elseif ($start && $end && $now->between($start, $end)) {
                $status = 'Available Now';
            } elseif ($start && $now->lt($start)) {
                $diffMinutes = $now->diffInMinutes($start, false);
                if ($diffMinutes <= 30) {
                    $status = 'Starting Soon';
                } else {
                    $status = 'Upcoming';
                }
            } else {
                $status = 'Available Now';
            }

            $scheduledIso = $start ? $start->toIso8601String() : null;
            $endIso = $end ? $end->toIso8601String() : null;
            $examType = $this->determineExamType($exam);
            $examCode = $this->formatExamCode($exam);
            $durationFormatted = $this->formatDuration($duration);
            $sectionDisplay = $exam->section ?: ($exam->settings['section'] ?? 'All Sections');

            $upcomingExams[] = [
                'id'                 => $exam->id,
                'title'              => $exam->title,
                'examTitle'          => $exam->title,
                'code'               => $examCode,
                'examCode'           => $examCode,
                'examType'           => $examType,
                'exam_type'          => $examType,
                'courseCode'         => $exam->course_code ?: ($exam->course?->code ?? 'N/A'),
                'course_code'        => $exam->course_code ?: ($exam->course?->code ?? 'N/A'),
                'courseName'         => $exam->course_name ?: ($exam->course?->title ?? 'Course'),
                'course_name'        => $exam->course_name ?: ($exam->course?->title ?? 'Course'),
                'instructor'         => $exam->instructor->name ?? 'Unassigned Faculty',
                'instructor_name'    => $exam->instructor->name ?? 'Unassigned Faculty',
                'instructor_email'   => $exam->instructor->email ?? '',
                'section'            => $sectionDisplay,
                'scheduledAt'        => $scheduledIso,
                'scheduled_at'       => $scheduledIso,
                'scheduledDate'      => $scheduledIso,
                'endAt'              => $endIso,
                'end_at'             => $endIso,
                'date'               => $start ? $start->format('M d, Y') : 'TBD',
                'startTime'          => $start ? $start->format('h:i A') : 'TBD',
                'start_time'         => $start ? $start->format('h:i A') : 'TBD',
                'endTime'            => $end ? $end->format('h:i A') : 'TBD',
                'end_time'           => $end ? $end->format('h:i A') : 'TBD',
                'timeRange'          => $start && $end ? ($start->format('h:i A') . ' - ' . $end->format('h:i A')) : 'Flexible Window',
                'durationMinutes'    => $duration,
                'duration_minutes'   => $duration,
                'duration'           => $durationFormatted,
                'totalMarks'         => $exam->total_marks ?? 100,
                'total_marks'        => $exam->total_marks ?? 100,
                'totalQuestions'     => $exam->questions()->count(),
                'total_questions'    => $exam->questions()->count(),
                'status'             => $status,
                'raw_status'         => $rawStatus,
                'room'               => $exam->settings['room'] ?? 'Online Examination Portal',
                'instructions'       => $exam->settings['instructions'] ?? $exam->description ?? '',
                'description'        => $exam->description ?? '',
                'allowCalculator'    => (bool)($exam->settings['allowCalculator'] ?? false),
                'allow_calculator'   => (bool)($exam->settings['allowCalculator'] ?? false),
                'attemptStatus'      => $attemptStatus,
                'attempt_status'     => $attemptStatus,
                'attemptId'          => $attempt ? $attempt->id : null,
                'attempt_id'         => $attempt ? $attempt->id : null,
                'attemptStartedAt'   => ($attempt && $attempt->started_at) ? $attempt->started_at->toIso8601String() : null,
                'attempt_started_at' => ($attempt && $attempt->started_at) ? $attempt->started_at->toIso8601String() : null,
                'isResultPublished'  => $attempt ? ($attempt->status === 'published' || ($exam->results_published ?? false)) : false,
            ];
        }

        // Priority sort: In Progress -> Available Now -> Starting Soon -> Upcoming (chronological) -> Completed -> Closed
        usort($upcomingExams, function ($a, $b) {
            $priority = [
                'In Progress'   => 1,
                'Available Now' => 2,
                'Starting Soon' => 3,
                'Upcoming'      => 4,
                'Completed'     => 5,
                'Closed'        => 6,
            ];
            $pa = $priority[$a['status']] ?? 99;
            $pb = $priority[$b['status']] ?? 99;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            return ($a['scheduledAt'] ?? '') <=> ($b['scheduledAt'] ?? '');
        });

        // active_exam is derived from in_progress exam for backward compatibility
        $activeExamData = null;
        foreach ($upcomingExams as $e) {
            if ($e['attemptStatus'] === 'in_progress') {
                $activeExamData = [
                    'id'              => $e['id'],
                    'attempt_id'      => $e['attemptId'],
                    'courseCode'      => $e['courseCode'],
                    'courseName'      => $e['courseName'],
                    'examTitle'       => $e['examType'],
                    'instructor'      => $e['instructor'],
                    'date'            => $e['date'],
                    'time'            => $e['startTime'],
                    'durationMinutes' => $e['durationMinutes'],
                    'totalMarks'      => $e['totalMarks'],
                    'started_at'      => $e['attemptStartedAt'],
                ];
                break;
            }
        }

        $serverTime = Carbon::now()->toIso8601String();

        return response()->json([
            'status'      => 'success',
            'server_time' => $serverTime,
            'data'        => [
                'active_exam'    => $activeExamData,
                'upcoming_exams' => $upcomingExams,
                'server_time'    => $serverTime,
            ]
        ]);
    }

    /**
     * Build base query for exams available to a given student based on department, year_level, and section.
     */
    public function getAvailableExamsQuery($student)
    {
        $studentSec = trim($student->section ?? '');
        $cleanStudentSec = strtoupper(trim(preg_replace('/^section\s*/i', '', $studentSec)));
        $yearNum = preg_replace('/[^0-9]/', '', $student->year_level ?? '');

        // Build list of acceptable year level strings
        $allowedYearLevels = array_unique(array_filter([
            $student->year_level,
            trim($student->year_level ?? ''),
        ]));
        $yearWords = [
            '1' => ['1st Year', '1st year', 'Year 1', 'year 1', '1', 'First Year', 'first year', '1st', 'Year-1'],
            '2' => ['2nd Year', '2nd year', 'Year 2', 'year 2', '2', 'Second Year', 'second year', '2nd', 'Year-2'],
            '3' => ['3rd Year', '3rd year', 'Year 3', 'year 3', '3', 'Third Year', 'third year', '3rd', 'Year-3'],
            '4' => ['4th Year', '4th year', 'Year 4', 'year 4', '4', 'Fourth Year', 'fourth year', '4th', 'Year-4'],
            '5' => ['5th Year', '5th year', 'Year 5', 'year 5', '5', 'Fifth Year', 'fifth year', '5th', 'Year-5'],
        ];
        if ($yearNum && isset($yearWords[$yearNum])) {
            $allowedYearLevels = array_unique(array_merge($allowedYearLevels, $yearWords[$yearNum]));
        }

        // Build list of acceptable section strings
        $allowedSections = [
            '', 'Both', 'both', 'Both Sections', 'both sections', 'BOTH SECTIONS',
            'All', 'all', 'All Sections', 'all sections', 'ALL SECTIONS',
            'Section A and B', 'Section A & B', 'Section A, B', 'A and B', 'A & B', 'A, B'
        ];
        if ($studentSec !== '') {
            $allowedSections[] = $studentSec;
        }
        if ($cleanStudentSec !== '') {
            $allowedSections[] = $cleanStudentSec;
            $allowedSections[] = strtolower($cleanStudentSec);
            $allowedSections[] = "Section $cleanStudentSec";
            $allowedSections[] = "section " . strtolower($cleanStudentSec);
            $allowedSections[] = "SECTION $cleanStudentSec";
        }
        $allowedSections = array_unique($allowedSections);

        return Exam::whereIn('status', ['published', 'scheduled'])
            ->where(function ($query) use ($allowedSections, $cleanStudentSec, $studentSec) {
                $query->whereNull('section')
                      ->orWhere('section', '')
                      ->orWhereIn('section', $allowedSections)
                      ->orWhereRaw("LOWER(TRIM(section)) IN ('all', 'all sections', 'both', 'both sections', 'section a and b', 'section a & b', 'a and b', 'a & b', 'section a, b', 'a, b', 'a,b')");

                if ($cleanStudentSec !== '') {
                    $query->orWhereRaw("LOWER(TRIM(REPLACE(section, 'Section', ''))) = ?", [strtolower($cleanStudentSec)])
                          ->orWhereRaw("LOWER(TRIM(section)) = ?", [strtolower($studentSec)])
                          ->orWhereRaw("LOWER(TRIM(section)) = ?", ['section ' . strtolower($cleanStudentSec)]);
                }
            })
            ->where(function ($query) use ($student, $allowedYearLevels) {
                // 1. Match via direct student enrollment in exam_student
                $query->whereHas('students', function ($sq) use ($student) {
                    $sq->where('users.id', $student->id);
                })
                // 2. OR match via Instructor's department & year_level
                ->orWhereHas('instructor', function ($q) use ($student, $allowedYearLevels) {
                    if ($student->department_id) {
                        $q->where('department_id', $student->department_id);
                    }
                    if (!empty($allowedYearLevels)) {
                        $q->where(function ($yq) use ($allowedYearLevels) {
                            $yq->whereNull('year_level')
                               ->orWhere('year_level', '')
                               ->orWhere('year_level', 'None')
                               ->orWhereIn('year_level', $allowedYearLevels);
                        });
                    }
                })
                // 3. OR match via Exam's course department & level
                ->orWhereHas('course', function ($q) use ($student, $allowedYearLevels) {
                    if ($student->department_id) {
                        $q->where('department_id', $student->department_id);
                    }
                    if (!empty($allowedYearLevels)) {
                        $q->where(function ($yq) use ($allowedYearLevels) {
                            $yq->whereNull('level')
                               ->orWhere('level', '')
                               ->orWhereIn('level', $allowedYearLevels);
                        });
                    }
                });
            });
    }

    /**
     * Start an exam attempt. Returns questions WITHOUT correct answers.
     */
    public function start(Request $request, Exam $exam): JsonResponse
    {
        $student = $request->user();

        if ($student->role !== 'student') {
            return response()->json([
                'message' => 'You are currently authenticated as ' . $student->role . ' (' . $student->name . '), not a student. Please log in with your student account.',
            ], 403);
        }

        // Verify exam is published or scheduled
        if (!in_array($exam->status, ['published', 'scheduled'])) {
            if ($exam->isCancelled()) {
                return response()->json([
                    'message'             => 'This examination has been cancelled by an administrator.',
                    'is_cancelled'        => true,
                    'cancellation_reason' => $exam->cancellation_reason,
                ], 403);
            }
            return response()->json(['message' => 'This exam is not available.'], 403);
        }

        if ($exam->isCancelled()) {
            return response()->json([
                'message'             => 'This examination has been cancelled by an administrator.',
                'is_cancelled'        => true,
                'cancellation_reason' => $exam->cancellation_reason,
            ], 403);
        }

        if ($exam->isPaused()) {
            return response()->json([
                'message'   => 'This examination is currently paused by an administrator. Please wait for resumption.',
                'is_paused' => true,
            ], 403);
        }

        // Check if student already has an attempt FIRST (to allow resuming in-progress attempts)
        $existingAttempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('user_id', $student->id)
            ->first();

        if ($existingAttempt && $existingAttempt->status === 'in_progress') {
            // Check if student's allocated time (including any approved extra time) has fully elapsed
            $remainingSeconds = $existingAttempt->getRemainingSeconds();
            if ($remainingSeconds <= 0) {
                $this->finalizeAttempt($existingAttempt);
                return response()->json([
                    'message'      => 'Your examination time has expired. Your attempt has been automatically finalized.',
                    'time_expired' => true,
                ], 403);
            }

            // Retrieve saved question order if available
            $savedOrder = $existingAttempt->question_order 
                ?? ($existingAttempt->answers['_question_order'] ?? null);
            $savedOptionOrder = $existingAttempt->option_order 
                ?? ($existingAttempt->answers['_option_order'] ?? null);

            $allQuestions = $exam->questions()->get();
            $isShuffleQuestions = $this->isShuffleQuestionsEnabled($exam);
            $isShuffleAnswers = $this->isShuffleAnswersEnabled($exam);

            if (!empty($savedOrder) && is_array($savedOrder)) {
                $orderedQuestions = $this->orderQuestionsByIds($allQuestions, $savedOrder);
            } elseif ($isShuffleQuestions) {
                // If attempt was created before shuffle was persisted, shuffle and persist now
                $orderedQuestions = $this->shuffleExamQuestions($allQuestions);
                $orderIds = $orderedQuestions->pluck('id')->map(fn($id) => (int)$id)->all();
                $existingAttempt->question_order = $orderIds;
                $answers = $existingAttempt->answers ?? [];
                $answers['_question_order'] = $orderIds;
                $existingAttempt->answers = $answers;
                $existingAttempt->save();
            } else {
                $orderedQuestions = $allQuestions;
            }

            $currentOptionOrders = is_array($savedOptionOrder) ? $savedOptionOrder : [];
            $optionOrdersUpdated = false;

            // Resume existing attempt — return questions in consistent attempt order
            $questions = $orderedQuestions->map(function ($q) use (&$currentOptionOrders, &$optionOrdersUpdated, $isShuffleAnswers) {
                $isMatching = $q->type === 'matching' || strtolower((string)$q->type) === 'matching';
                [$colA, $colB] = $this->extractMatchingColumns($q);

                $options = !$isMatching ? ($q->options ?? []) : [];
                if (!$isMatching && !empty($options) && count($options) > 1) {
                    if (!empty($currentOptionOrders[$q->id])) {
                        $options = $this->applyOptionOrder($options, $currentOptionOrders[$q->id]);
                    } elseif ($isShuffleAnswers) {
                        $res = $this->shuffleQuestionOptions($options);
                        $options = $res['options'];
                        $currentOptionOrders[$q->id] = $res['order_map'];
                        $optionOrdersUpdated = true;
                    }
                }

                return [
                    'id'          => $q->id,
                    'instruction' => $q->instruction,
                    'text'        => $q->text,
                    'type'        => $this->mapQuestionType($q->type),
                    'options'     => $options,
                    'pairs'       => $isMatching ? $this->extractMatchingPairs($q) : null,
                    'columnA'     => $isMatching ? $colA : null,
                    'columnB'     => $isMatching ? $colB : null,
                    'marks'       => $q->marks,
                ];
            });

            if ($optionOrdersUpdated) {
                $existingAttempt->option_order = $currentOptionOrders;
                $ans = $existingAttempt->answers ?? [];
                $ans['_option_order'] = $currentOptionOrders;
                $existingAttempt->answers = $ans;
            }

            $existingAttempt->last_heartbeat_at = now();
            $existingAttempt->save();

            // Filter internal metadata keys from saved_answers
            $cleanSavedAnswers = $existingAttempt->answers ?? [];
            if (is_array($cleanSavedAnswers)) {
                $cleanSavedAnswers = array_filter(
                    $cleanSavedAnswers,
                    fn($k) => !str_starts_with((string)$k, '_'),
                    ARRAY_FILTER_USE_KEY
                );
            }

            return response()->json([
                'data' => [
                    'attempt_id'         => $existingAttempt->id,
                    'exam_title'         => $exam->title,
                    'course_code'        => $exam->course_code,
                    'course_name'        => $exam->course_name,
                    'duration_minutes'   => $exam->duration_minutes,
                    'total_marks'        => $exam->total_marks,
                    'started_at'         => $existingAttempt->started_at->toISOString(),
                    'saved_answers'      => $cleanSavedAnswers,
                    'extra_time_seconds' => $existingAttempt->extra_time_seconds ?? 0,
                    'adjusted_deadline'  => $existingAttempt->getAdjustedDeadline()?->toIso8601String(),
                    'remaining_seconds'  => $remainingSeconds,
                    'is_cancelled'       => false,
                    'settings'           => $exam->settings ?? [],
                    'questions'          => $questions,
                ]
            ]);
        }

        if ($existingAttempt && (in_array($existingAttempt->status, ['submitted', 'graded', 'published']) || $existingAttempt->submitted_at !== null)) {
            return response()->json(['message' => 'You have already completed this exam.'], 409);
        }

        // Enforce time window ONLY for starting NEW attempts
        if ($exam->scheduled_at) {
            $now = Carbon::now();
            $examStart = $exam->scheduled_at;
            $examEnd = $exam->scheduled_at->copy()->addMinutes($exam->duration_minutes);

            if ($now->lt($examStart)) {
                return response()->json([
                    'message' => 'This exam has not started yet. It starts at ' . $examStart->format('g:i A') . '.'
                ], 403);
            }

            if ($now->gte($examEnd)) {
                return response()->json([
                    'message' => 'This exam has already ended.'
                ], 403);
            }
        }

        $allQuestions = $exam->questions()->get();
        $isShuffleQuestions = $this->isShuffleQuestionsEnabled($exam);
        $isShuffleAnswers = $this->isShuffleAnswersEnabled($exam);

        if ($isShuffleQuestions) {
            $orderedQuestions = $this->shuffleExamQuestions($allQuestions);
            $questionOrder = $orderedQuestions->pluck('id')->map(fn($id) => (int)$id)->all();
        } else {
            $orderedQuestions = $allQuestions;
            $questionOrder = $allQuestions->pluck('id')->map(fn($id) => (int)$id)->all();
        }

        $attemptOptionOrder = [];

        // Return questions in the attempt's assigned order WITHOUT correct_answer
        $questions = $orderedQuestions->map(function ($q) use ($isShuffleAnswers, &$attemptOptionOrder) {
            $isMatching = $q->type === 'matching' || strtolower((string)$q->type) === 'matching';
            [$colA, $colB] = $this->extractMatchingColumns($q);

            $options = !$isMatching ? ($q->options ?? []) : [];
            if (!$isMatching && !empty($options) && count($options) > 1 && $isShuffleAnswers) {
                $res = $this->shuffleQuestionOptions($options);
                $options = $res['options'];
                $attemptOptionOrder[$q->id] = $res['order_map'];
            }

            return [
                'id'          => $q->id,
                'instruction' => $q->instruction,
                'text'        => $q->text,
                'type'        => $this->mapQuestionType($q->type),
                'options'     => $options,
                'pairs'       => $isMatching ? $this->extractMatchingPairs($q) : null,
                'columnA'     => $isMatching ? $colA : null,
                'columnB'     => $isMatching ? $colB : null,
                'marks'       => $q->marks,
            ];
        });

        // Create new attempt with deterministic question order and option order
        $attempt = ExamAttempt::create([
            'exam_id'            => $exam->id,
            'user_id'            => $student->id,
            'total_marks'        => $exam->total_marks,
            'status'             => 'in_progress',
            'started_at'         => now(),
            'last_heartbeat_at'  => now(),
            'extra_time_seconds' => 0,
            'question_order'     => $questionOrder,
            'option_order'       => $attemptOptionOrder,
            'answers'            => [
                '_question_order' => $questionOrder,
                '_option_order'   => $attemptOptionOrder,
            ],
        ]);

        \App\Helpers\LogActivity::record(
            'Exam Attempt Started',
            'Examinations',
            "Student {$student->name} started attempt on \"{$exam->title}\"."
        );

        return response()->json([
            'data' => [
                'attempt_id'         => $attempt->id,
                'exam_title'         => $exam->title,
                'course_code'        => $exam->course_code,
                'course_name'        => $exam->course_name,
                'duration_minutes'   => $exam->duration_minutes,
                'total_marks'        => $exam->total_marks,
                'started_at'         => $attempt->started_at->toISOString(),
                'saved_answers'      => [],
                'extra_time_seconds' => 0,
                'adjusted_deadline'  => $attempt->getAdjustedDeadline()?->toIso8601String(),
                'remaining_seconds'  => $attempt->getRemainingSeconds(),
                'is_cancelled'       => false,
                'settings'           => $exam->settings ?? [],
                'questions'          => $questions,
            ]
        ], 201);
    }

    /**
     * Submit exam answers. Auto-grades MCQ and True/False.
     */
    public function submit(Request $request, Exam $exam): JsonResponse
    {
        $student = $request->user();

        if ($student->role !== 'student') {
            return response()->json([
                'message' => 'You are currently authenticated as ' . $student->role . ' (' . $student->name . '), not a student. Please log in with your student account.',
            ], 403);
        }

        if ($exam->isCancelled()) {
            return response()->json([
                'message'             => 'This examination has been cancelled by an administrator. Submission cannot be processed.',
                'is_cancelled'        => true,
                'cancellation_reason' => $exam->cancellation_reason,
            ], 403);
        }

        $attempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('user_id', $student->id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if (!$attempt) {
            $alreadyDone = ExamAttempt::where('exam_id', $exam->id)
                ->where('user_id', $student->id)
                ->where(function($q) {
                    $q->whereIn('status', ['submitted', 'graded', 'published'])
                      ->orWhereNotNull('submitted_at');
                })
                ->latest()
                ->first();

            if ($alreadyDone) {
                return response()->json([
                    'message' => 'This exam has already been submitted.',
                    'data' => [
                        'attempt_id'      => $alreadyDone->id,
                        'auto_score'      => $alreadyDone->score,
                        'auto_total'      => $alreadyDone->total_marks,
                        'pending_total'   => 0,
                        'has_pending'     => false,
                        'score'           => $alreadyDone->score,
                        'total_marks'     => $alreadyDone->total_marks,
                        'percentage'      => $alreadyDone->percentage,
                        'grade'           => $alreadyDone->grade,
                        'status'          => $alreadyDone->status,
                        'exam_title'      => $exam->title,
                        'course_code'     => $exam->course_code,
                        'course_name'     => $exam->course_name,
                        'questionsReview' => [],
                    ]
                ], 200);
            }

            return response()->json(['message' => 'Active exam attempt not found.'], 404);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
        ]);

        $submittedAnswers = $validated['answers'];
        $resultData = $this->finalizeAttempt($attempt, $submittedAnswers);

        return response()->json([
            'data' => $resultData
        ]);
    }

    /**
     * Finalize an exam attempt, calculate grades for auto-gradeable questions,
     * update the attempt status to 'submitted' or 'published', and set submitted_at.
     */
    public function finalizeAttempt(ExamAttempt $attempt, ?array $submittedAnswers = null): array
    {
        $exam = $attempt->exam;
        if (!$exam) {
            $exam = Exam::with('questions')->find($attempt->exam_id);
        }

        $answers = $submittedAnswers !== null ? $submittedAnswers : ($attempt->answers ?? []);
        if (is_string($answers)) {
            $answers = json_decode($answers, true) ?? [];
        }

        // Preserve _question_order and _option_order in answers
        if (!isset($answers['_question_order'])) {
            $order = $attempt->question_order ?? ($attempt->answers['_question_order'] ?? null);
            if (!empty($order)) {
                $answers['_question_order'] = $order;
            }
        }
        if (!isset($answers['_option_order'])) {
            $optOrder = $attempt->option_order ?? ($attempt->answers['_option_order'] ?? null);
            if (!empty($optOrder)) {
                $answers['_option_order'] = $optOrder;
            }
        }

        $questions = $exam ? $exam->questions()->get() : collect();

        // Question types that can be auto-graded
        $autoGradeTypes = ['multiple_choice', 'true_false', 'matching'];

        $autoScore   = 0;   // marks earned from auto-graded questions
        $autoTotal   = 0;   // total marks available from auto-graded questions
        $pendingTotal = 0;  // total marks from manual-graded questions (short_answer, fill_blank)

        // Per-type score tracking for the breakdown
        $typeBreakdown = [];
        $questionsReview = [];

        foreach ($questions as $q) {
            $studentAnswer = $answers[$q->id] ?? null;
            $isAutoGrade   = in_array($q->type, $autoGradeTypes);
            $isCorrect     = false;
            $earnedMarks   = 0;

            if ($isAutoGrade) {
                $autoTotal += $q->marks;

                if ($studentAnswer !== null && $studentAnswer !== '') {
                    if ($q->type === 'matching') {
                        // Partial grading: award marks_per_item for each correctly matched pair
                        $marksPerItem = (float)($q->marks_per_item ?? 0);
                        $pairs = $q->options ?? [];
                        if ($marksPerItem <= 0 && count($pairs) > 0) {
                            // Fallback: divide total marks evenly across non-empty Column A items
                            $colACount = count(array_filter($pairs, fn($p) => trim(strip_tags($p['left'] ?? '')) !== ''));
                            $colACount = max($colACount, 1);
                            $marksPerItem = round($q->marks / $colACount, 2);
                        }
                        // Retrieve correct_answers map from question_data or parse from correct_answer string
                        $correctAnswers = null;
                        if (!empty($q->question_data['correct_answers'])) {
                            $correctAnswers = $q->question_data['correct_answers'];
                        } elseif (!empty($q->correct_answer)) {
                            // Parse "1-A,2-B,3-C" format stored by frontend
                            $correctAnswers = [];
                            foreach (explode(',', $q->correct_answer) as $part) {
                                $p = explode('-', $part, 2);
                                if (count($p) === 2) {
                                    $correctAnswers[trim($p[0])] = strtoupper(trim($p[1]));
                                }
                            }
                        }
                        $result = $this->gradeMatchingPartial((string)$studentAnswer, $pairs, $marksPerItem, $correctAnswers);
                        $isCorrect   = $result['all_correct'];
                        $isPartial   = $result['partial'];
                        $earnedMarks = $result['earned'];
                        $autoScore  += $earnedMarks;

                        // Build per-type totals for the breakdown
                        $typeKey = $q->type;
                        if (!isset($typeBreakdown[$typeKey])) {
                            $typeBreakdown[$typeKey] = ['earned' => 0, 'total' => 0];
                        }
                        $typeBreakdown[$typeKey]['earned'] += $earnedMarks;
                        $typeBreakdown[$typeKey]['total']  += $q->marks;

                        $questionsReview[] = [
                            'question_id'   => $q->id,
                            'questionText'  => $q->text,
                            'type'          => $q->type,
                            'studentAnswer' => $studentAnswer,
                            'correctAnswer' => $q->correct_answer,
                            'isCorrect'     => $isCorrect,
                            'marks'         => $q->marks,
                            'earnedMarks'   => $earnedMarks,
                            'gradingStatus' => 'graded',
                            'explanation'   => $isCorrect
                                ? 'All pairs matched correctly!'
                                : "You matched {$result['correct_count']} of {$result['total_count']} pairs correctly.",
                            'correctCount'  => $result['correct_count'],
                            'totalCount'    => $result['total_count'],
                        ];
                        continue;
                    } elseif ($q->type === 'true_false' || $q->type === 'true-false') {
                        $isCorrect = $this->isTrueFalseCorrect($studentAnswer, $q->correct_answer);
                    } else {
                        // MCQ: match letter, option text, index, or option prefix
                        $optOrder = $attempt->option_order[$q->id] ?? ($answers['_option_order'][$q->id] ?? null);
                        $isCorrect = $this->gradeMultipleChoice($studentAnswer, $q, $optOrder);
                    }
                }

                if ($isCorrect) {
                    $earnedMarks  = $q->marks;
                    $autoScore   += $q->marks;
                }

                // Build per-type totals for the breakdown
                $typeKey = $q->type;
                if (!isset($typeBreakdown[$typeKey])) {
                    $typeBreakdown[$typeKey] = ['earned' => 0, 'total' => 0];
                }
                $typeBreakdown[$typeKey]['earned'] += $earnedMarks;
                $typeBreakdown[$typeKey]['total']  += $q->marks;

                $displayCorrect = $q->correct_answer;
                if ($q->type === 'true_false' || $q->type === 'true-false') {
                    $cRaw = strtolower(trim((string)$q->correct_answer));
                    $displayCorrect = in_array($cRaw, ['true', 'a', '1', 'yes', 'correct']) ? 'True' : 'False';
                }

                $questionsReview[] = [
                    'question_id'   => $q->id,
                    'questionText'  => $q->text,
                    'type'          => $q->type,
                    'studentAnswer' => $studentAnswer,
                    'correctAnswer' => $displayCorrect,
                    'isCorrect'     => $isCorrect,
                    'marks'         => $q->marks,
                    'earnedMarks'   => $earnedMarks,
                    'gradingStatus' => 'graded',
                    'explanation'   => $isCorrect
                        ? 'Correct!'
                        : 'The correct answer is: ' . $displayCorrect,
                ];
            } else {
                // Manual grading — short_answer, fill_blank, essay, etc.
                $pendingTotal += $q->marks;

                $questionsReview[] = [
                    'question_id'   => $q->id,
                    'questionText'  => $q->text,
                    'type'          => $q->type,
                    'studentAnswer' => $studentAnswer,
                    'correctAnswer' => null,
                    'isCorrect'     => null,   // null = not yet graded
                    'marks'         => $q->marks,
                    'earnedMarks'   => null,   // null = pending
                    'gradingStatus' => 'pending',
                    'explanation'   => 'This answer will be marked by your instructor.',
                ];
            }
        }

        $totalPossible    = $autoTotal + $pendingTotal;
        $hasPendingMarks  = $pendingTotal > 0;

        // Percentage is calculated on the auto-graded portion only when pending marks exist
        // so as not to artificially deflate the score before instructor grading
        $percentageBase   = $autoTotal > 0 ? $autoTotal : $totalPossible;
        $percentage       = $percentageBase > 0 ? round(($autoScore / $percentageBase) * 100, 2) : 0;
        $grade            = $hasPendingMarks ? 'Pending' : $this->calculateGrade($percentage);

        // Status logic after submission:
        // - No manual questions at all → auto-publish immediately ('published')
        // - Has manual questions (even if mixed with auto) → 'submitted' for instructor review
        // - 'graded' is only set by instructor via saveGrades (Save No Publish)
        // - 'published' is set by instructor via publishResult (Re-Publish) OR auto here
        $status = $hasPendingMarks ? 'submitted' : 'published';

        $adjustedDeadline = $attempt->getAdjustedDeadline();
        $submittedAt      = $attempt->submitted_at ?? ($adjustedDeadline && $adjustedDeadline->isPast() ? $adjustedDeadline : now());

        $attempt->update([
            'score'          => $autoScore,
            'total_marks'    => $totalPossible > 0 ? $totalPossible : ($exam?->total_marks ?? 0),
            'percentage'     => $percentage,
            'grade'          => $grade,
            'status'         => $status,
            'answers'        => $answers,
            'question_order' => $attempt->question_order ?? ($answers['_question_order'] ?? null),
            'option_order'   => $attempt->option_order ?? ($answers['_option_order'] ?? null),
            'submitted_at'   => $submittedAt,
        ]);

        return [
            'attempt_id'      => $attempt->id,
            // Auto-graded scores
            'auto_score'      => $autoScore,
            'auto_total'      => $autoTotal,
            // Pending manual marks
            'pending_total'   => $pendingTotal,
            'has_pending'     => $hasPendingMarks,
            // Overall
            'score'           => $autoScore,
            'total_marks'     => $totalPossible > 0 ? $totalPossible : ($exam?->total_marks ?? 0),
            'percentage'      => $hasPendingMarks ? null : $percentage,
            'grade'           => $grade,
            'status'          => $status,
            // Type-level breakdown e.g. {"multiple_choice":{"earned":10,"total":20},...}
            'type_breakdown'  => $typeBreakdown,
            // Exam meta
            'exam_title'      => $exam?->title ?? '',
            'course_code'     => $exam?->course_code ?? '',
            'course_name'     => $exam?->course_name ?? '',
            'questionsReview' => $questionsReview,
        ];
    }

    /**
     * Robust auto-grading for True / False questions.
     * Evaluates 'A', 'B', 'True', 'False', 1, 0, booleans.
     */
    private function isTrueFalseCorrect($studentAnswer, $correctAnswer): bool
    {
        $sAns = strtolower(trim((string)$studentAnswer));
        $cAns = strtolower(trim((string)$correctAnswer));

        if ($sAns === '' || $cAns === '') {
            return false;
        }

        $sTrue = in_array($sAns, ['a', 'true', 't', '1', 'yes', 'correct']);
        $sFalse = in_array($sAns, ['b', 'false', 'f', '0', 'no', 'incorrect']);
        $cTrue = in_array($cAns, ['a', 'true', 't', '1', 'yes', 'correct']);
        $cFalse = in_array($cAns, ['b', 'false', 'f', '0', 'no', 'incorrect']);

        return ($sTrue && $cTrue) || ($sFalse && $cFalse);
    }

    /**
     * Robust auto-grading for multiple choice questions.
     * Matches student letter selection (A, B, C...) against:
     * 1. Direct letter answer (A, B, C...)
     * 2. Full option text (e.g. "Software Requirements Specification")
     * 3. Numeric index (0, 1, 2...)
     * 4. Option prefixed strings ("Option A", "A. Text")
     * 5. Options marked is_correct in database
     * Supports shuffled options via $optionOrder mapping.
     */
    private function gradeMultipleChoice($studentAnswer, $q, ?array $optionOrder = null): bool
    {
        $studentAns = strtoupper(trim((string)$studentAnswer)); // e.g. "A", "B", "C"
        $correctAns = trim((string)$q->correct_answer);

        if ($studentAns === '' || $correctAns === '') {
            return false;
        }

        $options = $q->options ?? [];
        $studentIdx = ord($studentAns) - 65; // A -> 0, B -> 1, C -> 2...

        // If options were shuffled for this student attempt, map student's shuffled index to original index
        $originalIdx = $studentIdx;
        if (!empty($optionOrder) && isset($optionOrder[$studentIdx])) {
            $originalIdx = (int)$optionOrder[$studentIdx];
        }

        // 1. Direct letter comparison if not shuffled, or mapped letter if shuffled
        if (empty($optionOrder) && strtoupper($correctAns) === $studentAns) {
            return true;
        } elseif (!empty($optionOrder) && $originalIdx >= 0) {
            $originalLetter = chr(65 + $originalIdx);
            if (strtoupper($correctAns) === $originalLetter) {
                return true;
            }
        }

        // 2. If correct_answer matches the text of the original option chosen by the student
        if ($originalIdx >= 0 && isset($options[$originalIdx])) {
            $opt = $options[$originalIdx];
            $optText = is_array($opt) ? ($opt['text'] ?? $opt['label'] ?? '') : (string)$opt;
            $cleanOptText = strtolower(trim(strip_tags((string)$optText)));
            $cleanCorrect = strtolower(trim(strip_tags((string)$correctAns)));
            if ($cleanOptText !== '' && $cleanOptText === $cleanCorrect) {
                return true;
            }
        }

        // 3. If correct_answer matches an option text in the options array, compare its index with originalIdx
        foreach ($options as $optIdx => $opt) {
            $optText = is_array($opt) ? ($opt['text'] ?? $opt['label'] ?? '') : (string)$opt;
            $cleanOptText = strtolower(trim(strip_tags((string)$optText)));
            $cleanCorrect = strtolower(trim(strip_tags((string)$correctAns)));
            if ($cleanOptText !== '' && $cleanOptText === $cleanCorrect) {
                if ($optIdx === $originalIdx) {
                    return true;
                }
            }
        }

        // 4. If correct_answer is a numeric index (0 -> A, 1 -> B, etc.)
        if (is_numeric($correctAns)) {
            $cIdx = (int)$correctAns;
            if ($cIdx === $originalIdx || $cIdx === ($originalIdx + 1)) {
                return true;
            }
        }

        // 5. If correct_answer has a prefix like "Option A" or "A. Some text" or "A) ..."
        if (preg_match('/^(?:Option\s*)?([A-Za-z])[\.\)\:\s]/i', $correctAns, $matches)) {
            $targetLetter = strtoupper($matches[1]);
            $targetIdx = ord($targetLetter) - 65;
            if ($targetIdx === $originalIdx) {
                return true;
            }
        }

        // 6. If options list has is_correct flag
        if ($originalIdx >= 0 && isset($options[$originalIdx]) && is_array($options[$originalIdx]) && !empty($options[$originalIdx]['is_correct'])) {
            return true;
        }

        return false;
    }

    /**
     * Grade a matching question with partial marks per correctly matched pair.
     * Student answer format: "0:rightValue,1:rightValue,2:rightValue"
     * Pairs (from DB options): [{left: "...", right: "..."}, ...]
     * marksPerItem: marks to award for each correctly matched pair
     */
    private function gradeMatchingPartial(string $studentAnswer, array $pairs, float $marksPerItem, ?array $correctAnswers = null): array
    {
        if (empty($pairs) || empty(trim($studentAnswer))) {
            return ['earned' => 0, 'all_correct' => false, 'partial' => false, 'correct_count' => 0, 'total_count' => count($pairs)];
        }

        // Parse student selections: "0:A,1:B" => [0 => 'A', 1 => 'B']
        $selections = [];
        foreach (explode(',', $studentAnswer) as $part) {
            $pieces = explode(':', $part, 2);
            if (count($pieces) === 2) {
                $selections[(int)$pieces[0]] = strtoupper(trim($pieces[1]));
            }
        }

        $correctCount = 0;
        $totalCount   = 0;

        // Count only filled left-column pairs (actual Column A items)
        foreach ($pairs as $i => $pair) {
            $leftText = strip_tags(trim($pair['left'] ?? ''));
            if ($leftText === '') continue; // skip empty Column A rows

            $totalCount++;
            $colAKey = (string)($totalCount); // "1", "2", "3"... (1-indexed)

            if ($correctAnswers !== null && isset($correctAnswers[$colAKey])) {
                // Use correct_answers map: {"1": "A", "2": "B", ...}
                $correctLetter   = strtoupper(trim($correctAnswers[$colAKey]));
                $studentSelected = $selections[$i] ?? '';
                if ($studentSelected !== '' && $studentSelected === $correctLetter) {
                    $correctCount++;
                }
            } else {
                // Fallback: compare letter position to correct pair (pair i should map to letter i+1)
                $expectedLetter  = chr(65 + $i); // A, B, C...
                $studentSelected = $selections[$i] ?? '';
                if ($studentSelected !== '' && strtoupper($studentSelected) === $expectedLetter) {
                    $correctCount++;
                }
            }
        }

        if ($totalCount === 0) $totalCount = count($pairs);

        $earned     = round($correctCount * $marksPerItem, 2);
        $allCorrect = ($correctCount === $totalCount && $totalCount > 0 && !empty($selections));
        $partial    = (!$allCorrect && $correctCount > 0);

        return [
            'earned'        => $earned,
            'all_correct'   => $allCorrect,
            'partial'       => $partial,
            'correct_count' => $correctCount,
            'total_count'   => $totalCount,
        ];
    }

    /**
     * Grade a matching question.
     * Student answer format: "0:rightValue,1:rightValue,2:rightValue"
     * Pairs (from DB options): [{left: "...", right: "..."}, ...]
     * Returns true only if ALL pairs are correctly matched.
     */
    private function gradeMatching(string $studentAnswer, array $pairs): bool
    {
        if (empty($pairs) || empty(trim($studentAnswer))) {
            return false;
        }

        // Parse student selections: "0:val,1:val" => [0 => 'val', 1 => 'val']
        $selections = [];
        foreach (explode(',', $studentAnswer) as $part) {
            $pieces = explode(':', $part, 2);
            if (count($pieces) === 2) {
                $selections[(int)$pieces[0]] = trim($pieces[1]);
            }
        }

        // Check every pair
        foreach ($pairs as $i => $pair) {
            $correctRight    = strtolower(trim($pair['right'] ?? ''));
            $studentSelected = strtolower($selections[$i] ?? '');
            if ($studentSelected !== $correctRight) {
                return false;
            }
        }

        return !empty($selections);
    }

    /**
     * Get all completed exam results for the student.
     */
    public function results(Request $request): JsonResponse
    {
        $student = $request->user();
        $this->autoFinalizeExpiredAttempts($student);

        // Only return published results — graded (unpublished) results remain hidden from student
        $attempts = ExamAttempt::where('user_id', $student->id)
            ->where('status', 'published')
            ->with(['exam', 'exam.questions', 'exam.instructor'])
            ->latest('submitted_at')
            ->get();

        $results = $attempts->map(function ($attempt) {
            $exam = $attempt->exam;

            // Build question review from stored answers
            $review = [];
            if ($exam && $exam->questions) {
                $storedAnswers = $attempt->answers ?? [];
                foreach ($exam->questions as $q) {
                    $studentAnswer = $storedAnswers[$q->id] ?? null;
                    $isCorrect = false;

                    if ($studentAnswer !== null && $studentAnswer !== '') {
                        if ($q->type === 'true_false' || $q->type === 'true-false') {
                            $isCorrect = $this->isTrueFalseCorrect($studentAnswer, $q->correct_answer);
                        } elseif ($q->type === 'matching') {
                            $pairs = $q->options ?? [];
                            $correctAnswers = $q->question_data['correct_answers'] ?? null;
                            $res = $this->gradeMatchingPartial((string)$studentAnswer, $pairs, 1, $correctAnswers);
                            $isCorrect = $res['all_correct'];
                        } else {
                            $optOrder = $attempt->option_order[$q->id] ?? ($storedAnswers['_option_order'][$q->id] ?? null);
                            $isCorrect = $this->gradeMultipleChoice($studentAnswer, $q, $optOrder);
                        }
                    }

                    $displayCorrect = $q->correct_answer ?? 'N/A';
                    if ($q->type === 'true_false' || $q->type === 'true-false') {
                        $cRaw = strtolower(trim((string)$q->correct_answer));
                        $displayCorrect = in_array($cRaw, ['true', 'a', '1', 'yes', 'correct']) ? 'True' : 'False';
                    }

                    $review[] = [
                        'questionText'  => $q->text,
                        'studentAnswer' => $studentAnswer ?? 'Not answered',
                        'correctAnswer' => $displayCorrect,
                        'explanation'   => $isCorrect ? 'Correct!' : 'The correct answer is: ' . $displayCorrect,
                        'isCorrect'     => $isCorrect,
                    ];
                }
            }

            $instructor = $exam ? $exam->instructor : null;
            $courseCode = $exam->course_code ?? ($instructor->course_code ?? 'N/A');
            $courseName = $instructor->course_name ?? $courseCode;
            $examType   = $exam->course_name ?? 'Mid Exam';

            return [
                'id'              => $attempt->id,
                'courseCode'      => $courseCode,
                'courseName'      => $courseName,
                'examTitle'       => $exam->title ?? '',
                'examType'        => $examType,
                'score'           => $attempt->score ?? 0,
                'totalMarks'      => $attempt->total_marks,
                'percentage'      => (float) ($attempt->percentage ?? 0),
                'grade'           => $attempt->grade ?? 'N/A',
                'status'          => $attempt->percentage >= 50 ? 'Passed' : 'Failed',
                'completedDate'   => $attempt->submitted_at ? $attempt->submitted_at->format('M d, Y') : '',
                'questionsReview' => $review,
            ];
        });

        return response()->json([
            'data' => $results,
        ]);
    }

    /**
     * Get detailed result breakdown for a specific exam attempt.
     */
    public function showResult(Request $request, string $attemptId): JsonResponse
    {
        $student = $request->user();

        $attempt = ExamAttempt::where('id', (int)$attemptId)
            ->where('user_id', $student->id)
            ->with(['exam', 'exam.questions', 'exam.instructor'])
            ->first();

        if (!$attempt || !$attempt->exam) {
            return response()->json(['message' => 'Exam result not found.'], 404);
        }

        if ($attempt->status !== 'published') {
            return response()->json([
                'message' => 'This exam result has not been published yet. Results and manual scores will be visible once published by your instructor.',
            ], 403);
        }

        $exam = $attempt->exam;
        $totalMarks = (float)($attempt->total_marks ?: ($exam->total_marks ?: 0));
        $score = (float)($attempt->score ?: 0);
        $percentage = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 2) : 0;
        $grade = $attempt->grade ?: $this->calculateGrade($percentage);
        $status = $percentage >= 50 ? 'Passed' : 'Failed';
        $passingMarks = round($totalMarks * 0.5, 1);

        $answers = $attempt->answers ?? [];
        if (is_string($answers)) {
            $answers = json_decode($answers, true) ?? [];
        }

        // Questions ordered by instruction group then by id ascending (creation order)
        $rawQuestions = $exam->questions()->orderBy('id')->get();

        // Build stable instruction-group order map: instruction => first-seen position
        $instructionOrder = [];
        $pos = 0;
        foreach ($rawQuestions as $rq) {
            $inst = trim($rq->instruction ?? '');
            if (!array_key_exists($inst, $instructionOrder)) {
                $instructionOrder[$inst] = $pos++;
            }
        }

        $savedOrder = $attempt->question_order ?? ($answers['_question_order'] ?? null);
        if (!empty($savedOrder) && is_array($savedOrder)) {
            $sortedQuestions = $this->orderQuestionsByIds($rawQuestions, $savedOrder);
        } else {
            // Sort: first by instruction group order, then by id within the group
            $sortedQuestions = $rawQuestions->sort(function ($a, $b) use ($instructionOrder) {
                $instA = trim($a->instruction ?? '');
                $instB = trim($b->instruction ?? '');
                $orderA = $instructionOrder[$instA] ?? 9999;
                $orderB = $instructionOrder[$instB] ?? 9999;
                if ($orderA !== $orderB) return $orderA <=> $orderB;
                return $a->id <=> $b->id; // ascending creation order within group
            })->values();
        }

        $totalQuestionsCount = $sortedQuestions->count();
        $attemptedCount = 0;
        $correctCount = 0;
        $wrongCount = 0;
        $partialCount = 0;
        
        $autoScoreSum = 0;
        $autoScoreTotal = 0;
        $manualScoreSum = 0;
        $manualScoreTotal = 0;

        // Grouping for Type Breakdown
        $typeStats = [
            'multiple_choice' => ['label' => 'Multiple Choice', 'count' => 0, 'correct' => 0, 'earned' => 0, 'total' => 0],
            'true_false'      => ['label' => 'True / False',      'count' => 0, 'correct' => 0, 'earned' => 0, 'total' => 0],
            'fill_blank'      => ['label' => 'Fill in the Blank', 'count' => 0, 'correct' => 0, 'earned' => 0, 'total' => 0],
            'short_answer'    => ['label' => 'Short Answer',      'count' => 0, 'correct' => 0, 'earned' => 0, 'total' => 0],
            'matching'        => ['label' => 'Matching',          'count' => 0, 'correct' => 0, 'earned' => 0, 'total' => 0],
        ];

        $questionsList = [];

        foreach ($sortedQuestions as $idx => $q) {
            $qId = (string)$q->id;
            $studentAns = $answers[$qId] ?? ($answers[$q->id] ?? null);
            $qMarks = (float)($q->marks ?: 1);
            $qType = $q->type ?: 'multiple_choice';
            $isAnswered = ($studentAns !== null && $studentAns !== '');

            if ($isAnswered) {
                $attemptedCount++;
            }

            $earnedMarks = 0;
            $isCorrect = false;
            $isPartial = false;

            // Normalized type key
            $typeKey = match($qType) {
                'mcq', 'multiple_choice', 'multiple-choice' => 'multiple_choice',
                'true_false', 'true/false'                  => 'true_false',
                'fill_blank', 'fill_in_the_blank'           => 'fill_blank',
                'short_answer', 'essay', 'text'             => 'short_answer',
                'matching', 'Matching'                      => 'matching',
                default                                     => 'multiple_choice',
            };

            if (!isset($typeStats[$typeKey])) {
                $typeStats[$typeKey] = ['label' => ucfirst(str_replace('_', ' ', $typeKey)), 'count' => 0, 'correct' => 0, 'earned' => 0, 'total' => 0];
            }

            $typeStats[$typeKey]['total'] += $qMarks;

            // Format student and correct answer
            $formattedStudentAnswer = '';
            $formattedCorrectAnswer = '';
            $optionsList = [];
            $matchingData = null;

            if ($typeKey === 'multiple_choice') {
                $rawOptions = $q->options ?? [];
                $optOrder = $attempt->option_order[$q->id] ?? ($answers['_option_order'][$q->id] ?? null);
                $displayOptions = (!empty($optOrder) && is_array($optOrder))
                    ? $this->applyOptionOrder($rawOptions, $optOrder)
                    : $rawOptions;

                foreach ($displayOptions as $optIdx => $opt) {
                    $letter = chr(65 + $optIdx);
                    $optText = is_array($opt) ? ($opt['text'] ?? '') : (string)$opt;
                    $cleanText = strip_tags($optText);
                    $optionsList[] = ['label' => $letter, 'text' => $cleanText];
                }

                $correctLetter = strtoupper(trim((string)$q->correct_answer));
                $studentLetter = strtoupper(trim((string)$studentAns));

                $isCorrect = ($isAnswered && $this->gradeMultipleChoice($studentAns, $q, $optOrder));
                $earnedMarks = $isCorrect ? $qMarks : 0;

                // Find full text matching shuffled options
                $correctOpt = null;
                if (!empty($optOrder) && is_array($optOrder)) {
                    $corrLetter = strtoupper(trim((string)$q->correct_answer));
                    if (strlen($corrLetter) === 1 && $corrLetter >= 'A' && $corrLetter <= 'Z') {
                        $origCorrIdx = ord($corrLetter) - 65;
                        $shuffledCorrIdx = array_search($origCorrIdx, $optOrder, true);
                        if ($shuffledCorrIdx !== false) {
                            $shuffledLetter = chr(65 + $shuffledCorrIdx);
                            $correctOpt = collect($optionsList)->firstWhere('label', $shuffledLetter);
                        }
                    }
                }
                if (!$correctOpt) {
                    $correctOpt = collect($optionsList)->firstWhere('label', $correctLetter);
                }
                if (!$correctOpt) {
                    $cleanCorr = strtolower(trim(strip_tags((string)$q->correct_answer)));
                    $correctOpt = collect($optionsList)->first(fn($o) => strtolower(trim($o['text'])) === $cleanCorr);
                }
                $studentOpt = collect($optionsList)->firstWhere('label', $studentLetter);

                $formattedCorrectAnswer = $correctOpt ? "{$correctOpt['label']}. {$correctOpt['text']}" : ($correctLetter ?: 'N/A');
                $formattedStudentAnswer = $studentOpt ? "{$studentOpt['label']}. {$studentOpt['text']}" : ($studentLetter ?: 'Not answered');
            } elseif ($typeKey === 'true_false') {
                $isCorrect = ($isAnswered && $this->isTrueFalseCorrect($studentAns, $q->correct_answer));
                $earnedMarks = $isCorrect ? $qMarks : 0;

                $cRaw = strtolower(trim((string)$q->correct_answer));
                $correctVal = in_array($cRaw, ['true', 'a', '1', 'yes', 'correct']) ? 'True' : 'False';
                $sRaw = strtolower(trim((string)$studentAns));
                $studentVal = $isAnswered ? (in_array($sRaw, ['true', 'a', '1', 'yes', 'correct']) ? 'True' : 'False') : 'Not answered';

                $formattedCorrectAnswer = $correctVal;
                $formattedStudentAnswer = $studentVal;
            } elseif ($typeKey === 'fill_blank') {
                $correctVal = strip_tags($q->correct_answer ?? $q->sample_answer ?? '');
                $studentVal = $isAnswered ? strip_tags((string)$studentAns) : 'Not answered';

                if (in_array($attempt->status, ['graded', 'published']) || isset($answers['_manual_scores'][$q->id])) {
                    $earnedMarks = (float)($answers['_manual_scores'][$q->id] ?? 0);
                    $isCorrect = ($earnedMarks >= $qMarks);
                } else {
                    $earnedMarks = null;
                    $isCorrect = null;
                }

                $formattedCorrectAnswer = $correctVal ?: 'N/A';
                $formattedStudentAnswer = $studentVal;
            } elseif ($typeKey === 'short_answer') {
                $correctVal = strip_tags($q->sample_answer ?? $q->correct_answer ?? 'Sample explanation provided by instructor.');
                $studentVal = $isAnswered ? strip_tags((string)$studentAns) : 'Not answered';

                // For short answer / essay, if student attempted and it's marked or graded
                if (in_array($attempt->status, ['graded', 'published']) || isset($answers['_manual_scores'][$q->id])) {
                    $earnedMarks = (float)($answers['_manual_scores'][$q->id] ?? 0);
                    $isCorrect = ($earnedMarks >= $qMarks);
                    $isPartial = ($earnedMarks > 0 && $earnedMarks < $qMarks);
                } else {
                    $earnedMarks = null;
                    $isCorrect = null;
                    $isPartial = false;
                }

                $formattedCorrectAnswer = $correctVal;
                $formattedStudentAnswer = $studentVal;
            } elseif ($typeKey === 'matching') {
                $pairs = $this->extractMatchingPairs($q);

                // Parse correct_answers map
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

                // Parse student selections: "0:A,1:B" => [pairIndex => 'A', ...]
                $selections = [];
                if ($studentAns && is_string($studentAns)) {
                    foreach (explode(',', $studentAns) as $part) {
                        $pieces = explode(':', $part, 2);
                        if (count($pieces) === 2) {
                            $selections[(int)$pieces[0]] = strtoupper(trim($pieces[1]));
                        }
                    }
                }

                $formattedPairs = [];
                $allMatchedCorrectly = true;
                $colACounter = 0;
                $correctMatchCount = 0;

                foreach ($pairs as $pIdx => $pair) {
                    $leftText  = strip_tags($pair['left'] ?? '');
                    $rightText = strip_tags($pair['right'] ?? '');
                    if ($leftText === '') continue; // skip empty Column A rows

                    $colACounter++;
                    $colAKey = (string)$colACounter; // "1","2","3"...

                    // The correct Column B letter for this Column A item
                    $correctLetter = isset($correctAnswers[$colAKey]) ? strtoupper($correctAnswers[$colAKey]) : chr(65 + $pIdx);

                    // Map letter back to right text for display
                    $correctLetterIdx   = ord($correctLetter) - 65;
                    $correctRightText   = strip_tags($pairs[$correctLetterIdx]['right'] ?? $rightText);

                    // Student's selected letter for this row
                    $studentLetter      = $selections[$pIdx] ?? '';
                    $studentLetterIdx   = $studentLetter !== '' ? (ord($studentLetter) - 65) : -1;
                    $studentRightText   = ($studentLetterIdx >= 0 && isset($pairs[$studentLetterIdx]))
                        ? strip_tags($pairs[$studentLetterIdx]['right'] ?? 'Not matched')
                        : 'Not matched';

                    $rowCorrect = ($studentLetter !== '' && $studentLetter === $correctLetter);
                    if (!$rowCorrect) $allMatchedCorrectly = false;
                    else $correctMatchCount++;

                    $formattedPairs[] = [
                        'index'        => $colACounter,
                        'left'         => $leftText,
                        'correctRight' => $correctRightText,
                        'studentRight' => $studentRightText,
                        'isCorrect'    => $rowCorrect,
                    ];
                }

                // Compute earned marks: marks_per_item * correctly matched items
                $marksPerItem = (float)($q->marks_per_item ?? 0);
                if ($marksPerItem <= 0 && $colACounter > 0) {
                    $marksPerItem = round($qMarks / $colACounter, 2);
                }
                $earnedMarks = round($correctMatchCount * $marksPerItem, 2);
                $isCorrect = ($isAnswered && $allMatchedCorrectly && $colACounter > 0);
                $isPartial = (!$isCorrect && $earnedMarks > 0);

                $matchingData = $formattedPairs;
                $formattedCorrectAnswer = 'See correct matches below.';
                $formattedStudentAnswer = $isCorrect ? 'All pairs correctly matched.' : "Matched {$correctMatchCount} of {$colACounter} pairs correctly.";
            }

            if ($typeKey === 'matching') {
                $typeStats[$typeKey]['count'] += (isset($colACounter) ? $colACounter : 1);
                $typeStats[$typeKey]['correct'] += (isset($correctMatchCount) ? $correctMatchCount : ($isCorrect ? 1 : 0));
            } else {
                $typeStats[$typeKey]['count']++;
                if ($isCorrect) {
                    $correctCount++;
                    $typeStats[$typeKey]['correct']++;
                }
            }

            if ($isCorrect === false) {
                if ($isPartial) {
                    $partialCount++;
                } else {
                    $wrongCount++;
                }
            }

            $typeStats[$typeKey]['earned'] += (float)$earnedMarks;
            
            if (in_array($typeKey, ['multiple_choice', 'true_false', 'matching'])) {
                $autoScoreSum += (float)$earnedMarks;
                $autoScoreTotal += $qMarks;
            } else {
                $manualScoreSum += (float)$earnedMarks;
                $manualScoreTotal += $qMarks;
            }

            if ($isCorrect === null) {
                $statusStr = 'pending';
            } else {
                $statusStr = $isCorrect ? 'correct' : ($isPartial ? 'partial' : ($isAnswered ? 'wrong' : 'unanswered'));
            }

            $questionsList[] = [
                'id'            => $q->id,
                'number'        => $idx + 1,
                'type'          => $typeKey,
                'typeLabel'     => $typeStats[$typeKey]['label'],
                'instruction'   => strip_tags($q->instruction ?? ''),
                'text'          => strip_tags($q->text ?? ''),
                'options'       => $optionsList,
                'studentAnswer' => $formattedStudentAnswer,
                'correctAnswer' => $formattedCorrectAnswer,
                'matchingPairs' => $matchingData,
                'marks'         => $qMarks,
                'earnedMarks'   => $earnedMarks,
                'status'        => $statusStr,
                'isCorrect'     => $isCorrect,
                'isPartial'     => $isPartial,
                'explanation'   => strip_tags($q->explanation ?? ''),
                'correctCount'  => isset($correctMatchCount) ? $correctMatchCount : ($isCorrect ? 1 : 0),
                'totalCount'    => isset($colACounter) ? $colACounter : 1,
            ];
        }

        // Build Question Type breakdown array
        $questionTypeBreakdown = collect($typeStats)->filter(fn($item) => $item['count'] > 0)->map(function ($item, $key) {
            $pct = $item['total'] > 0 ? round(($item['earned'] / $item['total']) * 100) : 0;
            return [
                'key'        => $key,
                'label'      => $item['label'],
                'count'      => $item['count'],
                'correct'    => $item['correct'],
                'earned'     => $item['earned'],
                'total'      => $item['total'],
                'percentage' => $pct,
            ];
        })->values()->all();

        $instructor = $exam->instructor;
        $courseCode = $exam->course_code ?? ($instructor->course_code ?? 'SWE-301');
        $courseName = $instructor->course_name ?? $courseCode;
        $examType   = $exam->course_name ?? 'Mid Exam';

        return response()->json([
            'data' => [
                'attempt' => [
                    'id'            => $attempt->id,
                    'examTitle'     => $exam->title,
                    'courseCode'    => $courseCode,
                    'courseName'    => $courseName,
                    'examType'      => $examType,
                    'score'         => $score,
                    'totalMarks'    => $totalMarks,
                    'percentage'    => $percentage,
                    'grade'         => $grade,
                    'status'        => $status,
                    'db_status'     => $attempt->status,
                    'completedDate' => $attempt->submitted_at ? $attempt->submitted_at->format('M d, Y') : '',
                    'passingMarks'  => $passingMarks,
                ],
                'summary' => [
                    'autoScore'       => $autoScoreSum,
                    'autoScoreTotal'  => $autoScoreTotal,
                    'manualScore'     => $manualScoreSum,
                    'manualScoreTotal'=> $manualScoreTotal,
                    'yourScore'       => $percentage,
                    'score'           => round($autoScoreSum + $manualScoreSum, 2),
                    'totalMarks'      => $totalMarks,
                    'totalQuestions'  => $totalQuestionsCount,
                    'attempted'       => $attemptedCount,
                    'correctAnswers'  => $correctCount,
                    'correctPct'      => $totalQuestionsCount > 0 ? round(($correctCount / $totalQuestionsCount) * 100, 2) : 0,
                    'wrongAnswers'    => $wrongCount,
                    'wrongPct'        => $totalQuestionsCount > 0 ? round(($wrongCount / $totalQuestionsCount) * 100, 2) : 0,
                    'partialAnswers'  => $partialCount,
                    'passingMarks'    => $passingMarks,
                    'passingPct'      => 50,
                ],
                'scoreByType' => $questionTypeBreakdown,
                'questions'   => $questionsList,
            ]
        ]);
    }

    /**
     * Map backend question types to frontend-friendly types.
     */
    private function mapQuestionType(string $type): string
    {
        return match ($type) {
            'multiple_choice' => 'multiple-choice',
            'true_false'      => 'true_false',
            'fill_blank'      => 'fill_blank',
            'matching'        => 'matching',
            'multiple_true_false' => 'multiple_true_false',
            'short_answer'    => 'text',
            'essay'           => 'text',
            default           => $type,
        };
    }

    /**
     * Calculate letter grade from percentage.
     */
    private function calculateGrade(float $percentage): string
    {
        return match (true) {
            $percentage >= 95 => 'A+',
            $percentage >= 90 => 'A',
            $percentage >= 85 => 'A-',
            $percentage >= 80 => 'B+',
            $percentage >= 75 => 'B',
            $percentage >= 70 => 'B-',
            $percentage >= 65 => 'C+',
            $percentage >= 60 => 'C',
            $percentage >= 50 => 'D',
            default           => 'F',
        };
    }

    /**
     * Resiliently extract matching pairs from a question object/model.
     */
    protected function extractMatchingPairs($q): array
    {
        $qData = is_array($q->question_data) ? $q->question_data : (json_decode($q->question_data ?? '', true) ?: []);
        $rawOpts = is_array($q->options) ? $q->options : (json_decode($q->options ?? '', true) ?: []);

        $colA = $qData['column_a'] ?? $q->column_a ?? [];
        $colB = $qData['column_b'] ?? $q->column_b ?? [];

        // If column_a and column_b exist, align Column A prompts with Column B options (A, B, C, D)
        if (!empty($colA) && is_array($colA) && !empty($colB) && is_array($colB)) {
            $aligned = [];
            $maxLen = max(count($colA), count($colB));
            for ($i = 0; $i < $maxLen; $i++) {
                $leftText = isset($colA[$i]) ? (is_array($colA[$i]) ? ($colA[$i]['text'] ?? $colA[$i]['label'] ?? '') : (string)$colA[$i]) : '';
                $bItem = $colB[$i] ?? null;
                $rightText = '';
                if ($bItem !== null) {
                    $rightText = is_array($bItem) ? ($bItem['text'] ?? $bItem['label'] ?? '') : (string)$bItem;
                }
                if ($leftText !== '' || $rightText !== '') {
                    $aligned[] = ['left' => $leftText, 'right' => $rightText];
                }
            }
            if (!empty($aligned)) {
                return $aligned;
            }
        }

        // 2. From question_data['pairs']
        if (!empty($qData['pairs']) && is_array($qData['pairs'])) {
            return collect($qData['pairs'])->map(fn($p) => [
                'left'  => is_array($p) ? ($p['left'] ?? '') : (string)$p,
                'right' => is_array($p) ? ($p['right'] ?? ($p['text'] ?? '')) : '',
            ])->values()->toArray();
        }

        // 3. From options array
        if (!empty($rawOpts) && is_array($rawOpts)) {
            return collect($rawOpts)->map(fn($p) => [
                'left'  => is_array($p) ? ($p['left'] ?? '') : (string)$p,
                'right' => is_array($p) ? ($p['right'] ?? ($p['text'] ?? '')) : '',
            ])->values()->toArray();
        }

        return [];
    }

    /**
     * Extract Column A title and Column B title.
     */
    protected function extractMatchingColumns($q): array
    {
        $qData = is_array($q->question_data) ? $q->question_data : (json_decode($q->question_data ?? '', true) ?: []);
        $rawOpts = is_array($q->options) ? $q->options : (json_decode($q->options ?? '', true) ?: []);

        $colA = 'Column A';
        $colB = 'Column B';

        if (!empty($rawOpts[0]['columnA'])) {
            $colA = $rawOpts[0]['columnA'];
        } elseif (!empty($qData['column_a_title'])) {
            $colA = $qData['column_a_title'];
        }

        if (!empty($rawOpts[0]['columnB'])) {
            $colB = $rawOpts[0]['columnB'];
        } elseif (!empty($qData['column_b_title'])) {
            $colB = $qData['column_b_title'];
        }

        return [$colA, $colB];
    }

    /**
     * Determine if question shuffling is enabled for this exam.
     */
    protected function isShuffleQuestionsEnabled(Exam $exam): bool
    {
        $settings = $exam->settings;
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?: [];
        }
        if (!is_array($settings)) {
            return false;
        }

        $val = $settings['shuffleQuestions'] ?? $settings['shuffle_questions'] ?? null;
        if ($val === null) {
            return false;
        }

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Determine if answer options shuffling is enabled for this exam.
     */
    protected function isShuffleAnswersEnabled(Exam $exam): bool
    {
        $settings = $exam->settings;
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?: [];
        }
        if (!is_array($settings)) {
            return false;
        }

        $val = $settings['shuffleAnswers'] ?? $settings['shuffle_answers'] ?? $settings['shuffle_options'] ?? null;
        if ($val === null) {
            return false;
        }

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Shuffle multiple choice options and return the shuffled options along with the index mapping.
     * Returns: ['options' => array, 'order_map' => array]
     * order_map maps new_index => original_index.
     */
    protected function shuffleQuestionOptions(array $rawOptions): array
    {
        $count = count($rawOptions);
        if ($count <= 1) {
            return [
                'options'   => array_values($rawOptions),
                'order_map' => range(0, max(0, $count - 1)),
            ];
        }

        $indices = range(0, $count - 1);
        shuffle($indices);

        // Ensure it doesn't accidentally match the original order if count > 1
        if ($indices === range(0, $count - 1)) {
            $indices = array_reverse($indices);
        }

        $shuffledOptions = [];
        $orderMap = [];

        $rawList = array_values($rawOptions);
        foreach ($indices as $newIdx => $origIdx) {
            $shuffledOptions[] = $rawList[$origIdx];
            $orderMap[$newIdx] = (int)$origIdx;
        }

        return [
            'options'   => $shuffledOptions,
            'order_map' => $orderMap,
        ];
    }

    /**
     * Reorder options according to a saved order map (new_index => original_index).
     */
    protected function applyOptionOrder(array $rawOptions, array $orderIndices): array
    {
        $rawList = array_values($rawOptions);
        $reordered = [];

        foreach ($orderIndices as $origIdx) {
            $origIdx = (int)$origIdx;
            if (isset($rawList[$origIdx])) {
                $reordered[] = $rawList[$origIdx];
            }
        }

        // If any options weren't included (e.g. added later), append them
        $usedIndices = array_map('intval', array_values($orderIndices));
        foreach ($rawList as $idx => $opt) {
            if (!in_array($idx, $usedIndices, true)) {
                $reordered[] = $opt;
            }
        }

        return !empty($reordered) ? $reordered : $rawList;
    }

    /**
     * Randomize the question sequence for a student attempt.
     */
    protected function shuffleExamQuestions(\Illuminate\Support\Collection $questions): \Illuminate\Support\Collection
    {
        if ($questions->count() <= 1) {
            return $questions;
        }

        $shuffled = $questions->shuffle()->values();

        // Ensure the shuffled order is different from the original order if count > 1
        if ($questions->count() > 1 && $shuffled->pluck('id')->all() === $questions->pluck('id')->all()) {
            $shuffled = $questions->reverse()->values();
        }

        return $shuffled;
    }

    /**
     * Order a collection of questions according to a saved array of question IDs.
     */
    protected function orderQuestionsByIds(\Illuminate\Support\Collection $questions, array $orderIds): \Illuminate\Support\Collection
    {
        $byId = $questions->keyBy('id');
        $ordered = collect();

        foreach ($orderIds as $id) {
            $intId = (int)$id;
            if ($byId->has($intId)) {
                $ordered->push($byId->get($intId));
                $byId->forget($intId);
            }
        }

        // Append any remaining questions (e.g. added after attempt was started)
        foreach ($byId as $remaining) {
            $ordered->push($remaining);
        }

        return $ordered->values();
    }

    /**
     * Get authoritative examination schedules for the authenticated student.
     * Enforces strict authorization: only schedules belonging to the student's
     * enrolled courses, department, year level, and section.
     * Drafts are NEVER returned to students.
     */
    public function schedule(Request $request): JsonResponse
    {
        $student = $request->user();
        if (!$student || $student->role !== 'student') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $this->autoFinalizeExpiredAttempts($student);

        // Fetch exams relevant to student using the security boundary
        $query = $this->getStudentScheduleQuery($student)
            ->with(['course', 'instructor:id,name,email'])
            ->orderBy('scheduled_at', 'asc');

        // Optional server-side filtering
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('course_code', 'like', "%{$search}%")
                  ->orWhere('course_name', 'like', "%{$search}%")
                  ->orWhereHas('instructor', function ($iq) use ($search) {
                      $iq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('course') && $request->course !== 'all') {
            $query->where(function ($q) use ($request) {
                $q->where('course_code', $request->course)
                  ->orWhere('course_name', $request->course);
            });
        }

        if ($request->filled('semester') && $request->semester !== 'all') {
            $sem = $request->semester;
            $query->where(function ($q) use ($sem) {
                $q->where('settings->semester', $sem)
                  ->orWhereHas('course', function ($cq) use ($sem) {
                      $cq->where('semester', $sem);
                  });
            });
        }

        if ($request->filled('section') && $request->section !== 'all') {
            $sec = $request->section;
            $query->where(function ($q) use ($sec) {
                $q->where('section', $sec)
                  ->orWhere('settings->section', $sec);
            });
        }

        $allMatching = $query->get();

        // Get student attempts to determine completion status
        $attempts = ExamAttempt::where('user_id', $student->id)
            ->whereIn('exam_id', $allMatching->pluck('id'))
            ->get()
            ->keyBy('exam_id');

        $now = Carbon::now();

        // Transform records into authoritative schedule format
        $schedules = $allMatching->map(function ($exam) use ($attempts, $now) {
            $attempt = $attempts->get($exam->id);
            $attemptStatus = $attempt ? $attempt->status : null;
            $duration = $exam->duration_minutes ?? 60;
            $start = $exam->scheduled_at ? Carbon::parse($exam->scheduled_at) : null;
            $end = $start ? $start->copy()->addMinutes($duration) : null;

            // Determine authoritative display status
            $rawStatus = strtolower($exam->status ?? '');
            if ($rawStatus === 'cancelled' || $exam->cancelled_at !== null) {
                $displayStatus = 'Cancelled';
            } elseif ($attempt && in_array($attempt->status, ['submitted', 'graded', 'published'])) {
                $displayStatus = 'Completed';
            } elseif ($rawStatus === 'completed') {
                $displayStatus = 'Completed';
            } elseif ($end && $now->gt($end)) {
                $displayStatus = 'Completed';
            } elseif ($start && $end && $now->between($start, $end)) {
                $displayStatus = 'Active';
            } elseif ($rawStatus === 'published') {
                $displayStatus = 'Published';
            } else {
                $displayStatus = 'Scheduled';
            }

            $examCode = $this->formatExamCode($exam);
            $examType = $this->determineExamType($exam);
            $durationFormatted = $this->formatDuration($duration);
            $sectionDisplay = $exam->section ?: ($exam->settings['section'] ?? 'All Sections');

            return [
                'id'                => $exam->id,
                'title'             => $exam->title,
                'code'              => $examCode,
                'course_code'       => $exam->course_code ?: ($exam->course?->code ?? 'N/A'),
                'course_name'       => $exam->course_name ?: ($exam->course?->title ?? 'Course'),
                'instructor_name'   => $exam->instructor?->name ?? 'Unassigned Faculty',
                'instructor_email'  => $exam->instructor?->email ?? '',
                'section'           => $sectionDisplay,
                'date'              => $start ? $start->format('Y-m-d') : null,
                'date_formatted'    => $start ? $start->format('Y-m-d') : 'Unscheduled',
                'start_time'        => $start ? $start->format('h:i A') : 'TBD',
                'end_time'          => $end ? $end->format('h:i A') : 'TBD',
                'time_range'        => $start && $end ? ($start->format('h:i A') . ' - ' . $end->format('h:i A')) : 'TBD',
                'duration'          => $durationFormatted,
                'duration_minutes'  => $duration,
                'exam_type'         => $examType,
                'total_marks'       => $exam->total_marks ?? 100,
                'status'            => $displayStatus,
                'raw_status'        => $rawStatus,
                'room'              => $exam->settings['room'] ?? 'Room 101',
                'semester'          => $exam->settings['semester'] ?? ($exam->course?->semester ?? 'Semester 1'),
                'academic_year'     => $exam->settings['academic_year'] ?? '2025/2026',
                'description'       => $exam->description ?? '',
                'instructions'      => $exam->settings['instructions'] ?? $exam->description ?? '',
                'allow_calculator'  => (bool)($exam->settings['allowCalculator'] ?? false),
                'attempt_status'    => $attemptStatus,
                'attempt_id'        => $attempt ? $attempt->id : null,
                'questions_count'   => $exam->questions()->count(),
            ];
        });

        // Filter options based on student's actual dataset
        $semesters = $schedules->pluck('semester')->filter()->unique()->values();
        $courses = $schedules->map(function ($s) {
            return [
                'code' => $s['course_code'],
                'name' => $s['course_name'],
            ];
        })->unique('code')->values();

        $sections = $schedules->pluck('section')->filter()->unique()->values();

        return response()->json([
            'status'         => 'success',
            'data'           => $schedules,
            'filter_options' => [
                'semesters' => $semesters,
                'courses'   => $courses,
                'sections'  => $sections,
                'statuses'  => ['Scheduled', 'Published', 'Completed', 'Cancelled'],
            ],
            'student' => [
                'name'        => $student->name,
                'department'  => $student->department?->name,
                'year_level'  => $student->year_level,
                'section'     => $student->section,
            ],
        ]);
    }

    /**
     * Show single schedule detail for an authorized exam.
     */
    public function showSchedule(Request $request, $id): JsonResponse
    {
        $student = $request->user();
        if (!$student || $student->role !== 'student') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $exam = $this->getStudentScheduleQuery($student)
            ->with(['course', 'instructor:id,name,email'])
            ->find($id);

        if (!$exam) {
            return response()->json(['message' => 'Examination schedule not found or access denied.'], 404);
        }

        $attempt = ExamAttempt::where('user_id', $student->id)
            ->where('exam_id', $exam->id)
            ->first();

        $now = Carbon::now();
        $duration = $exam->duration_minutes ?? 60;
        $start = $exam->scheduled_at ? Carbon::parse($exam->scheduled_at) : null;
        $end = $start ? $start->copy()->addMinutes($duration) : null;

        $rawStatus = strtolower($exam->status ?? '');
        if ($rawStatus === 'cancelled' || $exam->cancelled_at !== null) {
            $displayStatus = 'Cancelled';
        } elseif ($attempt && in_array($attempt->status, ['submitted', 'graded', 'published'])) {
            $displayStatus = 'Completed';
        } elseif ($rawStatus === 'completed') {
            $displayStatus = 'Completed';
        } elseif ($end && $now->gt($end)) {
            $displayStatus = 'Completed';
        } elseif ($start && $end && $now->between($start, $end)) {
            $displayStatus = 'Active';
        } elseif ($rawStatus === 'published') {
            $displayStatus = 'Published';
        } else {
            $displayStatus = 'Scheduled';
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'                => $exam->id,
                'title'             => $exam->title,
                'code'              => $this->formatExamCode($exam),
                'course_code'       => $exam->course_code ?: ($exam->course?->code ?? 'N/A'),
                'course_name'       => $exam->course_name ?: ($exam->course?->title ?? 'Course'),
                'instructor_name'   => $exam->instructor?->name ?? 'Unassigned Faculty',
                'instructor_email'  => $exam->instructor?->email ?? '',
                'section'           => $exam->section ?: ($exam->settings['section'] ?? 'All Sections'),
                'date'              => $start ? $start->format('Y-m-d') : null,
                'date_formatted'    => $start ? $start->format('Y-m-d') : 'Unscheduled',
                'start_time'        => $start ? $start->format('h:i A') : 'TBD',
                'end_time'          => $end ? $end->format('h:i A') : 'TBD',
                'time_range'        => $start && $end ? ($start->format('h:i A') . ' - ' . $end->format('h:i A')) : 'TBD',
                'duration'          => $this->formatDuration($duration),
                'duration_minutes'  => $duration,
                'exam_type'         => $this->determineExamType($exam),
                'total_marks'       => $exam->total_marks ?? 100,
                'status'            => $displayStatus,
                'raw_status'        => $rawStatus,
                'room'              => $exam->settings['room'] ?? 'Room 101',
                'semester'          => $exam->settings['semester'] ?? ($exam->course?->semester ?? 'Semester 1'),
                'academic_year'     => $exam->settings['academic_year'] ?? '2025/2026',
                'description'       => $exam->description ?? '',
                'instructions'      => $exam->settings['instructions'] ?? $exam->description ?? '',
                'allow_calculator'  => (bool)($exam->settings['allowCalculator'] ?? false),
                'attempt_status'    => $attempt?->status,
                'attempt_id'        => $attempt?->id,
                'questions_count'   => $exam->questions()->count(),
            ]
        ]);
    }

    /**
     * Build base query for examination schedules visible to a student.
     * Strictly scopes by student department, section, and year level.
     * Drafts are strictly excluded.
     */
    public function getStudentScheduleQuery($student)
    {
        $studentSec = trim($student->section ?? '');
        $cleanStudentSec = strtoupper(trim(preg_replace('/^section\s*/i', '', $studentSec)));
        $yearNum = preg_replace('/[^0-9]/', '', $student->year_level ?? '');

        $allowedYearLevels = array_unique(array_filter([
            $student->year_level,
            trim($student->year_level ?? ''),
        ]));
        $yearWords = [
            '1' => ['1st Year', '1st year', 'Year 1', 'year 1', '1', 'First Year', 'first year', '1st', 'Year-1'],
            '2' => ['2nd Year', '2nd year', 'Year 2', 'year 2', '2', 'Second Year', 'second year', '2nd', 'Year-2'],
            '3' => ['3rd Year', '3rd year', 'Year 3', 'year 3', '3', 'Third Year', 'third year', '3rd', 'Year-3'],
            '4' => ['4th Year', '4th year', 'Year 4', 'year 4', '4', 'Fourth Year', 'fourth year', '4th', 'Year-4'],
            '5' => ['5th Year', '5th year', 'Year 5', 'year 5', '5', 'Fifth Year', 'fifth year', '5th', 'Year-5'],
        ];
        if ($yearNum && isset($yearWords[$yearNum])) {
            $allowedYearLevels = array_unique(array_merge($allowedYearLevels, $yearWords[$yearNum]));
        }

        $allowedSections = [
            '', 'Both', 'both', 'Both Sections', 'both sections', 'BOTH SECTIONS',
            'All', 'all', 'All Sections', 'all sections', 'ALL SECTIONS',
            'Section A and B', 'Section A & B', 'Section A, B', 'A and B', 'A & B', 'A, B'
        ];
        if ($studentSec !== '') {
            $allowedSections[] = $studentSec;
        }
        if ($cleanStudentSec !== '') {
            $allowedSections[] = $cleanStudentSec;
            $allowedSections[] = strtolower($cleanStudentSec);
            $allowedSections[] = "Section $cleanStudentSec";
            $allowedSections[] = "section " . strtolower($cleanStudentSec);
            $allowedSections[] = "SECTION $cleanStudentSec";
        }
        $allowedSections = array_unique($allowedSections);

        return Exam::where('status', '!=', 'draft')
            ->where(function ($query) use ($allowedSections, $cleanStudentSec, $studentSec) {
                $query->whereNull('section')
                      ->orWhere('section', '')
                      ->orWhereIn('section', $allowedSections)
                      ->orWhereRaw("LOWER(TRIM(section)) IN ('all', 'all sections', 'both', 'both sections', 'section a and b', 'section a & b', 'a and b', 'a & b', 'section a, b', 'a, b', 'a,b')");

                if ($cleanStudentSec !== '') {
                    $query->orWhereRaw("LOWER(TRIM(REPLACE(section, 'Section', ''))) = ?", [strtolower($cleanStudentSec)])
                          ->orWhereRaw("LOWER(TRIM(section)) = ?", [strtolower($studentSec)])
                          ->orWhereRaw("LOWER(TRIM(section)) = ?", ['section ' . strtolower($cleanStudentSec)]);
                }
            })
            ->where(function ($query) use ($student, $allowedYearLevels) {
                // 1. Match via direct student enrollment in exam_student
                $query->whereHas('students', function ($sq) use ($student) {
                    $sq->where('users.id', $student->id);
                })
                // 2. OR match via Instructor's department & year_level
                ->orWhereHas('instructor', function ($q) use ($student, $allowedYearLevels) {
                    if ($student->department_id) {
                        $q->where('department_id', $student->department_id);
                    }
                    if (!empty($allowedYearLevels)) {
                        $q->where(function ($yq) use ($allowedYearLevels) {
                            $yq->whereNull('year_level')
                               ->orWhere('year_level', '')
                               ->orWhere('year_level', 'None')
                               ->orWhereIn('year_level', $allowedYearLevels);
                        });
                    }
                })
                // 3. OR match via Exam's course department & level
                ->orWhereHas('course', function ($q) use ($student, $allowedYearLevels) {
                    if ($student->department_id) {
                        $q->where('department_id', $student->department_id);
                    }
                    if (!empty($allowedYearLevels)) {
                        $q->where(function ($yq) use ($allowedYearLevels) {
                            $yq->whereNull('level')
                               ->orWhere('level', '')
                               ->orWhereIn('level', $allowedYearLevels);
                        });
                    }
                })
                // 4. OR match via direct course code if assigned
                ->when($student->course_code, function ($cq) use ($student) {
                    $cq->orWhere('course_code', $student->course_code);
                });
            });
    }

    protected function formatExamCode($exam): string
    {
        if (!empty($exam->settings['exam_code'])) {
            return $exam->settings['exam_code'];
        }
        $year = $exam->scheduled_at ? $exam->scheduled_at->format('Y') : ($exam->created_at ? $exam->created_at->format('Y') : date('Y'));
        return 'EXM-' . $year . '-' . str_pad((string)$exam->id, 3, '0', STR_PAD_LEFT);
    }

    protected function formatDuration(?int $minutes): string
    {
        if (!$minutes || $minutes <= 0) return '60m';
        $hours = intdiv($minutes, 60);
        $remMinutes = $minutes % 60;
        if ($hours > 0 && $remMinutes > 0) {
            return "{$hours}h {$remMinutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        }
        return "{$remMinutes}m";
    }

    protected function determineExamType($exam): string
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
}
