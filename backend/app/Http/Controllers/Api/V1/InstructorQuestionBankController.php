<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\QuestionBank;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstructorQuestionBankController extends Controller
{
    /**
     * Display a listing of the question banks for the instructor's assigned course.
     */
    public function index(Request $request): JsonResponse
    {
        $instructor = $request->user();

        $query = QuestionBank::query();

        if ($request->boolean('all')) {
            // Exam creator mode or viewing all banks
            if ($request->filled('course_code')) {
                $query->where('course_code', $request->course_code);
            }
        } else {
            // Find banks owned by instructor or in same department/course
            $deptUserIds = ($instructor && $instructor->department_id)
                ? \App\Models\User::where('department_id', $instructor->department_id)->pluck('id')
                : collect();

            $scopedQuery = QuestionBank::where(function ($q) use ($instructor, $deptUserIds) {
                if ($instructor) {
                    $q->where('user_id', $instructor->id);
                    if ($instructor->course_code) {
                        $q->orWhere('course_code', $instructor->course_code);
                    }
                }
                if ($deptUserIds->isNotEmpty()) {
                    $q->orWhereIn('user_id', $deptUserIds);
                }
            });

            if ($request->filled('course_code')) {
                $scopedQuery->where('course_code', $request->course_code);
            }

            // If any banks match the user's scope, use that; otherwise fallback to all banks
            if ((clone $scopedQuery)->exists()) {
                $query = $scopedQuery;
            }
        }

        if ($request->filled('search')) {
            $searchTerm = trim($request->search);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhere('course_code', 'like', "%{$searchTerm}%");
            });
        }

        $banks = $query->with(['questions', 'instructor.department'])
            ->withCount('questions')
            ->latest()
            ->get();

        // Fetch questions inside banks to determine types (mcq, sa, essay, tf)
        $banks->each(function ($bank) {
            $questions = $bank->questions;
            $bank->mcq_count = $questions->where('type', 'multiple_choice')->count();
            $bank->sa_count = $questions->where('type', 'short_answer')->count();
            $bank->essay_count = $questions->where('type', 'essay')->count();
            $bank->tf_count = $questions->where('type', 'true_false')->count();
        });

        // Global stats across all banks
        $totalBanks = $banks->count();
        $totalQuestions = $banks->sum('questions_count');
        $totalMcq = $banks->sum('mcq_count');
        $totalSa = $banks->sum('sa_count');
        $totalEssay = $banks->sum('essay_count');

        $latestCreated = $banks->sortByDesc('created_at')->first();
        $latestUpdated = $banks->sortByDesc('updated_at')->first();

        return response()->json([
            'data' => [
                'banks' => $banks->map(fn($bank) => [
                    'id'               => $bank->id,
                    'title'            => $bank->title,
                    'description'      => $bank->description,
                    'course_code'      => $bank->course_code,
                    'course_name'      => $bank->course_code ?: ($bank->instructor?->course_name ?? $instructor?->course_name ?? 'General'),
                    'status'           => 'Active', // Mocking status since there isn't one on the model currently
                    'total_questions'  => $bank->questions_count,
                    'types'            => [
                        'mcq'   => $bank->mcq_count,
                        'sa'    => $bank->sa_count,
                        'essay' => $bank->essay_count,
                        'tf'    => $bank->tf_count ?? 0,
                    ],
                    'created_at'       => $bank->created_at?->toISOString(),
                    'updated_at'       => $bank->updated_at?->toISOString(),
                ]),
                'stats' => [
                    'total_banks'     => $totalBanks,
                    'total_questions' => $totalQuestions,
                    'mcq_questions'   => $totalMcq,
                    'sa_questions'    => $totalSa,
                    'essay_questions' => $totalEssay,
                    'last_created'    => $latestCreated ? [
                        'title'      => $latestCreated->title,
                        'created_at' => $latestCreated->created_at?->toISOString(),
                    ] : null,
                    'last_updated'    => $latestUpdated ? [
                        'title'      => $latestUpdated->title,
                        'updated_at' => $latestUpdated->updated_at?->toISOString(),
                    ] : null,
                ]
            ]
        ]);
    }

    /**
     * Display a single question bank with all its questions.
     */
    public function show(Request $request, QuestionBank $questionBank): JsonResponse
    {
        $instructor = $request->user();

        // Load questions
        $questions = $questionBank->questions()->orderBy('id')->get();

        // Count by type
        $mcqCount = $questions->where('type', 'multiple_choice')->count();
        $saCount = $questions->where('type', 'short_answer')->count();
        $essayCount = $questions->where('type', 'essay')->count();
        $tfCount = $questions->where('type', 'true_false')->count();
        $total = $questions->count();

        // Build categories from chapters
        $categoryCounts = $questions->groupBy('chapter')->map->count()->sortDesc();

        $bankOwner = $questionBank->instructor?->load('department');
        $totalMarks = $questions->sum('marks');

        return response()->json([
            'data' => [
                'bank' => [
                    'id'               => $questionBank->id,
                    'title'            => $questionBank->title,
                    'description'      => $questionBank->description,
                    'course_code'      => $questionBank->course_code,
                    'course_name'      => $questionBank->course_code ?: ($bankOwner?->course_name ?? $instructor?->course_name ?? 'General'),
                    'department'       => $bankOwner?->department?->name ?? $instructor?->department?->name ?? 'Computer Science',
                    'instructor'       => $bankOwner?->name ?? $instructor?->name ?? 'Instructor',
                    'academic_year'    => ($bankOwner?->year_level ?? $instructor?->year_level) ? ($bankOwner?->year_level ?? $instructor?->year_level) . ' Year' : '1st Year',
                    'semester'         => 'Semester I',
                    'total_questions'  => $total,
                    'total_marks'      => $totalMarks,
                    'status'           => 'Active',
                    'created_at'       => $questionBank->created_at?->toISOString(),
                    'updated_at'       => $questionBank->updated_at?->toISOString(),
                ],
                'stats' => [
                    'total'       => $total,
                    'total_marks' => $totalMarks,
                    'mcq'         => $mcqCount,
                    'sa'          => $saCount,
                    'essay'       => $essayCount,
                    'tf'          => $tfCount,
                ],
                'questions' => $questions->map(fn($q) => [
                    'id'             => $q->id,
                    'text'           => $q->text,
                    'type'           => $q->type,
                    'difficulty'     => $q->difficulty ?? 'Medium',
                    'marks'          => $q->marks,
                    'chapter'        => $q->chapter,
                    'topic'          => $q->topic,
                    'options'        => $q->options,
                    'correct_answer' => $q->correct_answer,
                    'question_data'  => $q->question_data,
                    'status'         => in_array(strtolower((string)$q->status), ['published', 'active', '1']) ? 'Active' : 'Draft',
                    'tags'           => $q->tags,
                    'created_at'     => $q->created_at?->toISOString(),
                    'updated_at'     => $q->updated_at?->toISOString(),
                ]),
                'categories' => $categoryCounts->map(fn($count, $name) => [
                    'name'  => $name ?: 'Uncategorized',
                    'count' => $count,
                ])->values(),
            ]
        ]);
    }

    /**
     * Store a newly created question bank.
     */
    public function store(Request $request): JsonResponse
    {
        $instructor = $request->user();

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_code' => 'nullable|string|max:50',
        ]);

        $courseCode = $validated['course_code'] ?? $instructor->course_code ?? 'GENERAL';
        $courseName = $instructor->course_name ?? 'General Course';

        $bank = QuestionBank::create([
            'user_id'     => $instructor->id,
            'course_code' => $courseCode,
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'message' => 'Question bank created successfully',
            'data'    => [
                'id'               => $bank->id,
                'title'            => $bank->title,
                'description'      => $bank->description,
                'course_code'      => $bank->course_code,
                'course_name'      => $courseName,
                'status'           => 'Active',
                'total_questions'  => 0,
                'types'            => [
                    'mcq'   => 0,
                    'sa'    => 0,
                    'essay' => 0,
                    'tf'    => 0,
                ],
                'created_at'       => $bank->created_at?->toISOString(),
                'updated_at'       => $bank->updated_at->toISOString(),
            ]
        ], 201);
    }

    /**
     * Helper to verify if the authenticated user has access to a question bank.
     */
    private function canAccessBank($user, QuestionBank $questionBank): bool
    {
        if (!$user) return false;
        if ($questionBank->user_id === $user->id) return true;
        if ($user->role === 'admin' || $user->role === 'department_head') return true;
        $bankOwner = $questionBank->instructor;
        if ($user->department_id && $bankOwner?->department_id === $user->department_id) return true;
        return false;
    }

    /**
     * Store a new question in the specified question bank.
     */
    public function storeQuestion(Request $request, QuestionBank $questionBank): JsonResponse
    {
        if (!$this->canAccessBank($request->user(), $questionBank)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = \Validator::make($request->all(), [
            'type'           => 'required|string',
            'title'          => 'nullable|string|max:255',
            'description'    => 'nullable|string',
            'instruction'    => 'nullable|string',
            'difficulty'     => 'nullable|string',
            'chapter'        => 'nullable|string',
            'topic'          => 'nullable|string',
            'text'           => 'required|string',
            'options'        => 'nullable|array',
            'correct_answer' => 'nullable|string',
            'explanation'    => 'nullable|string',
            'image_url'      => 'nullable|string',
            'marks'          => 'required|integer|min:1',
            'negative_marks' => 'nullable|integer',
            'time_seconds'   => 'nullable|integer',
            'status'         => 'nullable|string',
            'tags'           => 'nullable|string',
            'settings'       => 'nullable|array',
            'question_data'  => 'nullable|array',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation Failed: ', $validator->errors()->toArray());
            file_put_contents(storage_path('logs/validation_errors.log'), json_encode([
                'payload' => $request->all(),
                'errors' => $validator->errors()->toArray()
            ]));
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        if (empty($validated['status'])) {
            $validated['status'] = 'draft';
        }

        $question = $questionBank->questions()->create($validated);

        return response()->json([
            'message' => 'Question created successfully as draft',
            'data'    => $question
        ], 201);
    }

    /**
     * Bulk store questions in the specified question bank as drafts.
     */
    public function bulkStoreQuestions(Request $request, QuestionBank $questionBank): JsonResponse
    {
        if (!$this->canAccessBank($request->user(), $questionBank)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = \Validator::make($request->all(), [
            'questions' => 'required|array|min:1',
            'questions.*.type' => 'required|string',
            'questions.*.title' => 'nullable|string|max:255',
            'questions.*.description' => 'nullable|string',
            'questions.*.instruction' => 'nullable|string',
            'questions.*.difficulty' => 'nullable|string',
            'questions.*.chapter' => 'nullable|string',
            'questions.*.topic' => 'nullable|string',
            'questions.*.text' => 'required|string',
            'questions.*.options' => 'nullable|array',
            'questions.*.correct_answer' => 'nullable',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.marks' => 'required|numeric|min:0.1',
            'questions.*.settings' => 'nullable|array',
            'questions.*.question_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            \Log::error('Bulk Store Questions Validation Failed: ', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $created = [];

        DB::transaction(function () use ($questionBank, $validated, &$created) {
            foreach ($validated['questions'] as $qData) {
                if (empty($qData['status'])) {
                    $qData['status'] = 'draft';
                }
                if (isset($qData['correct_answer']) && !is_string($qData['correct_answer'])) {
                    $qData['correct_answer'] = (string) $qData['correct_answer'];
                }
                $created[] = $questionBank->questions()->create($qData);
            }
        });

        return response()->json([
            'message' => 'Successfully saved ' . count($created) . ' questions as drafts',
            'data' => $created,
            'count' => count($created)
        ], 201);
    }

    /**
     * Get all draft questions for a specific question bank, grouped hierarchically.
     */
    public function getDraftQuestions(Request $request, QuestionBank $questionBank): JsonResponse
    {
        if (!$this->canAccessBank($request->user(), $questionBank)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $drafts = $questionBank->questions()
            ->where(function($q) {
                $q->where('status', 'draft')
                  ->orWhere('status', '0')
                  ->orWhereNull('status');
            })
            ->oldest() // Keep in creation order
            ->get();

        // Group by questionType -> instructionGroups -> questions
        $grouped = $drafts->groupBy('type')->map(function ($questionsOfType, $type) {
            $instructionGroups = $questionsOfType->groupBy(function($q) {
                return $q->instruction ?: '';
            })->map(function ($questionsOfInstruction, $instruction) {
                return [
                    'instruction' => $instruction === '' ? null : $instruction,
                    'questions' => $questionsOfInstruction->values()
                ];
            })->values();

            return [
                'questionType' => strtoupper(str_replace('_', ' ', $type)), // e.g. MULTIPLE CHOICE
                'instructionGroups' => $instructionGroups
            ];
        })->values();

        return response()->json([
            'data' => $grouped
        ]);
    }

    /**
     * Get distinct instructor instructions for autocomplete.
     */
    public function getInstructions(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $type = $request->query('type');
        $bankId = $request->query('bank_id');
        
        $query = Question::whereNotNull('instruction')
            ->where('instruction', '!=', '');

        if ($bankId) {
            $query->where('question_bank_id', $bankId);
        } elseif ($userId) {
            $query->whereHas('questionBank', function($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        $instructions = $query->distinct()->pluck('instruction');

        return response()->json([
            'data' => $instructions
        ]);
    }

    /**
     * Publish all draft questions for a question bank using a DB transaction.
     */
    public function publishQuestions(Request $request, QuestionBank $questionBank): JsonResponse
    {
        if (!$this->canAccessBank($request->user(), $questionBank)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::transaction(function () use ($questionBank) {
            // Update draft questions to published
            $questionBank->questions()
                ->where(function($q) {
                    $q->where('status', 'draft')
                      ->orWhere('status', '0')
                      ->orWhereNull('status');
                })
                ->update(['status' => 'published']);

            // Update total questions count on bank
            $publishedCount = $questionBank->questions()->where('status', 'published')->count();
            $questionBank->update(['questions_count' => $publishedCount]);
        });

        return response()->json([
            'message' => 'All questions published successfully!'
        ]);
    }

    /**
     * Update the specified question bank.
     */
    public function update(Request $request, QuestionBank $questionBank): JsonResponse
    {
        if (!$this->canAccessBank($request->user(), $questionBank)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $questionBank->update($validated);

        return response()->json([
            'message' => 'Question bank updated successfully',
            'data'    => $questionBank
        ]);
    }

    /**
     * Remove the specified question bank.
     */
    public function destroy(Request $request, QuestionBank $questionBank): JsonResponse
    {
        if (!$this->canAccessBank($request->user(), $questionBank)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $questionBank->delete();

        return response()->json([
            'message' => 'Question bank deleted successfully'
        ]);
    }

    /**
     * Display the specified question with bank details.
     */
    public function showQuestion(Request $request, $bankOrQuestion, $questionId = null): JsonResponse
    {
        $question = null;
        $questionBank = null;

        if ($questionId !== null) {
            $questionBank = QuestionBank::find($bankOrQuestion);
            $question = Question::find($questionId);
        } else {
            $question = Question::find($bankOrQuestion);
            $questionBank = $question?->questionBank;
        }

        if (!$question || !$questionBank) {
            return response()->json(['message' => 'Question or question bank not found'], 404);
        }

        if (!$this->canAccessBank($request->user(), $questionBank)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $bankOwner = $questionBank->instructor?->load('department');
        $totalQuestions = $questionBank->questions()->count();

        // Calculate usage in exams
        $timesUsed = DB::table('exam_questions')->where('question_id', $question->id)->count();

        // Format options
        $parsedOptions = [];
        if (is_array($question->options)) {
            foreach ($question->options as $index => $opt) {
                $label = is_numeric($index) ? chr(65 + (int)$index) : (string)$index;
                $optText = is_array($opt) ? ($opt['text'] ?? '') : (string)$opt;
                $isCorrect = is_array($opt)
                    ? (!empty($opt['is_correct']) || !empty($opt['isCorrect']) || $question->correct_answer === $label || $question->correct_answer === $optText)
                    : ($question->correct_answer === $label || $question->correct_answer === $optText);

                $parsedOptions[] = [
                    'label' => $label,
                    'text' => $optText,
                    'isCorrect' => $isCorrect,
                ];
            }
        }

        $typeLabels = [
            'multiple_choice' => 'Multiple Choice Question',
            'true_false' => 'True / False Question',
            'matching' => 'Matching Question',
            'fill_in_blank' => 'Fill in the Blank Question',
            'short_answer' => 'Short Answer Question',
            'essay' => 'Essay Question',
        ];

        return response()->json([
            'data' => [
                'bank' => [
                    'id'               => $questionBank->id,
                    'title'            => $questionBank->title,
                    'description'      => $questionBank->description,
                    'course_code'      => $questionBank->course_code,
                    'course_name'      => $questionBank->course_code ?: ($bankOwner?->course_name ?? 'General Course'),
                    'total_questions'  => $totalQuestions,
                    'total_marks'      => (int)$questionBank->questions()->sum('marks'),
                    'created_by'       => $bankOwner?->name ?? 'Instructor',
                    'created_at'       => $questionBank->created_at?->toISOString(),
                    'updated_at'       => $questionBank->updated_at?->toISOString(),
                    'status'           => 'Active',
                ],
                'question' => [
                    'id'             => $question->id,
                    'display_id'     => 'QDB-' . str_pad((string)$question->id, 3, '0', STR_PAD_LEFT),
                    'type'           => $question->type,
                    'type_label'     => $typeLabels[$question->type] ?? ucfirst(str_replace('_', ' ', $question->type)),
                    'difficulty'     => $question->difficulty ?? 'Medium',
                    'marks'          => $question->marks,
                    'negative_marks' => $question->negative_marks ?? 0,
                    'status'         => in_array(strtolower((string)$question->status), ['published', 'active', '1']) ? 'Active' : 'Draft',
                    'text'           => $question->text,
                    'image_url'      => $question->image_url,
                    'has_image'      => !empty($question->image_url),
                    'options'        => $parsedOptions,
                    'correct_answer' => $question->correct_answer,
                    'explanation'    => $question->explanation ?: 'No explanation provided.',
                    'chapter'        => $question->chapter,
                    'topic'          => $question->topic,
                    'tags'           => $question->tags ? (is_array($question->tags) ? $question->tags : array_filter(array_map('trim', explode(',', $question->tags)))) : [],
                    'question_data'  => $question->question_data,
                    'created_by'     => $bankOwner?->name ?? 'Instructor',
                    'created_at'     => $question->created_at?->toISOString(),
                    'updated_at'     => $question->updated_at?->toISOString(),
                ],
                'stats' => [
                    'times_used'     => $timesUsed,
                    'average_score'  => 'N/A',
                    'correct_rate'   => 'N/A',
                    'total_attempts' => 0,
                ]
            ]
        ]);
    }

    /**
     * Update the specified question.
     */
    public function updateQuestion(Request $request, Question $question): JsonResponse
    {
        $questionBank = $question->questionBank;
        if (!$questionBank || !$this->canAccessBank($request->user(), $questionBank)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = \Validator::make($request->all(), [
            'type'           => 'required|string',
            'title'          => 'nullable|string|max:255',
            'description'    => 'nullable|string',
            'instruction'    => 'nullable|string',
            'difficulty'     => 'nullable|string',
            'chapter'        => 'nullable|string',
            'topic'          => 'nullable|string',
            'text'           => 'required|string',
            'options'        => 'nullable|array',
            'correct_answer' => 'nullable|string',
            'explanation'    => 'nullable|string',
            'image_url'      => 'nullable|string',
            'marks'          => 'required|integer|min:1',
            'negative_marks' => 'nullable|integer',
            'time_seconds'   => 'nullable|integer',
            'status'         => 'nullable|string',
            'tags'           => 'nullable|string',
            'settings'       => 'nullable|array',
            'question_data'  => 'nullable|array',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation Failed: ', $validator->errors()->toArray());
            file_put_contents(storage_path('logs/validation_errors.log'), json_encode([
                'payload' => $request->all(),
                'errors' => $validator->errors()->toArray()
            ]));
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $question->update($validated);

        return response()->json([
            'message' => 'Question updated successfully',
            'data'    => $question
        ]);
    }

    /**
     * Remove the specified question.
     */
    public function destroyQuestion(Request $request, Question $question): JsonResponse
    {
        $questionBank = $question->questionBank;
        if (!$questionBank || !$this->canAccessBank($request->user(), $questionBank)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $question->delete();
        $questionBank->decrement('questions_count');

        return response()->json([
            'message' => 'Question deleted successfully'
        ]);
    }

    /**
     * Import questions from CSV.
     */
    public function importQuestions(Request $request, QuestionBank $questionBank): JsonResponse
    {
        if (!$this->canAccessBank($request->user(), $questionBank)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimetypes:text/csv,text/plain,application/vnd.ms-excel'
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $importedCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 8) continue;
            
            $type = trim($row[1]);
            $difficulty = trim($row[2]);
            $marks = (int)trim($row[3]);
            $chapter = trim($row[4]);
            $topic = trim($row[5]);
            $text = trim($row[6]);
            $correctAnswer = trim($row[7]);
            
            if (!$text || !$type) continue; // Skip invalid rows

            $options = null;
            if ($type === 'multiple_choice') {
                $options = ['Option A', 'Option B', 'Option C', 'Option D'];
            } elseif ($type === 'true_false') {
                $options = ['True', 'False'];
            }

            $questionBank->questions()->create([
                'type' => $type,
                'difficulty' => $difficulty ?: 'Medium',
                'marks' => $marks ?: 1,
                'chapter' => $chapter,
                'topic' => $topic,
                'text' => $text,
                'correct_answer' => $correctAnswer,
                'options' => $options,
                'status' => true
            ]);
            $importedCount++;
        }
        
        fclose($handle);
        
        $questionBank->update(['questions_count' => $questionBank->questions()->count()]);

        return response()->json([
            'message' => "Successfully imported $importedCount questions",
            'imported' => $importedCount
        ]);
    }
}
