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
     * Resolve effective email configuration for a given department head or from system defaults.
     * Plaintext passwords from the server/env are never exposed to the frontend.
     */
    public static function resolveConfig(?User $user = null, ?array $override = null): array
    {
        $prefs = $user && is_array($user->notification_preferences)
            ? $user->notification_preferences
            : [];

        $savedConfig = is_array($prefs['emailConfig'] ?? null)
            ? $prefs['emailConfig']
            : [];

        $senderName = !empty($override['senderName'])
            ? trim($override['senderName'])
            : ($savedConfig['senderName'] ?? config('mail.from.name', 'Wollo University Exam Portal'));

        $senderEmail = !empty($override['senderEmail'])
            ? trim($override['senderEmail'])
            : ($savedConfig['senderEmail'] ?? config('mail.from.address', 'fitsumgashaw22@gmail.com'));

        $recipientEmail = !empty($override['recipientEmail'])
            ? trim($override['recipientEmail'])
            : ($savedConfig['recipientEmail'] ?? ($user?->email ?: 'fitsumgashaw22@gmail.com'));

        $smtpHost = !empty($override['smtpHost'])
            ? trim($override['smtpHost'])
            : ($savedConfig['smtpHost'] ?? config('mail.mailers.smtp.host', 'smtp.gmail.com'));

        $smtpPort = (int)(
            !empty($override['smtpPort'])
                ? $override['smtpPort']
                : ($savedConfig['smtpPort'] ?? config('mail.mailers.smtp.port', 587))
        );

        $smtpEncryption = !empty($override['smtpEncryption'])
            ? trim($override['smtpEncryption'])
            : ($savedConfig['smtpEncryption'] ?? config('mail.mailers.smtp.encryption', 'tls'));

        // Resolve password: if override is empty or masked (e.g. bullets), fall back to saved or .env
        $overridePass = $override['smtpPassword'] ?? '';
        $isMasked = preg_match('/^[•*●\s]+$/u', (string)$overridePass);

        if (!empty($overridePass) && !$isMasked) {
            $smtpPassword = $overridePass;
        } elseif (!empty($savedConfig['smtpPassword']) && !preg_match('/^[•*●\s]+$/u', (string)$savedConfig['smtpPassword'])) {
            $smtpPassword = $savedConfig['smtpPassword'];
        } else {
            $smtpPassword = config('mail.mailers.smtp.password', 'jmrd crdf vtze sksk');
        }

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
     * Build and execute direct SMTP email delivery via Symfony Mailer EsmtpTransport.
     * Passwords and sensitive authentication tokens are strictly redacted from errors and logs.
     */
    public static function deliverEmail(array $config, string $to, string $subject, string $htmlBody, string $textBody = ''): array
    {
        $host = $config['smtpHost'] ?? config('mail.mailers.smtp.host', 'smtp.gmail.com');
        $port = (int)($config['smtpPort'] ?? 587);
        $user = $config['senderEmail'] ?? config('mail.from.address', 'fitsumgashaw22@gmail.com');
        $rawPass = $config['smtpPassword'] ?? config('mail.mailers.smtp.password', 'jmrd crdf vtze sksk');
        $fromName = $config['senderName'] ?? config('mail.from.name', 'Wollo University Exam Portal');
        $fromAddress = $config['senderEmail'] ?? config('mail.from.address', 'fitsumgashaw22@gmail.com');

        // Sanitize 16-character google app password (removing spaces)
        $cleanPass = str_replace(' ', '', $rawPass);
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
                    $email->text(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody)));
                }

                $mailer->send($email);

                Log::info("Email notification dispatched successfully to {$to} (Subject: {$subject})");
                return [
                    'success' => true,
                    'message' => "Email sent successfully to {$to}",
                ];
            } catch (\Throwable $e) {
                // Redact raw password from exception message
                $sanitizedError = str_replace([$rawPass, $cleanPass], '[REDACTED]', $e->getMessage());
                $lastError = $sanitizedError;
                Log::warning("SMTP delivery attempt failed for {$user} on {$host}:{$port} with error: {$sanitizedError}");
            }
        }

        return [
            'success' => false,
            'error'   => $lastError ?? 'Unknown SMTP connection error',
        ];
    }

    /**
     * Send real test notification email to verify SMTP connection & credentials.
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
        $timestamp = now()->format('M d, Y • h:i:s A');

        $html = self::buildBaseTemplate(
            appName: $appName,
            headline: 'SMTP Email Delivery Verification',
            subtitle: 'Department Head Notification Service',
            contentHtml: "
                <div style='background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px;margin-bottom:24px;'>
                    <span style='color:#15803d;font-weight:700;font-size:14px;display:block;'>✓ SMTP Connection Authenticated Successfully</span>
                    <p style='color:#166534;font-size:13px;margin:6px 0 0 0;'>Your Wollo University Exam Portal email delivery channel is operational. Outgoing alerts and notifications will be dispatched through this verified SMTP account.</p>
                </div>

                <h2 style='font-size:15px;color:#1e293b;margin:0 0 14px 0;'>Verified Configuration Parameters:</h2>
                <table style='width:100%;border-collapse:collapse;font-size:13px;color:#334155;margin-bottom:24px;'>
                    <tr style='border-bottom:1px solid #f1f5f9;'>
                        <td style='padding:9px 0;font-weight:600;color:#64748b;width:35%;'>Recipient Email:</td>
                        <td style='padding:9px 0;font-weight:700;color:#0f172a;'>{$targetEmail}</td>
                    </tr>
                    <tr style='border-bottom:1px solid #f1f5f9;'>
                        <td style='padding:9px 0;font-weight:600;color:#64748b;'>Sender Name:</td>
                        <td style='padding:9px 0;color:#0f172a;'>{$config['senderName']}</td>
                    </tr>
                    <tr style='border-bottom:1px solid #f1f5f9;'>
                        <td style='padding:9px 0;font-weight:600;color:#64748b;'>SMTP Host & Port:</td>
                        <td style='padding:9px 0;color:#0f172a;'>{$config['smtpHost']}:{$config['smtpPort']} ({$config['smtpEncryption']})</td>
                    </tr>
                    <tr style='border-bottom:1px solid #f1f5f9;'>
                        <td style='padding:9px 0;font-weight:600;color:#64748b;'>Sender Account:</td>
                        <td style='padding:9px 0;color:#0f172a;'>{$config['senderEmail']}</td>
                    </tr>
                    <tr>
                        <td style='padding:9px 0;font-weight:600;color:#64748b;'>Verified At:</td>
                        <td style='padding:9px 0;color:#0f172a;'>{$timestamp}</td>
                    </tr>
                </table>

                <div style='background:#f8fafc;border-left:4px solid #6366f1;padding:14px 16px;border-radius:8px;font-size:12px;color:#475569;'>
                    <strong>Active Notification Channels:</strong> Automated department updates (Instructor registrations, course creations, exam publications, and scheduled weekly reports) are now active according to your notification toggles.
                </div>"
        );

        $result = self::deliverEmail($config, $targetEmail, $subject, $html);

        // Update persistent SMTP status on the user
        $prefs = is_array($user->notification_preferences) ? $user->notification_preferences : [];
        if ($result['success']) {
            $prefs['smtpStatus'] = 'connected';
            $prefs['lastTestTime'] = now()->toIso8601String();
            $prefs['lastTestResult'] = 'Connected and verified successfully';
            $prefs['lastTestRecipient'] = $targetEmail;

            LogActivity::record(
                'Sent',
                'Email Integration',
                "Sent test notification email to {$targetEmail} ({$appName})",
                'Success',
                $user->department_id,
                $user
            );
        } else {
            $prefs['smtpStatus'] = 'failed';
            $prefs['lastTestTime'] = now()->toIso8601String();
            $prefs['lastTestResult'] = substr($result['error'], 0, 200);

            LogActivity::record(
                'Failed',
                'Email Integration',
                "Failed to send test email to {$targetEmail}: " . substr($result['error'], 0, 150),
                'Failed',
                $user->department_id,
                $user
            );
        }

        $user->notification_preferences = $prefs;
        $user->save();

        $result['lastTestTime'] = $prefs['lastTestTime'];
        $result['targetEmail'] = $targetEmail;
        return $result;
    }

    /**
     * Notify Department Head when a new instructor joins.
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
        $dateStr = now()->format('M d, Y • h:i A');

        $subject = "{$appName} — New Instructor Registered: {$instructor->name}";
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

        self::deliverEmail($config, $target, $subject, $html);
    }

    /**
     * Notify Department Head when a new course is created.
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
        $deptName = $department?->name ?? $deptHead->department?->name ?? 'Department';
        $dateStr = now()->format('M d, Y • h:i A');

        $subject = "{$appName} — New Course Created: {$course->title} ({$course->code})";
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

        self::deliverEmail($config, $target, $subject, $html);
    }

    /**
     * Notify Department Head when an exam is published.
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
        $deptName = $deptHead->department?->name ?? 'Department';
        $dateStr = now()->format('M d, Y • h:i A');

        $subject = "{$appName} — Examination Published: {$exam->title}";
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

        self::deliverEmail($config, $target, $subject, $html);
    }

    /**
     * Generate and send the comprehensive weekly department executive summary report.
     */
    public static function sendWeeklyDepartmentReport(User $deptHead, bool $force = false): array
    {
        $prefs = is_array($deptHead->notification_preferences) ? $deptHead->notification_preferences : [];
        if (empty($prefs['weeklyReport'])) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Weekly report toggle is disabled.',
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
        $target = $config['recipientEmail'] ?: $deptHead->email;
        $appName = $config['senderName'] ?: 'Wollo University Exam Portal';
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
                'Email Integration',
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
                    <div style='display:inline-block;width:44px;height:44px;line-height:44px;background:rgba(255,255,255,0.2);border-radius:12px;font-size:22px;color:#ffffff;margin-bottom:10px;'>🎓</div>
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
