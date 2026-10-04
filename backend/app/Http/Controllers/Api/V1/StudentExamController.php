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
     * Dashboard stats for the student.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $student = $request->user();

        // Count completed attempts
        $completedAttempts = ExamAttempt::where('user_id', $student->id)
            ->whereIn('status', ['submitted', 'graded'])
            ->count();

        // Average score across all submitted attempts
        $avgScore = ExamAttempt::where('user_id', $student->id)
            ->whereIn('status', ['submitted', 'graded'])
            ->whereNotNull('percentage')
            ->avg('percentage');

        // Count upcoming published/scheduled exams for student's department & year_level that haven't been completed
        $completedAttemptExamIds = ExamAttempt::where('user_id', $student->id)
            ->whereIn('status', ['submitted', 'graded'])
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

        // Get all published/scheduled exams for the student's department & year_level & section
        $exams = $this->getAvailableExamsQuery($student)
            ->with('instructor:id,name')
            ->latest('scheduled_at')
            ->get();

        // Get all attempts by this student, keyed by exam_id
        $attempts = ExamAttempt::where('user_id', $student->id)
            ->get()
            ->keyBy('exam_id');

        $upcomingExams = [];

        foreach ($exams as $exam) {
            $attempt = $attempts->get($exam->id);
            $attemptStatus = $attempt ? $attempt->status : null; // null | 'in_progress' | 'submitted' | 'graded'

            // Exclude exams the student has already submitted/graded (fully completed)
            if ($attemptStatus === 'submitted' || $attemptStatus === 'graded') {
                continue;
            }

            $scheduledIso = $exam->scheduled_at ? $exam->scheduled_at->toISOString() : null;

            // Determine status for student UI (Ready if currently in ongoing/ready window or in progress)
            $status = 'Upcoming';
            if ($attemptStatus === 'in_progress') {
                $status = 'Ready';
            } elseif ($exam->scheduled_at) {
                $now = Carbon::now();
                $examStart = $exam->scheduled_at;
                $examEnd = $exam->scheduled_at->copy()->addMinutes($exam->duration_minutes);
                if ($now->gte($examStart->copy()->subMinutes(10)) && $now->lt($examEnd)) {
                    $status = 'Ready';
                }
            } else {
                $status = 'Ready';
            }

            $upcomingExams[] = [
                'id'              => $exam->id,
                'courseCode'      => $exam->course_code,
                'courseName'      => $exam->course_name,
                'instructor'      => $exam->instructor->name ?? 'Unknown',
                'examType'        => $exam->title,
                'scheduledAt'     => $scheduledIso,
                'scheduledDate'   => $scheduledIso, // legacy alias
                'startTime'       => $exam->scheduled_at ? $exam->scheduled_at->format('h:i A') : 'TBD',
                'durationMinutes' => $exam->duration_minutes,
                'totalMarks'      => $exam->total_marks,
                'totalQuestions'  => $exam->questions()->count(),
                'status'          => $status,
                // Attempt tracking — allows card to show Continue vs Start
                'attemptStatus'   => $attemptStatus, // null or 'in_progress'
                'attemptId'       => $attempt ? $attempt->id : null,
                'attemptStartedAt' => ($attempt && $attempt->started_at) ? $attempt->started_at->toISOString() : null,
            ];
        }

        // active_exam is now derived from upcoming_exams (in_progress) for backward compat
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
                    'date'            => $e['scheduledAt'] ? Carbon::parse($e['scheduledAt'])->format('M d, Y') : now()->format('M d, Y'),
                    'time'            => $e['startTime'],
                    'durationMinutes' => $e['durationMinutes'],
                    'totalMarks'      => $e['totalMarks'],
                    'started_at'      => $e['attemptStartedAt'],
                ];
                break;
            }
        }

        return response()->json([
            'data' => [
                'active_exam'    => $activeExamData,
                'upcoming_exams' => $upcomingExams,
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

        // Verify exam is published or scheduled
        if (!in_array($exam->status, ['published', 'scheduled'])) {
            return response()->json(['message' => 'This exam is not available.'], 403);
        }

        // Enforce time window: student cannot start before scheduled_at or after the exam ends
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

        // Check if student already has an attempt
        $existingAttempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('user_id', $student->id)
            ->first();

        if ($existingAttempt && $existingAttempt->status === 'in_progress') {
            // Resume existing attempt — return questions again
            $questions = $exam->questions()->get()->map(fn($q) => [
                'id'          => $q->id,
                'instruction' => $q->instruction,
                'text'        => $q->text,
                'type'        => $this->mapQuestionType($q->type),
                'options'     => $q->type !== 'matching' ? ($q->options ?? []) : [],
                'pairs'       => $q->type === 'matching' ? collect($q->options ?? [])->map(fn($p) => ['left' => $p['left'] ?? '', 'right' => $p['right'] ?? ''])->values()->toArray() : null,
                'columnA'     => $q->type === 'matching' ? (($q->options[0]['columnA'] ?? null) ?: 'Column A') : null,
                'columnB'     => $q->type === 'matching' ? (($q->options[0]['columnB'] ?? null) ?: 'Column B') : null,
                'marks'       => $q->marks,
            ]);

            return response()->json([
                'data' => [
                    'attempt_id'      => $existingAttempt->id,
                    'exam_title'      => $exam->title,
                    'course_code'     => $exam->course_code,
                    'course_name'     => $exam->course_name,
                    'duration_minutes' => $exam->duration_minutes,
                    'total_marks'     => $exam->total_marks,
                    'started_at'      => $existingAttempt->started_at->toISOString(),
                    'settings'        => $exam->settings ?? [],
                    'questions'       => $questions,
                ]
            ]);
        }

        if ($existingAttempt && in_array($existingAttempt->status, ['submitted', 'graded'])) {
            return response()->json(['message' => 'You have already completed this exam.'], 409);
        }

        // Create new attempt
        $attempt = ExamAttempt::create([
            'exam_id'     => $exam->id,
            'user_id'     => $student->id,
            'total_marks' => $exam->total_marks,
            'status'      => 'in_progress',
            'started_at'  => now(),
        ]);

        // Return questions WITHOUT correct_answer
        $questions = $exam->questions()->get()->map(fn($q) => [
            'id'          => $q->id,
            'instruction' => $q->instruction,
            'text'        => $q->text,
            'type'        => $this->mapQuestionType($q->type),
            'options'     => $q->type !== 'matching' ? ($q->options ?? []) : [],
            'pairs'       => $q->type === 'matching' ? collect($q->options ?? [])->map(fn($p) => ['left' => $p['left'] ?? '', 'right' => $p['right'] ?? ''])->values()->toArray() : null,
            'columnA'     => $q->type === 'matching' ? (($q->options[0]['columnA'] ?? null) ?: 'Column A') : null,
            'columnB'     => $q->type === 'matching' ? (($q->options[0]['columnB'] ?? null) ?: 'Column B') : null,
            'marks'       => $q->marks,
        ]);

        return response()->json([
            'data' => [
                'attempt_id'       => $attempt->id,
                'exam_title'       => $exam->title,
                'course_code'      => $exam->course_code,
                'course_name'      => $exam->course_name,
                'duration_minutes' => $exam->duration_minutes,
                'total_marks'      => $exam->total_marks,
                'started_at'       => $attempt->started_at->toISOString(),
                'settings'         => $exam->settings ?? [],
                'questions'        => $questions,
            ]
        ], 201);
    }

    /**
     * Submit exam answers. Auto-grades MCQ and True/False.
     */
    public function submit(Request $request, Exam $exam): JsonResponse
    {
        $student = $request->user();

        $attempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('user_id', $student->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $validated = $request->validate([
            'answers' => 'required|array',
        ]);

        $submittedAnswers = $validated['answers'];
        $questions = $exam->questions()->get();

        // Question types that can be auto-graded
        $autoGradeTypes = ['multiple_choice', 'true_false', 'matching'];

        $autoScore   = 0;   // marks earned from auto-graded questions
        $autoTotal   = 0;   // total marks available from auto-graded questions
        $pendingTotal = 0;  // total marks from manual-graded questions (short_answer, fill_blank)

        // Per-type score tracking for the breakdown
        $typeBreakdown = [];
        $questionsReview = [];

        foreach ($questions as $q) {
            $studentAnswer = $submittedAnswers[$q->id] ?? null;
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
                        $result = $this->gradeMatchingPartial($studentAnswer, $pairs, $marksPerItem, $correctAnswers);
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
                    } else {
                        // MCQ / True-False: letter comparison (A, B, C... or A/B for T/F)
                        $isCorrect = strtolower(trim((string)$studentAnswer))
                                  === strtolower(trim((string)$q->correct_answer));
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
                        ? 'Correct!'
                        : 'The correct answer is: ' . $q->correct_answer,
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

        $attempt->update([
            'score'        => $autoScore,
            'total_marks'  => $totalPossible,
            'percentage'   => $percentage,
            'grade'        => $grade,
            'status'       => $status,
            'answers'      => $submittedAnswers,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'attempt_id'      => $attempt->id,
                // Auto-graded scores
                'auto_score'      => $autoScore,
                'auto_total'      => $autoTotal,
                // Pending manual marks
                'pending_total'   => $pendingTotal,
                'has_pending'     => $hasPendingMarks,
                // Overall
                'score'           => $autoScore,
                'total_marks'     => $totalPossible,
                'percentage'      => $hasPendingMarks ? null : $percentage,
                'grade'           => $grade,
                'status'          => $status,
                // Type-level breakdown e.g. {"multiple_choice":{"earned":10,"total":20},...}
                'type_breakdown'  => $typeBreakdown,
                // Exam meta
                'exam_title'      => $exam->title,
                'course_code'     => $exam->course_code,
                'course_name'     => $exam->course_name,
                'questionsReview' => $questionsReview,
            ]
        ]);
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

        $attempts = ExamAttempt::where('user_id', $student->id)
            ->whereIn('status', ['submitted', 'graded'])
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
                    $canAutoGrade = in_array($q->type, ['multiple_choice', 'true_false']);
                    $isCorrect = $canAutoGrade
                        ? strtolower(trim((string)$studentAnswer)) === strtolower(trim((string)$q->correct_answer))
                        : false;

                    $review[] = [
                        'questionText'  => $q->text,
                        'studentAnswer' => $studentAnswer ?? 'Not answered',
                        'correctAnswer' => $q->correct_answer ?? 'N/A',
                        'explanation'   => $isCorrect ? 'Correct!' : 'The correct answer is: ' . ($q->correct_answer ?? 'N/A'),
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

        // Sort: first by instruction group order, then by id within the group
        $sortedQuestions = $rawQuestions->sort(function ($a, $b) use ($instructionOrder) {
            $instA = trim($a->instruction ?? '');
            $instB = trim($b->instruction ?? '');
            $orderA = $instructionOrder[$instA] ?? 9999;
            $orderB = $instructionOrder[$instB] ?? 9999;
            if ($orderA !== $orderB) return $orderA <=> $orderB;
            return $a->id <=> $b->id; // ascending creation order within group
        })->values();

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
                foreach ($rawOptions as $optIdx => $opt) {
                    $letter = chr(65 + $optIdx);
                    $optText = is_array($opt) ? ($opt['text'] ?? '') : (string)$opt;
                    $cleanText = strip_tags($optText);
                    $optionsList[] = ['label' => $letter, 'text' => $cleanText];
                }

                $correctLetter = strtoupper(trim((string)$q->correct_answer));
                $studentLetter = strtoupper(trim((string)$studentAns));

                $isCorrect = ($isAnswered && $studentLetter === $correctLetter);
                $earnedMarks = $isCorrect ? $qMarks : 0;

                // Find full text
                $correctOpt = collect($optionsList)->firstWhere('label', $correctLetter);
                $studentOpt = collect($optionsList)->firstWhere('label', $studentLetter);

                $formattedCorrectAnswer = $correctOpt ? "{$correctOpt['label']}. {$correctOpt['text']}" : ($correctLetter ?: 'N/A');
                $formattedStudentAnswer = $studentOpt ? "{$studentOpt['label']}. {$studentOpt['text']}" : ($studentLetter ?: 'Not answered');
            } elseif ($typeKey === 'true_false') {
                $correctRaw = strtolower(trim((string)$q->correct_answer));
                $studentRaw = strtolower(trim((string)$studentAns));

                $correctVal = in_array($correctRaw, ['true', 'a', '1']) ? 'True' : 'False';
                $studentVal = $isAnswered ? (in_array($studentRaw, ['true', 'a', '1']) ? 'True' : 'False') : 'Not answered';

                $isCorrect = ($isAnswered && $studentVal === $correctVal);
                $earnedMarks = $isCorrect ? $qMarks : 0;

                $formattedCorrectAnswer = $correctVal;
                $formattedStudentAnswer = $studentVal;
            } elseif ($typeKey === 'fill_blank') {
                $correctVal = strip_tags($q->correct_answer ?? $q->sample_answer ?? '');
                $studentVal = $isAnswered ? strip_tags((string)$studentAns) : 'Not answered';

                if ($attempt->status === 'graded') {
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
                // If attempt score contains manual marking or auto heuristic
                if ($attempt->status === 'graded') {
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
                $pairs = $q->options ?? [];

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
}
