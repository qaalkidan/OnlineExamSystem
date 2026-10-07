<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamRecoveryRequest;
use App\Models\SemesterSubmission;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ExamRecoveryController extends Controller
{
    /**
     * Heartbeat endpoint for active student exam attempts.
     * Keeps connection alive, synchronizes interim answers, and returns authoritative timer & status.
     */
    public function heartbeat(Request $request, Exam $exam): JsonResponse
    {
        $student = $request->user();

        $attempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('user_id', $student->id)
            ->where('status', 'in_progress')
            ->first();

        if (!$attempt) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Active exam attempt not found.',
            ], 404);
        }

        // Check if exam is cancelled
        if ($exam->isCancelled()) {
            if (!$attempt->is_cancelled) {
                $attempt->is_cancelled = true;
                $attempt->save();
            }

            return response()->json([
                'status'              => 'cancelled',
                'is_cancelled'        => true,
                'cancellation_reason' => $exam->cancellation_reason ?: 'This examination was cancelled by an administrator.',
                'message'             => 'This examination has been cancelled by an administrator.',
            ], 200);
        }

        // Check if exam is paused
        if ($exam->isPaused()) {
            return response()->json([
                'status'    => 'paused',
                'is_paused' => true,
                'message'   => 'This examination is currently paused by an administrator. Please wait for resumption.',
            ], 200);
        }

        // Save any unsynced answers sent with the heartbeat
        $incomingAnswers = $request->input('answers');
        if (is_array($incomingAnswers) && !empty($incomingAnswers)) {
            $existing = $attempt->answers ?? [];
            $attempt->answers = array_merge($existing, $incomingAnswers);
        }

        $attempt->last_heartbeat_at = Carbon::now();
        $attempt->save();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'attempt_id'         => $attempt->id,
                'exam_status'        => $exam->status,
                'is_cancelled'       => false,
                'is_paused'          => false,
                'extra_time_seconds' => $attempt->extra_time_seconds,
                'remaining_seconds'  => $attempt->getRemainingSeconds(),
                'adjusted_deadline'  => $attempt->getAdjustedDeadline()?->toIso8601String(),
                'server_time'        => Carbon::now()->toIso8601String(),
                'answers_synced'     => true,
            ]
        ]);
    }

    /**
     * Reconnect endpoint called when student's browser regains network connectivity.
     * Calculates interruption duration using trusted server timestamps, creates/updates recovery request,
     * and synchronizes queued answers.
     */
    public function reconnect(Request $request, Exam $exam): JsonResponse
    {
        $student = $request->user();

        $attempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('user_id', $student->id)
            ->where('status', 'in_progress')
            ->first();

        if (!$attempt) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Active exam attempt not found.',
            ], 404);
        }

        // Check cancellation
        if ($exam->isCancelled()) {
            $attempt->is_cancelled = true;
            $attempt->save();

            return response()->json([
                'status'              => 'cancelled',
                'is_cancelled'        => true,
                'cancellation_reason' => $exam->cancellation_reason ?: 'This examination was cancelled by an administrator.',
                'message'             => 'This examination has been cancelled by an administrator.',
            ], 200);
        }

        $now = Carbon::now();

        // Authoritative server-side calculation: duration between last recorded heartbeat and now
        $lastHeartbeat = $attempt->last_heartbeat_at;
        $interruptionSeconds = 0;

        if ($lastHeartbeat) {
            $interruptionSeconds = max(1, (int) $now->diffInSeconds($lastHeartbeat));
        } else {
            // If no previous heartbeat, check client reported disconnect timestamp safely
            $clientDisconnect = $request->input('client_disconnected_at');
            if ($clientDisconnect) {
                try {
                    $clientTime = Carbon::parse($clientDisconnect);
                    if ($clientTime->lt($now)) {
                        $interruptionSeconds = min(1800, max(1, (int) $now->diffInSeconds($clientTime)));
                    }
                } catch (\Exception $e) {
                    $interruptionSeconds = 30;
                }
            } else {
                $interruptionSeconds = 30;
            }
        }

        // 30-minute configurable recovery window
        $recoveryWindowMinutes = (int) (SystemSetting::where('key', 'recovery_window_minutes')->value('value') ?? 30);
        $recoveryWindowSeconds = $recoveryWindowMinutes * 60;

        $disconnectedAt = $lastHeartbeat ?: $now->copy()->subSeconds($interruptionSeconds);
        $isExtended = $interruptionSeconds > $recoveryWindowSeconds;

        $status = $isExtended ? 'extended_interruption' : 'pending_approval';
        $suggestedSeconds = $isExtended ? 0 : $interruptionSeconds;

        // Prevent duplicate spam: if an identical pending request was created within the last 60 seconds, update it
        $recentRecovery = ExamRecoveryRequest::where('exam_attempt_id', $attempt->id)
            ->where('status', 'pending_approval')
            ->where('created_at', '>=', $now->copy()->subSeconds(60))
            ->latest()
            ->first();

        if ($recentRecovery) {
            $recentRecovery->reconnected_at = $now;
            $recentRecovery->interruption_seconds = max($recentRecovery->interruption_seconds, $interruptionSeconds);
            $recentRecovery->suggested_seconds = $recentRecovery->interruption_seconds;
            $recentRecovery->save();
            $recoveryRequest = $recentRecovery;
        } else {
            $recoveryRequest = ExamRecoveryRequest::create([
                'exam_attempt_id'      => $attempt->id,
                'exam_id'              => $exam->id,
                'user_id'              => $student->id,
                'disconnected_at'      => $disconnectedAt,
                'reconnected_at'       => $now,
                'interruption_seconds' => $interruptionSeconds,
                'suggested_seconds'    => $suggestedSeconds,
                'approved_seconds'     => null,
                'status'               => $status,
            ]);
        }

        // Synchronize any answers queued during offline state
        $pendingAnswers = $request->input('pending_answers') ?: $request->input('answers');
        if (is_array($pendingAnswers) && !empty($pendingAnswers)) {
            $existing = $attempt->answers ?? [];
            $attempt->answers = array_merge($existing, $pendingAnswers);
        }

        $attempt->last_heartbeat_at = $now;
        $attempt->save();

        // Audit log
        LogActivity::record(
            'Connection Restored',
            'Exam Recovery',
            "Student {$student->name} (ID: {$student->id}) reconnected to \"{$exam->title}\". Calculated interruption: {$interruptionSeconds}s. Status: {$status}."
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Connection restored and answers synchronized successfully.',
            'data'    => [
                'recovery_request_id' => $recoveryRequest->id,
                'recovery_status'     => $recoveryRequest->status,
                'interruption_seconds' => $recoveryRequest->interruption_seconds,
                'suggested_seconds'   => $recoveryRequest->suggested_seconds,
                'extra_time_seconds'  => $attempt->extra_time_seconds,
                'remaining_seconds'   => $attempt->getRemainingSeconds(),
                'adjusted_deadline'   => $attempt->getAdjustedDeadline()?->toIso8601String(),
                'answers_synced'      => true,
            ]
        ]);
    }

    /**
     * List recovery requests for the authenticated instructor.
     */
    public function instructorIndex(Request $request): JsonResponse
    {
        $instructor = $request->user();

        $query = ExamRecoveryRequest::with(['student', 'exam.course', 'attempt', 'reviewer'])
            ->whereHas('exam', function ($q) use ($instructor) {
                // If instructor role, limit to exams owned by instructor
                if ($instructor->role !== 'admin') {
                    $q->where('user_id', $instructor->id);
                }
            });

        if ($request->filled('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate(15);

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
     * Instructor reviews and approves/rejects a connection recovery request.
     */
    public function review(Request $request, $id): JsonResponse
    {
        $instructor = $request->user();
        $recovery = ExamRecoveryRequest::with(['exam.course', 'attempt', 'student'])->findOrFail($id);

        // Ownership authorization check
        if ($instructor->role !== 'admin' && $recovery->exam->user_id !== $instructor->id) {
            return response()->json(['message' => 'You are not authorized to review this exam recovery request.'], 403);
        }

        // Preserve Semester Lock: Instructors cannot modify records if their semester submission is locked
        if ($instructor->role !== 'admin') {
            $isLocked = SemesterSubmission::where('instructor_id', $instructor->id)
                ->whereIn('status', ['submitted', 'approved'])
                ->exists();

            if ($isLocked) {
                return response()->json([
                    'message' => 'Modifications are locked because your semester submission has been completed.',
                    'locked'  => true,
                ], 403);
            }
        }

        $validated = $request->validate([
            'action'           => 'required|in:approve,reject,approved,rejected',
            'approved_seconds' => 'nullable|integer|min:0',
            'review_notes'     => 'nullable|string|max:500',
        ]);

        $attempt = $recovery->attempt;
        if (!$attempt) {
            return response()->json(['message' => 'Associated exam attempt not found.'], 404);
        }

        // Prevent duplicate approvals
        if ($recovery->status === 'approved' || $recovery->status === 'applied') {
            return response()->json(['message' => 'This recovery request has already been approved.'], 422);
        }

        $isApprove = in_array(strtolower($validated['action']), ['approve', 'approved']);

        if ($isApprove) {
            $approvedSeconds = isset($validated['approved_seconds'])
                ? (int) $validated['approved_seconds']
                : $recovery->suggested_seconds;

            // Policy limit: instructor cannot approve more than 30 minutes (1800s) extra per interruption without admin override
            if ($approvedSeconds > 1800 && $instructor->role !== 'admin') {
                return response()->json([
                    'message' => 'Approval exceeds the maximum instructor limit of 30 minutes (1800s). Higher-level administrator authorization is required.',
                ], 422);
            }

            $oldDeadline = $attempt->getAdjustedDeadline()?->format('h:i:s A');

            // Apply approved extra time to student attempt
            $attempt->increment('extra_time_seconds', $approvedSeconds);
            $newDeadline = $attempt->getAdjustedDeadline()?->format('h:i:s A');

            $recovery->update([
                'approved_seconds' => $approvedSeconds,
                'status'           => 'approved',
                'reviewed_by'      => $instructor->id,
                'reviewed_at'      => Carbon::now(),
                'review_notes'     => $validated['review_notes'] ?? null,
            ]);

            // Audit log
            LogActivity::record(
                'Approved Recovery',
                'Exam Recovery',
                "Instructor {$instructor->name} approved +{$approvedSeconds}s for student {$recovery->student->name} on \"{$recovery->exam->title}\". Previous deadline: {$oldDeadline}, New deadline: {$newDeadline}."
            );
        } else {
            $recovery->update([
                'status'       => 'rejected',
                'reviewed_by'  => $instructor->id,
                'reviewed_at'  => Carbon::now(),
                'review_notes' => $validated['review_notes'] ?? 'Rejected by instructor',
            ]);

            // Audit log
            LogActivity::record(
                'Rejected Recovery',
                'Exam Recovery',
                "Instructor {$instructor->name} rejected recovery for student {$recovery->student->name} on \"{$recovery->exam->title}\". Reason: " . ($validated['review_notes'] ?? 'None provided')
            );
        }

        $recovery->load(['student', 'exam.course', 'attempt', 'reviewer']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Recovery request ' . ($isApprove ? 'approved' : 'rejected') . ' successfully.',
            'data'    => $this->formatRecoveryRequest($recovery),
        ]);
    }

    /**
     * Format a recovery request model into a clean frontend contract.
     */
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
            'status_label'              => $this->mapStatusLabel($r->status),
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
        ];
    }

    private function mapStatusLabel(string $status): string
    {
        return match ($status) {
            'monitoring'            => 'Monitoring',
            'pending_approval'      => 'Pending Approval',
            'approved'              => 'Approved',
            'rejected'              => 'Rejected',
            'extended_interruption' => 'Extended Interruption',
            'applied'               => 'Applied',
            'closed'                => 'Closed',
            default                 => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
