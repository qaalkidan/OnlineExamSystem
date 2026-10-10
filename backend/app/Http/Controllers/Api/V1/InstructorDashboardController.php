<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InstructorDashboardController extends Controller
{
    /**
     * Return aggregate stats for the authenticated instructor's dashboard.
     * All data is scoped to their single assigned course.
     */
    public function stats(Request $request): JsonResponse
    {
        $instructor = $request->user()->load('department');

        // Scope queries to this instructor's course (or instructor's created exams)
        $baseQuery = Exam::where('user_id', $instructor->id);
        if ($instructor->course_code) {
            $baseQuery->where('course_code', $instructor->course_code);
        }

        $totalExams = (clone $baseQuery)->count();
        $publishedExams = (clone $baseQuery)->where('status', 'published')->count();

        $upcomingExams = (clone $baseQuery)
            ->where(function ($q) {
                $q->where('status', 'scheduled')
                  ->orWhere(function ($sq) {
                      $sq->where('status', 'published')
                         ->whereNotNull('scheduled_at');
                  });
            })
            ->where('scheduled_at', '>', Carbon::now())
            ->count();

        // Total distinct students enrolled in this instructor's course exams
        $examIds = (clone $baseQuery)->pluck('id');

        $totalStudents = DB::table('exam_student')
            ->whereIn('exam_id', $examIds)
            ->distinct('user_id')
            ->count('user_id');

        if ($totalStudents === 0 && $instructor->department_id && $instructor->year_level) {
            $studentQuery = \App\Models\User::where('role', 'student')
                ->where('department_id', $instructor->department_id)
                ->where('year_level', $instructor->year_level);
            if ($instructor->section) {
                $raw = $instructor->section;
                $clean = trim(preg_replace('/^[Ss]ection\s+/i', '', $raw));
                $studentQuery->where(function ($q) use ($raw, $clean) {
                    $q->where('section', $raw)
                      ->orWhere('section', $clean)
                      ->orWhere('section', 'Section ' . $clean);
                });
            }
            $totalStudents = $studentQuery->count();
        }

        // Graded and published attempts
        $attemptsQuery = ExamAttempt::whereIn('exam_id', $examIds)
            ->whereIn('status', ['graded', 'published']);

        $attemptsCount = (clone $attemptsQuery)->count();
        $averageScore = $attemptsCount > 0 ? (clone $attemptsQuery)->avg('percentage') : 0;
        $highestScore = $attemptsCount > 0 ? (clone $attemptsQuery)->max('percentage') : null;
        $lowestScore = $attemptsCount > 0 ? (clone $attemptsQuery)->min('percentage') : null;

        // Performance breakdown by exam (if attempts exist)
        $performanceByExam = Exam::whereIn('id', $examIds)
            ->with(['attempts' => function ($q) {
                $q->whereIn('status', ['graded', 'published']);
            }])
            ->get()
            ->map(function ($exam) {
                $avg = $exam->attempts->avg('percentage');
                return [
                    'exam_id'        => $exam->id,
                    'title'          => $exam->title,
                    'attempts_count' => $exam->attempts->count(),
                    'average_score'  => $avg !== null ? round((float) $avg, 1) : null,
                ];
            })
            ->filter(fn($item) => $item['attempts_count'] > 0)
            ->values();

        return response()->json([
            'data' => [
                'course_code'       => $instructor->course_code,
                'course_name'       => $instructor->course_name,
                'department_name'   => $instructor->department?->name,
                'year_level'        => $instructor->year_level,
                'section'           => $instructor->section,
                'totalExams'        => $totalExams,
                'publishedExams'    => $publishedExams,
                'upcomingExams'     => $upcomingExams,
                'totalStudents'     => $totalStudents,
                'averageScore'      => round((float) $averageScore, 1),
                'highestScore'      => $highestScore !== null ? round((float) $highestScore, 1) : null,
                'lowestScore'       => $lowestScore !== null ? round((float) $lowestScore, 1) : null,
                'attemptsCount'     => $attemptsCount,
                'performanceByExam' => $performanceByExam,
            ]
        ]);
    }

    /**
     * Return the 6 most recently created exams for this instructor's course.
     */
    public function recentExams(Request $request): JsonResponse
    {
        $instructor = $request->user();

        $query = Exam::where('user_id', $instructor->id);
        if ($instructor->course_code) {
            $query->where('course_code', $instructor->course_code);
        }

        $exams = $query->withCount(['students', 'attempts'])
            ->latest()
            ->limit(6)
            ->get();

        return response()->json([
            'data' => $exams->map(fn($exam) => [
                'id'               => $exam->id,
                'title'            => $exam->title,
                'course_code'      => $exam->course_code,
                'course_name'      => $exam->course_name,
                'status'           => $exam->status,
                'duration_minutes' => $exam->duration_minutes,
                'total_marks'      => $exam->total_marks,
                'students_count'   => max((int)$exam->students_count, (int)$exam->attempts_count),
                'attempts_count'   => (int)$exam->attempts_count,
                'scheduled_at'     => $exam->scheduled_at?->toISOString(),
                'created_at'       => $exam->created_at->toISOString(),
            ])
        ]);
    }

    /**
     * Return upcoming scheduled exams for this instructor's course.
     */
    public function upcomingExams(Request $request): JsonResponse
    {
        $instructor = $request->user();

        $query = Exam::where('user_id', $instructor->id);
        if ($instructor->course_code) {
            $query->where('course_code', $instructor->course_code);
        }

        $exams = $query->where(function ($q) {
                $q->where('status', 'scheduled')
                  ->orWhere(function ($sq) {
                      $sq->where('status', 'published')
                         ->whereNotNull('scheduled_at');
                  });
            })
            ->where('scheduled_at', '>', Carbon::now())
            ->withCount(['students', 'attempts'])
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $exams->map(fn($exam) => [
                'id'               => $exam->id,
                'title'            => $exam->title,
                'course_code'      => $exam->course_code,
                'course_name'      => $exam->course_name,
                'status'           => $exam->status,
                'duration_minutes' => $exam->duration_minutes,
                'total_marks'      => $exam->total_marks,
                'students_count'   => max((int)$exam->students_count, (int)$exam->attempts_count),
                'attempts_count'   => (int)$exam->attempts_count,
                'scheduled_at'     => $exam->scheduled_at?->toISOString(),
                'created_at'       => $exam->created_at->toISOString(),
            ])
        ]);
    }
}
