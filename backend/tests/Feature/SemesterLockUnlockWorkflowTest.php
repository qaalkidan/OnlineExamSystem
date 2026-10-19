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

        // Verify that instructor tokens are revoked upon lock
        $this->assertEquals(0, \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $this->instructor->id)->count());

        // 4. When locked, instructor trying to log in is blocked with 403 Access Locked notification
        $loginLockedRes = $this->postJson('/api/v1/login', [
            'login'    => $this->instructor->email,
            'password' => 'password123',
            'role'     => 'instructor',
        ]);

        $loginLockedRes->assertStatus(403)
            ->assertJson([
                'error_type' => 'semester_locked',
                'locked'     => true,
            ]);

        $this->assertStringContainsString('Access Locked: The Department Head has closed access to your', $loginLockedRes->json('message'));

        // 5. Instructor attempts a mutation request: blocked by InstructorSemesterLockMiddleware with exact message
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

        // 6. Department Head reopens the semester (Unlock)
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

        // 7. When reopened, instructor CAN log in successfully
        $loginReopenedRes = $this->postJson('/api/v1/login', [
            'login'    => $this->instructor->email,
            'password' => 'password123',
            'role'     => 'instructor',
        ]);

        $loginReopenedRes->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'token',
                ]
            ]);

        // 8. Instructor workspace access is restored (not blocked by 403 lock)
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

    public function test_dept_head_locking_own_course_submission_does_not_sign_out_dept_head(): void
    {
        // 1. Create a course where the instructor is the Department Head (e.g. Fitsum Gashaw)
        $headCourse = Course::create([
            'title'         => 'System Architecture',
            'code'          => 'CS501',
            'credit_hours'  => 4,
            'department_id' => $this->department->id,
            'instructor_id' => $this->deptHead->id,
            'academic_year' => '2025/2026',
            'semester'      => 'Second Semester',
            'status'        => 'active',
        ]);

        // 2. Create an approved submission for this course
        $headSubmission = SemesterSubmission::create([
            'instructor_id' => $this->deptHead->id,
            'academic_year' => '2025/2026',
            'semester'      => 'Second Semester',
            'department'    => $this->department->name,
            'section'       => 'Section A',
            'status'        => 'approved',
            'submitted_at'  => now(),
            'approved_at'   => now(),
            'approved_by'   => $this->deptHead->id,
            'locked_at'     => null,
        ]);

        // 3. Create a Dept Head token and an Instructor token for this dual-role user
        $deptHeadTokenObj = $this->deptHead->createToken('auth_token:dept_head', ['role:dept_head', '*']);
        $deptHeadToken = $deptHeadTokenObj->plainTextToken;

        $instTokenObj = $this->deptHead->createToken('auth_token:instructor', ['role:instructor', '*']);
        $instToken = $instTokenObj->plainTextToken;

        $this->assertEquals(2, $this->deptHead->tokens()->count());

        // 4. Dept Head closes/locks their own submission using the Dept Head bearer token
        $lockRes = $this->withHeader('Authorization', "Bearer {$deptHeadToken}")
            ->putJson("/api/v1/dept-head/semester-submissions/{$headSubmission->id}/lock");

        $lockRes->assertOk()
            ->assertJson([
                'submission' => [
                    'is_locked' => true,
                    'status'    => 'Approved',
                ]
            ]);

        $headSubmission->refresh();
        $this->assertTrue($headSubmission->isLocked());

        // 5. Dept Head token MUST NOT be deleted, while instructor token MUST be deleted
        $remainingTokens = $this->deptHead->tokens()->get();
        $this->assertTrue($remainingTokens->contains('id', $deptHeadTokenObj->accessToken->id), 'Dept Head token must be preserved.');
        $this->assertFalse($remainingTokens->contains('id', $instTokenObj->accessToken->id), 'Instructor token must be deleted.');

        // 6. Dept Head session remains completely valid and can access Dept Head endpoints
        $activityRes = $this->withHeader('Authorization', "Bearer {$deptHeadToken}")
            ->getJson('/api/v1/dept-head/activity-logs/unread-count');
        $activityRes->assertOk();

        // 7. When attempting to log in as instructor, access is blocked with 403 semester_locked
        $loginInstRes = $this->postJson('/api/v1/login', [
            'login'    => $this->deptHead->email,
            'password' => 'password123',
            'role'     => 'instructor',
        ]);
        $loginInstRes->assertStatus(403)
            ->assertJson([
                'error_type' => 'semester_locked',
                'locked'     => true,
            ]);

        // 8. Dept Head logging in via Staff tab succeeds without being blocked
        $loginStaffRes = $this->postJson('/api/v1/login', [
            'login'    => $this->deptHead->email,
            'password' => 'password123',
            'role'     => 'staff',
        ]);
        $loginStaffRes->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'token',
                ]
            ]);
    }

    public function test_cannot_request_correction_or_reject_when_semester_is_locked(): void
    {
        // 1. Create an approved and locked submission
        $lockedSubmission = SemesterSubmission::create([
            'instructor_id' => $this->instructor->id,
            'academic_year' => '2025/2026',
            'semester'      => 'Second Semester',
            'department'    => $this->department->name,
            'section'       => 'Section A',
            'status'        => 'approved',
            'submitted_at'  => now(),
            'approved_at'   => now(),
            'approved_by'   => $this->deptHead->id,
            'locked_at'     => now(),
        ]);

        $this->assertTrue($lockedSubmission->isLocked());

        Sanctum::actingAs($this->deptHead);

        // 2. Attempting to request correction fails with 422
        $correctionRes = $this->putJson("/api/v1/dept-head/semester-submissions/{$lockedSubmission->id}/status", [
            'status'  => 'correction_required',
            'remarks' => 'Please adjust final grades',
        ]);

        $correctionRes->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot request correction or reject while the semester workspace is closed and locked. Please reopen the semester first.'
            ]);

        // 3. Attempting to reject fails with 422
        $rejectRes = $this->putJson("/api/v1/dept-head/semester-submissions/{$lockedSubmission->id}/status", [
            'status'  => 'rejected',
            'remarks' => 'Missing core documentation',
        ]);

        $rejectRes->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot request correction or reject while the semester workspace is closed and locked. Please reopen the semester first.'
            ]);

        // State remains approved and locked
        $lockedSubmission->refresh();
        $this->assertEquals('approved', $lockedSubmission->status);
        $this->assertTrue($lockedSubmission->isLocked());
    }
}


