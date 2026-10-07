<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\InstructorDashboardController;
use App\Http\Controllers\Api\V1\InstructorExamController;
use App\Http\Controllers\Api\V1\InstructorQuestionBankController;
use App\Http\Controllers\Api\V1\InstructorStudentController;
use App\Http\Controllers\Api\V1\InstructorReportController;
use App\Http\Controllers\Api\V1\StudentExamController;

/*
|--------------------------------------------------------------------------
| API Routes — Wollo University Online Exam System
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api (configured in bootstrap/app.php)
| and versioned under /v1.
|
*/

// ------------------------------------------------------------------
// Public routes (no auth required)
// ------------------------------------------------------------------
Route::prefix('v1')->group(function () {

    // Auth
    Route::post('/login',  [\App\Http\Controllers\Api\V1\AuthController::class, 'login']);
    Route::post('/register', [\App\Http\Controllers\Api\V1\AuthController::class, 'register']);

});

// ------------------------------------------------------------------
// Protected routes (Sanctum token required)
// ------------------------------------------------------------------
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // Current user info
    Route::get('/user', fn(Request $request) => $request->user()->load('department.head'));

    // Auth — logout & change password
    Route::post('/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
    Route::put('/user/profile', [\App\Http\Controllers\Api\V1\AuthController::class, 'updateProfile']);
    Route::put('/user/change-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'changePassword']);
    Route::post('/user/profile-photo', [\App\Http\Controllers\Api\V1\AuthController::class, 'updateProfilePhoto']);
    Route::delete('/user/profile-photo', [\App\Http\Controllers\Api\V1\AuthController::class, 'removeProfilePhoto']);
    Route::get('/user/me', [\App\Http\Controllers\Api\V1\AuthController::class, 'me']);
    Route::get('/user/activity-logs', [\App\Http\Controllers\Api\V1\AuthController::class, 'activityLogs']);

    // Global settings (accessible by all authenticated users)
    Route::get('/settings', [\App\Http\Controllers\Api\V1\SystemSettingController::class, 'index']);

    // ------------------------------------------------------------------
    // Super Admin Routes
    // ------------------------------------------------------------------
    Route::prefix('admin')->group(function () {
        Route::get('dashboard-stats', [\App\Http\Controllers\Api\V1\AdminDashboardController::class, 'index']);
        Route::get('dashboard/system-status', [\App\Http\Controllers\Api\V1\AdminDashboardController::class, 'systemStatus']);
        Route::get('departments-export', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'export']);
        Route::post('departments/{department}/toggle-status', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'toggleStatus']);
        Route::apiResource('departments', \App\Http\Controllers\Api\V1\DepartmentController::class);
        Route::post('departments/{department}/assign-head', [\App\Http\Controllers\Api\V1\DepartmentController::class, 'assignHead']);
        Route::apiResource('users', \App\Http\Controllers\Api\V1\AdminUserController::class);
        Route::post('users/{user}', [\App\Http\Controllers\Api\V1\AdminUserController::class, 'update']);
        Route::get('users-export', [\App\Http\Controllers\Api\V1\AdminUserController::class, 'export']);
        Route::post('users-import', [\App\Http\Controllers\Api\V1\AdminUserController::class, 'import']);
        
        Route::apiResource('courses', \App\Http\Controllers\Api\V1\AdminCourseController::class);
        Route::get('courses-export', [\App\Http\Controllers\Api\V1\AdminCourseController::class, 'export']);
        Route::post('courses-import', [\App\Http\Controllers\Api\V1\AdminCourseController::class, 'import']);
        
        Route::get('exams', [\App\Http\Controllers\Api\V1\AdminExamController::class, 'index']);
        Route::post('exams', [\App\Http\Controllers\Api\V1\AdminExamController::class, 'store']);
        Route::get('exams-export', [\App\Http\Controllers\Api\V1\AdminExamController::class, 'export']);
        Route::get('exams/{id}', [\App\Http\Controllers\Api\V1\AdminExamController::class, 'show']);
        Route::put('exams/{id}', [\App\Http\Controllers\Api\V1\AdminExamController::class, 'update']);
        Route::delete('exams/{id}', [\App\Http\Controllers\Api\V1\AdminExamController::class, 'destroy']);
        
        Route::get('activity-logs/unread-count', [\App\Http\Controllers\Api\V1\ActivityLogController::class, 'unreadCount']);
        Route::post('activity-logs/mark-all-read', [\App\Http\Controllers\Api\V1\ActivityLogController::class, 'markAllAsRead']);
        Route::get('activity-logs/export', [\App\Http\Controllers\Api\V1\ActivityLogController::class, 'export']);
        Route::get('activity-logs/{id}', [\App\Http\Controllers\Api\V1\ActivityLogController::class, 'show']);
        Route::post('activity-logs/{id}/read', [\App\Http\Controllers\Api\V1\ActivityLogController::class, 'markAsRead']);
        Route::get('settings/info', [\App\Http\Controllers\Api\V1\SystemSettingController::class, 'systemInfo']);
        Route::post('settings/reset-defaults', [\App\Http\Controllers\Api\V1\SystemSettingController::class, 'resetDefaults']);
        Route::post('settings', [\App\Http\Controllers\Api\V1\SystemSettingController::class, 'store']);

        // Admin Exam Control & Recovery Overrides
        Route::get('recovery-requests', [\App\Http\Controllers\Api\V1\AdminExamControlController::class, 'indexRecoveryRequests']);
        Route::get('exams/{exam}/recovery-requests', [\App\Http\Controllers\Api\V1\AdminExamControlController::class, 'indexRecoveryRequests']);
        Route::post('recovery-requests/{id}/override', [\App\Http\Controllers\Api\V1\AdminExamControlController::class, 'overrideRecovery']);
        Route::post('exams/{exam}/cancel', [\App\Http\Controllers\Api\V1\AdminExamControlController::class, 'cancelExam']);
        Route::post('exams/{exam}/reinstate', [\App\Http\Controllers\Api\V1\AdminExamControlController::class, 'reinstateExam']);
        Route::post('exams/{exam}/pause', [\App\Http\Controllers\Api\V1\AdminExamControlController::class, 'pauseExam']);
        Route::post('exams/{exam}/resume', [\App\Http\Controllers\Api\V1\AdminExamControlController::class, 'resumeExam']);

        // Calendar Event Categories
        Route::apiResource('calendar/categories', \App\Http\Controllers\Api\V1\AdminCalendarController::class)
            ->except(['show'])
            ->parameters(['categories' => 'id']);

        // Calendar Academic Events
        Route::get('calendar/events/export',   [\App\Http\Controllers\Api\V1\AdminCalendarController::class, 'exportEvents']);
        Route::get('calendar/events',          [\App\Http\Controllers\Api\V1\AdminCalendarController::class, 'indexEvents']);
        Route::post('calendar/events',         [\App\Http\Controllers\Api\V1\AdminCalendarController::class, 'storeEvent']);
        Route::put('calendar/events/{id}',     [\App\Http\Controllers\Api\V1\AdminCalendarController::class, 'updateEvent']);
        Route::delete('calendar/events/{id}',  [\App\Http\Controllers\Api\V1\AdminCalendarController::class, 'destroyEvent']);

        // Reports
        Route::get('reports/stats', [\App\Http\Controllers\Api\V1\AdminReportController::class, 'stats']);
        Route::get('reports/export', [\App\Http\Controllers\Api\V1\AdminReportController::class, 'export']);

        // List all instructors (for assign-head modal), filtered strictly by department_id when provided
        Route::get('instructors', function (Request $request) {
            $query = \App\Models\User::whereIn('role', ['instructor', 'dept_head']);
            if ($request->filled('department_id')) {
                $query->where('department_id', $request->department_id);
            }
            return response()->json([
                'data' => $query->select('id', 'name', 'email', 'role', 'department_id', 'course_code', 'section')
                    ->orderBy('name')
                    ->get(),
            ]);
        });
    });

    // ------------------------------------------------------------------
    // Department Head Routes
    // ------------------------------------------------------------------
    Route::prefix('dept-head')->group(function () {
        Route::get('dashboard-stats', [\App\Http\Controllers\Api\V1\DeptHead\DashboardController::class, 'stats']);
        Route::get('instructors/export', [\App\Http\Controllers\Api\V1\DeptHead\InstructorController::class, 'export']);
        Route::patch('instructors/{id}/status', [\App\Http\Controllers\Api\V1\DeptHead\InstructorController::class, 'updateStatus']);
        Route::match(['put', 'post'], 'instructors/{id}', [\App\Http\Controllers\Api\V1\DeptHead\InstructorController::class, 'update']);
        Route::apiResource('instructors', \App\Http\Controllers\Api\V1\DeptHead\InstructorController::class);
        Route::apiResource('courses', \App\Http\Controllers\Api\V1\DeptHead\CourseController::class);
        Route::get('students/export', [\App\Http\Controllers\Api\V1\DeptHead\StudentController::class, 'export']);
        Route::get('students', [\App\Http\Controllers\Api\V1\DeptHead\StudentController::class, 'index']);
        Route::match(['put', 'post'], 'students/{id}', [\App\Http\Controllers\Api\V1\DeptHead\StudentController::class, 'update']);
        Route::delete('students/{id}', [\App\Http\Controllers\Api\V1\DeptHead\StudentController::class, 'destroy']);
        Route::get('exams', [\App\Http\Controllers\Api\V1\DeptHead\ExamController::class, 'index']);
        Route::post('exams', [\App\Http\Controllers\Api\V1\DeptHead\ExamController::class, 'store']);
        Route::get('exams/{id}', [\App\Http\Controllers\Api\V1\DeptHead\ExamController::class, 'show']);
        Route::delete('exams/{id}', [\App\Http\Controllers\Api\V1\DeptHead\ExamController::class, 'destroy']);
        Route::get('results', [\App\Http\Controllers\Api\V1\DeptHead\ResultController::class, 'index']);
        Route::get('results/{examId}', [\App\Http\Controllers\Api\V1\DeptHead\ResultController::class, 'showExamResults']);
        Route::get('reports', [\App\Http\Controllers\Api\V1\DeptHead\ReportController::class, 'index']);
        Route::get('reports/export', [\App\Http\Controllers\Api\V1\DeptHead\ReportController::class, 'export']);
        Route::get('semester-submissions', [\App\Http\Controllers\Api\V1\DeptHead\SemesterSubmissionController::class, 'index']);
        Route::get('semester-submissions/details', [\App\Http\Controllers\Api\V1\DeptHead\SemesterSubmissionController::class, 'details']);
        Route::get('semester-submissions/export', [\App\Http\Controllers\Api\V1\DeptHead\SemesterSubmissionController::class, 'export']);
        Route::get('semester-submissions/{id}', [\App\Http\Controllers\Api\V1\DeptHead\SemesterSubmissionController::class, 'show']);
        Route::put('semester-submissions/{id}/status', [\App\Http\Controllers\Api\V1\DeptHead\SemesterSubmissionController::class, 'updateStatus']);
        Route::put('semester-submissions/{id}/reopen', [\App\Http\Controllers\Api\V1\DeptHead\SemesterSubmissionController::class, 'reopen']);
        Route::get('activity-logs/export', [\App\Http\Controllers\Api\V1\DeptHead\ActivityLogController::class, 'export']);
        Route::post('activity-logs/clear', [\App\Http\Controllers\Api\V1\DeptHead\ActivityLogController::class, 'clearOldLogs']);
        Route::get('activity-logs', [\App\Http\Controllers\Api\V1\DeptHead\ActivityLogController::class, 'index']);
        Route::get('activity-logs/{id}', [\App\Http\Controllers\Api\V1\DeptHead\ActivityLogController::class, 'show']);
        Route::get('settings', [\App\Http\Controllers\Api\V1\DeptHead\SettingController::class, 'getSettings']);
        Route::put('settings', [\App\Http\Controllers\Api\V1\DeptHead\SettingController::class, 'updateSettings']);
    });

    // ------------------------------------------------------------------
    // Instructor Routes
    // ------------------------------------------------------------------
    Route::prefix('instructor')->middleware('instructor.semester.lock')->group(function () {

        // Dashboard stats & lists
        Route::get('/dashboard-stats', [InstructorDashboardController::class, 'stats']);
        Route::get('/recent-exams',    [InstructorDashboardController::class, 'recentExams']);
        Route::get('/upcoming-exams',  [InstructorDashboardController::class, 'upcomingExams']);

        // Exam Management
        Route::apiResource('exams', InstructorExamController::class);

        // Question Banks Management
        Route::apiResource('question-banks', InstructorQuestionBankController::class);
        Route::post('question-banks/{question_bank}/questions', [InstructorQuestionBankController::class, 'storeQuestion']);
        Route::get('question-banks/{question_bank}/drafts', [InstructorQuestionBankController::class, 'getDraftQuestions']);
        Route::post('question-banks/{question_bank}/publish', [InstructorQuestionBankController::class, 'publishQuestions']);
        Route::post('question-banks/{question_bank}/import', [InstructorQuestionBankController::class, 'importQuestions']);
        Route::put('questions/{question}', [InstructorQuestionBankController::class, 'updateQuestion']);
        Route::delete('questions/{question}', [InstructorQuestionBankController::class, 'destroyQuestion']);

        // Instructor Instructions (for autocomplete)
        Route::get('instructor-instructions', [InstructorQuestionBankController::class, 'getInstructions']);

        // Students Management
        Route::get('/students', [InstructorStudentController::class, 'index']);
        Route::get('/students/export', [InstructorStudentController::class, 'export']);
        Route::post('/students/import', [InstructorStudentController::class, 'import']);
        Route::post('/students/announcement', [InstructorStudentController::class, 'announcement']);
        
        // Semester Submission & Lock Status
        Route::get('/semester-lock-status', [\App\Http\Controllers\Api\V1\InstructorSemesterSubmissionController::class, 'lockStatus']);
        Route::get('/semester-submission/status', [\App\Http\Controllers\Api\V1\InstructorSemesterSubmissionController::class, 'status']);
        Route::post('/semester-submission/submit', [\App\Http\Controllers\Api\V1\InstructorSemesterSubmissionController::class, 'submit']);

        // Reports & Results Management
        Route::get('/reports', [InstructorReportController::class, 'index']);
        Route::get('/results', [\App\Http\Controllers\Api\V1\InstructorResultController::class, 'index']);
        Route::get('/results/{examId}', [\App\Http\Controllers\Api\V1\InstructorResultController::class, 'showExamResults']);
        Route::get('/results/{examId}/student/{studentId}', [\App\Http\Controllers\Api\V1\InstructorResultController::class, 'showStudentResult']);
        Route::post('/results/{examId}/student/{studentId}/save', [\App\Http\Controllers\Api\V1\InstructorResultController::class, 'saveGrades']);
        Route::post('/results/{examId}/student/{studentId}/publish', [\App\Http\Controllers\Api\V1\InstructorResultController::class, 'publishResult']);

        // Exam Attempt Recovery & Interruption Control
        Route::get('/recovery-requests', [\App\Http\Controllers\Api\V1\ExamRecoveryController::class, 'instructorIndex']);
        Route::get('/exams/{exam}/recovery-requests', [\App\Http\Controllers\Api\V1\ExamRecoveryController::class, 'instructorIndex']);
        Route::post('/recovery-requests/{id}/review', [\App\Http\Controllers\Api\V1\ExamRecoveryController::class, 'review']);

        // Instructor profile — name, department, year_level, section (for header)
        Route::get('/me', function (Request $request) {
            $user = $request->user()->load('department');
            return response()->json([
                'data' => [
                    'id'          => $user->id,
                    'name'        => $user->name,
                    'role'        => $user->role,
                    'department'  => $user->department?->name,
                    'year_level'  => $user->year_level,
                    'section'     => $user->section,
                    'course_name' => $user->course_name,
                    'course_code' => $user->course_code,
                ]
            ]);
        });

    });

    // ------------------------------------------------------------------
    // Student Routes
    // ------------------------------------------------------------------
    Route::prefix('student')->group(function () {

        Route::get('/dashboard', [StudentExamController::class, 'dashboard']);
        Route::get('/exams',     [StudentExamController::class, 'index']);
        Route::post('/exams/{exam}/start',  [StudentExamController::class, 'start']);
        Route::post('/exams/{exam}/submit', [StudentExamController::class, 'submit']);
        Route::get('/results',              [StudentExamController::class, 'results']);
        Route::get('/results/{attemptId}',  [StudentExamController::class, 'showResult']);

        // Connection Monitoring, Heartbeat, and Recovery
        Route::post('/exams/{exam}/heartbeat', [\App\Http\Controllers\Api\V1\ExamRecoveryController::class, 'heartbeat']);
        Route::post('/exams/{exam}/reconnect', [\App\Http\Controllers\Api\V1\ExamRecoveryController::class, 'reconnect']);

    });

});
