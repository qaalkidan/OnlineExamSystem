<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\QuestionValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InstructorExamController extends Controller
{
    protected QuestionValidationService $questionValidationService;

    public function __construct(QuestionValidationService $questionValidationService)
    {
        $this->questionValidationService = $questionValidationService;
    }

    /**
     * Check for scheduling conflicts with other exams in the same department and year level.
     */
    private function checkSchedulingConflict($instructor, $scheduledAt, $durationMinutes, $excludeExamId = null)
    {
        if (!$scheduledAt) {
            return null;
        }

        $newStart = Carbon::parse($scheduledAt);
        $newEnd = $newStart->copy()->addMinutes($durationMinutes);

        return Exam::whereIn('status', ['published', 'scheduled'])
            ->whereHas('instructor', function ($query) use ($instructor) {
                $query->where('department_id', $instructor->department_id);
                if ($instructor->year_level && $instructor->year_level !== 'None') {
                    $query->where('year_level', $instructor->year_level);
                }
            })
            ->when($excludeExamId, function ($query) use ($excludeExamId) {
                return $query->where('id', '!=', $excludeExamId);
            })
            ->get()
            ->first(function ($exam) use ($newStart, $newEnd) {
                if (!$exam->scheduled_at) {
                    return false;
                }
                $existingStart = Carbon::parse($exam->scheduled_at);
                $existingEnd = $existingStart->copy()->addMinutes($exam->duration_minutes);

                // Overlap condition: Start A < End B && End A > Start B
                return $newStart->lt($existingEnd) && $newEnd->gt($existingStart);
            });
    }

    /**
     * Display a listing of the exams for the instructor's assigned course.
     */
    public function index(Request $request): JsonResponse
    {
        $instructor = $request->user();

        // Scope to instructor's single assigned course
        $exams = Exam::where('user_id', $instructor->id)
            ->withCount(['students', 'questions'])
            ->latest()
            ->get();

        // Stats for ExamStatCards
        $totalExams = $exams->count();
        $publishedExams = $exams->where('status', 'published')->count();
        $draftExams = $exams->where('status', 'draft')->count();
        $completedExams = $exams->where('status', 'completed')->count();

        return response()->json([
            'data' => [
                'exams' => $exams->map(fn($exam) => [
                    'id'               => $exam->id,
                    'title'            => $exam->title,
                    'course_code'      => $exam->course_code,
                    'course_name'      => $exam->course_name,
                    'section'          => $exam->section,
                    'status'           => $exam->status,
                    'duration_minutes' => $exam->duration_minutes,
                    'total_marks'      => $exam->total_marks,
                    'students_count'   => $exam->students_count,
                    'questions_count'  => $exam->questions_count,
                    'scheduled_at'     => $exam->scheduled_at?->toISOString(),
                    'settings'         => $exam->settings,
                    'created_at'       => $exam->created_at->toISOString(),
                ]),
                'stats' => [
                    'total'     => $totalExams,
                    'published' => $publishedExams,
                    'draft'     => $draftExams,
                    'completed' => $completedExams,
                ]
            ]
        ]);
    }

    /**
     * Download a sample question import template in PDF format.
     */
    public function downloadPdfTemplate(): \Illuminate\Http\Response
    {
        $content = $this->questionValidationService->generatePdfTemplate();

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="exam_questions_template.pdf"',
        ]);
    }

    /**
     * Validate an uploaded question file (.xlsx, .csv, .json, .pdf) before import.
     */
    public function validateImport(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB
        ]);

        $result = $this->questionValidationService->validateAndParseFile($request->file('file'));

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? ['file' => [$result['message']]],
            ], 422);
        }

        return response()->json([
            'message' => 'File validated successfully.',
            'data'    => $result,
        ]);
    }

    /**
     * Store a newly created exam in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $instructor = $request->user();

        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'course_code'      => 'nullable|string|max:50',
            'course_name'      => 'nullable|string|max:255',
            'section'          => 'nullable|string|max:50',
            'duration_minutes' => 'required|integer|min:1',
            'total_marks'      => 'required|integer|min:1',
            'status'           => 'required|in:draft,published,scheduled,completed',
            'scheduled_at'     => 'nullable|date',
            'settings'         => 'nullable|array',
            'questions'        => 'nullable|array',
        ]);

        if (in_array($validated['status'], ['published', 'scheduled']) && isset($validated['scheduled_at'])) {
            $conflict = $this->checkSchedulingConflict($instructor, $validated['scheduled_at'], $validated['duration_minutes']);
            if ($conflict) {
                return response()->json([
                    'message' => 'Scheduling Conflict: Another exam is already scheduled during this time in your department and year level.',
                    'errors' => [
                        'scheduled_at' => ['An exam titled "' . $conflict->title . '" is scheduled from ' . Carbon::parse($conflict->scheduled_at)->format('g:i A') . ' to ' . Carbon::parse($conflict->scheduled_at)->addMinutes($conflict->duration_minutes)->format('g:i A') . '. Please choose a time after this exam finishes.']
                    ]
                ], 409);
            }
        }

        // Validate questions array if provided
        $validatedQuestions = [];
        if ($request->has('questions') && is_array($request->input('questions')) && count($request->input('questions')) > 0) {
            $batchResult = $this->questionValidationService->validateQuestionsBatch($request->input('questions'));
            if (!$batchResult['valid']) {
                return response()->json([
                    'message' => 'One or more questions failed validation against the question format schema.',
                    'errors'  => $batchResult['errors'],
                ], 422);
            }
            $validatedQuestions = $batchResult['results'];
        }

        // Use DB Transaction to guarantee atomic creation
        $exam = DB::transaction(function () use ($instructor, $validated, $validatedQuestions) {
            $exam = Exam::create([
                'user_id'          => $instructor->id,
                'course_code'      => $validated['course_code'] ?? $instructor->course_code ?? 'GENERAL',
                'course_name'      => $validated['course_name'] ?? $instructor->course_name ?? 'General Course',
                'section'          => $validated['section'] ?? null,
                'title'            => $validated['title'],
                'duration_minutes' => $validated['duration_minutes'],
                'total_marks'      => $validated['total_marks'],
                'status'           => $validated['status'],
                'scheduled_at'     => !empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null,
                'settings'         => $validated['settings'] ?? null,
            ]);

            foreach ($validatedQuestions as $item) {
                $q = $item['question'];
                $exam->questions()->create([
                    'type'           => $q['type'],
                    'instruction'    => $q['instruction'] ?: null,
                    'text'           => $q['text'],
                    'options'        => $q['options'],
                    'correct_answer' => $q['correct_answer'],
                    'question_data'  => $q['question_data'],
                    'marks'          => $q['marks'],
                    'marks_per_item' => $q['question_data']['marks_per_item'] ?? null,
                    'difficulty'     => $q['difficulty'] ?? 'Medium',
                    'description'    => $q['description'] ?: null,
                    'status'         => 1,
                ]);
            }

            return $exam;
        });

        $exam->loadCount(['questions', 'students']);

        return response()->json([
            'message' => 'Exam created successfully',
            'data'    => [
                'id'               => $exam->id,
                'title'            => $exam->title,
                'course_code'      => $exam->course_code,
                'course_name'      => $exam->course_name,
                'status'           => $exam->status,
                'duration_minutes' => $exam->duration_minutes,
                'total_marks'      => $exam->total_marks,
                'students_count'   => $exam->students_count,
                'questions_count'  => $exam->questions_count,
                'scheduled_at'     => $exam->scheduled_at?->toISOString(),
                'settings'         => $exam->settings,
                'created_at'       => $exam->created_at->toISOString(),
            ]
        ], 201);
    }

    /**
     * Display the specified exam.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $instructor = $request->user();
        
        $exam = Exam::where('user_id', $instructor->id)
            ->where('id', $id)
            ->with('questions')
            ->withCount('students')
            ->firstOrFail();

        return response()->json([
            'data' => $exam
        ]);
    }

    /**
     * Update the specified exam in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $instructor = $request->user();

        $exam = Exam::where('user_id', $instructor->id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'title'            => 'sometimes|string|max:255',
            'course_code'      => 'nullable|string|max:50',
            'course_name'      => 'nullable|string|max:255',
            'section'          => 'nullable|string|max:50',
            'duration_minutes' => 'sometimes|integer|min:1',
            'total_marks'      => 'sometimes|integer|min:1',
            'status'           => 'sometimes|in:draft,published,scheduled,completed',
            'scheduled_at'     => 'nullable|date',
            'settings'         => 'nullable|array',
            'questions'        => 'nullable|array',
        ]);

        if (isset($validated['scheduled_at'])) {
            $validated['scheduled_at'] = Carbon::parse($validated['scheduled_at']);
        }

        $newStatus = $validated['status'] ?? $exam->status;
        $newScheduledAt = $validated['scheduled_at'] ?? $exam->scheduled_at;
        $newDuration = $validated['duration_minutes'] ?? $exam->duration_minutes;

        if (in_array($newStatus, ['published', 'scheduled']) && $newScheduledAt) {
            $conflict = $this->checkSchedulingConflict($instructor, $newScheduledAt, $newDuration, $exam->id);
            if ($conflict) {
                return response()->json([
                    'message' => 'Scheduling Conflict: Another exam is already scheduled during this time in your department and year level.',
                    'errors' => [
                        'scheduled_at' => ['An exam titled "' . $conflict->title . '" is scheduled from ' . Carbon::parse($conflict->scheduled_at)->format('g:i A') . ' to ' . Carbon::parse($conflict->scheduled_at)->addMinutes($conflict->duration_minutes)->format('g:i A') . '. Please choose a time after this exam finishes.']
                    ]
                ], 409);
            }
        }

        // Validate questions array if provided
        $validatedQuestions = [];
        if ($request->has('questions') && is_array($request->input('questions')) && count($request->input('questions')) > 0) {
            $batchResult = $this->questionValidationService->validateQuestionsBatch($request->input('questions'));
            if (!$batchResult['valid']) {
                return response()->json([
                    'message' => 'One or more questions failed validation against the question format schema.',
                    'errors'  => $batchResult['errors'],
                ], 422);
            }
            $validatedQuestions = $batchResult['results'];
        }

        DB::transaction(function () use ($exam, $validated, $request, $validatedQuestions) {
            $exam->update($validated);

            if ($request->has('questions') && is_array($request->input('questions'))) {
                // Remove existing questions for this exam
                $exam->questions()->delete();

                // Insert validated questions
                foreach ($validatedQuestions as $item) {
                    $q = $item['question'];
                    $exam->questions()->create([
                        'type'           => $q['type'],
                        'instruction'    => $q['instruction'] ?: null,
                        'text'           => $q['text'],
                        'options'        => $q['options'],
                        'correct_answer' => $q['correct_answer'],
                        'question_data'  => $q['question_data'],
                        'marks'          => $q['marks'],
                        'marks_per_item' => $q['question_data']['marks_per_item'] ?? null,
                        'difficulty'     => $q['difficulty'] ?? 'Medium',
                        'description'    => $q['description'] ?: null,
                        'status'         => 1,
                    ]);
                }
            }
        });

        $exam->loadCount(['questions', 'students']);
        $exam->load('questions');

        return response()->json([
            'message' => 'Exam updated successfully',
            'data'    => [
                'id'               => $exam->id,
                'title'            => $exam->title,
                'course_code'      => $exam->course_code,
                'course_name'      => $exam->course_name,
                'status'           => $exam->status,
                'duration_minutes' => $exam->duration_minutes,
                'total_marks'      => $exam->total_marks,
                'students_count'   => $exam->students_count,
                'questions_count'  => $exam->questions_count,
                'scheduled_at'     => $exam->scheduled_at?->toISOString(),
                'settings'         => $exam->settings,
                'created_at'       => $exam->created_at->toISOString(),
                'questions'        => $exam->questions,
            ]
        ]);
    }

    /**
     * Remove the specified exam from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $instructor = $request->user();

        $exam = Exam::where('user_id', $instructor->id)
            ->where('id', $id)
            ->firstOrFail();

        // Clean up dependent child records before deletion
        $exam->questions()->delete();
        $exam->attempts()->delete();
        $exam->students()->detach();
        $exam->delete();

        return response()->json([
            'message' => 'Exam deleted successfully'
        ]);
    }
}
