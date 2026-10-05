<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemesterSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructor_id',
        'academic_year',
        'semester',
        'department',
        'section',
        'status',
        'remarks',
        'submitted_at',
        'approved_at',
        'reopened_at',
        'reopened_by',
        'reopen_reason',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at'  => 'datetime',
            'reopened_at'  => 'datetime',
            'locked_at'    => 'datetime',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /**
     * Check if instructor is in locked (READ-ONLY) mode for this semester.
     */
    public function isLocked(): bool
    {
        return in_array($this->status, ['submitted', 'approved']);
    }

    /**
     * Check if instructor is in editable mode for this semester.
     */
    public function isEditable(): bool
    {
        return !$this->isLocked();
    }
}

