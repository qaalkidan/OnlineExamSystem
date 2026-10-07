<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamRecoveryRequest;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminExamControlController extends Controller
{
    /**
     * List all recovery requests platform-wide for administrators.
     */
    public function indexRecoveryRequests(Request $request): JsonResponse
    {
        $query = ExamRecoveryRequest::with(['student', 'exam.course', 'exam.instructor', 'attempt', 'reviewer', 'overrider']);

        if ($request->filled('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate(20);

        return response()->json([
            'data' => collect($requests->items())->map(function ($r) {
                return $this->formatRecoveryRequest($r);
            }),
            'pagination' => [
                'total'        => $requests->total(),
                'per_page'     => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page'    => $requests->lastPage(),
            ]
        ]);
    }

    /**
     * Administrator override of an individual student's recovery time.
     * Requires confirmation, reason, and an audit-log entry.
     */
    public function overrideRecovery(Request $request, $id): JsonResponse
    {
        $admin = $request->user();

        if ($admin->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Administrator access required.'], 403);
        }

        $recovery = ExamRecoveryRequest::with(['exam', 'attempt', 'student'])->findOrFail($id);
        $attempt = $recovery->attempt;

        if (!$attempt) {
            return response()->json(['message' => 'Associated exam attempt not found.'], 404);
        }

        $validated = $request->validate([
            'approved_seconds' => 'required|integer|min:0',
            'override_reason'  => 'required|string|min:4|max:500',
        ], [
            'override_reason.required' => 'An administrative reason is strictly required to perform an override.',
            'override_reason.min'      => 'Please provide a clear override reason (minimum 4 characters).',
        ]);

        $approvedSeconds = (int) $validated['approved_seconds'];
        $oldExtra = $attempt->extra_time_seconds;
        $oldDeadline = $attempt->getAdjustedDeadline()?->format('h:i:s A');

        // Overwrite or update the attempt's extra time
        // Calculate difference to set the extra_time_seconds accurately
        $attempt->extra_time_seconds = $approvedSeconds;
        $attempt->save();

        $newDeadline = $attempt->getAdjustedDeadline()?->format('h:i:s A');

        $recovery->update([
            'approved_seconds' => $approvedSeconds,
            'status'           => 'approved',
            'override_by'      => $admin->id,
            'override_reason'  => $validated['override_reason'],
            'reviewed_at'      => Carbon::now(),
        ]);

        // Audit log
        LogActivity::record(
            'Admin Override',
            'Exam Recovery',
            "Admin {$admin->name} overrode extra time for student {$recovery->student->name} on \"{$recovery->exam->title}\". Previous: +{$oldExtra}s ({$oldDeadline}), New: +{$approvedSeconds}s ({$newDeadline}). Reason: {$validated['override_reason']}"
        );

        $recovery->load(['student', 'exam', 'attempt', 'reviewer', 'overrider']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Administrator override applied successfully.',
            'data'    => $this->formatRecoveryRequest($recovery),
        ]);
    }

    /**
     * Cancel an examination globally.
     * Prevents new attempts, halts active attempts, stops timers, and preserves answers & records.
     */
    public function cancelExam(Request $request, Exam $exam): JsonResponse
    {
        $admin = $request->user();

        if ($admin->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Only administrators can cancel examinations.'], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:4|max:500',
        ], [
            'reason.required' => 'A cancellation reason is required.',
            'reason.min'      => 'Cancellation reason must be at least 4 characters long.',
        ]);

        if ($exam->isCancelled()) {
            return response()->json(['message' => 'This examination is already cancelled.'], 422);
        }

        $exam->status = 'cancelled';
        $exam->cancelled_at = Carbon::now();
        $exam->cancelled_by = $admin->id;
        $exam->cancellation_reason = $validated['reason'];
        $exam->save();

        // Flag active in-progress attempts as cancelled without deleting answers or records
        ExamAttempt::where('exam_id', $exam->id)
            ->where('status', 'in_progress')
            ->update([
                'is_cancelled' => true,
            ]);

        // Audit log
        LogActivity::record(
            'Exam Cancelled',
            'Examinations',
            "Admin {$admin->name} cancelled exam \"{$exam->title}\" (ID: {$exam->id}). Reason: {$validated['reason']}"
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Examination has been cancelled successfully for all students.',
            'data'    => [
                'id'                  => $exam->id,
                'title'               => $exam->title,
                'status'              => $exam->status,
                'cancelled_at'        => $exam->cancelled_at->toIso8601String(),
                'cancellation_reason' => $exam->cancellation_reason,
            ]
        ]);
    }

    /**
     * Reinstate a previously cancelled examination.
     */
    public function reinstateExam(Request $request, Exam $exam): JsonResponse
    {
        $admin = $request->user();

        if ($admin->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Administrator access required.'], 403);
        }

        if (!$exam->isCancelled()) {
            return response()->json(['message' => 'This exam is not currently cancelled.'], 422);
        }

        $previousReason = $exam->cancellation_reason;

        $exam->status = 'published';
        $exam->cancelled_at = null;
        $exam->cancelled_by = null;
        $exam->cancellation_reason = null;
        $exam->save();

        // Reset is_cancelled flag on in_progress attempts
        ExamAttempt::where('exam_id', $exam->id)
            ->where('status', 'in_progress')
            ->update([
                'is_cancelled' => false,
            ]);

        // Audit log
        LogActivity::record(
            'Exam Reinstated',
            'Examinations',
            "Admin {$admin->name} reinstated previously cancelled exam \"{$exam->title}\" (ID: {$exam->id}). Previous reason: {$previousReason}"
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Examination has been reinstated successfully.',
            'data'    => [
                'id'     => $exam->id,
                'title'  => $exam->title,
                'status' => $exam->status,
            ]
        ]);
    }

    /**
     * Pause an active examination.
     */
    public function pauseExam(Request $request, Exam $exam): JsonResponse
    {
        $admin = $request->user();

        if ($admin->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Administrator access required.'], 403);
        }

        if ($exam->isCancelled()) {
            return response()->json(['message' => 'Cannot pause a cancelled examination.'], 422);
        }

        $exam->status = 'paused';
        $exam->paused_at = Carbon::now();
        $exam->paused_by = $admin->id;
        $exam->save();

        LogActivity::record(
            'Exam Paused',
            'Examinations',
            "Admin {$admin->name} paused exam \"{$exam->title}\" (ID: {$exam->id})."
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Examination paused successfully.',
            'data'    => [
                'id'        => $exam->id,
                'status'    => $exam->status,
                'paused_at' => $exam->paused_at->toIso8601String(),
            ]
        ]);
    }

    /**
     * Resume a paused examination.
     * Automatically calculates pause duration and grants equivalent time to in-progress student attempts.
     */
    public function resumeExam(Request $request, Exam $exam): JsonResponse
    {
        $admin = $request->user();

        if ($admin->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Administrator access required.'], 403);
        }

        if (!$exam->isPaused()) {
            return response()->json(['message' => 'This examination is not paused.'], 422);
        }

        $now = Carbon::now();
        $pauseDuration = $exam->paused_at ? max(0, (int) $now->diffInSeconds($exam->paused_at)) : 0;

        // Auto-extend in-progress attempts by the paused duration so students do not lose exam time
        if ($pauseDuration > 0) {
            ExamAttempt::where('exam_id', $exam->id)
                ->where('status', 'in_progress')
                ->increment('extra_time_seconds', $pauseDuration);
        }

        $exam->status = 'published';
        $exam->paused_at = null;
        $exam->paused_by = null;
        $exam->save();

        LogActivity::record(
            'Exam Resumed',
            'Examinations',
            "Admin {$admin->name} resumed exam \"{$exam->title}\". In-progress attempts extended by {$pauseDuration}s."
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Examination resumed successfully.',
            'data'    => [
                'id'             => $exam->id,
                'status'         => $exam->status,
                'pause_duration' => $pauseDuration,
            ]
        ]);
    }

    private function formatRecoveryRequest(ExamRecoveryRequest $r): array
    {
        $attempt = $r->attempt;
        $exam = $r->exam;
        $student = $r->student;

        $formatDuration = function (int $secs) {
            $m = floor($secs / 60);
            $s = $secs % 60;
            return sprintf('%02d:%02d', $m, $s);
        };

        return [
            'id'                        => $r->id,
            'exam_id'                   => $r->exam_id,
            'exam_title'                => $exam->title ?? 'Exam',
            'instructor_name'           => $exam->instructor->name ?? 'Instructor',
            'attempt_id'                => $r->exam_attempt_id,
            'attempt_status'            => $attempt->status ?? 'unknown',
            'student_id'                => $student->id_no ?: ('STU-' . ($student->id ?? '0')),
            'student_name'              => $student->name ?? 'Student',
            'student_email'             => $student->email ?? '',
            'original_duration_minutes' => $exam->duration_minutes ?? 60,
            'original_start_time'       => $attempt?->started_at?->format('M d, Y h:i A') ?? 'N/A',
            'original_deadline'         => $attempt?->getOriginalDeadline()?->format('M d, Y h:i A') ?? 'N/A',
            'disconnected_at'           => $r->disconnected_at?->toIso8601String(),
            'disconnected_at_formatted' => $r->disconnected_at?->format('M d, Y h:i:s A') ?? 'N/A',
            'reconnected_at'            => $r->reconnected_at?->toIso8601String(),
            'reconnected_at_formatted'  => $r->reconnected_at?->format('M d, Y h:i:s A') ?? 'Pending',
            'interruption_seconds'      => $r->interruption_seconds,
            'interruption_formatted'    => $formatDuration($r->interruption_seconds),
            'suggested_seconds'         => $r->suggested_seconds,
            'suggested_formatted'       => '+' . $formatDuration($r->suggested_seconds),
            'approved_seconds'          => $r->approved_seconds,
            'approved_formatted'        => $r->approved_seconds !== null ? '+' . $formatDuration($r->approved_seconds) : null,
            'adjusted_deadline'         => $attempt?->getAdjustedDeadline()?->toIso8601String(),
            'adjusted_deadline_formatted' => $attempt?->getAdjustedDeadline()?->format('M d, Y h:i A') ?? 'N/A',
            'extra_time_seconds'        => $attempt->extra_time_seconds ?? 0,
            'status'                    => $r->status,
            'reviewed_by_name'          => $r->reviewer->name ?? null,
            'reviewed_at'               => $r->reviewed_at?->format('M d, Y h:i A') ?? null,
            'review_notes'              => $r->review_notes,
            'override_by_name'          => $r->overrider->name ?? null,
            'override_reason'           => $r->override_reason,
            'created_at'                => $r->created_at?->toIso8601String(),
            'student'                   => [
                'id'       => $student?->id,
                'name'     => $student?->name ?? 'Student',
                'email'    => $student?->email ?? '',
                'username' => $student?->username ?? $student?->name ?? '',
                'id_no'    => $student?->id_no ?: ('STU-' . ($student?->id ?? '0')),
            ],
            'exam'                      => [
                'id'               => $exam?->id,
                'title'            => $exam?->title ?? 'Exam',
                'duration_minutes' => $exam?->duration_minutes ?? 60,
                'course'           => [
                    'name' => $exam?->course?->name ?? '',
                    'code' => $exam?->course?->code ?? '',
                ],
            ],
            'exam_attempt'              => [
                'id'                 => $attempt?->id,
                'status'             => $attempt?->status,
                'extra_time_seconds' => $attempt?->extra_time_seconds ?? 0,
                'adjusted_deadline'  => $attempt?->getAdjustedDeadline()?->toIso8601String(),
            ],
            'reviewer'                  => [
                'id'   => $r->reviewer?->id,
                'name' => $r->reviewer?->name,
            ],
            'overrider'                 => [
                'id'   => $r->overrider?->id,
                'name' => $r->overrider?->name,
            ],
        ];
    }
}
