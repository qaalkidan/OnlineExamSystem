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

    public function test_complete_approval_locking_unlocking_and_instructor_access_lifecycle(): void
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

        // 1. Dept Head Approves submission:
        // Approval must NOT automatically lock the semester (access state remains Open)
        Sanctum::actingAs($this->deptHead);
        $approveRes = $this->putJson("/api/v1/dept-head/semester-submissions/{$submission->id}/status", [
            'status'        => 'approved',
            'academic_year' => '2025/2026',
            'semester'      => 'Second Semester',
        ]);

        $approveRes->assertOk();
        $submission->refresh();
        $this->assertEquals('approved', $submission->status);
        $this->assertNotNull($submission->approved_at);
        $this->assertNull($submission->locked_at, 'Approved semester must remain OPEN until explicitly closed & locked.');
        $this->assertFalse($submission->isLocked(), 'isLocked() must be false after approval.');

        // 2. While approved & open, instructor is NOT locked out
        Sanctum::actingAs($this->instructor);
        $statusRes = $this->getJson('/api/v1/instructor/semester-submission/lock-status');
        $statusRes->assertOk()
            ->assertJsonPath('data.is_locked', false);

        // 3. Dept Head explicitly closes and locks the semester
        Sanctum::actingAs($this->deptHead);
        $lockRes = $this->putJson("/api/v1/dept-head/semester-submissions/{$submission->id}/lock", [
            'academic_year' => '2025/2026',
            'semester'      => 'Second Semester',
        ]);

        $lockRes->assertOk()
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

        // 4. Instructor attempts a mutation request: blocked by InstructorSemesterLockMiddleware with exact message
        Sanctum::actingAs($this->instructor);
        $mutationRes = $this->postJson('/api/v1/instructor/exams', [
            'title'       => 'Unauthorized Exam Attempt',
            'course_code' => $this->course->code,
        ]);

        $mutationRes->assertStatus(403)
            ->assertJson([
                'locked' => true,
            ]);

        $this->assertStringContainsString('Semester Closed: The Department Head has closed access to your', $mutationRes->json('message'));
        $this->assertStringContainsString('semester workspace', $mutationRes->json('message'));

        // 5. Department Head reopens the semester (Unlock)
        Sanctum::actingAs($this->deptHead);
        $reopenRes = $this->putJson("/api/v1/dept-head/semester-submissions/{$submission->id}/reopen", [
            'reason' => 'Grade revision authorized by department head.',
        ]);

        $reopenRes->assertOk()
            ->assertJson([
                'submission' => [
                    'is_locked' => false,
                    'status'    => 'Approved',
                ]
            ]);

        $submission->refresh();
        $this->assertEquals('approved', $submission->status, 'Submission status remains Approved after reopening.');
        $this->assertNull($submission->locked_at, 'locked_at is cleared to null.');
        $this->assertFalse($submission->isLocked(), 'isLocked() returns false after reopening.');
        $this->assertNotNull($submission->reopened_at);

        // 6. Instructor workspace access is restored (not blocked by 403 lock)
        Sanctum::actingAs($this->instructor);
        $statusAfterReopen = $this->getJson('/api/v1/instructor/semester-submission/lock-status');
        $statusAfterReopen->assertOk()
            ->assertJsonPath('data.is_locked', false);

        // 7. Cannot reopen an already unlocked semester
        Sanctum::actingAs($this->deptHead);
        $reopenAgainRes = $this->putJson("/api/v1/dept-head/semester-submissions/{$submission->id}/reopen");
        $reopenAgainRes->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot unlock semester: submission is not currently locked.'
            ]);

        // 8. Department Head can close and lock again after reopening
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
