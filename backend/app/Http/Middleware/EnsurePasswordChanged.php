<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Handle an incoming request.
     * Enforces that users with `must_change_password` set to true
     * cannot access any portal endpoints until they update their temporary password.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            // Whitelist endpoints required to complete password change or log out
            if ($request->is('*/user/change-password') ||
                $request->is('*/logout') ||
                $request->is('*/user/me')) {
                return $next($request);
            }

            return response()->json([
                'status'               => 'error',
                'error_type'           => 'password_change_required',
                'must_change_password' => true,
                'message'              => 'Password change required: You must change your temporary password before accessing portal features.',
            ], 403);
        }

        return $next($request);
    }
}
