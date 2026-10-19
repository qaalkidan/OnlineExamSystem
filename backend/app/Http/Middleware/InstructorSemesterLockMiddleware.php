<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\SemesterSubmission;

class InstructorSemesterLockMiddleware
{
    /**
     * Handle an incoming request.
     * Enforce strict read-only lock for instructors whose semester submission
     * is in 'submitted' or 'approved' status.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Always allow querying lock status so instructor frontend can detect status
        if ($request->is('*semester-lock-status') || $request->is('*semester-submission/lock-status')) {
            return $next($request);
        }

        // 2. Allow submitting the semester submission itself if not already approved
        if ($request->is('*semester-submission/submit')) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        // 3. Super admins and department heads managing the department have unrestricted access
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Check if an exam is being modified to target its specific course/semester scope
        $examId = $request->route('exam') ?? $request->route('id');
        $academicYear = null;
        $semester = null;

        if ($examId && is_numeric($examId)) {
            $exam = \App\Models\Exam::find($examId);
            if ($exam && $exam->course_code) {
                $course = \App\Models\Course::where('code', $exam->course_code)->first();
                if ($course && $course->semester) {
                    $semester = $course->semester;
                }
            }
        }

        if (!$academicYear || !$semester) {
            $sysSettings = \App\Models\SystemSetting::pluck('value', 'key')->toArray();
            $academicYear = $user->academic_year ?? ($sysSettings['academicYear'] ?? '2025/2026');
            $semester = $user->semester ?? ($sysSettings['semester'] ?? 'Second Semester');
        }

        // 4. Query current submission status for this instructor in this specific course-semester scope
        $submission = SemesterSubmission::where('instructor_id', $user->id)
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->latest('updated_at')
            ->first();

        if (!$submission) {
            $submission = SemesterSubmission::where('instructor_id', $user->id)
                ->latest('updated_at')
                ->first();
        }

        // 5. If locked, reject with 403 Forbidden
        if ($submission && $submission->isLocked()) {
            $courseTitle = 'Course';
            if (isset($course) && $course && $course->title) {
                $courseTitle = $course->title;
            } else {
                $assigned = \App\Models\Course::where('instructor_id', $user->id)->first();
                if ($assigned && $assigned->title) {
                    $courseTitle = $assigned->title;
                }
            }

            $msg = "Semester Closed: The Department Head has closed access to your {$courseTitle} semester workspace. Please contact your Department Head if you need access restored.";

            return response()->json([
                'message'      => $msg,
                'error'        => $msg,
                'locked'       => true,
                'course_title' => $courseTitle,
                'status'       => $submission->status,
                'submission'   => [
                    'id'            => $submission->id,
                    'status'        => $submission->status,
                    'academic_year' => $submission->academic_year,
                    'semester'      => $submission->semester,
                    'submitted_at'  => $submission->submitted_at?->format('M d, Y • h:i A'),
                    'approved_at'   => $submission->approved_at?->format('M d, Y • h:i A'),
                    'locked_at'     => $submission->locked_at?->format('M d, Y • h:i A'),
                ]
            ], 403);
        }

        return $next($request);
    }
}
