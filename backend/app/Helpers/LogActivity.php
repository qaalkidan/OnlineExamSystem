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
        $currentUser = $user ?? (Auth::user() ?: request()->user());
        if ($currentUser) {
            ActivityLog::create([
                'user_id'       => $currentUser->id,
                'department_id' => $departmentId ?? $currentUser->department_id,
                'actor_role'    => $currentUser->role,
                'action'        => $details, // 'action' in existing model is the description/details
                'type'          => $action,  // 'type' in existing model is the verb (Created, Updated)
                'module'        => $module,
                'details'       => $details, // storing in both for backward compatibility and simplicity
                'ip_address'    => request()->ip() ?: '127.0.0.1',
                'log_status'    => $logStatus,
            ]);
        }
    }
}
