<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Department;
use App\Models\Course;
use App\Models\SemesterSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class SemesterLockUnlockWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $deptHead;
    protected User $instructor;
    protected Department $department;
    protected Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name'        => 'Computer Science',
            'code'        => 'CS',
            'description' => 'Computer Science Department',
            'status'      => 'active',
        ]);

        $this->deptHead = User::create([
            'name'          => 'CS Dept Head',
            'email'         => 'depthead@wollo.edu.et',
            'password'      => bcrypt('password123'),
            'role'          => 'dept_head',
            'department_id' => $this->department->id,
            'status'        => 'active',
        ]);

        $this->instructor = User::create([
            'name'          => 'Dr. Instructor',
            'email'         => 'instructor@wollo.edu.et',
            'password'      => bcrypt('password123'),
            'role'          => 'instructor',
            'department_id' => $this->department->id,
            'academic_year' => '2025/2026',
            'semester'      => 'Second Semester',
            'status'        => 'active',
        ]);

        $this->course = Course::create([
            'title'         => 'Advanced Software Engineering',
            'code'          => 'CS401',
            'credit_hours'  => 4,
            'department_id' => $this->department->id,
            'instructor_id' => $this->instructor->id,
            'academic_year' => '2025/2026',
            'semester'      => 'Second Semester',
            'status'        => 'active',
        ]);
    }

    public function test_cannot_close_and_lock_before_approval(): void
    {
        $submission = SemesterSubmission::create([
            'instructor_id' => $this->instructor->id,
            'academic_year' => '2025/2026',
            'semester'      => 'Second Semester',
            'department'    => $this->department->name,
            'section'       => 'Section A',
            'status'        => 'pending',
            'submitted_at'  => now(),
            'locked_at'     => null,
            'approved_at'   => null,
        ]);

        Sanctum::actingAs($this->deptHead);

        $response = $this->putJson("/api/v1/dept-head/semester-submissions/{$submission->id}/lock");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot close and lock semester: submission must be approved first.'
            ]);

        $submission->refresh();
        $this->assertNull($submission->locked_at);
        $this->assertFalse($submission->isLocked());
    }

    public function test_approve_and_lock_end_to_end_workflow(): void
    {
        $submission = SemesterSubmission::create([
            'instructor_id' => $this->instructor->id,
            'academic_year' => '2025/2026',
            'semester'      => 'Second Semester',
            'department'    => $this->department->name,
            'section'       => 'Section A',
            'status'        => 'pending',
            'submitted_at'  => now(),
            'locked_at'     => null,
            'approved_at'   => null,
        ]);

        // 1. Dept Head Approves & Locks submission
        Sanctum::actingAs($this->deptHead);
        $approveRes = $this->putJson("/api/v1/dept-head/semester-submissions/{$submission->id}/status", [
            'status'        => 'approved',
            'academic_year' => '2025/2026',
            'semester'      => 'Second Semester',
        ]);

        $approveRes->assertOk();
        $submission->refresh();
        $this->assertEquals('approved', $submission->status);
        $this->assertNotNull($submission->locked_at);
        $this->assertNotNull($submission->approved_at);
        $this->assertTrue($submission->isLocked());

        // 2. Instructor attempts a mutation request: blocked by InstructorSemesterLockMiddleware
        Sanctum::actingAs($this->instructor);
        $mutationRes = $this->postJson('/api/v1/instructor/exams', [
            'title'       => 'Unauthorized Exam Attempt',
            'course_code' => $this->course->code,
        ]);

        $mutationRes->assertStatus(403)
            ->assertJson([
                'locked' => true,
            ]);

        $this->assertStringContainsString('Semester Closed: Your access to the', $mutationRes->json('message'));
        $this->assertStringContainsString('workspace is temporarily restricted by the Department Head', $mutationRes->json('message'));

        // 3. Department Head reopens the semester
        Sanctum::actingAs($this->deptHead);
        $reopenRes = $this->putJson("/api/v1/dept-head/semester-submissions/{$submission->id}/reopen", [
            'reason' => 'Grade revision authorized by department head.',
        ]);

        $reopenRes->assertOk()
            ->assertJson([
                'submission' => [
                    'is_locked' => false,
                    'status'    => 'Reopened',
                ]
            ]);

        $submission->refresh();
        $this->assertEquals('reopened', $submission->status);
        $this->assertNull($submission->locked_at);
        $this->assertFalse($submission->isLocked());

        // 4. Department Head closes and locks again after reopen
        $lockAgainRes = $this->putJson("/api/v1/dept-head/semester-submissions/{$submission->id}/lock", [
            'academic_year' => '2025/2026',
            'semester'      => 'Second Semester',
        ]);

        $lockAgainRes->assertOk()
            ->assertJson([
                'submission' => [
                    'is_locked' => true,
                    'status'    => 'Approved',
                ]
            ]);

        $submission->refresh();
        $this->assertEquals('approved', $submission->status);
        $this->assertNotNull($submission->locked_at);
        $this->assertTrue($submission->isLocked());
    }
}
