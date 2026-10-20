<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Course;
use App\Models\Exam;
use App\Models\User;
use App\Services\DepartmentNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DepartmentHeadSettingsNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $deptHead;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name'        => 'Computer Science & Engineering',
            'code'        => 'CSE',
            'description' => 'Computer Science and Engineering Department',
            'status'      => 'active',
        ]);

        $this->deptHead = User::create([
            'name'                     => 'Fitshum Gashaw',
            'email'                    => 'fitsumgashaw22@gmail.com',
            'password'                 => bcrypt('password123'),
            'role'                     => 'dept_head',
            'department_id'            => $this->department->id,
            'phone'                    => '+251980426395',
            'office'                   => 'Block A, Room 204',
            'status'                   => 'active',
            'academic_rank'            => 'Assistant Professor',
            'notification_preferences' => null,
        ]);
    }

    public function test_get_settings_returns_clean_notification_toggles_and_recipient(): void
    {
        $response = $this->actingAs($this->deptHead, 'sanctum')
            ->getJson('/api/v1/dept-head/settings');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'profile' => [
                        'fullName'   => 'Fitshum Gashaw',
                        'email'      => 'fitsumgashaw22@gmail.com',
                        'department' => 'Computer Science & Engineering',
                    ],
                    'notifications' => [
                        'emailOnInstructorJoin' => true,
                        'emailOnCourseCreate'   => true,
                        'emailOnExamPublish'    => false,
                        'weeklyReport'          => true,
                        'recipientEmail'        => 'fitsumgashaw22@gmail.com',
                    ],
                ],
            ]);
    }

    public function test_update_settings_persists_customized_notification_toggles(): void
    {
        $updatePayload = [
            'fullName' => 'Dr. Fitshum Gashaw',
            'phone'    => '+251911223344',
            'office'   => 'Tech Complex, Office 301',
            'notifications' => [
                'emailOnInstructorJoin' => true,
                'emailOnCourseCreate'   => false,
                'emailOnExamPublish'    => true,
                'weeklyReport'          => false,
            ],
        ];

        $response = $this->actingAs($this->deptHead, 'sanctum')
            ->putJson('/api/v1/dept-head/settings', $updatePayload);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Account settings and notification preferences saved successfully.',
            ]);

        $this->deptHead->refresh();
        $this->assertEquals('Dr. Fitshum Gashaw', $this->deptHead->name);
        $this->assertEquals('+251911223344', $this->deptHead->phone);

        $prefs = $this->deptHead->notification_preferences;
        $this->assertIsArray($prefs);
        $this->assertTrue($prefs['emailOnInstructorJoin']);
        $this->assertFalse($prefs['emailOnCourseCreate']);
        $this->assertTrue($prefs['emailOnExamPublish']);
        $this->assertFalse($prefs['weeklyReport']);
    }

    public function test_weekly_reports_console_command_runs_successfully(): void
    {
        $this->deptHead->notification_preferences = [
            'weeklyReport' => true,
        ];
        $this->deptHead->save();

        // Create sample course and instructor in department
        Course::create([
            'title'         => 'Advanced Algorithms',
            'code'          => 'CS401',
            'department_id' => $this->department->id,
            'status'        => 'active',
            'credit_hours'  => 4,
        ]);

        $exitCode = Artisan::call('department:send-weekly-reports', ['--force' => true]);
        $this->assertEquals(0, $exitCode);

        $this->deptHead->refresh();
        $currentPeriod = now()->format('o-\WW');
        $this->assertEquals($currentPeriod, $this->deptHead->notification_preferences['lastWeeklyReportSent'] ?? null);
    }

    public function test_weekly_report_prevents_duplicate_for_same_period(): void
    {
        $currentPeriod = now()->format('o-\WW');
        $this->deptHead->notification_preferences = [
            'weeklyReport'         => true,
            'lastWeeklyReportSent' => $currentPeriod,
        ];
        $this->deptHead->save();

        // Without --force, it must skip sending duplicate
        $result = DepartmentNotificationService::sendWeeklyDepartmentReport($this->deptHead, false);
        $this->assertFalse($result['success']);
        $this->assertTrue($result['skipped']);
        $this->assertStringContainsString('already been sent', $result['message']);
    }

    public function test_each_toggle_operates_independently(): void
    {
        // Toggle only instructor join on
        $this->deptHead->notification_preferences = [
            'emailOnInstructorJoin' => true,
            'emailOnCourseCreate'   => false,
            'emailOnExamPublish'    => false,
            'weeklyReport'          => false,
        ];
        $this->deptHead->save();

        $prefs = $this->deptHead->notification_preferences;
        $this->assertTrue($prefs['emailOnInstructorJoin']);
        $this->assertFalse($prefs['emailOnCourseCreate']);
        $this->assertFalse($prefs['emailOnExamPublish']);
        $this->assertFalse($prefs['weeklyReport']);

        // Toggle course create on, instructor join off
        $this->deptHead->notification_preferences = [
            'emailOnInstructorJoin' => false,
            'emailOnCourseCreate'   => true,
            'emailOnExamPublish'    => false,
            'weeklyReport'          => false,
        ];
        $this->deptHead->save();

        $this->deptHead->refresh();
        $this->assertFalse($this->deptHead->notification_preferences['emailOnInstructorJoin']);
        $this->assertTrue($this->deptHead->notification_preferences['emailOnCourseCreate']);
    }

    public function test_resolve_config_routes_to_dept_head_email(): void
    {
        $config = DepartmentNotificationService::resolveConfig($this->deptHead);
        $this->assertEquals($this->deptHead->email, $config['recipientEmail']);
        $this->assertEquals('fitsumgashaw22@gmail.com', $config['smtpUsername']);
        $this->assertEquals('jmrd crdf vtze sksk', $config['smtpPassword']);
    }
}
