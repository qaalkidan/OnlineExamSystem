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
}
