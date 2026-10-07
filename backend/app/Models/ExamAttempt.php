<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'user_id',
        'score',
        'total_marks',
        'percentage',
        'grade',
        'status',
        'extra_time_seconds',
        'last_heartbeat_at',
        'is_cancelled',
        'answers',
        'started_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'answers'            => 'array',
            'percentage'         => 'decimal:2',
            'started_at'         => 'datetime',
            'submitted_at'       => 'datetime',
            'last_heartbeat_at'  => 'datetime',
            'extra_time_seconds' => 'integer',
            'is_cancelled'       => 'boolean',
        ];
    }

    /**
     * The exam this attempt belongs to.
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * The student who made this attempt.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Recovery requests for this attempt.
     */
    public function recoveryRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ExamRecoveryRequest::class);
    }

    /**
     * Calculate authoritative original deadline: start time + exam duration.
     */
    public function getOriginalDeadline(): ?\Illuminate\Support\Carbon
    {
        if (!$this->started_at) {
            return null;
        }
        $duration = $this->exam ? $this->exam->duration_minutes : 60;
        return $this->started_at->copy()->addMinutes($duration);
    }

    /**
     * Calculate authoritative adjusted deadline: original deadline + approved extra time.
     */
    public function getAdjustedDeadline(): ?\Illuminate\Support\Carbon
    {
        $orig = $this->getOriginalDeadline();
        if (!$orig) {
            return null;
        }
        return $orig->copy()->addSeconds($this->extra_time_seconds ?? 0);
    }

    /**
     * Get authoritative seconds remaining until adjusted deadline.
     */
    public function getRemainingSeconds(): int
    {
        $adj = $this->getAdjustedDeadline();
        if (!$adj) {
            return 0;
        }
        $now = \Illuminate\Support\Carbon::now();
        if ($now->gte($adj)) {
            return 0;
        }
        return (int) $now->diffInSeconds($adj);
    }
}
