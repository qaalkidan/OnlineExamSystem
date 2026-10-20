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
     * Get the logged-in Department Head's account settings and preferences.
     * Sensitive credentials like SMTP passwords are masked before sending to the client.
     */
    public function getSettings(Request $request): JsonResponse
    {
        $user = $request->user()->load('department');

        $defaultEmailConfig = [
            'senderName'     => config('mail.from.name', 'Wollo University Exam Portal'),
            'senderEmail'    => config('mail.from.address', 'fitsumgashaw22@gmail.com'),
            'recipientEmail' => $user->email ?: 'fitsumgashaw22@gmail.com',
            'smtpHost'       => config('mail.mailers.smtp.host', 'smtp.gmail.com'),
            'smtpPort'       => (string)config('mail.mailers.smtp.port', '587'),
            'smtpEncryption' => config('mail.mailers.smtp.encryption', 'tls'),
            'smtpPassword'   => config('mail.mailers.smtp.password', 'jmrd crdf vtze sksk'),
        ];

        $defaultNotifications = [
            'emailOnInstructorJoin' => true,
            'emailOnCourseCreate'   => true,
            'emailOnExamPublish'    => false,
            'weeklyReport'          => true,
            'emailConfig'           => $defaultEmailConfig,
        ];

        $userPrefs = is_array($user->notification_preferences) ? $user->notification_preferences : [];
        $mergedEmailConfig = array_merge($defaultEmailConfig, $userPrefs['emailConfig'] ?? []);

        // Mask password before returning to frontend
        $hasPassword = !empty($mergedEmailConfig['smtpPassword']) || !empty(config('mail.mailers.smtp.password'));
        $safeEmailConfig = $mergedEmailConfig;
        $safeEmailConfig['smtpPassword'] = $hasPassword ? '••••••••••••••••' : '';
        $safeEmailConfig['hasPassword'] = $hasPassword;

        $notifications = [
            'emailOnInstructorJoin' => (bool)($userPrefs['emailOnInstructorJoin'] ?? $defaultNotifications['emailOnInstructorJoin']),
            'emailOnCourseCreate'   => (bool)($userPrefs['emailOnCourseCreate'] ?? $defaultNotifications['emailOnCourseCreate']),
            'emailOnExamPublish'    => (bool)($userPrefs['emailOnExamPublish'] ?? $defaultNotifications['emailOnExamPublish']),
            'weeklyReport'          => (bool)($userPrefs['weeklyReport'] ?? $defaultNotifications['weeklyReport']),
            'smtpStatus'            => $userPrefs['smtpStatus'] ?? 'connected',
            'lastTestTime'          => $userPrefs['lastTestTime'] ?? null,
            'lastTestResult'        => $userPrefs['lastTestResult'] ?? null,
            'emailConfig'           => $safeEmailConfig,
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
     * Update account settings (profile information, security/password, notification preferences & email integration).
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

        $validated = $request->validate($rules);

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

        // Update Notification Preferences & Email Integration Form
        if ($request->has('notifications') && is_array($request->notifications)) {
            $notifData = $request->notifications;
            $emailConfig = is_array($notifData['emailConfig'] ?? null) ? $notifData['emailConfig'] : [];
            $existingPrefs = is_array($user->notification_preferences) ? $user->notification_preferences : [];
            $existingEmailConfig = $existingPrefs['emailConfig'] ?? [];

            // Password handling: preserve existing password if incoming is masked or empty
            $incomingPass = $emailConfig['smtpPassword'] ?? '';
            $isMasked = preg_match('/^[•*●\s]+$/u', (string)$incomingPass);
            if (!empty($incomingPass) && !$isMasked) {
                $finalPassword = $incomingPass;
            } elseif (!empty($existingEmailConfig['smtpPassword'])) {
                $finalPassword = $existingEmailConfig['smtpPassword'];
            } else {
                $finalPassword = config('mail.mailers.smtp.password', 'jmrd crdf vtze sksk');
            }

            $user->notification_preferences = [
                'emailOnInstructorJoin' => (bool)($notifData['emailOnInstructorJoin'] ?? false),
                'emailOnCourseCreate'   => (bool)($notifData['emailOnCourseCreate'] ?? false),
                'emailOnExamPublish'    => (bool)($notifData['emailOnExamPublish'] ?? false),
                'weeklyReport'          => (bool)($notifData['weeklyReport'] ?? false),
                'smtpStatus'            => $existingPrefs['smtpStatus'] ?? 'connected',
                'lastTestTime'          => $existingPrefs['lastTestTime'] ?? null,
                'lastTestResult'        => $existingPrefs['lastTestResult'] ?? null,
                'lastWeeklyReportSent'  => $existingPrefs['lastWeeklyReportSent'] ?? null,
                'emailConfig'           => [
                    'senderName'     => $emailConfig['senderName'] ?? 'Wollo University Exam Portal',
                    'senderEmail'    => $emailConfig['senderEmail'] ?? config('mail.from.address', 'fitsumgashaw22@gmail.com'),
                    'recipientEmail' => $emailConfig['recipientEmail'] ?? ($user->email ?: 'fitsumgashaw22@gmail.com'),
                    'smtpHost'       => $emailConfig['smtpHost'] ?? config('mail.mailers.smtp.host', 'smtp.gmail.com'),
                    'smtpPort'       => (string)($emailConfig['smtpPort'] ?? '587'),
                    'smtpEncryption' => $emailConfig['smtpEncryption'] ?? 'tls',
                    'smtpPassword'   => $finalPassword,
                ],
            ];
        }

        $user->save();

        // Log the activity
        $logMessage = "Department Head updated profile information, notification preferences & email configuration";
        if ($passwordChanged) {
            $logMessage .= " and changed security password";
        }
        LogActivity::record('Updated', 'Settings', $logMessage, 'Success', $user->department_id, $user);

        // Format safe response for frontend
        $safePrefs = $user->notification_preferences;
        if (isset($safePrefs['emailConfig']['smtpPassword'])) {
            $safePrefs['emailConfig']['smtpPassword'] = '••••••••••••••••';
            $safePrefs['emailConfig']['hasPassword'] = true;
        }

        return response()->json([
            'status'  => 'success',
            'message' => $passwordChanged
                ? 'Account settings, email integration, and password updated successfully.'
                : 'Account settings and notification email preferences saved successfully.',
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
                'notifications' => $safePrefs,
            ]
        ]);
    }

    /**
     * Send a real test email using the configured notification preferences & email credentials.
     */
    public function sendTestEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'emailConfig'                  => 'nullable|array',
            'emailConfig.senderName'       => 'nullable|string|max:100',
            'emailConfig.senderEmail'      => 'nullable|email|max:150',
            'emailConfig.recipientEmail'   => 'nullable|email|max:150',
            'emailConfig.smtpHost'         => 'nullable|string|max:150',
            'emailConfig.smtpPort'         => 'nullable|numeric',
            'emailConfig.smtpEncryption'   => 'nullable|string|in:tls,ssl,none',
            'emailConfig.smtpPassword'     => 'nullable|string',
            'testType'                     => 'nullable|string|in:integration,instructor_join,course_create,exam_publish,weekly_report',
        ];

        $request->validate($rules);

        $override = $request->input('emailConfig', []);
        $testType = $request->input('testType', 'integration');

        $result = DepartmentNotificationService::sendTestNotification($user, $override, $testType);

        if ($result['success']) {
            $target = $result['targetEmail'] ?? ($override['recipientEmail'] ?? ($user->email ?: 'fitsumgashaw22@gmail.com'));
            return response()->json([
                'status'       => 'success',
                'message'      => "Test notification email dispatched successfully to {$target}!",
                'smtpStatus'   => 'connected',
                'lastTestTime' => $result['lastTestTime'] ?? now()->toIso8601String(),
                'details'      => [
                    'recipient' => $target,
                    'timestamp' => now()->format('M d, Y h:i A'),
                ],
            ]);
        }

        // Return descriptive error details to the frontend
        $rawError = $result['error'] ?? 'Unknown SMTP connection error';
        $userFriendlyNotice = 'Failed to connect to SMTP server.';

        if (str_contains($rawError, '535') || str_contains($rawError, 'BadCredentials') || str_contains($rawError, 'Username and Password not accepted')) {
            $userFriendlyNotice = 'Google SMTP rejected the credentials (535 Bad Credentials). Please confirm that: (1) The Sender Email matches the exact Google Account for this App Password (e.g. fitsumgashaw22@gmail.com), (2) 2-Step Verification is active, and (3) the 16-character App Password is correct.';
        } elseif (str_contains($rawError, 'Connection could not be established') || str_contains($rawError, 'Connection timed out')) {
            $userFriendlyNotice = 'Connection timed out connecting to smtp.gmail.com:587. Please check network port access.';
        }

        return response()->json([
            'status'        => 'error',
            'message'       => $userFriendlyNotice,
            'smtpStatus'    => 'failed',
            'error_details' => $rawError,
        ], 422);
    }
}
