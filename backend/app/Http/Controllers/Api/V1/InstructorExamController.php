<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InstructorExamController extends Controller
{
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
                $query->where('department_id', $instructor->department_id)
                      ->where('year_level', $instructor->year_level);
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

        $exam = Exam::create([
            'user_id'          => $instructor->id,
            'course_code'      => $validated['course_code'] ?? $instructor->course_code ?? 'GENERAL',
            'course_name'      => $validated['course_name'] ?? $instructor->course_name ?? 'General Course',
            'section'          => $validated['section'] ?? null,
            'title'            => $validated['title'],
            'duration_minutes' => $validated['duration_minutes'],
            'total_marks'      => $validated['total_marks'],
            'status'           => $validated['status'],
            'scheduled_at'     => $validated['scheduled_at'] ? Carbon::parse($validated['scheduled_at']) : null,
            'settings'         => $validated['settings'] ?? null,
        ]);

        \Log::info('Exam Creation Payload: ', $request->all());

        if ($request->has('questions') && is_array($request->input('questions'))) {
            \Log::info('Questions array found: ', $request->input('questions'));
            foreach ($request->input('questions') as $q) {
                $exam->questions()->create([
                    'type'           => $q['type'] ?? 'multiple_choice',
                    'instruction'    => $q['instruction'] ?? null,
                    'text'           => $q['text'] ?? 'Untitled Question',
                    'options'        => collect($q['options'] ?? [])->map(function ($opt) { return is_string($opt) ? ['text' => $opt] : $opt; })->toArray(),
                    'correct_answer' => $q['correct_answer'] ?? null,
                    'marks'          => $q['marks'] ?? 5,
                    'marks_per_item' => isset($q['marks_per_item']) ? (float)$q['marks_per_item'] : null,
                    'difficulty'     => $q['difficulty'] ?? 'Medium',
                    'status'         => 1,
                ]);
            }
        } else {
            \Log::warning('No questions array found in request.');
        }

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

        $exam->update($validated);

        if ($request->has('questions') && is_array($request->input('questions'))) {
            // Remove existing questions for this exam
            $exam->questions()->delete();
            
            // Insert updated questions
            foreach ($request->input('questions') as $q) {
                $exam->questions()->create([
                    'type'           => $q['type'] ?? 'multiple_choice',
                    'instruction'    => $q['instruction'] ?? null,
                    'text'           => $q['text'] ?? 'Untitled Question',
                    'options'        => collect($q['options'] ?? [])->map(function ($opt) { return is_string($opt) ? ['text' => $opt] : $opt; })->toArray(),
                    'correct_answer' => $q['correct_answer'] ?? null,
                    'marks'          => $q['marks'] ?? 5,
                    'marks_per_item' => isset($q['marks_per_item']) ? (float)$q['marks_per_item'] : null,
                    'difficulty'     => $q['difficulty'] ?? 'Medium',
                    'status'         => 1,
                ]);
            }
        }

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
