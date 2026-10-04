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
                    'updated_at'       => $bank->updated_at->toISOString(),
                ]),
                'stats' => [
                    'total_banks'     => $totalBanks,
                    'total_questions' => $totalQuestions,
                    'mcq_questions'   => $totalMcq,
                    'sa_questions'    => $totalSa,
                    'essay_questions' => $totalEssay,
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
                    'status'         => $q->status ? 'Active' : 'Inactive',
                    'tags'           => $q->tags,
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
                'updated_at'       => $bank->updated_at->toISOString(),
            ]
        ], 201);
    }

    /**
     * Store a new question in the specified question bank.
     */
    public function storeQuestion(Request $request, QuestionBank $questionBank): JsonResponse
    {
        // Ensure the instructor owns the question bank
        if ($questionBank->user_id !== $request->user()->id) {
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
     * Get all draft questions for a specific question bank, grouped hierarchically.
     */
    public function getDraftQuestions(Request $request, QuestionBank $questionBank): JsonResponse
    {
        if ($questionBank->user_id !== $request->user()->id) {
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
        if ($questionBank->user_id !== $request->user()->id) {
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
        if ($questionBank->user_id !== $request->user()->id) {
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
        if ($questionBank->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $questionBank->delete();

        return response()->json([
            'message' => 'Question bank deleted successfully'
        ]);
    }

    /**
     * Update the specified question.
     */
    public function updateQuestion(Request $request, Question $question): JsonResponse
    {
        $questionBank = $question->questionBank;
        if ($questionBank->user_id !== $request->user()->id) {
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
        if ($questionBank->user_id !== $request->user()->id) {
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
        if ($questionBank->user_id !== $request->user()->id) {
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
