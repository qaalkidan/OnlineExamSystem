<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentExamScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_student_schedule(): void
    {
        $response = $this->getJson('/api/v1/student/schedule');
        $response->assertStatus(401);
    }

    public function test_student_can_only_see_published_or_scheduled_exams_for_their_department_and_section(): void
    {
        // 1. Create Departments
        $deptA = Department::create([
            'name'    => 'Computer Science',
            'code'    => 'CS',
            'college' => 'Computing',
        ]);
        $deptB = Department::create([
            'name'    => 'Electrical Engineering',
            'code'    => 'EE',
            'college' => 'Engineering',
        ]);

        // 2. Create Instructors and Student
        $instructorA = User::factory()->create([
            'role'          => 'instructor',
            'department_id' => $deptA->id,
            'year_level'    => '2nd Year',
        ]);

        $instructorB = User::factory()->create([
            'role'          => 'instructor',
            'department_id' => $deptB->id,
            'year_level'    => '2nd Year',
        ]);

        $studentA = User::factory()->create([
            'role'          => 'student',
            'department_id' => $deptA->id,
            'year_level'    => '2nd Year',
            'section'       => 'Section A',
        ]);

        // 3. Create Courses
        $courseA = Course::create([
            'department_id' => $deptA->id,
            'code'          => 'CS201',
            'title'         => 'Data Structures',
            'credits'       => 3,
            'level'         => '2nd Year',
            'semester'      => 'First Semester',
            'section'       => 'Section A',
        ]);

        $courseB = Course::create([
            'department_id' => $deptB->id,
            'code'          => 'EE201',
            'title'         => 'Circuits',
            'credits'       => 3,
            'level'         => '2nd Year',
            'semester'      => 'First Semester',
            'section'       => 'Section A',
        ]);

        // 4. Create Exams
        // Exam 1: Published in Dept A, Section A (Should be visible to Student A)
        $examVisible = Exam::create([
            'user_id'          => $instructorA->id,
            'title'            => 'Data Structures Midterm',
            'course_code'      => $courseA->code,
            'course_name'      => $courseA->title,
            'section'          => 'Section A',
            'duration_minutes' => 60,
            'total_marks'      => 50,
            'status'           => 'published',
            'scheduled_at'     => now()->addDays(2),
            'settings'         => [
                'exam_type' => 'midterm',
                'semester'  => 'First Semester',
                'section'   => 'Section A',
            ],
        ]);

        // Exam 2: Draft in Dept A, Section A (NEVER visible to student)
        $examDraft = Exam::create([
            'user_id'          => $instructorA->id,
            'title'            => 'Data Structures Final (Draft)',
            'course_code'      => $courseA->code,
            'course_name'      => $courseA->title,
            'section'          => 'Section A',
            'duration_minutes' => 120,
            'total_marks'      => 100,
            'status'           => 'draft',
            'scheduled_at'     => now()->addDays(10),
            'settings'         => [
                'exam_type' => 'final',
                'semester'  => 'First Semester',
            ],
        ]);

        // Exam 3: Scheduled in Dept A, Section B (Different section - Student A should NOT see)
        $examWrongSection = Exam::create([
            'user_id'          => $instructorA->id,
            'title'            => 'Data Structures Section B Only',
            'course_code'      => $courseA->code,
            'course_name'      => $courseA->title,
            'section'          => 'Section B',
            'duration_minutes' => 60,
            'total_marks'      => 50,
            'status'           => 'scheduled',
            'scheduled_at'     => now()->addDays(3),
            'settings'         => [
                'section' => 'Section B',
            ],
        ]);

        // Exam 4: In Dept B (Different department - Student A should NOT see)
        $examWrongDept = Exam::create([
            'user_id'          => $instructorB->id,
            'title'            => 'Circuits Midterm',
            'course_code'      => $courseB->code,
            'course_name'      => $courseB->title,
            'section'          => 'Section A',
            'duration_minutes' => 60,
            'total_marks'      => 50,
            'status'           => 'published',
            'scheduled_at'     => now()->addDays(2),
            'settings'         => [
                'section' => 'Section A',
            ],
        ]);

        // Query /api/v1/student/schedule as Student A
        $response = $this->actingAs($studentA)->getJson('/api/v1/student/schedule');
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertEquals('success', $json['status']);

        $returnedIds = collect($json['data'])->pluck('id')->toArray();

        // Must include visible exam
        $this->assertContains($examVisible->id, $returnedIds);

        // Must NOT include draft
        $this->assertNotContains($examDraft->id, $returnedIds);

        // Must NOT include wrong section
        $this->assertNotContains($examWrongSection->id, $returnedIds);

        // Must NOT include wrong department
        $this->assertNotContains($examWrongDept->id, $returnedIds);

        // Single detail endpoint tests
        $detailSuccess = $this->actingAs($studentA)->getJson("/api/v1/student/schedule/{$examVisible->id}");
        $detailSuccess->assertStatus(200);
        $this->assertEquals($examVisible->title, $detailSuccess->json('data.title'));

        // Accessing wrong dept or draft should return 404
        $detailDeniedDraft = $this->actingAs($studentA)->getJson("/api/v1/student/schedule/{$examDraft->id}");
        $detailDeniedDraft->assertStatus(404);

        $detailDeniedDept = $this->actingAs($studentA)->getJson("/api/v1/student/schedule/{$examWrongDept->id}");
        $detailDeniedDept->assertStatus(404);

        // 5. Test Filters
        // Search filter matching
        $searchRes = $this->actingAs($studentA)->getJson('/api/v1/student/schedule?search=Structures');
        $searchRes->assertStatus(200);
        $this->assertCount(1, $searchRes->json('data'));

        // Search filter non-matching
        $searchEmpty = $this->actingAs($studentA)->getJson('/api/v1/student/schedule?search=NonExistentSubject');
        $searchEmpty->assertStatus(200);
        $this->assertCount(0, $searchEmpty->json('data'));

        // Course filter
        $courseRes = $this->actingAs($studentA)->getJson('/api/v1/student/schedule?course=CS201');
        $courseRes->assertStatus(200);
        $this->assertCount(1, $courseRes->json('data'));

        // Semester filter
        $semRes = $this->actingAs($studentA)->getJson('/api/v1/student/schedule?semester=First+Semester');
        $semRes->assertStatus(200);
        $this->assertCount(1, $semRes->json('data'));

        // 6. Test Department Head publishing previously drafted exam
        $examDraft->update(['status' => 'published']);
        $afterPublishRes = $this->actingAs($studentA)->getJson('/api/v1/student/schedule');
        $afterPublishRes->assertStatus(200);
        $newReturnedIds = collect($afterPublishRes->json('data'))->pluck('id')->toArray();
        $this->assertContains($examDraft->id, $newReturnedIds);

        // 7. Test Cancelled Exam Visibility
        $examVisible->update([
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => 'Severe weather reschedule',
        ]);
        $cancelledRes = $this->actingAs($studentA)->getJson("/api/v1/student/schedule/{$examVisible->id}");
        $cancelledRes->assertStatus(200);
        $this->assertEquals('Cancelled', $cancelledRes->json('data.status'));
    }
}
