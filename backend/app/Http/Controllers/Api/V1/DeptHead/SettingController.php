<?php

namespace App\Http\Controllers\Api\V1\DeptHead;

use App\Http\Controllers\Controller;
use App\Helpers\LogActivity;
use App\Services\DepartmentNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    /**
     * Get the logged-in Department Head's account settings and notification preferences.
     */
    public function getSettings(Request $request): JsonResponse
    {
        $user = $request->user()->load('department');
        $userPrefs = is_array($user->notification_preferences) ? $user->notification_preferences : [];

        $notifications = [
            'emailOnInstructorJoin' => (bool)($userPrefs['emailOnInstructorJoin'] ?? true),
            'emailOnCourseCreate'   => (bool)($userPrefs['emailOnCourseCreate'] ?? true),
            'emailOnExamPublish'    => (bool)($userPrefs['emailOnExamPublish'] ?? false),
            'weeklyReport'          => (bool)($userPrefs['weeklyReport'] ?? true),
            'recipientEmail'        => $user->email,
        ];

        return response()->json([
            'status' => 'success',
            'data'   => [
                'profile' => [
                    'id'          => $user->id,
                    'fullName'    => $user->name,
                    'email'       => $user->email,
                    'phone'       => $user->phone ?? '+251980426395',
                    'office'      => $user->office ?? 'Block A, Room 204',
                    'role'        => $user->role,
                    'department'  => $user->department?->name ?? 'Computer Science',
                ],
                'notifications' => $notifications,
            ]
        ]);
    }

    /**
     * Update account settings (profile information, security/password, notification preferences).
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'fullName'        => 'required|string|max:255',
            'phone'           => 'nullable|string|max:50',
            'office'          => 'nullable|string|max:100',
            'notifications'   => 'nullable|array',
            'currentPassword' => 'nullable|string',
            'newPassword'     => 'nullable|string|min:6',
            'confirmPassword' => 'nullable|string',
        ];

        $request->validate($rules);

        // Handle Password Change if requested
        $passwordChanged = false;
        if (!empty($request->newPassword)) {
            if (empty($request->currentPassword)) {
                throw ValidationException::withMessages([
                    'currentPassword' => ['Current password is required to set a new password.'],
                ]);
            }

            if (!Hash::check($request->currentPassword, $user->password)) {
                throw ValidationException::withMessages([
                    'currentPassword' => ['The provided current password does not match our records.'],
                ]);
            }

            if ($request->newPassword !== $request->confirmPassword) {
                throw ValidationException::withMessages([
                    'confirmPassword' => ['Password confirmation does not match.'],
                ]);
            }

            $user->password = Hash::make($request->newPassword);
            $passwordChanged = true;
        }

        // Update Profile
        $user->name = $request->fullName;
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }
        if ($request->has('office')) {
            $user->office = $request->office;
        }

        // Update Notification Preferences (4 toggles)
        if ($request->has('notifications') && is_array($request->notifications)) {
            $notifData = $request->notifications;
            $existingPrefs = is_array($user->notification_preferences) ? $user->notification_preferences : [];

            $user->notification_preferences = [
                'emailOnInstructorJoin' => (bool)($notifData['emailOnInstructorJoin'] ?? false),
                'emailOnCourseCreate'   => (bool)($notifData['emailOnCourseCreate'] ?? false),
                'emailOnExamPublish'    => (bool)($notifData['emailOnExamPublish'] ?? false),
                'weeklyReport'          => (bool)($notifData['weeklyReport'] ?? false),
                'lastWeeklyReportSent'  => $existingPrefs['lastWeeklyReportSent'] ?? null,
            ];
        }

        $user->save();

        // Log the activity
        $logMessage = "Department Head updated profile information and notification preferences";
        if ($passwordChanged) {
            $logMessage .= " and changed security password";
        }
        LogActivity::record('Updated', 'Settings', $logMessage, 'Success', $user->department_id, $user);

        return response()->json([
            'status'  => 'success',
            'message' => $passwordChanged
                ? 'Account settings, notification preferences, and password updated successfully.'
                : 'Account settings and notification preferences saved successfully.',
            'data'    => [
                'profile' => [
                    'id'          => $user->id,
                    'fullName'    => $user->name,
                    'email'       => $user->email,
                    'phone'       => $user->phone,
                    'office'      => $user->office,
                    'role'        => $user->role,
                    'department'  => $user->department?->name,
                ],
                'notifications' => [
                    'emailOnInstructorJoin' => (bool)($user->notification_preferences['emailOnInstructorJoin'] ?? false),
                    'emailOnCourseCreate'   => (bool)($user->notification_preferences['emailOnCourseCreate'] ?? false),
                    'emailOnExamPublish'    => (bool)($user->notification_preferences['emailOnExamPublish'] ?? false),
                    'weeklyReport'          => (bool)($user->notification_preferences['weeklyReport'] ?? false),
                    'recipientEmail'        => $user->email,
                ],
            ]
        ]);
    }

    /**
     * Send a real test email to the Department Head's email address.
     */
    public function sendTestEmail(Request $request): JsonResponse
    {
        $user = $request->user();
        $config = DepartmentNotificationService::resolveConfig($user);
        $target = $user->email ?: config('mail.from.address', 'fitsumgashaw22@gmail.com');
        $appName = $config['senderName'];

        $subject = "{$appName} — Notification Integration Verification";
        $timestamp = now()->format('M d, Y • h:i A');

        $html = "
        <div style='font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;max-width:580px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;'>
            <div style='background:linear-gradient(135deg,#3730a3 0%,#4f46e5 100%);padding:24px;text-align:center;'>
                <h1 style='color:#fff;font-size:18px;margin:0;'>{$appName}</h1>
                <p style='color:#c7d2fe;font-size:12px;margin:4px 0 0 0;'>Department Head Notification Service</p>
            </div>
            <div style='padding:24px;'>
                <div style='background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px;margin-bottom:20px;'>
                    <span style='color:#15803d;font-weight:700;font-size:13px;display:block;'>✓ Notification Channel Operational</span>
                    <p style='color:#166534;font-size:12px;margin:4px 0 0 0;'>Automated department notifications will be delivered directly to your email: <strong>{$target}</strong>.</p>
                </div>
                <p style='font-size:13px;color:#475569;'>Verified on: <strong>{$timestamp}</strong></p>
            </div>
        </div>";

        $result = DepartmentNotificationService::deliverEmail($config, $target, $subject, $html);

        if ($result['success']) {
            return response()->json([
                'status'  => 'success',
                'message' => "Test notification email sent successfully to your Department Head email ({$target})!",
            ]);
        }

        return response()->json([
            'status'        => 'error',
            'message'       => 'Failed to deliver notification email. Please check server mail logs.',
            'error_details' => $result['error'] ?? 'SMTP Error',
        ], 422);
    }
}
