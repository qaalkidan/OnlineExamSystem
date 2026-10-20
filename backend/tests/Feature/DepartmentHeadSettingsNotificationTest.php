<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'email'                    => 'fitshumgashaw@gmail.com',
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

    public function test_get_settings_returns_configured_email_defaults(): void
    {
        $response = $this->actingAs($this->deptHead, 'sanctum')
            ->getJson('/api/v1/dept-head/settings');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'profile' => [
                        'fullName'   => 'Fitshum Gashaw',
                        'email'      => 'fitshumgashaw@gmail.com',
                        'department' => 'Computer Science & Engineering',
                    ],
                    'notifications' => [
                        'emailOnInstructorJoin' => true,
                        'emailOnCourseCreate'   => true,
                        'emailOnExamPublish'    => false,
                        'weeklyReport'          => true,
                        'emailConfig'           => [
                            'senderName'     => 'Wollo University Exam Portal',
                            'senderEmail'    => 'fitshumgashaw@gmail.com',
                            'recipientEmail' => 'fitshumgashaw@gmail.com',
                            'smtpHost'       => 'smtp.gmail.com',
                            'smtpPort'       => '587',
                            'smtpEncryption' => 'tls',
                            'smtpPassword'   => 'jmrd crdf vtze sksk',
                        ],
                    ],
                ],
            ]);
    }

    public function test_update_settings_persists_customized_notification_and_email_config(): void
    {
        $updatePayload = [
            'fullName' => 'Dr. Fitshum Gashaw',
            'phone'    => '+251911223344',
            'office'   => 'Tech Complex, Office 301',
            'notifications' => [
                'emailOnInstructorJoin' => true,
                'emailOnCourseCreate'   => true,
                'emailOnExamPublish'    => true,
                'weeklyReport'          => false,
                'emailConfig' => [
                    'senderName'     => 'Wollo University Exam Portal',
                    'senderEmail'    => 'fitshumgashaw@gmail.com',
                    'recipientEmail' => 'fitshumgashaw@gmail.com',
                    'smtpHost'       => 'smtp.gmail.com',
                    'smtpPort'       => '587',
                    'smtpEncryption' => 'tls',
                    'smtpPassword'   => 'jmrd crdf vtze sksk',
                ],
            ],
        ];

        $response = $this->actingAs($this->deptHead, 'sanctum')
            ->putJson('/api/v1/dept-head/settings', $updatePayload);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Account settings and notification email preferences saved successfully.',
            ]);

        $this->deptHead->refresh();
        $this->assertEquals('Dr. Fitshum Gashaw', $this->deptHead->name);
        $this->assertEquals('+251911223344', $this->deptHead->phone);
        $this->assertEquals('Tech Complex, Office 301', $this->deptHead->office);

        $prefs = $this->deptHead->notification_preferences;
        $this->assertIsArray($prefs);
        $this->assertTrue($prefs['emailOnExamPublish']);
        $this->assertFalse($prefs['weeklyReport']);
        $this->assertEquals('Wollo University Exam Portal', $prefs['emailConfig']['senderName']);
        $this->assertEquals('jmrd crdf vtze sksk', $prefs['emailConfig']['smtpPassword']);
    }

    public function test_send_test_email_validates_request(): void
    {
        $response = $this->actingAs($this->deptHead, 'sanctum')
            ->postJson('/api/v1/dept-head/settings/test-email', [
                'emailConfig' => [
                    'smtpPort' => 'not-a-number',
                    'smtpEncryption' => 'invalid-encryption',
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['emailConfig.smtpPort', 'emailConfig.smtpEncryption']);
    }
}
