<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'user_id',
        'actor_role',
        'action',
        'type',
        'module',
        'details',
        'ip_address',
        'log_status',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Enforce audit log immutability and tamper-resistance.
     */
    protected static function booted()
    {
        static::updating(function ($log) {
            // Only 'is_read' may be modified (read receipt state)
            $dirtyKeys = array_keys($log->getDirty());
            if (count(array_diff($dirtyKeys, ['is_read', 'updated_at'])) > 0) {
                throw new \RuntimeException('Institutional audit logs are immutable and cannot be modified.');
            }
        });

        static::deleting(function ($log) {
            // Strictly disallow deleting audit records
            throw new \RuntimeException('Institutional audit logs are immutable electronic records and cannot be deleted.');
        });
    }
}
