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
        // 1. Only intercept mutation requests (POST, PUT, PATCH, DELETE)
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        // 2. Allow submitting the semester submission itself
        if ($request->is('*semester-submission/submit')) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        // 3. Only apply lock to instructors (or users acting in instructor role)
        // Super admins and department heads have unrestricted access
        if ($user->role === 'admin' || $user->role === 'dept_head') {
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

        // 5. If locked (status is submitted or approved), reject with 403 Forbidden
        if ($submission && $submission->isLocked()) {
            return response()->json([
                'message' => 'Your semester submission has been completed. Academic modifications are locked for this semester. Please contact the Department Head if a correction is required.',
                'error'   => 'Semester Locked: Academic modifications are unavailable because your semester submission has been submitted/approved.',
                'locked'  => true,
                'status'  => $submission->status,
                'submission' => [
                    'id'            => $submission->id,
                    'status'        => $submission->status,
                    'academic_year' => $submission->academic_year,
                    'semester'      => $submission->semester,
                    'submitted_at'  => $submission->submitted_at?->format('M d, Y • h:i A'),
                    'approved_at'   => $submission->approved_at?->format('M d, Y • h:i A'),
                ]
            ], 403);
        }

        return $next($request);
    }
}
