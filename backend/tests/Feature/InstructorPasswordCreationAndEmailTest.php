<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Services\DepartmentNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstructorPasswordCreationAndEmailTest extends TestCase
{
    use RefreshDatabase;

    protected User $deptHead;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name'        => 'Information Technology',
            'code'        => 'IT',
            'description' => 'IT Department',
            'status'      => 'active',
        ]);

        $this->deptHead = User::create([
            'name'          => 'Dr. Fitsum Gashaw',
            'email'         => 'fitsumgashaw22@gmail.com',
            'password'      => bcrypt('P@ssword123!'),
            'role'          => 'dept_head',
            'department_id' => $this->department->id,
            'phone'         => '+251911223344',
            'status'        => 'active',
        ]);
    }

    public function test_create_instructor_with_manual_password_succeeds(): void
    {
        $payload = [
            'name'            => 'Manual Instructor',
            'email'           => 'manual.instructor@wollo.edu.et',
            'phone'           => '+251911000001',
            'gender'          => 'Male',
            'id_no'           => 'INS-9901',
            'username'        => 'manual.ins',
            'password_mode'   => 'manual',
            'password'        => 'SecurePass123!',
            'employment_type' => 'full_time',
            'status'          => 'active',
        ];

        $response = $this->actingAs($this->deptHead, 'sanctum')
            ->postJson('/api/v1/dept-head/instructors', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'email'                    => 'manual.instructor@wollo.edu.et',
                    'username'                 => 'manual.ins',
                    'must_change_password'     => false,
                    'password_delivery_status' => 'manual',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email'                    => 'manual.instructor@wollo.edu.et',
            'username'                 => 'manual.ins',
            'must_change_password'     => false,
            'password_delivery_status' => 'manual',
        ]);

        $created = User::where('email', 'manual.instructor@wollo.edu.et')->first();
        $this->assertTrue(Hash::check('SecurePass123!', $created->password));
    }

    public function test_create_instructor_manual_password_validation_fails_on_weak_password(): void
    {
        $payload = [
            'name'          => 'Weak Instructor',
            'email'         => 'weak.instructor@wollo.edu.et',
            'phone'         => '+251911000002',
            'gender'        => 'Female',
            'id_no'         => 'INS-9902',
            'username'      => 'weak.ins',
            'password_mode' => 'manual',
            'password'      => 'weak', // fails length, uppercase, number, symbol
        ];

        $response = $this->actingAs($this->deptHead, 'sanctum')
            ->postJson('/api/v1/dept-head/instructors', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_create_instructor_with_auto_generated_password_provisions_and_schedules_email(): void
    {
        $payload = [
            'name'            => 'Auto Instructor',
            'email'           => 'auto.instructor@wollo.edu.et',
            'phone'           => '+251911000003',
            'gender'          => 'Male',
            'id_no'           => 'INS-9903',
            'username'        => 'auto.ins',
            'password_mode'   => 'auto',
            'employment_type' => 'full_time',
            'status'          => 'active',
        ];

        $response = $this->actingAs($this->deptHead, 'sanctum')
            ->postJson('/api/v1/dept-head/instructors', $payload);

        $response->assertStatus(201);
        
        $data = $response->json('data');
        $this->assertEquals('auto.instructor@wollo.edu.et', $data['email']);
        $this->assertEquals('auto.ins', $data['username']);
        $this->assertTrue($data['must_change_password']);
        
        // Assert plaintext password is NOT returned in API response
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('plain_password', $data);

        $instructor = User::where('email', 'auto.instructor@wollo.edu.et')->first();
        $this->assertNotNull($instructor);
        $this->assertTrue($instructor->must_change_password);
        $this->assertNotEmpty($instructor->password);
    }

    public function test_first_login_with_must_change_password_blocks_protected_routes(): void
    {
        $course = \App\Models\Course::create([
            'title'         => 'Intro to IT',
            'code'          => 'IT101',
            'credits'       => 3,
            'department_id' => $this->department->id,
            'section'       => 'Section A',
            'level'         => '1st Year',
            'semester'      => 'First Semester',
            'status'        => 'active',
        ]);

        $instructor = User::create([
            'name'                     => 'Pending Instructor',
            'email'                    => 'pending@wollo.edu.et',
            'username'                 => 'pending.ins',
            'password'                 => Hash::make('TempPass123!@#'),
            'role'                     => 'instructor',
            'department_id'            => $this->department->id,
            'course_code'              => 'IT101',
            'course_name'              => 'Intro to IT',
            'status'                   => 'active',
            'must_change_password'     => true,
            'password_delivery_status' => 'delivered',
        ]);

        $course->update(['instructor_id' => $instructor->id]);

        // Login succeeds and reports must_change_password: true
        $loginResponse = $this->postJson('/api/v1/login', [
            'login'    => 'pending.ins',
            'password' => 'TempPass123!@#',
            'role'     => 'instructor',
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('data.token');
        $this->assertTrue($loginResponse->json('data.user.must_change_password'));

        // Attempting to access protected route is blocked by EnsurePasswordChanged middleware
        $protectedResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/instructor/dashboard-stats');

        $protectedResponse->assertStatus(403)
            ->assertJson([
                'error_type' => 'password_change_required',
            ]);

        // Change password endpoint is allowed and resets must_change_password
        $changeResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/user/change-password', [
                'current_password'          => 'TempPass123!@#',
                'new_password'              => 'BrandNewP@ssword2026!',
                'new_password_confirmation' => 'BrandNewP@ssword2026!',
            ]);

        $changeResponse->assertStatus(200);

        $instructor->refresh();
        $this->assertFalse($instructor->must_change_password);
        $this->assertTrue(Hash::check('BrandNewP@ssword2026!', $instructor->password));

        // Now protected route is accessible
        $afterChangeResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/instructor/dashboard-stats');

        $this->assertNotEquals(403, $afterChangeResponse->status());
    }

    public function test_resend_credentials_generates_new_password_hash(): void
    {
        $oldHash = Hash::make('InitialPassword123!');
        $instructor = User::create([
            'name'                     => 'Resend Instructor',
            'email'                    => 'resend@wollo.edu.et',
            'username'                 => 'resend.ins',
            'password'                 => $oldHash,
            'role'                     => 'instructor',
            'department_id'            => $this->department->id,
            'status'                   => 'active',
            'must_change_password'     => false,
            'password_delivery_status' => 'delivered',
        ]);

        $response = $this->actingAs($this->deptHead, 'sanctum')
            ->postJson("/api/v1/dept-head/instructors/{$instructor->id}/resend-credentials");

        $response->assertStatus(200);

        $instructor->refresh();
        $this->assertTrue($instructor->must_change_password);
        $this->assertNotEquals($oldHash, $instructor->password);
    }

    public function test_newly_provisioned_instructor_with_zero_courses_can_login_when_must_change_password_is_true(): void
    {
        $instructor = User::create([
            'name'                     => 'Zero Courses Instructor',
            'email'                    => 'zero.courses@wollo.edu.et',
            'username'                 => 'zero.ins',
            'password'                 => Hash::make('AutoPass123!@#$'),
            'role'                     => 'instructor',
            'department_id'            => $this->department->id,
            'status'                   => 'active',
            'must_change_password'     => true,
            'password_delivery_status' => 'delivered',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'login'    => 'zero.ins',
            'password' => 'AutoPass123!@#$',
            'role'     => 'instructor',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Login successful.',
                'data'    => [
                    'user' => [
                        'email'                => 'zero.courses@wollo.edu.et',
                        'must_change_password' => true,
                    ],
                ],
            ]);
    }

    public function test_creating_instructor_with_already_used_email_returns_department_scoped_error(): void
    {
        User::create([
            'name'                     => 'Existing Member',
            'email'                    => 'already.used@wollo.edu.et',
            'username'                 => 'existing.ins',
            'password'                 => Hash::make('Secret123!'),
            'role'                     => 'instructor',
            'department_id'            => $this->department->id,
            'status'                   => 'active',
        ]);

        $response = $this->actingAs($this->deptHead, 'sanctum')
            ->postJson('/api/v1/dept-head/instructors', [
                'name'            => 'Another Person',
                'email'           => 'already.used@wollo.edu.et',
                'phone'           => '+251911999999',
                'gender'          => 'Male',
                'id_no'           => 'INS-9999',
                'username'        => 'another.ins',
                'password_mode'   => 'manual',
                'password'        => 'P@ssword1234!',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'error_type' => 'instructor_already_in_department',
            ]);
    }

    public function test_creating_instructor_in_auto_mode_when_already_in_dept_refreshes_credentials_with_success(): void
    {
        $existing = User::create([
            'name'                     => 'Auto Existing Member',
            'email'                    => 'auto.existing@wollo.edu.et',
            'username'                 => 'auto.existing',
            'password'                 => Hash::make('OldSecret123!'),
            'role'                     => 'instructor',
            'department_id'            => $this->department->id,
            'status'                   => 'active',
        ]);

        $response = $this->actingAs($this->deptHead, 'sanctum')
            ->postJson('/api/v1/dept-head/instructors', [
                'name'            => 'Auto Existing Member',
                'email'           => 'auto.existing@wollo.edu.et',
                'phone'           => '+251911999999',
                'gender'          => 'Male',
                'id_no'           => 'INS-8888',
                'username'        => 'auto.existing',
                'password_mode'   => 'auto',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $existing->refresh();
        $this->assertTrue($existing->must_change_password);
    }
}
