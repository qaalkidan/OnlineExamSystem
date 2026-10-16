<?php

namespace App\Helpers;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class LogActivity
{
    /**
     * Record an activity log.
     *
     * @param string $action E.g., 'Created', 'Updated', 'Deleted', 'Assigned', 'Login'
     * @param string $module E.g., 'Instructors', 'Students', 'Courses', 'Departments', 'Authentication'
     * @param string|null $details Details of the action. E.g., 'Created a new exam "Database Midterm"'
     * @param string $logStatus 'Success' or 'Failed'
     */
    public static function record(string $action, string $module, string $details = null, string $logStatus = 'Success', $departmentId = null, $user = null)
    {
        try {
            $currentUser = $user ?? (Auth::user() ?: request()?->user());
            $ipAddress = request()?->ip() ?: '127.0.0.1';

            ActivityLog::create([
                'user_id'       => $currentUser?->id,
                'department_id' => $departmentId ?? $currentUser?->department_id,
                'actor_role'    => $currentUser?->role ?? 'system',
                'action'        => $details ?: $action,
                'type'          => $action,
                'module'        => $module,
                'details'       => $details ?: $action,
                'ip_address'    => $ipAddress,
                'log_status'    => $logStatus,
                'is_read'       => false,
            ]);
        } catch (\Throwable $e) {
            // Log locally if activity log creation fails so original operation is not broken
            \Illuminate\Support\Facades\Log::error("Failed to record activity log: " . $e->getMessage(), [
                'action' => $action,
                'module' => $module,
                'status' => $logStatus,
            ]);
        }
    }
}
