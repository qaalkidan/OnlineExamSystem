<?php

namespace App\Services;

use App\Models\User;
use App\Models\Department;
use App\Models\Course;
use App\Models\Exam;
use App\Models\SemesterSubmission;
use App\Helpers\LogActivity;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class DepartmentNotificationService
{
    /**
     * Resolve server-side SMTP configuration and Department Head recipient.
     * All outgoing notifications authenticate via verified server SMTP credentials (.env),
     * and are delivered directly to the Department Head's account email.
     */
    public static function resolveConfig(?User $deptHead = null): array
    {
        $smtpHost = config('mail.mailers.smtp.host', 'smtp.gmail.com');
        $smtpPort = (int)config('mail.mailers.smtp.port', 587);
        $smtpEncryption = config('mail.mailers.smtp.encryption', 'tls');
        $smtpUsername = config('mail.mailers.smtp.username', 'fitsumgashaw22@gmail.com');
        $smtpPassword = config('mail.mailers.smtp.password', 'jmrd crdf vtze sksk');
        $senderName = config('mail.from.name', 'Wollo University Exam Portal');
        $senderEmail = config('mail.from.address', 'fitsumgashaw22@gmail.com');

        // Recipient is ALWAYS the Department Head's account email
        $recipientEmail = $deptHead?->email ?: $senderEmail;

        return [
            'senderName'     => $senderName,
            'senderEmail'    => $senderEmail,
            'recipientEmail' => $recipientEmail,
            'smtpHost'       => $smtpHost,
            'smtpPort'       => $smtpPort,
            'smtpEncryption' => $smtpEncryption,
            'smtpUsername'   => $smtpUsername,
            'smtpPassword'   => $smtpPassword,
        ];
    }

    /**
     * Resolve the authorized Department Head user for a given department ID or actor.
     */
    public static function resolveDeptHead(?int $deptId, ?User $actor = null): ?User
    {
        // 1. If actor is explicitly passed and is a dept_head, prioritize them if deptId matches or none specified
        $current = $actor ?? auth()->user();
        if ($current && $current->role === 'dept_head') {
            if (!$deptId || $current->department_id == $deptId) {
                return $current;
            }
        }

        // 2. Query department head by department ID
        if ($deptId) {
            $deptHead = User::where('department_id', $deptId)
                ->where('role', 'dept_head')
                ->first();
            if ($deptHead) {
                return $deptHead;
            }
        }

        // 3. Fallback to current authenticated dept_head if any
        if ($current && $current->role === 'dept_head') {
            return $current;
        }

        // 4. Fallback to any department head
        return User::where('role', 'dept_head')->first();
    }

    /**
     * Check if a specific notification toggle is enabled for a Department Head.
     * Default for instructor join, course create, weekly report is true unless explicitly disabled.
     */
    public static function isNotificationEnabled(User $deptHead, string $key, bool $default = true): bool
    {
        $prefs = $deptHead->notification_preferences;
        if (!is_array($prefs)) {
            return $default;
        }

        if (array_key_exists($key, $prefs)) {
            return (bool)$prefs[$key];
        }

        return $default;
    }

    /**
     * Build and execute direct SMTP email delivery via Symfony Mailer EsmtpTransport.
     * Passwords and sensitive credentials are redacted from logs and errors.
     */
    public static function deliverEmail(array $config, string $to, string $subject, string $htmlBody, string $textBody = ''): array
    {
        $host = $config['smtpHost'] ?? 'smtp.gmail.com';
        $port = (int)($config['smtpPort'] ?? 587);
        $username = $config['smtpUsername'] ?? config('mail.mailers.smtp.username', 'fitsumgashaw22@gmail.com');
        $rawPass = $config['smtpPassword'] ?? config('mail.mailers.smtp.password', 'jmrd crdf vtze sksk');
        $fromName = $config['senderName'] ?? config('mail.from.name', 'Wollo University Exam Portal');
        $fromAddress = $config['senderEmail'] ?? config('mail.from.address', 'fitsumgashaw22@gmail.com');

        // Sanitize 16-character google app password
        $cleanPass = str_replace(' ', '', $rawPass);

        // Try configured port, with automatic fallback to port 465 (direct SSL) if 587 encounters a socket error
        $isPrimarySsl = ($port === 465 || strtolower($config['smtpEncryption'] ?? '') === 'ssl');
        $connectionProfiles = [
            ['port' => $port, 'ssl' => $isPrimarySsl, 'pass' => $cleanPass],
            ['port' => $port, 'ssl' => $isPrimarySsl, 'pass' => $rawPass],
            ['port' => 465,   'ssl' => true,          'pass' => $cleanPass],
        ];

        $lastError = null;

        foreach ($connectionProfiles as $profile) {
            try {
                $transport = new EsmtpTransport($host, $profile['port'], $profile['ssl']);
                $transport->setUsername($username);
                $transport->setPassword($profile['pass']);

                $mailer = new Mailer($transport);

                $email = (new Email())
                    ->from(new Address($fromAddress, $fromName))
                    ->to($to)
                    ->replyTo(new Address($fromAddress, $fromName))
                    ->priority(Email::PRIORITY_HIGHEST)
                    ->subject($subject)
                    ->html($htmlBody);

                if (!empty($textBody)) {
                    $email->text($textBody);
                } else {
                    $email->text(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody)));
                }

                $mailer->send($email);

                Log::info("Email notification dispatched successfully to {$to} via {$host}:{$profile['port']} (Subject: {$subject})");
                return [
                    'success' => true,
                    'message' => "Email sent successfully to {$to}",
                ];
            } catch (\Throwable $e) {
                $sanitizedError = str_replace([$rawPass, $cleanPass], '[REDACTED]', $e->getMessage());
                $lastError = $sanitizedError;
                Log::warning("SMTP delivery attempt failed for {$username} on {$host}:{$profile['port']} with error: {$sanitizedError}");
            }
        }

        return [
            'success' => false,
            'error'   => $lastError ?? 'Unknown SMTP connection error',
        ];
    }

    /**
     * Notify Department Head when a new instructor joins.
     * The email is sent directly to the Department Head's email address ($deptHead->email).
     */
    public static function notifyInstructorJoined(User $instructor, ?Department $department = null): array
    {
        $deptId = $department?->id ?? $instructor->department_id;
        $creator = $instructor->creator ?? ($instructor->created_by ? User::find($instructor->created_by) : null);
        $deptHead = self::resolveDeptHead($deptId, $creator);

        if (!$deptHead || empty($deptHead->email)) {
            Log::info("No Department Head found to notify for new instructor ID {$instructor->id}.");
            return ['success' => false, 'message' => 'No Department Head found to notify.'];
        }

        if (!self::isNotificationEnabled($deptHead, 'emailOnInstructorJoin', true)) {
            Log::info("emailOnInstructorJoin notification toggle is disabled for Department Head {$deptHead->email}.");
            return ['success' => false, 'skipped' => true, 'message' => 'Toggle is disabled.'];
        }

        $config = self::resolveConfig($deptHead);
        $target = $deptHead->email;
        $appName = $config['senderName'];
        $deptName = $department?->name ?? $deptHead->department?->name ?? 'Department';
        $dateStr = now()->format('M d, Y • h:i A');

        $subject = "{$appName} - New Instructor Registered: {$instructor->name}";
        $html = self::buildBaseTemplate(
            appName: $appName,
            headline: 'New Instructor Registered',
            subtitle: "Department of {$deptName}",
            contentHtml: "
                <p style='font-size:14px;color:#1e293b;margin:0 0 16px 0;'>Hello <strong>{$deptHead->name}</strong>,</p>
                <p style='font-size:13px;color:#475569;margin:0 0 20px 0;'>A new instructor account has been created in your department.</p>

                <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:18px;margin-bottom:24px;'>
                    <table style='width:100%;font-size:13px;color:#334155;border-collapse:collapse;'>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;width:35%;'>Instructor Name:</td>
                            <td style='padding:8px 0;font-weight:700;color:#0f172a;'>{$instructor->name}</td>
                        </tr>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Email Address:</td>
                            <td style='padding:8px 0;color:#0f172a;'>{$instructor->email}</td>
                        </tr>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Department:</td>
                            <td style='padding:8px 0;color:#0f172a;'>{$deptName}</td>
                        </tr>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Academic Year:</td>
                            <td style='padding:8px 0;color:#0f172a;'>" . ($instructor->academic_year ?: '2025/2026') . "</td>
                        </tr>
                        <tr>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Registration Date:</td>
                            <td style='padding:8px 0;color:#0f172a;'>{$dateStr}</td>
                        </tr>
                    </table>
                </div>

                <div style='background:#f0fdf4;border-left:4px solid #22c55e;padding:12px 16px;border-radius:8px;font-size:12px;color:#166534;'>
                    You can assign courses and semester responsibilities to this instructor from your Department Head dashboard.
                </div>"
        );

        $result = self::deliverEmail($config, $target, $subject, $html);

        if ($result['success']) {
            LogActivity::record(
                'Sent',
                'Email Notification',
                "Sent New Instructor alert to Department Head {$target} for {$instructor->name}",
                'Success',
                $deptId,
                $deptHead
            );
        }

        return $result;
    }

    /**
     * Send account credentials (username & temporary password) directly to the instructor's registered email address.
     * The plaintext password is only delivered inside this email and is never saved or logged.
     */
    public static function sendInstructorCredentialsEmail(User $instructor, string $plainPassword, ?Department $department = null): array
    {
        $target = $instructor->email;
        if (empty($target) || !filter_var($target, FILTER_VALIDATE_EMAIL)) {
            Log::warning("Cannot dispatch credentials email: Instructor ID {$instructor->id} has invalid email address.");
            return [
                'success' => false,
                'error'   => 'Instructor has no valid email address configured.',
            ];
        }

        $config = self::resolveConfig();
        $appName = $config['senderName'] ?? 'Wollo University Exam Portal';
        $deptName = $department?->name ?? $instructor->department?->name ?? 'Academic Department';
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');
        $loginUrl = $frontendUrl . '/login';
        $dateStr = now()->format('M d, Y • h:i A');

        $subject = "{$appName} - Your Account Credentials";

        $textBody = "Dear {$instructor->name},\n\n"
            . "An instructor account has been created for you in the {$deptName} on the Wollo University Exam Portal.\n\n"
            . "YOUR LOGIN CREDENTIALS:\n"
            . "• Portal URL: {$loginUrl}\n"
            . "• Username: {$instructor->username}\n"
            . "• Registered Email: {$instructor->email}\n"
            . "• Temporary Password: {$plainPassword}\n"
            . "• Provisioned Date: {$dateStr}\n\n"
            . "SECURITY NOTICE:\n"
            . "This is a temporary password generated securely by the system. You will be required to change your password immediately upon your first sign in. Do not share these credentials with anyone.\n\n"
            . "Regards,\nWollo University Exam Portal";

        $html = self::buildBaseTemplate(
            appName: $appName,
            headline: 'Account Credentials & Portal Access',
            subtitle: "Faculty Provisioning &bull; {$deptName}",
            contentHtml: "
                <p style='font-size:14px;color:#1e293b;margin:0 0 16px 0;'>Dear <strong>{$instructor->name}</strong>,</p>
                <p style='font-size:13px;color:#475569;margin:0 0 20px 0;'>An instructor account has been created for you in the <strong>{$deptName}</strong> on the Wollo University Exam Portal.</p>

                <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin-bottom:24px;'>
                    <h4 style='margin:0 0 14px 0;font-size:13px;font-weight:700;color:#0f172a;text-transform:uppercase;letter-spacing:0.5px;'>Your Login Credentials</h4>
                    <table style='width:100%;font-size:13px;color:#334155;border-collapse:collapse;'>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;width:35%;'>Username:</td>
                            <td style='padding:8px 0;font-weight:700;color:#0f172a;font-family:monospace;font-size:14px;'>{$instructor->username}</td>
                        </tr>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Registered Email:</td>
                            <td style='padding:8px 0;color:#0f172a;'>{$instructor->email}</td>
                        </tr>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Temporary Password:</td>
                            <td style='padding:8px 0;'>
                                <span style='display:inline-block;background:#eef2ff;color:#4338ca;font-weight:700;font-family:Consolas,Monaco,monospace;font-size:15px;padding:6px 14px;border-radius:8px;border:1px dashed #6366f1;letter-spacing:1px;'>
                                    {$plainPassword}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Provisioned Date:</td>
                            <td style='padding:8px 0;color:#0f172a;'>{$dateStr}</td>
                        </tr>
                    </table>
                </div>

                <div style='text-align:center;margin:28px 0;'>
                    <a href='{$loginUrl}' style='display:inline-block;background:#4f46e5;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:12px 32px;border-radius:10px;box-shadow:0 2px 8px rgba(79,70,229,0.3);'>
                        Sign In to Exam Portal &rarr;
                    </a>
                    <p style='font-size:11px;color:#64748b;margin:8px 0 0 0;'>Portal URL: <a href='{$loginUrl}' style='color:#6366f1;text-decoration:underline;'>{$loginUrl}</a></p>
                </div>

                <div style='background:#fef3c7;border-left:4px solid #f59e0b;padding:14px 16px;border-radius:8px;font-size:12px;color:#92400e;margin-bottom:20px;'>
                    <strong>Security Notice:</strong> This is a temporary password generated securely by the system. You will be required to change your password immediately upon your first sign in. Do not share these credentials with anyone.
                </div>

                <div style='background:#f0fdf4;border-left:4px solid #22c55e;padding:12px 16px;border-radius:8px;font-size:12px;color:#166534;'>
                    If you did not expect to receive this account invitation or experience any sign-in issues, please contact your Department Head or university IT support.
                </div>"
        );

        $result = self::deliverEmail($config, $target, $subject, $html, $textBody);

        if ($result['success']) {
            LogActivity::record(
                'Sent',
                'Credentials Email',
                "Dispatched temporary account credentials to instructor {$instructor->name} at {$target}",
                'Success',
                $instructor->department_id,
                $instructor
            );
        } else {
            LogActivity::record(
                'Delivery Failed',
                'Credentials Email',
                "Failed to dispatch temporary credentials to {$target}: " . ($result['error'] ?? 'Unknown error'),
                'Failed',
                $instructor->department_id,
                $instructor
            );
        }

        return $result;
    }

    /**
     * Notify Department Head when a new course is created.
     * The email is sent directly to the Department Head's email address ($deptHead->email).
     */
    public static function notifyCourseCreated(Course $course, ?Department $department = null): array
    {
        $deptId = $department?->id ?? $course->department_id;
        $creator = $course->creator ?? ($course->created_by ? User::find($course->created_by) : null);
        $deptHead = self::resolveDeptHead($deptId, $creator);

        if (!$deptHead || empty($deptHead->email)) {
            Log::info("No Department Head found to notify for new course ID {$course->id}.");
            return ['success' => false, 'message' => 'No Department Head found to notify.'];
        }

        if (!self::isNotificationEnabled($deptHead, 'emailOnCourseCreate', true)) {
            Log::info("emailOnCourseCreate notification toggle is disabled for Department Head {$deptHead->email}.");
            return ['success' => false, 'skipped' => true, 'message' => 'Toggle is disabled.'];
        }

        $config = self::resolveConfig($deptHead);
        $target = $deptHead->email;
        $appName = $config['senderName'];
        $deptName = $department?->name ?? $deptHead->department?->name ?? 'Department';
        $dateStr = now()->format('M d, Y • h:i A');

        $subject = "{$appName} - New Course Created: {$course->title} ({$course->code})";
        $html = self::buildBaseTemplate(
            appName: $appName,
            headline: 'Curriculum Course Created',
            subtitle: "Department of {$deptName}",
            contentHtml: "
                <p style='font-size:14px;color:#1e293b;margin:0 0 16px 0;'>Hello <strong>{$deptHead->name}</strong>,</p>
                <p style='font-size:13px;color:#475569;margin:0 0 20px 0;'>A new course has been successfully added to your department curriculum.</p>

                <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:18px;margin-bottom:24px;'>
                    <table style='width:100%;font-size:13px;color:#334155;border-collapse:collapse;'>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;width:35%;'>Course Title:</td>
                            <td style='padding:8px 0;font-weight:700;color:#0f172a;'>{$course->title}</td>
                        </tr>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Course Code:</td>
                            <td style='padding:8px 0;font-weight:700;color:#4338ca;'>{$course->code}</td>
                        </tr>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Credit Hours:</td>
                            <td style='padding:8px 0;color:#0f172a;'>" . ($course->credit_hours ?: '3') . " Credit Hours</td>
                        </tr>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Department:</td>
                            <td style='padding:8px 0;color:#0f172a;'>{$deptName}</td>
                        </tr>
                        <tr>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Date Created:</td>
                            <td style='padding:8px 0;color:#0f172a;'>{$dateStr}</td>
                        </tr>
                    </table>
                </div>

                <div style='background:#eef2ff;border-left:4px solid #6366f1;padding:12px 16px;border-radius:8px;font-size:12px;color:#3730a3;'>
                    Course is active and available for instructor assignment and exam authoring.
                </div>"
        );

        $result = self::deliverEmail($config, $target, $subject, $html);

        if ($result['success']) {
            LogActivity::record(
                'Sent',
                'Email Notification',
                "Sent New Course alert to Department Head {$target} for {$course->title} ({$course->code})",
                'Success',
                $deptId,
                $deptHead
            );
        }

        return $result;
    }

    /**
     * Notify Department Head when an exam is published.
     * The email is sent directly to the Department Head's email address ($deptHead->email).
     */
    public static function notifyExamPublished(Exam $exam): array
    {
        $course = Course::where('code', $exam->course_code)->first();
        $deptId = $course?->department_id ?? $exam->user?->department_id;
        $actor = $exam->user ?? null;
        $deptHead = self::resolveDeptHead($deptId, $actor);

        if (!$deptHead || empty($deptHead->email)) {
            Log::info("No Department Head found to notify for exam published ID {$exam->id}.");
            return ['success' => false, 'message' => 'No Department Head found to notify.'];
        }

        if (!self::isNotificationEnabled($deptHead, 'emailOnExamPublish', false)) {
            Log::info("emailOnExamPublish notification toggle is disabled for Department Head {$deptHead->email}.");
            return ['success' => false, 'skipped' => true, 'message' => 'Toggle is disabled.'];
        }

        $config = self::resolveConfig($deptHead);
        $target = $deptHead->email;
        $appName = $config['senderName'];
        $deptName = $deptHead->department?->name ?? 'Department';
        $dateStr = now()->format('M d, Y • h:i A');

        $subject = "{$appName} - Examination Published: {$exam->title}";
        $html = self::buildBaseTemplate(
            appName: $appName,
            headline: 'Examination Published',
            subtitle: "Department of {$deptName}",
            contentHtml: "
                <p style='font-size:14px;color:#1e293b;margin:0 0 16px 0;'>Hello <strong>{$deptHead->name}</strong>,</p>
                <p style='font-size:13px;color:#475569;margin:0 0 20px 0;'>An examination has been officially published and scheduled for students.</p>

                <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:18px;margin-bottom:24px;'>
                    <table style='width:100%;font-size:13px;color:#334155;border-collapse:collapse;'>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;width:35%;'>Exam Title:</td>
                            <td style='padding:8px 0;font-weight:700;color:#0f172a;'>{$exam->title}</td>
                        </tr>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Course:</td>
                            <td style='padding:8px 0;font-weight:700;color:#4338ca;'>{$exam->course_code}</td>
                        </tr>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Duration:</td>
                            <td style='padding:8px 0;color:#0f172a;'>" . ($exam->duration_minutes ?: '60') . " Minutes</td>
                        </tr>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Pass Mark:</td>
                            <td style='padding:8px 0;color:#0f172a;'>" . ($exam->pass_percentage ?: '50') . "%</td>
                        </tr>
                        <tr>
                            <td style='padding:8px 0;font-weight:600;color:#64748b;'>Published At:</td>
                            <td style='padding:8px 0;color:#0f172a;'>{$dateStr}</td>
                        </tr>
                    </table>
                </div>

                <div style='background:#f0fdf4;border-left:4px solid #22c55e;padding:12px 16px;border-radius:8px;font-size:12px;color:#166534;'>
                    Enrolled students can view the exam in their assessment schedules.
                </div>"
        );

        $result = self::deliverEmail($config, $target, $subject, $html);

        if ($result['success']) {
            LogActivity::record(
                'Sent',
                'Email Notification',
                "Sent Exam Published alert to Department Head {$target} for {$exam->title}",
                'Success',
                $deptId,
                $deptHead
            );
        }

        return $result;
    }

    /**
     * Generate and send the comprehensive weekly department executive summary report directly to the Department Head email.
     */
    public static function sendWeeklyDepartmentReport(User $deptHead, bool $force = false): array
    {
        $prefs = is_array($deptHead->notification_preferences) ? $deptHead->notification_preferences : [];

        if (!self::isNotificationEnabled($deptHead, 'weeklyReport', true)) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Weekly report toggle is disabled.',
            ];
        }

        if (empty($deptHead->email)) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Department Head does not have an email address configured.',
            ];
        }

        $currentPeriod = now()->format('o-\WW'); // e.g. 2026-W42
        if (!$force && ($prefs['lastWeeklyReportSent'] ?? '') === $currentPeriod) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => "Report for period {$currentPeriod} has already been sent to this Department Head.",
            ];
        }

        $deptId = $deptHead->department_id;
        $dept = $deptHead->department ?: Department::find($deptId);
        $deptName = $dept?->name ?? 'Department';

        // Gather real database metrics
        $instructorCount = User::where('department_id', $deptId)->where('role', 'instructor')->count();
        $courseCount = Course::where('department_id', $deptId)->count();
        $deptCourseCodes = Course::where('department_id', $deptId)->pluck('code');
        $totalExams = Exam::whereIn('course_code', $deptCourseCodes)->count();
        $publishedExams = Exam::whereIn('course_code', $deptCourseCodes)->where('status', 'published')->count();

        // Semester submissions breakdown
        $submissionsQuery = SemesterSubmission::where(function ($q) use ($deptId, $deptName) {
            $q->where('department', $deptName);
        });

        $totalSubmissions = (clone $submissionsQuery)->count();
        $approvedSubmissions = (clone $submissionsQuery)->where('status', 'approved')->count();
        $lockedSubmissions = (clone $submissionsQuery)->whereNotNull('locked_at')->count();
        $pendingSubmissions = (clone $submissionsQuery)->where('status', 'pending')->count();
        $correctionSubmissions = (clone $submissionsQuery)->where('status', 'correction_required')->count();

        $config = self::resolveConfig($deptHead);
        $target = $deptHead->email;
        $appName = $config['senderName'];
        $reportPeriodLabel = now()->startOfWeek()->format('M d') . ' – ' . now()->endOfWeek()->format('M d, Y');

        $subject = "{$appName} — Weekly Department Executive Digest ({$reportPeriodLabel})";

        $html = self::buildBaseTemplate(
            appName: $appName,
            headline: 'Weekly Department Executive Digest',
            subtitle: "Department of {$deptName} • Period: {$reportPeriodLabel}",
            contentHtml: "
                <p style='font-size:14px;color:#1e293b;margin:0 0 16px 0;'>Hello <strong>{$deptHead->name}</strong>,</p>
                <p style='font-size:13px;color:#475569;margin:0 0 20px 0;'>Here is your automated weekly summary of faculty activity, curriculum courses, examination states, and semester submissions.</p>

                <!-- 4 Metrics KPI Grid -->
                <table style='width:100%;border-collapse:collapse;margin-bottom:24px;'>
                    <tr>
                        <td style='width:25%;padding:6px;'>
                            <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px;text-align:center;'>
                                <span style='font-size:20px;font-weight:800;color:#4338ca;display:block;'>{$instructorCount}</span>
                                <span style='font-size:11px;color:#64748b;font-weight:600;'>Instructors</span>
                            </div>
                        </td>
                        <td style='width:25%;padding:6px;'>
                            <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px;text-align:center;'>
                                <span style='font-size:20px;font-weight:800;color:#0891b2;display:block;'>{$courseCount}</span>
                                <span style='font-size:11px;color:#64748b;font-weight:600;'>Courses</span>
                            </div>
                        </td>
                        <td style='width:25%;padding:6px;'>
                            <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px;text-align:center;'>
                                <span style='font-size:20px;font-weight:800;color:#059669;display:block;'>{$publishedExams}/{$totalExams}</span>
                                <span style='font-size:11px;color:#64748b;font-weight:600;'>Published Exams</span>
                            </div>
                        </td>
                        <td style='width:25%;padding:6px;'>
                            <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px;text-align:center;'>
                                <span style='font-size:20px;font-weight:800;color:#d97706;display:block;'>{$totalSubmissions}</span>
                                <span style='font-size:11px;color:#64748b;font-weight:600;'>Submissions</span>
                            </div>
                        </td>
                    </tr>
                </table>

                <h3 style='font-size:14px;color:#1e293b;margin:0 0 12px 0;'>Semester Submission Pipeline Status:</h3>
                <div style='background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:24px;'>
                    <table style='width:100%;font-size:12px;color:#334155;border-collapse:collapse;'>
                        <tr style='background:#f8fafc;border-bottom:1px solid #e2e8f0;text-align:left;'>
                            <th style='padding:10px 14px;color:#64748b;'>Status Category</th>
                            <th style='padding:10px 14px;color:#64748b;'>Count</th>
                            <th style='padding:10px 14px;color:#64748b;'>Description</th>
                        </tr>
                        <tr style='border-bottom:1px solid #f1f5f9;'>
                            <td style='padding:10px 14px;font-weight:700;color:#15803d;'>Approved</td>
                            <td style='padding:10px 14px;font-weight:700;'>{$approvedSubmissions}</td>
                            <td style='padding:10px 14px;color:#64748b;'>Grades verified and ratified</td>
                        </tr>
                        <tr style='border-bottom:1px solid #f1f5f9;'>
                            <td style='padding:10px 14px;font-weight:700;color:#7c3aed;'>Locked / Closed</td>
                            <td style='padding:10px 14px;font-weight:700;'>{$lockedSubmissions}</td>
                            <td style='padding:10px 14px;color:#64748b;'>Semester access restricted</td>
                        </tr>
                        <tr style='border-bottom:1px solid #f1f5f9;'>
                            <td style='padding:10px 14px;font-weight:700;color:#d97706;'>Pending Review</td>
                            <td style='padding:10px 14px;font-weight:700;'>{$pendingSubmissions}</td>
                            <td style='padding:10px 14px;color:#64748b;'>Awaiting Department Head approval</td>
                        </tr>
                        <tr>
                            <td style='padding:10px 14px;font-weight:700;color:#dc2626;'>Correction Required</td>
                            <td style='padding:10px 14px;font-weight:700;'>{$correctionSubmissions}</td>
                            <td style='padding:10px 14px;color:#64748b;'>Sent back to faculty for adjustments</td>
                        </tr>
                    </table>
                </div>

                <div style='background:#f8fafc;border-left:4px solid #6366f1;padding:12px 16px;border-radius:8px;font-size:12px;color:#475569;'>
                    This report was automatically scheduled and generated by the Wollo University Exam Portal system scheduler.
                </div>"
        );

        $result = self::deliverEmail($config, $target, $subject, $html);

        if ($result['success']) {
            $prefs['lastWeeklyReportSent'] = $currentPeriod;
            $deptHead->notification_preferences = $prefs;
            $deptHead->save();

            LogActivity::record(
                'Sent',
                'Email Notification',
                "Dispatched weekly department executive digest to {$target} ({$currentPeriod})",
                'Success',
                $deptId,
                $deptHead
            );

            return [
                'success' => true,
                'target'  => $target,
                'period'  => $currentPeriod,
            ];
        }

        return $result;
    }

    /**
     * Base HTML container template for Wollo University branded emails.
     */
    private static function buildBaseTemplate(string $appName, string $headline, string $subtitle, string $contentHtml): string
    {
        $year = date('Y');
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$headline}</title>
        </head>
        <body style='margin:0;padding:24px;background-color:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,Helvetica,Arial,sans-serif;'>
            <div style='max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.04);'>
                <!-- Header Banner -->
                <div style='background:linear-gradient(135deg, #3730a3 0%, #4f46e5 100%);padding:28px 24px;text-align:center;'>
                    <div style='display:inline-block;width:44px;height:44px;line-height:44px;background:rgba(255,255,255,0.2);border-radius:12px;font-size:16px;font-weight:900;letter-spacing:1px;color:#ffffff;margin-bottom:10px;'>WU</div>
                    <h1 style='color:#ffffff;font-size:19px;margin:0;font-weight:700;letter-spacing:-0.2px;'>{$appName}</h1>
                    <p style='color:#c7d2fe;font-size:12px;margin:4px 0 0 0;font-weight:500;'>{$headline}</p>
                    <div style='display:inline-block;margin-top:8px;padding:3px 10px;background:rgba(255,255,255,0.15);border-radius:20px;font-size:11px;color:#ffffff;'>{$subtitle}</div>
                </div>

                <!-- Main Content Body -->
                <div style='padding:28px 24px;'>
                    {$contentHtml}
                </div>

                <!-- Footer -->
                <div style='background:#f8fafc;padding:20px 24px;border-top:1px solid #e2e8f0;text-align:center;font-size:11px;color:#94a3b8;line-height:1.6;'>
                    <strong>Wollo University</strong> &bull; Online Examination & Academic Management System<br>
                    This is an automated notification. Please do not reply directly to this email.<br>
                    &copy; {$year} Wollo University. All rights reserved.
                </div>
            </div>
        </body>
        </html>";
    }
}
