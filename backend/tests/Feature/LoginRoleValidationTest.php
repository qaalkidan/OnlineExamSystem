<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRoleValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Department $department;
    protected User $admin;
    protected User $instructor;
    protected User $student;
    protected User $deptHead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name'        => 'Software Engineering',
            'code'        => 'SE',
            'description' => 'Software Engineering Department',
            'status'      => 'active',
        ]);

        $this->admin = User::create([
            'name'          => 'System Administrator',
            'email'         => 'admin@wollo.edu.et',
            'username'      => 'superadmin',
            'password'      => bcrypt('password123'),
            'role'          => 'admin',
            'status'        => 'active',
        ]);

        $this->instructor = User::create([
            'name'          => 'Instructor Abebe',
            'email'         => 'abebe@wollo.edu.et',
            'username'      => 'abebe_inst',
            'password'      => bcrypt('password123'),
            'role'          => 'instructor',
            'department_id' => $this->department->id,
            'status'        => 'active',
        ]);

        // Assign a course so instructor can log in without instructor_not_assigned error
        Course::create([
            'title'         => 'Web Programming',
            'code'          => 'SE201',
            'department_id' => $this->department->id,
            'instructor_id' => $this->instructor->id,
            'status'        => 'active',
            'credit_hours'  => 3,
        ]);

        $this->student = User::create([
            'name'          => 'Student Kebede',
            'email'         => 'kebede@wollo.edu.et',
            'username'      => 'kebede_stud',
            'password'      => bcrypt('password123'),
            'role'          => 'student',
            'department_id' => $this->department->id,
            'status'        => 'active',
        ]);

        $this->deptHead = User::create([
            'name'          => 'Dept Head Fitsum',
            'email'         => 'fitsum@wollo.edu.et',
            'username'      => 'fitsum_head',
            'password'      => bcrypt('password123'),
            'role'          => 'dept_head',
            'department_id' => $this->department->id,
            'status'        => 'active',
        ]);
    }

    public function test_admin_cannot_login_with_student_dropdown(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'login'    => 'admin@wollo.edu.et',
            'password' => 'password123',
            'role'     => 'student',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login']);

        $this->assertStringContainsString('Administrator', $response->json('errors.login.0'));
        $this->assertStringContainsString('Staff', $response->json('errors.login.0'));
    }

    public function test_admin_cannot_login_with_instructor_dropdown(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'login'    => 'admin@wollo.edu.et',
            'password' => 'password123',
            'role'     => 'instructor',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login']);

        $this->assertStringContainsString('Administrator', $response->json('errors.login.0'));
        $this->assertStringContainsString('Staff', $response->json('errors.login.0'));
    }

    public function test_admin_can_login_with_staff_dropdown(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'login'    => 'admin@wollo.edu.et',
            'password' => 'password123',
            'role'     => 'staff',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'user' => [
                        'email' => 'admin@wollo.edu.et',
                        'role'  => 'admin',
                    ],
                ],
            ]);
    }

    public function test_instructor_cannot_login_with_student_dropdown(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'login'    => 'abebe@wollo.edu.et',
            'password' => 'password123',
            'role'     => 'student',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login']);

        $this->assertStringContainsString('Instructor', $response->json('errors.login.0'));
    }

    public function test_instructor_cannot_login_with_staff_dropdown(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'login'    => 'abebe@wollo.edu.et',
            'password' => 'password123',
            'role'     => 'staff',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login']);

        $this->assertStringContainsString('Instructor', $response->json('errors.login.0'));
    }

    public function test_instructor_can_login_with_instructor_dropdown(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'login'    => 'abebe@wollo.edu.et',
            'password' => 'password123',
            'role'     => 'instructor',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'user' => [
                        'email' => 'abebe@wollo.edu.et',
                        'role'  => 'instructor',
                    ],
                ],
            ]);
    }

    public function test_student_cannot_login_with_staff_dropdown(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'login'    => 'kebede@wollo.edu.et',
            'password' => 'password123',
            'role'     => 'staff',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login']);

        $this->assertStringContainsString('Student', $response->json('errors.login.0'));
    }

    public function test_student_cannot_login_with_instructor_dropdown(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'login'    => 'kebede@wollo.edu.et',
            'password' => 'password123',
            'role'     => 'instructor',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login']);

        $this->assertStringContainsString('Student', $response->json('errors.login.0'));
    }

    public function test_student_can_login_with_student_dropdown(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'login'    => 'kebede@wollo.edu.et',
            'password' => 'password123',
            'role'     => 'student',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'user' => [
                        'email' => 'kebede@wollo.edu.et',
                        'role'  => 'student',
                    ],
                ],
            ]);
    }

    public function test_dept_head_cannot_login_with_student_dropdown(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'login'    => 'fitsum@wollo.edu.et',
            'password' => 'password123',
            'role'     => 'student',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login']);

        $this->assertStringContainsString('Department Head', $response->json('errors.login.0'));
    }

    public function test_dept_head_can_login_with_staff_dropdown(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'login'    => 'fitsum@wollo.edu.et',
            'password' => 'password123',
            'role'     => 'staff',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'user' => [
                        'email' => 'fitsum@wollo.edu.et',
                        'role'  => 'dept_head',
                    ],
                ],
            ]);
    }
}
