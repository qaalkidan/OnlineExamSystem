<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $instructor;
    protected User $student;
    protected User $deptHead;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name'        => 'Computer Science',
            'code'        => 'CS',
            'description' => 'Department of Computer Science',
            'status'      => 'active',
        ]);

        $this->admin = User::create([
            'name'     => 'Super Admin',
            'email'    => 'admin@wollo.edu.et',
            'password' => bcrypt('AdminPassword123!'),
            'role'     => 'admin',
            'status'   => 'active',
        ]);

        $this->instructor = User::create([
            'name'          => 'CS Instructor',
            'email'         => 'instructor@wollo.edu.et',
            'password'      => bcrypt('InstructorPassword123!'),
            'role'          => 'instructor',
            'department_id' => $this->department->id,
            'status'        => 'active',
        ]);

        $this->student = User::create([
            'name'          => 'CS Student',
            'email'         => 'student@wollo.edu.et',
            'password'      => bcrypt('StudentPassword123!'),
            'role'          => 'student',
            'department_id' => $this->department->id,
            'status'        => 'active',
        ]);

        $this->deptHead = User::create([
            'name'          => 'CS Dept Head',
            'email'         => 'depthead@wollo.edu.et',
            'password'      => bcrypt('DeptHeadPassword123!'),
            'role'          => 'dept_head',
            'department_id' => $this->department->id,
            'status'        => 'active',
        ]);
    }

    public function test_unauthenticated_request_is_denied(): void
    {
        $response = $this->getJson('/api/v1/admin/activity-logs');
        $response->assertStatus(401);
    }

    public function test_non_admin_roles_are_forbidden(): void
    {
        // Student attempt
        Sanctum::actingAs($this->student);
        $this->getJson('/api/v1/admin/activity-logs')
            ->assertStatus(403)
            ->assertJson(['status' => 'error']);

        // Instructor attempt
        Sanctum::actingAs($this->instructor);
        $this->getJson('/api/v1/admin/activity-logs')
            ->assertStatus(403)
            ->assertJson(['status' => 'error']);
    }

    public function test_department_head_can_access_activity_logs(): void
    {
        Sanctum::actingAs($this->deptHead);
        $this->getJson('/api/v1/admin/activity-logs')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'pagination',
                'stats',
                'filter_options',
            ]);

        $this->getJson('/api/v1/admin/activity-logs/unread-count')
            ->assertStatus(200)
            ->assertJsonStructure(['count']);
    }

    public function test_super_admin_can_access_activity_logs_with_accurate_metrics(): void
    {
        // Seed diverse test logs
        ActivityLog::create([
            'user_id'       => $this->admin->id,
            'department_id' => $this->department->id,
            'actor_role'    => 'admin',
            'action'        => 'Created course "Computer Networks"',
            'type'          => 'Created',
            'module'        => 'Courses',
            'details'       => 'Created course "Computer Networks"',
            'ip_address'    => '10.0.0.1',
            'log_status'    => 'Success',
            'is_read'       => false,
        ]);

        ActivityLog::create([
            'user_id'       => $this->instructor->id,
            'department_id' => $this->department->id,
            'actor_role'    => 'instructor',
            'action'        => 'Failed exam publishing',
            'type'          => 'Exam Control',
            'module'        => 'Examinations',
            'details'       => 'Failed exam publishing',
            'ip_address'    => '10.0.0.2',
            'log_status'    => 'Failed',
            'is_read'       => false,
        ]);

        ActivityLog::create([
            'user_id'       => $this->admin->id,
            'department_id' => null,
            'actor_role'    => 'admin',
            'action'        => 'Approved exam recovery for student',
            'type'          => 'Approved Recovery',
            'module'        => 'Exam Recovery',
            'details'       => 'Approved exam recovery for student',
            'ip_address'    => '10.0.0.1',
            'log_status'    => 'Success',
            'is_read'       => true,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/activity-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data',
                'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
                'stats' => ['total', 'today', 'successful', 'failed', 'security_events', 'unread'],
                'filter_options' => ['modules', 'actions', 'roles', 'users'],
            ]);

        $stats = $response->json('stats');
        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['successful']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertEquals(1, $stats['security_events']);
        $this->assertEquals(2, $stats['unread']);
    }

    public function test_server_side_filters_and_search_operate_accurately(): void
    {
        ActivityLog::create([
            'user_id'       => $this->admin->id,
            'actor_role'    => 'admin',
            'action'        => 'Admin Override on Exam #42',
            'type'          => 'Admin Override',
            'module'        => 'Exam Recovery',
            'details'       => 'Admin Override on Exam #42',
            'ip_address'    => '192.168.1.50',
            'log_status'    => 'Success',
            'is_read'       => false,
        ]);

        ActivityLog::create([
            'user_id'       => $this->instructor->id,
            'actor_role'    => 'instructor',
            'action'        => 'Updated question bank',
            'type'          => 'Updated',
            'module'        => 'Courses',
            'details'       => 'Updated question bank',
            'ip_address'    => '192.168.1.60',
            'log_status'    => 'Success',
            'is_read'       => false,
        ]);

        Sanctum::actingAs($this->admin);

        // Search by IP
        $resIp = $this->getJson('/api/v1/admin/activity-logs?search=192.168.1.50');
        $resIp->assertStatus(200);
        $this->assertCount(1, $resIp->json('data'));
        $this->assertEquals('Admin Override', $resIp->json('data.0.action'));

        // Filter by Module
        $resMod = $this->getJson('/api/v1/admin/activity-logs?module=Courses');
        $resMod->assertStatus(200);
        $this->assertCount(1, $resMod->json('data'));
        $this->assertEquals('Courses', $resMod->json('data.0.module'));

        // Filter by Role
        $resRole = $this->getJson('/api/v1/admin/activity-logs?role=instructor');
        $resRole->assertStatus(200);
        $this->assertCount(1, $resRole->json('data'));
        $this->assertEquals('CS Instructor', $resRole->json('data.0.user'));

        // Filter by Category Security
        $resCat = $this->getJson('/api/v1/admin/activity-logs?category=security');
        $resCat->assertStatus(200);
        $this->assertCount(1, $resCat->json('data'));
        $this->assertEquals('Admin Override', $resCat->json('data.0.action'));
    }

    public function test_mark_log_as_read_and_mark_all_read(): void
    {
        $log1 = ActivityLog::create([
            'user_id'       => $this->admin->id,
            'actor_role'    => 'admin',
            'action'        => 'Event 1',
            'type'          => 'Created',
            'module'        => 'System',
            'details'       => 'Event 1',
            'ip_address'    => '127.0.0.1',
            'log_status'    => 'Success',
            'is_read'       => false,
        ]);

        $log2 = ActivityLog::create([
            'user_id'       => $this->admin->id,
            'actor_role'    => 'admin',
            'action'        => 'Event 2',
            'type'          => 'Updated',
            'module'        => 'System',
            'details'       => 'Event 2',
            'ip_address'    => '127.0.0.1',
            'log_status'    => 'Success',
            'is_read'       => false,
        ]);

        Sanctum::actingAs($this->admin);

        // Check unread count
        $countRes = $this->getJson('/api/v1/admin/activity-logs/unread-count');
        $countRes->assertStatus(200)->assertJson(['count' => 2]);

        // Mark single log as read
        $markRes = $this->postJson("/api/v1/admin/activity-logs/{$log1->id}/read");
        $markRes->assertStatus(200);
        $this->assertTrue($log1->fresh()->is_read);

        // Check unread count is now 1
        $countRes2 = $this->getJson('/api/v1/admin/activity-logs/unread-count');
        $countRes2->assertStatus(200)->assertJson(['count' => 1]);

        // Mark all as read
        $markAllRes = $this->postJson('/api/v1/admin/activity-logs/mark-all-read');
        $markAllRes->assertStatus(200);
        $this->assertTrue($log2->fresh()->is_read);

        $countRes3 = $this->getJson('/api/v1/admin/activity-logs/unread-count');
        $countRes3->assertStatus(200)->assertJson(['count' => 0]);
    }

    public function test_export_csv_and_formula_injection_defense(): void
    {
        // Create a log with potential spreadsheet formula injection
        ActivityLog::create([
            'user_id'       => $this->admin->id,
            'actor_role'    => 'admin',
            'action'        => '=HYPERLINK("http://evil.com","Click")',
            'type'          => '+CMD|',
            'module'        => '@calc',
            'details'       => '-2+3+cmd',
            'ip_address'    => '127.0.0.1',
            'log_status'    => 'Success',
            'is_read'       => true,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->get('/api/v1/admin/activity-logs/export?format=csv');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        // Formula injection protection must sanitize dangerous prefixes with a single quote
        $this->assertStringContainsString("'+CMD|", $content);
        $this->assertStringContainsString("'@calc", $content);
        $this->assertStringContainsString("'-2+3+cmd", $content);
    }

    public function test_audit_log_immutability_enforced_by_model(): void
    {
        $log = ActivityLog::create([
            'user_id'       => $this->admin->id,
            'actor_role'    => 'admin',
            'action'        => 'Original Description',
            'type'          => 'Created',
            'module'        => 'System',
            'details'       => 'Original Details',
            'ip_address'    => '127.0.0.1',
            'log_status'    => 'Success',
            'is_read'       => false,
        ]);

        // Modifying is_read is allowed (read receipt)
        $log->update(['is_read' => true]);
        $this->assertTrue($log->fresh()->is_read);

        // Modifying any audit property must throw an immutable exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Institutional audit logs are immutable and cannot be modified.');
        $log->update(['details' => 'Tampered details']);
    }

    public function test_audit_log_cannot_be_deleted_via_model(): void
    {
        $log = ActivityLog::create([
            'user_id'       => $this->admin->id,
            'actor_role'    => 'admin',
            'action'        => 'Immutable Log Entry',
            'type'          => 'Security',
            'module'        => 'Authentication',
            'details'       => 'Login record',
            'ip_address'    => '127.0.0.1',
            'log_status'    => 'Success',
            'is_read'       => false,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Institutional audit logs are immutable electronic records and cannot be deleted.');
        $log->delete();
    }
}
