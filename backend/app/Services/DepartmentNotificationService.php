<?php

namespace App\Services;

use App\Models\User;
use App\Models\Department;
use App\Models\Course;
use App\Models\Exam;
use App\Helpers\LogActivity;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class DepartmentNotificationService
{
    /**
     * Resolve effective email configuration for a given department head or from system defaults.
     */
    public static function resolveConfig(?User $user = null, ?array $override = null): array
    {
        $prefs = $user && is_array($user->notification_preferences)
            ? $user->notification_preferences
            : [];

        $savedConfig = is_array($prefs['emailConfig'] ?? null)
            ? $prefs['emailConfig']
            : [];

        $senderName = $override['senderName']
            ?? $savedConfig['senderName']
            ?? config('mail.from.name', 'Wollo University Exam Portal');

        $senderEmail = $override['senderEmail']
            ?? $savedConfig['senderEmail']
            ?? config('mail.from.address', 'fitshumgashaw@gmail.com');

        $recipientEmail = $override['recipientEmail']
            ?? $savedConfig['recipientEmail']
            ?? ($user?->email ?: 'fitshumgashaw@gmail.com');

        $smtpHost = $override['smtpHost']
            ?? $savedConfig['smtpHost']
            ?? config('mail.mailers.smtp.host', 'smtp.gmail.com');

        $smtpPort = (int)(
            $override['smtpPort']
            ?? $savedConfig['smtpPort']
            ?? config('mail.mailers.smtp.port', 587)
        );

        $smtpEncryption = $override['smtpEncryption']
            ?? $savedConfig['smtpEncryption']
            ?? config('mail.mailers.smtp.encryption', 'tls');

        $smtpPassword = $override['smtpPassword']
            ?? $savedConfig['smtpPassword']
            ?? config('mail.mailers.smtp.password', 'jmrd crdf vtze sksk');

        return [
            'senderName'     => $senderName,
            'senderEmail'    => $senderEmail,
            'recipientEmail' => $recipientEmail,
            'smtpHost'       => $smtpHost,
            'smtpPort'       => $smtpPort,
            'smtpEncryption' => $smtpEncryption,
            'smtpPassword'   => $smtpPassword,
        ];
    }

    /**
     * Build and execute direct SMTP email delivery.
     */
    public static function deliverEmail(array $config, string $to, string $subject, string $htmlBody, string $textBody = ''): array
    {
        $host = $config['smtpHost'] ?? 'smtp.gmail.com';
        $port = (int)($config['smtpPort'] ?? 587);
        $user = $config['senderEmail'] ?? 'fitshumgashaw@gmail.com';
        $rawPass = $config['smtpPassword'] ?? 'jmrd crdf vtze sksk';
        $fromName = $config['senderName'] ?? 'Wollo University Exam Portal';
        $fromAddress = $config['senderEmail'] ?? 'fitshumgashaw@gmail.com';

        // Clean app password (remove spaces if 16-letter google app password format)
        $cleanPass = str_replace(' ', '', $rawPass);

        // Attempt delivery with clean password first, then raw pass if needed
        $attempts = array_unique([$cleanPass, $rawPass]);
        $lastError = null;

        foreach ($attempts as $passToTry) {
            try {
                $isSsl = ($port === 465 || strtolower($config['smtpEncryption'] ?? '') === 'ssl');
                $transport = new EsmtpTransport($host, $port, $isSsl);
                $transport->setUsername($user);
                $transport->setPassword($passToTry);

                $mailer = new Mailer($transport);

                $email = (new Email())
                    ->from(new Address($fromAddress, $fromName))
                    ->to($to)
                    ->subject($subject)
                    ->html($htmlBody);

                if (!empty($textBody)) {
                    $email->text($textBody);
                } else {
                    $email->text(strip_tags($htmlBody));
                }

                $mailer->send($email);

                Log::info("Email notification sent successfully to {$to} (Subject: {$subject})");
                return [
                    'success' => true,
                    'message' => "Email sent successfully to {$to}",
                ];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning("SMTP attempt failed for {$user} on {$host}:{$port} with error: " . $e->getMessage());
            }
        }

        return [
            'success' => false,
            'error'   => $lastError ?? 'Unknown SMTP connection error',
        ];
    }

    /**
     * Send test notification email to verify integration.
     */
    public static function sendTestNotification(User $user, ?array $overrideConfig = null, string $testType = 'integration'): array
    {
        $config = self::resolveConfig($user, $overrideConfig);
        $targetEmail = $config['recipientEmail'] ?: $user->email;
        $appName = $config['senderName'] ?: 'Wollo University Exam Portal';

        $typeLabels = [
            'integration'     => 'Email Integration Test Verification',
            'instructor_join' => 'Sample Alert: New Instructor Joined',
            'course_create'   => 'Sample Alert: New Course Registered',
            'exam_publish'    => 'Sample Alert: Examination Published',
            'weekly_report'   => 'Sample Alert: Department Weekly Digest',
        ];

        $subject = "{$appName} — " . ($typeLabels[$testType] ?? 'Notification Integration Test');
        $timestamp = now()->format('M d, Y • h:i A');

        $html = "
        <div style='font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,Helvetica,Arial,sans-serif;max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.05);'>
            <div style='background:linear-gradient(135deg, #4338ca 0%, #6366f1 100%);padding:28px 24px;text-align:center;'>
                <div style='display:inline-block;width:48px;height:48px;line-height:48px;background:rgba(255,255,255,0.2);border-radius:12px;font-size:24px;color:#ffffff;margin-bottom:12px;'>✉</div>
                <h1 style='color:#ffffff;font-size:20px;margin:0;font-weight:700;'>{$appName}</h1>
                <p style='color:#e0e7ff;font-size:13px;margin:6px 0 0 0;'>Department Head Notification Service</p>
            </div>
            
            <div style='padding:28px 24px;'>
                <div style='background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px;margin-bottom:24px;'>
                    <span style='color:#15803d;font-weight:700;font-size:14px;display:block;'>✓ Email Integration Verified Successfully</span>
                    <p style='color:#166534;font-size:13px;margin:6px 0 0 0;'>Your Wollo University Exam Portal email delivery channel is operational and authenticated.</p>
                </div>

                <h2 style='font-size:16px;color:#1e293b;margin:0 0 16px 0;'>Connection & Dispatch Details:</h2>
                <table style='width:100%;border-collapse:collapse;font-size:13px;color:#334155;margin-bottom:24px;'>
                    <tr style='border-bottom:1px solid #f1f5f9;'>
                        <td style='padding:10px 0;font-weight:600;color:#64748b;'>Recipient:</td>
                        <td style='padding:10px 0;font-weight:700;color:#0f172a;'>{$targetEmail}</td>
                    </tr>
                    <tr style='border-bottom:1px solid #f1f5f9;'>
                        <td style='padding:10px 0;font-weight:600;color:#64748b;'>Sender Name:</td>
                        <td style='padding:10px 0;color:#0f172a;'>{$config['senderName']}</td>
                    </tr>
                    <tr style='border-bottom:1px solid #f1f5f9;'>
                        <td style='padding:10px 0;font-weight:600;color:#64748b;'>SMTP Host & Port:</td>
                        <td style='padding:10px 0;color:#0f172a;'>{$config['smtpHost']}:{$config['smtpPort']} ({$config['smtpEncryption']})</td>
                    </tr>
                    <tr style='border-bottom:1px solid #f1f5f9;'>
                        <td style='padding:10px 0;font-weight:600;color:#64748b;'>Sender Email:</td>
                        <td style='padding:10px 0;color:#0f172a;'>{$config['senderEmail']}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px 0;font-weight:600;color:#64748b;'>Timestamp:</td>
                        <td style='padding:10px 0;color:#0f172a;'>{$timestamp}</td>
                    </tr>
                </table>

                <div style='background:#f8fafc;border-left:4px solid #6366f1;padding:14px 16px;border-radius:8px;font-size:12px;color:#475569;margin-bottom:24px;'>
                    <strong>Active Notification Preferences:</strong> Automated alerts will be dispatched according to your toggled preferences (New Instructor Joined, New Course Created, Exam Published, and Weekly Department Reports).
                </div>
            </div>

            <div style='background:#f8fafc;padding:18px 24px;border-top:1px solid #e2e8f0;text-align:center;font-size:11px;color:#94a3b8;'>
                &copy; " . date('Y') . " Wollo University. All rights reserved. &bull; Academic Management & Examination System
            </div>
        </div>";

        $result = self::deliverEmail($config, $targetEmail, $subject, $html);

        if ($result['success']) {
            LogActivity::record(
                'Sent',
                'Email Integration',
                "Sent test notification email to {$targetEmail} ({$appName})",
                'Success',
                $user->department_id,
                $user
            );
        } else {
            LogActivity::record(
                'Failed',
                'Email Integration',
                "Failed to send test email to {$targetEmail}: " . substr($result['error'], 0, 150),
                'Failed',
                $user->department_id,
                $user
            );
        }

        return $result;
    }

    /**
     * Notify department head when a new instructor joins.
     */
    public static function notifyInstructorJoined(User $instructor, ?Department $department = null): void
    {
        $deptId = $department?->id ?? $instructor->department_id;
        if (!$deptId) return;

        $deptHead = User::where('department_id', $deptId)
            ->where('role', 'dept_head')
            ->first();

        if (!$deptHead) return;

        $prefs = $deptHead->notification_preferences;
        if (!is_array($prefs) || empty($prefs['emailOnInstructorJoin'])) {
            return;
        }

        $config = self::resolveConfig($deptHead);
        $target = $config['recipientEmail'] ?: $deptHead->email;
        $appName = $config['senderName'] ?: 'Wollo University Exam Portal';
        $deptName = $department?->name ?? $deptHead->department?->name ?? 'Department';

        $subject = "{$appName} — New Instructor Joined: {$instructor->name}";
        $html = "
        <div style='font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;'>
            <div style='background:#4f46e5;padding:24px;text-align:center;'>
                <h2 style='color:#fff;margin:0;'>{$appName}</h2>
                <p style='color:#c7d2fe;font-size:12px;margin:4px 0 0 0;'>New Faculty Member Registration</p>
            </div>
            <div style='padding:24px;'>
                <p style='font-size:14px;color:#1e293b;'>Hello <strong>{$deptHead->name}</strong>,</p>
                <p style='font-size:13px;color:#475569;'>A new instructor has joined <strong>{$deptName}</strong>:</p>
                <div style='background:#f8fafc;padding:16px;border-radius:10px;border:1px solid #e2e8f0;font-size:13px;'>
                    <p style='margin:4px 0;'><strong>Name:</strong> {$instructor->name}</p>
                    <p style='margin:4px 0;'><strong>Email:</strong> {$instructor->email}</p>
                    <p style='margin:4px 0;'><strong>Role:</strong> " . ucfirst($instructor->role) . "</p>
                </div>
            </div>
        </div>";

        self::deliverEmail($config, $target, $subject, $html);
    }

    /**
     * Notify department head when a new course is created.
     */
    public static function notifyCourseCreated(Course $course, ?Department $department = null): void
    {
        $deptId = $department?->id ?? $course->department_id;
        if (!$deptId) return;

        $deptHead = User::where('department_id', $deptId)
            ->where('role', 'dept_head')
            ->first();

        if (!$deptHead) return;

        $prefs = $deptHead->notification_preferences;
        if (!is_array($prefs) || empty($prefs['emailOnCourseCreate'])) {
            return;
        }

        $config = self::resolveConfig($deptHead);
        $target = $config['recipientEmail'] ?: $deptHead->email;
        $appName = $config['senderName'] ?: 'Wollo University Exam Portal';

        $subject = "{$appName} — New Course Registered: {$course->title} ({$course->code})";
        $html = "
        <div style='font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;'>
            <div style='background:#4f46e5;padding:24px;text-align:center;'>
                <h2 style='color:#fff;margin:0;'>{$appName}</h2>
                <p style='color:#c7d2fe;font-size:12px;margin:4px 0 0 0;'>Curriculum Update</p>
            </div>
            <div style='padding:24px;'>
                <p style='font-size:14px;color:#1e293b;'>Hello <strong>{$deptHead->name}</strong>,</p>
                <p style='font-size:13px;color:#475569;'>A new course has been registered in your department:</p>
                <div style='background:#f8fafc;padding:16px;border-radius:10px;border:1px solid #e2e8f0;font-size:13px;'>
                    <p style='margin:4px 0;'><strong>Course Title:</strong> {$course->title}</p>
                    <p style='margin:4px 0;'><strong>Course Code:</strong> {$course->code}</p>
                    <p style='margin:4px 0;'><strong>Credit Hours:</strong> {$course->credit_hours}</p>
                </div>
            </div>
        </div>";

        self::deliverEmail($config, $target, $subject, $html);
    }

    /**
     * Notify department head when an exam is published.
     */
    public static function notifyExamPublished(Exam $exam): void
    {
        $course = Course::where('code', $exam->course_code)->first();
        $deptId = $course?->department_id;
        if (!$deptId) return;

        $deptHead = User::where('department_id', $deptId)
            ->where('role', 'dept_head')
            ->first();

        if (!$deptHead) return;

        $prefs = $deptHead->notification_preferences;
        if (!is_array($prefs) || empty($prefs['emailOnExamPublish'])) {
            return;
        }

        $config = self::resolveConfig($deptHead);
        $target = $config['recipientEmail'] ?: $deptHead->email;
        $appName = $config['senderName'] ?: 'Wollo University Exam Portal';

        $subject = "{$appName} — Exam Published: {$exam->title}";
        $html = "
        <div style='font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;'>
            <div style='background:#4f46e5;padding:24px;text-align:center;'>
                <h2 style='color:#fff;margin:0;'>{$appName}</h2>
                <p style='color:#c7d2fe;font-size:12px;margin:4px 0 0 0;'>Assessment Published</p>
            </div>
            <div style='padding:24px;'>
                <p style='font-size:14px;color:#1e293b;'>Hello <strong>{$deptHead->name}</strong>,</p>
                <p style='font-size:13px;color:#475569;'>An examination has been published for students:</p>
                <div style='background:#f8fafc;padding:16px;border-radius:10px;border:1px solid #e2e8f0;font-size:13px;'>
                    <p style='margin:4px 0;'><strong>Exam Title:</strong> {$exam->title}</p>
                    <p style='margin:4px 0;'><strong>Course:</strong> {$exam->course_code}</p>
                    <p style='margin:4px 0;'><strong>Status:</strong> Published</p>
                </div>
            </div>
        </div>";

        self::deliverEmail($config, $target, $subject, $html);
    }
}
