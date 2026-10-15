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
     * Restrict non-administrator API requests when System Maintenance Mode is active.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If no authenticated user or user is a super admin, allow all requests
        if (!$user || $user->role === 'admin') {
            return $next($request);
        }

        // Always allow logout so users can terminate their sessions cleanly
        if ($request->is('*logout')) {
            return $next($request);
        }

        // Allow reading system settings / status
        if ($request->is('*settings*') && $request->isMethod('GET')) {
            return $next($request);
        }

        // Check if System Maintenance Mode is enabled
        $isMaintenance = SystemSetting::where('key', 'maintenanceMode')->value('value') === 'true';

        if ($isMaintenance) {
            return response()->json([
                'message'    => 'System Maintenance Active: The platform is currently restricted for core database operations. Non-administrator access is temporarily paused. Please check back later.',
                'error_type' => 'system_maintenance',
                'maintenance'=> true,
            ], 503);
        }

        return $next($request);
    }
}
