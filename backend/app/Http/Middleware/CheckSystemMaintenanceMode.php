<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSystemMaintenanceMode
{
    /**
     * Handle an incoming request.
     * Restrict non-administrator operational API requests when System Maintenance Mode is active.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if System Maintenance Mode is enabled in settings
        $isMaintenance = SystemSetting::where('key', 'maintenanceMode')->value('value') === 'true';
        if (!$isMaintenance) {
            return $next($request);
        }

        $user = $request->user();

        // 2. Administrators are NEVER restricted
        if ($user && $user->role === 'admin') {
            return $next($request);
        }

        // 3. Administrative routes are NEVER restricted
        if ($request->is('*admin/*') || $request->is('*admin')) {
            return $next($request);
        }

        // 4. Always allow essential auth, session, profile, and settings routes
        if (
            $request->is('*logout') ||
            $request->is('*user/me') ||
            $request->is('*user/profile*') ||
            $request->is('*user/activity-logs*') ||
            $request->is('*settings*') ||
            $request->is('*system/*')
        ) {
            return $next($request);
        }

        // 5. Restrict academic/exam/student/instructor operations during maintenance
        return response()->json([
            'message'    => 'System Maintenance Active: The platform is currently in maintenance mode for core database operations. Non-administrator operations are temporarily paused.',
            'error_type' => 'system_maintenance',
            'maintenance'=> true,
        ], 503);
    }
}
