<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'course_code',
        'course_name',
        'section',
        'description',
        'duration_minutes',
        'total_marks',
        'status',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'paused_at',
        'paused_by',
        'scheduled_at',
        'published_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'paused_at'    => 'datetime',
            'settings'     => 'array',
        ];
    }

    /**
     * The instructor who created this exam.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Students enrolled in this exam.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'exam_student')->withTimestamps();
    }

    /**
     * Questions belonging to this exam.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Attempts made by students on this exam.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /**
     * Course associated with this exam.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_code', 'code');
    }

    /**
     * Recovery requests for interruptions during this exam.
     */
    public function recoveryRequests(): HasMany
    {
        return $this->hasMany(ExamRecoveryRequest::class);
    }

    /**
     * Admin who cancelled the exam.
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Admin who paused the exam.
     */
    public function pausedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paused_by');
    }

    /**
     * Check if exam is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled' || $this->cancelled_at !== null;
    }

    /**
     * Check if exam is paused.
     */
    public function isPaused(): bool
    {
        return $this->status === 'paused' || $this->paused_at !== null;
    }

    /**
     * Check if exam is concluded/completed.
     */
    public function isCompleted(): bool
    {
        if ($this->isCancelled()) {
            return false;
        }

        if (strtolower($this->status ?? '') === 'completed') {
            return true;
        }

        // If scheduled date exists and scheduled duration has elapsed
        if ($this->scheduled_at) {
            $endAt = $this->scheduled_at->copy()->addMinutes($this->duration_minutes ?? 60);
            if (\Illuminate\Support\Carbon::now()->gt($endAt)) {
                // If there are no active in_progress attempts, it is concluded
                $hasActiveAttempts = $this->attempts()->where('status', 'in_progress')->exists();
                return !$hasActiveAttempts;
            }
        }

        return false;
    }

    /**
     * Check if exam is currently live/ongoing.
     */
    public function isLive(): bool
    {
        if ($this->isCancelled() || $this->isPaused() || $this->isCompleted()) {
            return false;
        }

        if (strtolower($this->status ?? '') === 'draft') {
            return false;
        }

        // Active if student attempts are currently ongoing
        if ($this->attempts()->where('status', 'in_progress')->exists()) {
            return true;
        }

        if ($this->scheduled_at) {
            $now = \Illuminate\Support\Carbon::now();
            $endAt = $this->scheduled_at->copy()->addMinutes($this->duration_minutes ?? 60);
            return $now->between($this->scheduled_at, $endAt);
        }

        return strtolower($this->status ?? '') === 'published';
    }

    /**
     * Check if exam is scheduled for the future.
     */
    public function isUpcoming(): bool
    {
        if ($this->isCancelled() || $this->isPaused() || $this->isCompleted()) {
            return false;
        }

        if ($this->scheduled_at && \Illuminate\Support\Carbon::now()->lt($this->scheduled_at)) {
            return true;
        }

        return strtolower($this->status ?? '') === 'scheduled';
    }

    /**
     * Determine if exam can be paused by an admin.
     * Can only pause live, running examination sessions.
     */
    public function canPause(): bool
    {
        return $this->isLive() && !$this->isPaused() && !$this->isCancelled() && !$this->isCompleted();
    }

    /**
     * Determine if exam can be resumed.
     */
    public function canResume(): bool
    {
        return $this->isPaused() && !$this->isCancelled();
    }

    /**
     * Determine if exam can be cancelled.
     * Only upcoming or live/ongoing examinations can be cancelled. Concluded exams cannot be cancelled.
     */
    public function canCancel(): bool
    {
        return !$this->isCancelled() && !$this->isCompleted();
    }

    /**
     * Determine if exam can be reinstated.
     */
    public function canReinstate(): bool
    {
        return $this->isCancelled();
    }

    /**
     * Authoritative computed status.
     */
    public function getComputedStatusAttribute(): string
    {
        if ($this->isCancelled()) {
            return 'cancelled';
        }
        if ($this->isPaused()) {
            return 'paused';
        }
        if ($this->isCompleted()) {
            return 'completed';
        }
        if ($this->isLive()) {
            return 'live';
        }
        if ($this->isUpcoming()) {
            return 'scheduled';
        }
        return strtolower($this->status ?? 'draft');
    }
}

