<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InstructorResultController extends Controller
{
    /**
     * Results dashboard — course info, stats, exam list, grading progress.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $instructor = $request->user()->load('department');

            $exams = Exam::where('user_id', $instructor->id)
                ->withCount(['questions', 'attempts'])
                ->orderBy('created_at', 'desc')
                ->get();

            $examIds     = $exams->pluck('id');
            $allAttempts = ExamAttempt::whereIn('exam_id', $examIds)->get();

            $completedExams = $exams->filter(fn($e) =>
                in_array($e->status, ['published', 'completed']) || $e->attempts_count > 0
            )->count();

            $pendingGrading = $exams->filter(fn($e) =>
                $allAttempts->where('exam_id', $e->id)->where('status', 'submitted')->count() > 0
            )->count();

            $publishedResults = $exams->filter(fn($e) =>
                !is_null($e->published_at)
            )->count();

            $avgScore = $allAttempts->count() > 0 ? round($allAttempts->avg('score'), 1) : 0;

            // Count real students in instructor's class
            $classStudents  = $this->getClassStudents($instructor);
            $totalStudents  = $classStudents->count();
            if ($totalStudents === 0 && $instructor->department_id) {
                $totalStudents = User::where('role', 'student')
                    ->where('department_id', $instructor->department_id)->count();
            }

            $resultExams = $exams->map(function ($exam) use ($allAttempts, $totalStudents) {
                $examAttempts = $allAttempts->where('exam_id', $exam->id);
                $submittedCount = $examAttempts->filter(fn($a) => $a->submitted_at !== null || $a->status !== 'in_progress')->count();
                // A student is considered "graded" if their status is either 'graded' or 'published'
                $gradedCount    = $examAttempts->whereIn('status', ['graded', 'published'])->count();
                $publishedCount = $examAttempts->where('status', 'published')->count();
                $avg            = $examAttempts->count() > 0 ? round($examAttempts->avg('score'), 1) : null;
                
                // An exam is fully published ONLY if all submitted students have their results published
                $isPublished    = ($submittedCount > 0 && $publishedCount === $submittedCount);
                // An exam is fully graded ONLY if all submitted students are graded (but not yet all published)
                $isFullyGraded  = ($submittedCount > 0 && $gradedCount === $submittedCount && !$isPublished);

                // ── Time-based Status Logic ──────────────────────────────────────────
                $now = now();
                $scheduledAt  = $exam->scheduled_at;
                $durationMins = (int)($exam->duration_minutes ?? 0);

                if ($isPublished) {
                    $statusStr = 'Published';
                } elseif ($isFullyGraded) {
                    $statusStr = 'Graded';
                } elseif ($scheduledAt) {
                    $examEnd = $scheduledAt->copy()->addMinutes($durationMins);
                    if ($now->lt($scheduledAt)) {
                        // Before exam starts
                        $statusStr = 'Draft';
                    } elseif ($now->lte($examEnd)) {
                        // During exam window
                        $statusStr = 'Pending';
                    } else {
                        // Exam time has fully elapsed, but not fully graded yet
                        $statusStr = 'Completed';
                    }
                } else {
                    // No scheduled_at set — fall back to submission counts
                    if ($gradedCount > 0)                 $statusStr = 'Completed';
                    elseif ($submittedCount > 0)          $statusStr = 'Pending';
                    else                                  $statusStr = 'Draft';
                }
                // ─────────────────────────────────────────────────────────────────────

                return [
                    'id'              => $exam->id,
                    'title'           => $exam->title,
                    // NOTE: exam.course_name column stores exam TYPE (e.g. "Mid Exam"),
                    //       real course name is on the instructor profile.
                    'subtitle'        => 'Semester Examination',
                    'type'            => $exam->course_name ?? 'Mid Exam',
                    'scheduled_at'    => $exam->scheduled_at
                        ? $exam->scheduled_at->toIso8601String()
                        : $exam->created_at->toIso8601String(),
                    'total_students'  => $totalStudents,
                    'submitted_count' => $submittedCount,
                    'graded_count'    => $gradedCount,
                    'is_published'    => $isPublished,
                    'average_score'   => $avg,
                    'status'          => $statusStr,
                ];
            });

            $gradedCount  = $allAttempts->where('status', 'graded')->count();
            $gradingPct   = $allAttempts->count() > 0
                ? round(($gradedCount / $allAttempts->count()) * 100)
                : 0;

            return response()->json([
                'data' => [
                    'course' => [
                        'name'            => $instructor->course_name ?? 'N/A',
                        'code'            => $instructor->course_code ?? 'N/A',
                        'instructor_name' => $instructor->name,
                        'status'          => 'Active',
                        'semester'        => $instructor->semester ?? 'Semester I',
                        'academic_year'   => $instructor->academic_year ?? '2025 / 2026',
                        'department'      => $instructor->department?->name ?? 'N/A',
                    ],
                    'stats' => [
                        'total_exams'            => $exams->count(),
                        'completed_exams'        => $completedExams,
                        'pending_manual_grading' => $pendingGrading,
                        'published_results'      => $publishedResults,
                        'average_score'          => $avgScore,
                        'students_evaluated'     => $gradedCount,
                        'total_students'         => $totalStudents,
                    ],
                    'results'           => $resultExams,
                    'grading_progress'  => [
                        'percentage' => $gradingPct,
                        'graded'     => $gradedCount,
                        'total'      => $totalStudents,
                    ],
                ]
            ]);

        } catch (\Throwable $e) {
            Log::error('InstructorResultController::index - ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Exam result detail — exam info card + per-student result rows.
     */
    public function showExamResults(Request $request, string $examId): JsonResponse
    {
        try {
            $instructor = $request->user()->load('department');

            // 1. Load the exam
            $exam = Exam::find((int)$examId);
            if (!$exam) {
                return response()->json(['error' => 'Exam not found'], 404);
            }

            // KEY: exam.course_name column actually stores the EXAM TYPE (e.g. "Mid Exam"),
            //      NOT the course name. Real course name lives on the instructor profile.
            $examType        = $exam->course_name ?? 'Mid Exam';      // e.g. "Mid Exam"
            $courseCode      = $exam->course_code ?? $instructor->course_code ?? 'N/A';  // e.g. "FN-3"
            $courseName      = $instructor->course_name ?? $courseCode; // e.g. "fundamental"
            $totalMarks      = (int)($exam->total_marks ?? 0);
            $durationMinutes = (int)($exam->duration_minutes ?? 0);
            $scheduledAt     = $exam->scheduled_at
                ? $exam->scheduled_at->toIso8601String()
                : $exam->created_at->toIso8601String();
            $isPublished     = !is_null($exam->published_at);

            // 2. Fetch attempts for this exam — student relationship is named 'student' in ExamAttempt model
            $attempts = ExamAttempt::where('exam_id', $exam->id)
                ->with('student')                             // BelongsTo User via 'student' method
                ->get()
                ->filter(fn($a) => $a->student && $a->student->role === 'student') // exclude instructor
                ->keyBy('user_id');

            // 3. Get the real student list for instructor's class
            $classStudents = $this->getClassStudents($instructor);

            // 4. Merge any attempted students not already in class list
            if ($attempts->isNotEmpty()) {
                $attemptedUserIds = $attempts->keys();
                $extraStudents = User::where('role', 'student')
                    ->whereIn('id', $attemptedUserIds)
                    ->get();
                $classStudents = $classStudents->merge($extraStudents)->unique('id')->values();
            }

            // Load all questions for this exam (to compute real auto/manual splits)
            $examQuestions = \App\Models\Question::where('exam_id', $exam->id)->get();
            $autoQIds = $examQuestions->filter(fn($q) => in_array($q->type, ['multiple_choice', 'true_false', 'matching']))->pluck('id')->flip();
            $manualQIds = $examQuestions->filter(fn($q) => !in_array($q->type, ['multiple_choice', 'true_false', 'matching']))->pluck('id')->flip();
            $autoQTotal = $examQuestions->filter(fn($q) => in_array($q->type, ['multiple_choice', 'true_false', 'matching']))->sum('marks');
            $manualQTotal = $examQuestions->filter(fn($q) => !in_array($q->type, ['multiple_choice', 'true_false', 'matching']))->sum('marks');
            $hasManualQuestions = $manualQIds->count() > 0;

            // 5. Build per-student result rows
            $formattedStudents = $classStudents->map(function ($student, $idx) use ($attempts, $totalMarks, $scheduledAt, $isPublished, $examQuestions, $autoQIds, $manualQIds, $autoQTotal, $manualQTotal, $hasManualQuestions) {
                $attempt = $attempts->get($student->id);

                if ($attempt) {
                    $answers = $attempt->answers ?? [];
                    if (is_string($answers)) $answers = json_decode($answers, true) ?? [];

                    // Real auto score computed across all MCQ, TF, and Matching questions
                    $autoScore = 0;
                    foreach ($examQuestions as $q) {
                        if (!$autoQIds->has($q->id)) continue;
                        $studentAns = $answers[(string)$q->id] ?? null;
                        $autoScore += $this->computeQuestionAutoScore($q, $studentAns);
                    }
                    $autoScore = round($autoScore, 2);

                    // Real manual score from stored _manual_scores
                    $manualScores = $answers['_manual_scores'] ?? [];
                    $manualScore  = round(array_sum($manualScores), 2);

                    $score = round($autoScore + $manualScore, 2);
                    $pct   = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 1) : 0;
                    $grade = $this->calculateGrade($pct);

                    // Auto-sync attempt record in DB if score was previously out of sync
                    if ((float)($attempt->score ?? 0) !== (float)$score) {
                        $attempt->update([
                            'score'      => $score,
                            'percentage' => $pct,
                            'grade'      => $grade,
                        ]);
                    }

                    // Status shown in the instructor's student result table:
                    // Published: attempt status is 'published' (auto-graded all-MCQ, or instructor clicked Re-Publish)
                    // Graded: instructor clicked Save Grades (No Publish) — student cannot see result yet
                    // Pending: student submitted with manual/subjective questions awaiting instructor review
                    // Absent: no submission
                    if ($attempt->status === 'published') {
                        $status = 'Published';
                    } elseif ($attempt->status === 'graded') {
                        $status = 'Graded';
                    } elseif (in_array($attempt->status, ['submitted', 'in_progress'])) {
                        $status = 'Pending';
                    } else {
                        $status = 'Absent';
                    }

                    $submittedOn = $attempt->submitted_at
                        ? $attempt->submitted_at->toIso8601String()
                        : ($attempt->updated_at ? $attempt->updated_at->toIso8601String() : $scheduledAt);
                } else {
                    $autoScore   = 0;
                    $manualScore = 0;
                    $score       = 0;
                    $status      = 'Absent';
                    $submittedOn = $scheduledAt;
                    $grade       = 'F';
                }

                return [
                    'id'          => $student->id,
                    'studentId'   => $student->id_no ?? ('STU' . str_pad($student->id, 7, '0', STR_PAD_LEFT)),
                    'name'        => $student->name,
                    'section'     => $student->section ?? 'N/A',
                    'submittedOn' => $submittedOn,
                    'autoScore'   => $autoScore,
                    'autoTotal'   => $autoQTotal,
                    'manualScore' => $manualScore,
                    'manualTotal' => $manualQTotal,
                    'finalScore'  => $score,
                    'totalMarks'  => $totalMarks,
                    'grade'       => $grade,
                    'status'      => $status,
                ];
            })->values();

                    // 6. Summary metrics
            $total     = max($formattedStudents->count(), 1);
            $submitted = $formattedStudents->filter(fn($s) => $s['status'] !== 'Absent')->count();
            $graded    = $formattedStudents->filter(fn($s) => $s['status'] === 'Graded' || $s['status'] === 'Published')->count();
            $published = $formattedStudents->filter(fn($s) => $s['status'] === 'Published')->count();
            $pending   = $formattedStudents->filter(fn($s) => $s['status'] === 'Pending')->count();
            $absent    = $formattedStudents->filter(fn($s) => $s['status'] === 'Absent')->count();

            // Status logic for the top card
            $publishStatus = 'Not Published';
            if ($submitted > 0) {
                if ($published === $submitted) {
                    $publishStatus = 'Published';
                } elseif ($graded === $submitted) {
                    $publishStatus = 'Graded';
                } elseif ($pending > 0 || $graded > 0 || $published > 0) {
                    $publishStatus = 'Pending';
                }
            }

            return response()->json([
                'data' => [
                    'exam' => [
                        'id'               => $exam->id,
                        'title'            => $exam->title,
                        'type'             => $examType,
                        'course_code'      => $courseCode,
                        'course_name'      => $courseName,
                        'scheduled_at'     => $scheduledAt,
                        'duration_minutes' => $durationMinutes,
                        'total_marks'      => $totalMarks,
                        'total_students'   => $formattedStudents->count(),
                        'submitted_count'  => $submitted,
                        'submitted_pct'    => round(($submitted / $total) * 100, 1),
                        'is_published'     => $publishStatus === 'Published',
                        'publish_status'   => $publishStatus,
                    ],
                    'summary' => [
                        'total_students' => $formattedStudents->count(),
                        'submitted'      => $submitted,
                        'submitted_pct'  => round(($submitted / $total) * 100, 1),
                        'graded'         => $graded,
                        'graded_pct'     => round(($graded / $total) * 100, 1),
                        'pending'        => $pending,
                        'pending_pct'    => round(($pending / $total) * 100, 1),
                        'absent'         => $absent,
                        'absent_pct'     => round(($absent / $total) * 100, 1),
                    ],
                    'score_distribution' => [
                        'A' => $formattedStudents->filter(fn($s) => str_starts_with($s['grade'], 'A'))->count(),
                        'B' => $formattedStudents->filter(fn($s) => str_starts_with($s['grade'], 'B'))->count(),
                        'C' => $formattedStudents->filter(fn($s) => str_starts_with($s['grade'], 'C'))->count(),
                        'D' => $formattedStudents->filter(fn($s) => str_starts_with($s['grade'], 'D'))->count(),
                        'F' => $formattedStudents->filter(fn($s) => $s['grade'] === 'F')->count(),
                    ],
                    'students' => $formattedStudents->all(),
                ]
            ]);

        } catch (\Throwable $e) {
            Log::error('showExamResults error: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Return students matching the instructor's department + year_level + section.
     * Handles section stored as "A" vs "Section A" in different user records.
     */
    private function getClassStudents($instructor)
    {
        $query = User::where('role', 'student');

        if ($instructor->department_id) {
            $query->where('department_id', $instructor->department_id);
        }

        if ($instructor->year_level) {
            $query->where('year_level', $instructor->year_level);
        }

        if ($instructor->section) {
            // Normalize: strip "Section " prefix → "A"
            $raw   = $instructor->section;                               // "Section A"
            $clean = trim(preg_replace('/^[Ss]ection\s+/i', '', $raw)); // "A"

            $query->where(function ($q) use ($raw, $clean) {
                $q->where('section', $raw)
                  ->orWhere('section', $clean)
                  ->orWhere('section', 'Section ' . $clean);
            });
        }

        $students = $query->get();

        // Fallback to dept + year_level only (ignore section mismatch)
        if ($students->isEmpty() && $instructor->department_id && $instructor->year_level) {
            $students = User::where('role', 'student')
                ->where('department_id', $instructor->department_id)
                ->where('year_level', $instructor->year_level)
                ->get();
        }

        // Fallback to dept only
        if ($students->isEmpty() && $instructor->department_id) {
            $students = User::where('role', 'student')
                ->where('department_id', $instructor->department_id)
                ->get();
        }

        return $students;
    }

    /**
     * GET /instructor/results/{examId}/student/{studentId}
     * Returns full per-student result: student info, attempt, and question-by-question breakdown.
     */
    public function showStudentResult(Request $request, string $examId, string $studentId): JsonResponse
    {
        try {
            $instructor = $request->user()->load('department');

            $exam = Exam::find((int)$examId);
            if (!$exam) {
                return response()->json(['error' => 'Exam not found'], 404);
            }

            $student = User::find((int)$studentId);
            if (!$student) {
                return response()->json(['error' => 'Student not found'], 404);
            }

            $attempt = ExamAttempt::where('exam_id', $exam->id)
                ->where('user_id', $student->id)
                ->first();

            $totalMarks      = (int)($exam->total_marks ?? 0);
            $durationMinutes = (int)($exam->duration_minutes ?? 0);
            $examType        = $exam->course_name ?? 'Mid Exam';
            $courseCode      = $exam->course_code ?? $instructor->course_code ?? 'N/A';
            $courseName      = $instructor->course_name ?? $courseCode;

            // Load questions for this exam, ordered by instruction group then by id ascending.
            // The instructor groups questions under the same instruction text — we must
            // preserve the FIRST OCCURRENCE order of each unique instruction string,
            // then sort questions within that group by id (creation order).
            $rawQuestions = \App\Models\Question::where('exam_id', $exam->id)
                ->orderBy('id')
                ->get();

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

            // Student answers keyed by question_id
            $answers = $attempt ? ($attempt->answers ?? []) : [];
            if (is_string($answers)) {
                $answers = json_decode($answers, true) ?? [];
            }

            // Build question breakdown
            $questionList = $sortedQuestions->map(function ($q, $idx) use ($answers) {
                $qId         = (string)$q->id;
                $studentAns  = $answers[$qId] ?? null;
                $correct     = $q->correct_answer;
                $options     = $q->options ?? [];
                $instruction = trim($q->instruction ?? '');
                $isMatching  = $q->type === 'matching';
                $isAuto      = in_array($q->type, ['multiple_choice', 'true_false']);

                if ($isMatching && empty($options)) {
                    $qData = is_array($q->question_data) ? $q->question_data : (json_decode($q->question_data ?? '', true) ?: []);
                    $colA = $qData['column_a'] ?? [];
                    $colB = $qData['column_b'] ?? [];
                    if (!empty($colA) && !empty($colB)) {
                        $maxLen = max(count($colA), count($colB));
                        for ($i = 0; $i < $maxLen; $i++) {
                            $leftText = isset($colA[$i]) ? (is_array($colA[$i]) ? ($colA[$i]['text'] ?? $colA[$i]['label'] ?? '') : (string)$colA[$i]) : '';
                            $bItem = $colB[$i] ?? null;
                            $rightText = '';
                            if ($bItem !== null) {
                                $rightText = is_array($bItem) ? ($bItem['text'] ?? $bItem['label'] ?? '') : (string)$bItem;
                            }
                            if ($leftText !== '' || $rightText !== '') {
                                $options[] = ['left' => $leftText, 'right' => $rightText];
                            }
                        }
                    } elseif (!empty($qData['pairs'])) {
                        $options = $qData['pairs'];
                    }
                }

                // ── MCQ / True-False ─────────────────────────────────────────
                if ($isAuto) {
                    $scored    = $this->computeQuestionAutoScore($q, $studentAns);
                    $isCorrect = ($q->marks > 0 && $scored >= $q->marks);

                    $formattedOptions = collect($options)->map(function ($opt, $i) {
                        $letter = chr(65 + $i);
                        $text   = is_array($opt) ? ($opt['text'] ?? '') : (string)$opt;
                        return ['letter' => $letter, 'text' => strip_tags($text)];
                    })->values()->all();

                    return [
                        'id'             => $q->id,
                        'number'         => $idx + 1,
                        'type'           => $q->type,
                        'instruction'    => $instruction,
                        'text'           => strip_tags($q->text ?? ''),
                        'options'        => $formattedOptions,
                        'matching_pairs' => null,
                        'correct_answer' => $correct,
                        'student_answer' => $studentAns,
                        'marks'          => $q->marks,
                        'scored'         => $scored,
                        'is_auto'        => true,
                        'is_correct'     => $isCorrect,
                        'explanation'    => strip_tags($q->explanation ?? ''),
                        'manual_score'   => null,
                    ];
                }

                // ── Matching ─────────────────────────────────────────────────
                if ($isMatching) {
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
                    if ($studentAns && is_string($studentAns)) {
                        $decoded = json_decode($studentAns, true);
                        if (is_array($decoded)) {
                            $studentPairs = $decoded;
                        } else {
                            foreach (explode(',', $studentAns) as $part) {
                                $pieces = explode(':', $part, 2);
                                if (count($pieces) === 2) {
                                    $studentPairs[(string)$pieces[0]] = strtoupper(trim($pieces[1]));
                                }
                            }
                        }
                    } elseif (is_array($studentAns)) {
                        $studentPairs = $studentAns;
                    }

                    // Build Column A (left items) and Column B (right items)
                    $colACounter = 0;
                    $pairs = collect($options)->map(function ($opt, $i) use ($studentPairs, $options, $correctAnswers, &$colACounter) {
                        $leftText = is_array($opt) ? strip_tags($opt['left'] ?? '') : '';
                        if ($leftText === '') return null;

                        $colACounter++;
                        $colAKey = (string)$colACounter;

                        // Expected letter and text for this row
                        $expectedLetter = isset($correctAnswers[$colAKey])
                            ? strtoupper($correctAnswers[$colAKey])
                            : ($correctAnswers[(string)$i] ?? chr(65 + $i));

                        $expectedLetterIdx = ord($expectedLetter) - 65;
                        $correctRightText  = ($expectedLetterIdx >= 0 && isset($options[$expectedLetterIdx]))
                            ? strip_tags($options[$expectedLetterIdx]['right'] ?? '')
                            : '';

                        // Student selection
                        $studentMatchLetter = $studentPairs[(string)$i]
                            ?? ($studentPairs[$colAKey]
                            ?? ($studentPairs[chr(65 + $i)]
                            ?? ''));

                        $studentRightText = 'Not matched';
                        if ($studentMatchLetter !== '') {
                            $studentLetterIdx = ord(strtoupper($studentMatchLetter)) - 65;
                            if ($studentLetterIdx >= 0 && isset($options[$studentLetterIdx])) {
                                $studentRightText = strip_tags($options[$studentLetterIdx]['right'] ?? 'Not matched');
                            }
                        }

                        $isRowCorrect = $studentMatchLetter !== '' &&
                            strtoupper(trim((string)$studentMatchLetter)) === strtoupper(trim((string)$expectedLetter));

                        return [
                            'index'          => $i,
                            'left_letter'    => (string)$colACounter,
                            'left_text'      => $leftText,
                            'right_text'     => $correctRightText,  // correct match text
                            'student_match'  => $studentRightText === 'Not matched' ? null : $studentRightText,
                            'is_correct'     => $isRowCorrect,
                        ];
                    })->filter()->values()->all();

                    // Column B = all right-side texts with letter labels (A, B, C, D...)
                    $columnB = collect($options)->map(function ($opt, $i) {
                        $rightText = is_array($opt) ? strip_tags($opt['right'] ?? '') : '';
                        return ['index' => $i, 'label' => chr(65 + $i), 'text' => $rightText];
                    })->values()->all();

                    $earnedMarks = $this->computeQuestionAutoScore($q, $studentAns);
                    $allCorrect  = ($q->marks > 0 && $earnedMarks >= $q->marks);

                    return [
                        'id'             => $q->id,
                        'number'         => $idx + 1,
                        'type'           => $q->type,
                        'instruction'    => $instruction,
                        'text'           => strip_tags($q->text ?? ''),
                        'options'        => [],           // not used for matching
                        'matching_pairs' => $pairs,       // per-row result
                        'column_b'       => $columnB,     // right-side items list
                        'correct_answer' => 'See correct matches below.',
                        'student_answer' => $studentAns ? 'Attempted' : 'Not answered',
                        'marks'          => $q->marks,
                        'scored'         => $earnedMarks,
                        'is_auto'        => true,
                        'is_correct'     => $allCorrect,
                        'explanation'    => strip_tags($q->explanation ?? ''),
                        'manual_score'   => null,
                    ];
                }

                // ── Short Answer / Essay (manual) ─────────────────────────────
                return [
                    'id'             => $q->id,
                    'number'         => $idx + 1,
                    'type'           => $q->type,
                    'instruction'    => $instruction,
                    'text'           => strip_tags($q->text ?? ''),
                    'options'        => [],
                    'matching_pairs' => null,
                    'correct_answer' => $correct,
                    'student_answer' => is_array($studentAns) ? json_encode($studentAns) : ($studentAns ?? ''),
                    'marks'          => $q->marks,
                    'scored'         => null,
                    'is_auto'        => false,
                    'is_correct'     => null,
                    'explanation'    => strip_tags($q->explanation ?? ''),
                    'manual_score'   => $answers['_manual_scores'][$q->id] ?? null,
                ];
            })->values()->all();

            // Score breakdown
            $autoQuestions    = collect($questionList)->filter(fn($q) => $q['is_auto']);
            $manualQuestions  = collect($questionList)->filter(fn($q) => !$q['is_auto']);
            $autoTotal        = $autoQuestions->sum('marks');
            $manualTotal      = $manualQuestions->sum('marks');
            $autoScore        = round($autoQuestions->sum('scored'), 2);
            $manualScoresList = $answers['_manual_scores'] ?? [];
            $manualScore      = round(array_sum($manualScoresList), 2);
            $finalScore       = round($autoScore + $manualScore, 2);
            $pct              = $totalMarks > 0 ? round(($finalScore / $totalMarks) * 100, 1) : 0;
            $grade            = $attempt ? ($attempt->grade ?? $this->calculateGrade($pct)) : 'F';

            // Sync attempt in DB if out of sync
            if ($attempt && (float)($attempt->score ?? 0) !== (float)$finalScore) {
                $attempt->update([
                    'score'      => $finalScore,
                    'percentage' => $pct,
                    'grade'      => $grade,
                ]);
            }

            // Time taken calculation
            $timeTaken = null;
            if ($attempt && $attempt->started_at && $attempt->submitted_at) {
                $timeTaken = (int)$attempt->started_at->diffInMinutes($attempt->submitted_at);
            }

            $submittedOn = $attempt?->submitted_at?->toIso8601String() ?? null;
            $status = $attempt ? match($attempt->status) {
                'published' => 'Published',
                'graded'    => 'Graded',
                'submitted' => 'Submitted',
                default     => 'In Progress',
            } : 'Absent';

            // Find next/prev student
            $attempts = ExamAttempt::where('exam_id', $exam->id)->with('student')->get()->filter(fn($a) => $a->student && $a->student->role === 'student')->keyBy('user_id');
            $classStudents = $this->getClassStudents($instructor);
            if ($attempts->isNotEmpty()) {
                $attemptedUserIds = $attempts->keys();
                $extraStudents = User::where('role', 'student')->whereIn('id', $attemptedUserIds)->get();
                $classStudents = $classStudents->merge($extraStudents)->unique('id')->values();
            }
            // Sort to ensure consistent order (e.g. by name)
            $classStudents = $classStudents->sortBy('name')->values();

            $currentIndex = $classStudents->search(fn($s) => (int)$s->id === (int)$student->id);
            $prevStudentId = $currentIndex !== false && $currentIndex > 0 ? $classStudents[$currentIndex - 1]->id : null;
            $nextStudentId = $currentIndex !== false && $currentIndex < $classStudents->count() - 1 ? $classStudents[$currentIndex + 1]->id : null;

            return response()->json([
                'data' => [
                    'prev_student_id' => $prevStudentId,
                    'next_student_id' => $nextStudentId,
                    'student' => [
                        'id'       => $student->id,
                        'name'     => $student->name,
                        'id_no'    => $student->id_no ?? ('STU' . str_pad($student->id, 7, '0', STR_PAD_LEFT)),
                        'email'    => $student->email,
                        'section'  => $student->section,
                        'initials' => collect(explode(' ', $student->name))->map(fn($w) => strtoupper($w[0] ?? ''))->take(2)->join(''),
                    ],
                    'exam' => [
                        'id'               => $exam->id,
                        'title'            => $exam->title,
                        'type'             => $examType,
                        'course_code'      => $courseCode,
                        'course_name'      => $courseName,
                        'total_marks'      => $totalMarks,
                        'duration_minutes' => $durationMinutes,
                        'is_published'     => !is_null($exam->published_at),
                    ],
                    'attempt' => [
                        'id'           => $attempt?->id,
                        'status'       => $status,
                        'submitted_on' => $submittedOn,
                        'time_taken'   => $timeTaken,
                        'attempt_no'   => 1,
                        'auto_score'   => $autoScore,
                        'auto_total'   => $autoTotal,
                        'manual_score' => $manualScore,
                        'manual_total' => $manualTotal,
                        'final_score'  => $finalScore,
                        'total_marks'  => $totalMarks,
                        'percentage'   => $pct,
                        'grade'        => $grade,
                    ],
                    'questions' => $questionList,
                    'breakdown' => [
                        'mcq_count'    => $autoQuestions->count(),
                        'manual_count' => $manualQuestions->count(),
                        'total_count'  => count($questionList),
                        'correct'      => $autoQuestions->filter(fn($q) => $q['is_correct'])->count(),
                        'incorrect'    => $autoQuestions->filter(fn($q) => !$q['is_correct'] && $q['student_answer'] !== null)->count(),
                        'pending'      => $manualQuestions->count(),
                    ],
                ]
            ]);

        } catch (\Throwable $e) {
            Log::error('showStudentResult: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /instructor/results/{examId}/student/{studentId}/save
     * Save manual scores per question without publishing (status = 'graded').
     * Only visible to instructor; strictly hidden from student.
     */
    public function saveGrades(Request $request, string $examId, string $studentId): JsonResponse
    {
        try {
            $exam    = Exam::findOrFail((int)$examId);
            $student = User::findOrFail((int)$studentId);

            $attempt = ExamAttempt::where('exam_id', $exam->id)
                ->where('user_id', $student->id)
                ->first();

            if (!$attempt) {
                return response()->json(['error' => 'No attempt found for this student'], 404);
            }

            $answers = $attempt->answers ?? [];
            if (is_string($answers)) $answers = json_decode($answers, true) ?? [];

            // If manual_scores are provided in request, update them
            // Otherwise preserve existing manual_scores from $answers['_manual_scores']
            if ($request->has('manual_scores')) {
                $manualScores = $request->input('manual_scores', []);
                $answers['_manual_scores'] = $manualScores;
            } else {
                $manualScores = $answers['_manual_scores'] ?? [];
            }

            // Accurately calculate auto-score (MCQ, True/False, Matching)
            $questions = \App\Models\Question::where('exam_id', $exam->id)->get();
            $autoScore = 0;
            foreach ($questions as $q) {
                if (in_array($q->type, ['multiple_choice', 'true_false', 'true-false', 'matching'])) {
                    $studentAns = $answers[(string)$q->id] ?? null;
                    $autoScore += $this->computeQuestionAutoScore($q, $studentAns);
                }
            }
            $autoScore   = round($autoScore, 2);
            $manualTotal = round(array_sum($manualScores), 2);
            $finalScore  = round($autoScore + $manualTotal, 2);
            $totalMarks  = (int)($exam->total_marks ?? 0);
            $pct         = $totalMarks > 0 ? round(($finalScore / $totalMarks) * 100, 1) : 0;
            $grade       = $this->calculateGrade($pct);

            // Save Grades (No Publish) → status = 'graded' (only visible to instructor, strictly hidden from student)
            $attempt->update([
                'score'      => $finalScore,
                'percentage' => $pct,
                'grade'      => $grade,
                'status'     => 'graded',
                'answers'    => $answers,
            ]);

            return response()->json([
                'message'     => 'Grades saved successfully (Unpublished)',
                'final_score' => $finalScore,
                'percentage'  => $pct,
                'grade'       => $grade,
            ]);

        } catch (\Throwable $e) {
            Log::error('saveGrades: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /instructor/results/{examId}/student/{studentId}/publish
     * Save grades AND mark the attempt as published (visible to student).
     */
    public function publishResult(Request $request, string $examId, string $studentId): JsonResponse
    {
        try {
            // Reuse saveGrades logic (preserves existing manual_scores if not passed in request)
            $saveResponse = $this->saveGrades($request, $examId, $studentId);
            $saveData     = json_decode($saveResponse->getContent(), true);

            if ($saveResponse->getStatusCode() !== 200) {
                return $saveResponse;
            }

            // Publish: update attempt status to 'published' so student can see their result
            $student = \App\Models\User::find((int)$studentId);
            if ($student) {
                ExamAttempt::where('exam_id', (int)$examId)
                    ->where('user_id', $student->id)
                    ->update(['status' => 'published']);
            }

            // Check if all submitted attempts are now published
            $submittedCount = ExamAttempt::where('exam_id', (int)$examId)
                ->whereIn('status', ['submitted', 'graded', 'published'])
                ->count();
            $publishedCount = ExamAttempt::where('exam_id', (int)$examId)
                ->where('status', 'published')
                ->count();

            if ($submittedCount > 0 && $publishedCount >= $submittedCount) {
                Exam::where('id', (int)$examId)->update([
                    'status'       => 'published',
                    'published_at' => now(),
                ]);
            }

            return response()->json([
                'message'     => 'Results published successfully',
                'final_score' => $saveData['final_score'] ?? 0,
                'percentage'  => $saveData['percentage'] ?? 0,
                'grade'       => $saveData['grade'] ?? 'F',
            ]);

        } catch (\Throwable $e) {
            Log::error('publishResult: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
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

        if ($q->type === 'matching') {
            $options = $q->options ?? [];
            if (is_string($options)) $options = json_decode($options, true) ?? [];
            if (empty($options)) return 0.0;

            // 1. Parse correct_answers map (e.g. {"1": "B", "2": "D", "3": "C", "4": "A"})
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

            // 2. Parse student selections (e.g. "0:B,1:D,2:C,3:A" or {"0":"B","1":"D"} or ["B","D"])
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

        if ($q->type === 'true_false' || $q->type === 'true-false') {
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

        if ($q->type === 'multiple_choice') {
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
}
