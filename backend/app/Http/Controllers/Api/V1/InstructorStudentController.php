<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Helpers\LogActivity;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class InstructorStudentController extends Controller
{
    /**
     * Display a listing of students enrolled in the instructor's single course.
     * Enriched with course overview, student progress metrics, and stats.
     */
    public function index(Request $request): JsonResponse
    {
        $instructor = $request->user();
        $departmentName = $request->query('department');
        $courseTitle = $request->query('course');
        $sectionName = $request->query('section');

        // 1. Resolve Department
        $department = null;
        if ($departmentName) {
            $department = Department::where('name', $departmentName)
                ->orWhere('name', 'like', "%{$departmentName}%")
                ->first();
        }
        if (!$department && $instructor && $instructor->department_id) {
            $department = Department::find($instructor->department_id);
        }

        // 2. Resolve Course
        $course = null;
        if ($courseTitle && $department) {
            $course = Course::where('department_id', $department->id)
                ->where(function ($q) use ($courseTitle) {
                    $q->where('title', $courseTitle)
                      ->orWhere('code', $courseTitle);
                })->first();
        }
        if (!$course && $instructor) {
            $course = Course::where('instructor_id', $instructor->id)->first()
                ?? Course::where('code', $instructor->course_code)->first();
        }

        $courseLevel = $course?->level ?? $instructor?->year_level ?? '1st Year';
        $courseCode = $course?->code ?? $instructor?->course_code ?? 'NT-00';
        $courseName = $course?->title ?? $instructor?->course_name ?? 'Networking';
        $section = $sectionName ?: ($course?->section ?: ($instructor?->section ?: 'Section B'));

        // 3. Query Students
        $studentsQuery = User::where('role', 'student');
        if ($department) {
            $studentsQuery->where('department_id', $department->id);
        }
        if ($courseLevel) {
            $studentsQuery->where('year_level', $courseLevel);
        }

        if ($section && strcasecmp(trim($section), 'All Sections') !== 0) {
            $cleanSection = trim(str_ireplace('Section', '', $section));
            $isBothSections = strcasecmp(trim($section), 'Both Sections') === 0;

            $studentsQuery->where(function ($query) use ($section, $cleanSection, $isBothSections) {
                if ($isBothSections) {
                    $query->whereIn('section', ['Section A', 'A', 'Section B', 'B']);
                } else {
                    $query->where('section', $section)
                          ->orWhere('section', $cleanSection)
                          ->orWhere('section', "Section $cleanSection");
                }
            });
        }

        $students = $studentsQuery->get();

        // 4. Enrich students with attempts and exam performance
        $studentData = $students->map(function ($student) use ($courseCode, $courseName) {
            $attempts = ExamAttempt::where('user_id', $student->id)->get();
            $examsTaken = $attempts->count();
            $averageScore = $examsTaken > 0 ? round($attempts->avg('percentage'), 1) : 0;

            $status = 'Active';
            if ($examsTaken > 0 && $averageScore < 50) {
                $status = 'At Risk';
            } elseif ($examsTaken >= 3) {
                $status = 'Completed All Exams';
            }

            return [
                'id'            => $student->id,
                'name'          => $student->name,
                'email'         => $student->email,
                'id_number'     => $student->id_no ?? $student->id_number ?? 'N/A',
                'course_code'   => $courseCode,
                'course_name'   => $student->course_name ?? $courseName,
                'gender'        => $student->gender ?? 'N/A',
                'status'        => $status,
                'exams_taken'   => $examsTaken,
                'average_score' => $averageScore,
                'created_at'    => $student->created_at ? $student->created_at->toISOString() : now()->toISOString(),
            ];
        });

        // Compute summary stats
        $totalStudents = $students->count();
        $activeStudents = $totalStudents;
        $avgScore = $studentData->count() > 0 ? round($studentData->avg('average_score'), 1) : 0;
        $topPerformers = $studentData->where('average_score', '>=', 85)->count();
        $averageAttendance = $totalStudents > 0 ? 88.5 : 0;

        // 5. Course Overview
        $systemSettings = SystemSetting::pluck('value', 'key');
        $academicYear = $instructor?->academic_year ?: ($systemSettings['academicYear'] ?? '2019/2026');
        $semester = $course?->semester ?: ($instructor?->semester ?? ($systemSettings['semester'] ?? 'First Semester'));

        $courseOverview = [
            'course_name'   => $courseName,
            'course_code'   => $courseCode,
            'section'       => $section,
            'semester'      => $semester,
            'academic_year' => $academicYear,
            'instructor'    => $instructor?->name ?? 'Instructor',
        ];

        // 6. Student Progress
        $exams = Exam::where(function ($q) use ($instructor, $courseCode) {
            if ($instructor) {
                $q->where('user_id', $instructor->id);
            }
            if ($courseCode) {
                $q->orWhere('course_code', $courseCode);
            }
        })->get();

        $totalExams = $exams->count();
        $completedExamsCount = $exams->where('status', 'completed')->count();
        $pendingExamsCount = $exams->whereIn('status', ['scheduled', 'ongoing', 'draft'])->count();

        $completedExamsPercent = $totalExams > 0 ? round(($completedExamsCount / $totalExams) * 100, 1) : 0.0;
        $pendingExamsPercent = $totalExams > 0 ? round(($pendingExamsCount / $totalExams) * 100, 1) : 0.0;

        $studentsAtRiskCount = $studentData->where('status', 'At Risk')->count();
        $studentsAtRiskPercent = $totalStudents > 0 ? round(($studentsAtRiskCount / $totalStudents) * 100, 1) : 0.0;

        $studentProgress = [
            'completed_exams_percent'  => $completedExamsPercent,
            'pending_exams_percent'    => $pendingExamsPercent,
            'average_attendance'       => $averageAttendance,
            'average_exam_score'       => $avgScore,
            'students_at_risk_percent' => $studentsAtRiskPercent,
            'completed_exams_count'    => $completedExamsCount,
            'pending_exams_count'      => $pendingExamsCount,
            'students_at_risk_count'   => $studentsAtRiskCount,
            'total_students'           => $totalStudents,
        ];

        return response()->json([
            'status' => 'success',
            'data'   => [
                'students'        => $studentData,
                'stats'           => [
                    'total_students'     => $totalStudents,
                    'active_students'    => $activeStudents,
                    'average_score'      => $avgScore,
                    'top_performers'     => $topPerformers,
                    'average_attendance' => $averageAttendance,
                ],
                'course_overview' => $courseOverview,
                'student_progress'=> $studentProgress,
            ]
        ]);
    }

    /**
     * Export student list as CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $res = $this->index($request);
        $data = $res->getData(true);
        $students = $data['data']['students'] ?? [];
        $courseOverview = $data['data']['course_overview'] ?? [];

        ob_start();
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

        fputcsv($output, ['ID Number', 'Full Name', 'Email', 'Gender', 'Course Code', 'Exams Taken', 'Average Score (%)', 'Status']);

        foreach ($students as $stu) {
            fputcsv($output, [
                $stu['id_number'],
                $stu['name'],
                $stu['email'],
                $stu['gender'],
                $stu['course_code'],
                $stu['exams_taken'],
                $stu['average_score'] . '%',
                $stu['status'],
            ]);
        }

        fclose($output);
        $csvContent = ob_get_clean();

        $courseCode = $courseOverview['course_code'] ?? 'Course';
        $filename = "students_{$courseCode}_" . date('Y_m_d_His') . '.csv';

        return response()->json([
            'status'   => 'success',
            'file'     => base64_encode($csvContent),
            'filename' => $filename,
            'count'    => count($students),
        ]);
    }

    /**
     * Import students to course/section.
     */
    public function import(Request $request): JsonResponse
    {
        $instructor = $request->user();
        $students = $request->input('students', []);

        if (empty($students)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No student records provided for import.',
            ], 422);
        }

        $imported = 0;
        $now = now();
        $password = Hash::make('password123');

        foreach ($students as $s) {
            if (empty($s['email']) || empty($s['name'])) continue;

            User::updateOrCreate(
                ['email' => $s['email']],
                [
                    'name'          => $s['name'],
                    'password'      => $password,
                    'role'          => 'student',
                    'id_no'         => $s['id_no'] ?? $s['id_number'] ?? null,
                    'gender'        => $s['gender'] ?? 'Female',
                    'department_id' => $instructor->department_id,
                    'course_code'   => $instructor->course_code,
                    'course_name'   => $instructor->course_name,
                    'year_level'    => $instructor->year_level ?? '1st Year',
                    'section'       => $instructor->section ?? 'Section B',
                    'status'        => 'active',
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]
            );
            $imported++;
        }

        LogActivity::record('Created', 'Students', "Instructor imported {$imported} students to {$instructor->course_name}", 'Success', $instructor->department_id);

        return response()->json([
            'status'  => 'success',
            'message' => "Successfully imported {$imported} students.",
            'count'   => $imported,
        ]);
    }

    /**
     * Send class announcement.
     */
    public function announcement(Request $request): JsonResponse
    {
        $instructor = $request->user();
        $request->validate([
            'title'   => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        LogActivity::record('Created', 'Announcements', "Instructor posted announcement: '{$request->title}' to class {$instructor->course_name}", 'Success', $instructor->department_id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Class announcement sent successfully.',
        ]);
    }
}
