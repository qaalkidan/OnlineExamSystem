<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamRecoveryRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_attempt_id',
        'exam_id',
        'user_id',
        'disconnected_at',
        'reconnected_at',
        'interruption_seconds',
        'suggested_seconds',
        'approved_seconds',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'override_by',
        'override_reason',
    ];

    protected function casts(): array
    {
        return [
            'disconnected_at'      => 'datetime',
            'reconnected_at'       => 'datetime',
            'reviewed_at'          => 'datetime',
            'interruption_seconds' => 'integer',
            'suggested_seconds'    => 'integer',
            'approved_seconds'     => 'integer',
        ];
    }

    /**
     * The exam attempt this recovery request belongs to.
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    /**
     * The exam associated with this request.
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * The student who experienced the interruption.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The instructor or controller who reviewed this request.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The administrator who overrode this request, if any.
     */
    public function overrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by');
    }
}
