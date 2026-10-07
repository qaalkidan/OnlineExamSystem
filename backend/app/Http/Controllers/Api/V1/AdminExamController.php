<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminExamController extends Controller
{
    /**
     * Determine exam type from settings or title keywords.
     */
    private function determineExamType($exam): string
    {
        if (!empty($exam->settings['exam_type'])) {
            return ucfirst($exam->settings['exam_type']);
        }
        $title = strtolower($exam->title ?? '');
        if (str_contains($title, 'final')) return 'Final Exam';
        if (str_contains($title, 'quiz')) return 'Quiz';
        if (str_contains($title, 'assignment')) return 'Assignment';
        if (str_contains($title, 'mid')) return 'Mid Exam';
        if (str_contains($title, 'practical') || str_contains($title, 'lab')) return 'Practical Exam';
        
        $courseName = strtolower($exam->course_name ?? '');
        if (str_contains($courseName, 'final')) return 'Final Exam';
        if (str_contains($courseName, 'quiz')) return 'Quiz';
        if (str_contains($courseName, 'mid')) return 'Mid Exam';

        return 'Mid Exam';
    }

    /**
     * Format academic exam reference code.
     */
    private function formatExamCode($exam): string
    {
        if (!empty($exam->settings['exam_code'])) {
            return $exam->settings['exam_code'];
        }
        $year = $exam->scheduled_at ? $exam->scheduled_at->format('Y') : ($exam->created_at ? $exam->created_at->format('Y') : date('Y'));
        return 'EXM-' . $year . '-' . str_pad((string)$exam->id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Format duration into human readable string.
     */
    private function formatDuration(?int $minutes): string
    {
        if (!$minutes || $minutes <= 0) return '60m';
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;
        if ($hours > 0 && $rem > 0) return "{$hours}h {$rem}m";
        if ($hours > 0) return "{$hours}h";
        return "{$rem}m";
    }

    /**
     * Display a listing of all exams for Super Admin.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Exam::with([
            'instructor:id,name,email,department_id,year_level,section',
            'instructor.department:id,name',
            'course:id,title,code,department_id,semester,level',
            'course.department:id,name',
        ])
        ->withCount(['questions', 'attempts', 'students'])
        ->latest('id');

        // Optional query filters
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('title', 'LIKE', "%{$s}%")
                  ->orWhere('course_code', 'LIKE', "%{$s}%")
                  ->orWhere('course_name', 'LIKE', "%{$s}%")
                  ->orWhere('section', 'LIKE', "%{$s}%")
                  ->orWhereHas('instructor', function ($sub) use ($s) {
                      $sub->where('name', 'LIKE', "%{$s}%")
                          ->orWhere('email', 'LIKE', "%{$s}%");
                  })
                  ->orWhereHas('course', function ($sub) use ($s) {
                      $sub->where('title', 'LIKE', "%{$s}%")
                          ->orWhere('code', 'LIKE', "%{$s}%");
                  });
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $status = strtolower($request->status);
            if ($status === 'cancelled') {
                $query->where(function ($q) {
                    $q->where('status', 'cancelled')->orWhereNotNull('cancelled_at');
                });
            } else {
                $query->where('status', $status)->whereNull('cancelled_at');
            }
        }

        if ($request->filled('department') && $request->department !== 'all') {
            $dept = $request->department;
            $query->where(function ($q) use ($dept) {
                $q->whereHas('course.department', function ($sub) use ($dept) {
                    $sub->where('name', $dept)->orWhere('id', $dept);
                })
                ->orWhereHas('instructor.department', function ($sub) use ($dept) {
                    $sub->where('name', $dept)->orWhere('id', $dept);
                });
            });
        }

        if ($request->filled('course') && $request->course !== 'all') {
            $courseFilter = $request->course;
            $query->where(function ($q) use ($courseFilter) {
                $q->where('course_code', $courseFilter)
                  ->orWhere('course_name', $courseFilter)
                  ->orWhereHas('course', function ($sub) use ($courseFilter) {
                      $sub->where('title', $courseFilter)->orWhere('code', $courseFilter);
                  });
            });
        }

        $allExams = $query->get();

        $formatted = $allExams->map(function ($exam) {
            $courseTitle = $exam->course?->title ?: ($exam->course_name ?: $exam->title);
            $courseCode  = $exam->course_code ?: ($exam->course?->code ?: 'N/A');
            $deptName    = $exam->course?->department?->name ?? $exam->instructor?->department?->name ?? 'Information Technology';
            $semester    = $exam->settings['semester'] ?? $exam->course?->semester ?? 'Semester 1';
            $yearLevel   = $exam->settings['year_level'] ?? $exam->course?->level ?? $exam->instructor?->year_level ?? 'Year 3';
            $examType    = $this->determineExamType($exam);
            $examCode    = $this->formatExamCode($exam);
            $isCancelled = $exam->isCancelled();
            $status      = $isCancelled ? 'cancelled' : strtolower($exam->status ?? 'draft');

            return [
                'id'                  => $exam->id,
                'title'               => $exam->title,
                'code'                => $examCode,
                'examCode'            => $examCode,
                'course'              => $courseTitle,
                'courseName'          => $courseTitle,
                'courseCode'          => $courseCode,
                'course_code'         => $courseCode,
                'course_name'         => $courseTitle,
                'department'          => $deptName,
                'department_id'       => $exam->course?->department_id ?? $exam->instructor?->department_id,
                'year'                => $yearLevel,
                'year_level'          => $yearLevel,
                'semester'            => $semester,
                'section'             => $exam->section ?: 'Both',
                'instructor'          => $exam->instructor?->name ?: 'Administrator',
                'instructor_id'       => $exam->user_id,
                'instructorEmail'     => $exam->instructor?->email ?: '',
                'type'                => $examType,
                'exam_type'           => $examType,
                'totalMarks'          => $exam->total_marks ?? 100,
                'total_marks'         => $exam->total_marks ?? 100,
                'duration'            => $this->formatDuration($exam->duration_minutes),
                'duration_minutes'    => $exam->duration_minutes ?? 60,
                'status'              => $status,
                'is_cancelled'        => $isCancelled,
                'cancellation_reason' => $exam->cancellation_reason,
                'cancelled_at'        => $exam->cancelled_at?->toISOString(),
                'is_paused'           => $exam->paused_at !== null,
                'scheduled_at'        => $exam->scheduled_at?->toISOString(),
                'examDate'            => $exam->scheduled_at ? $exam->scheduled_at->format('M d, Y') : 'TBD',
                'examTime'            => $exam->scheduled_at ? $exam->scheduled_at->format('h:i A') : 'TBD',
                'questions_count'     => $exam->questions_count ?? 0,
                'attempts_count'      => $exam->attempts_count ?? 0,
                'students_count'      => $exam->students_count ?? 0,
                'settings'            => $exam->settings ?? [],
                'createdAt'           => $exam->created_at?->toISOString(),
            ];
        });

        // 100% Real Database Statistics
        $stats = [
            'total'           => Exam::count(),
            'published'       => Exam::where('status', 'published')->whereNull('cancelled_at')->count(),
            'draft'           => Exam::where('status', 'draft')->whereNull('cancelled_at')->count(),
            'scheduled'       => Exam::where('status', 'scheduled')->whereNull('cancelled_at')->count(),
            'completed'       => Exam::where('status', 'completed')->count(),
            'cancelled'       => Exam::where(function ($q) {
                $q->where('status', 'cancelled')->orWhereNotNull('cancelled_at');
            })->count(),
            'total_questions' => Question::count(),
            'total_attempts'  => ExamAttempt::count(),
        ];

        // Unique dynamic lists for clean frontend filtering
        $courses = Course::orderBy('title')->select('id', 'title', 'code')->get();
        $departments = Department::orderBy('name')->select('id', 'name', 'code')->get();
        $instructors = User::whereIn('role', ['instructor', 'dept_head'])->orderBy('name')->select('id', 'name', 'email', 'department_id', 'course_code')->get();

        return response()->json([
            'data' => [
                'exams'       => $formatted->values(),
                'stats'       => $stats,
                'courses'     => $courses,
                'departments' => $departments,
                'instructors' => $instructors,
            ]
        ]);
    }

    /**
     * Display the specified exam with real questions, attempts, and security settings.
     */
    public function show(string $id): JsonResponse
    {
        $exam = Exam::with([
            'instructor:id,name,email,department_id,year_level,section',
            'instructor.department:id,name',
            'course:id,title,code,department_id,semester,level',
            'course.department:id,name',
            'questions',
            'attempts',
            'cancelledBy:id,name,email',
            'pausedBy:id,name,email',
        ])
        ->withCount(['questions', 'attempts', 'students'])
        ->findOrFail($id);

        $courseTitle = $exam->course?->title ?: ($exam->course_name ?: $exam->title);
        $courseCode  = $exam->course_code ?: ($exam->course?->code ?: 'N/A');
        $deptName    = $exam->course?->department?->name ?? $exam->instructor?->department?->name ?? 'Information Technology';
        $semester    = $exam->settings['semester'] ?? $exam->course?->semester ?? 'Semester 1';
        $yearLevel   = $exam->settings['year_level'] ?? $exam->course?->level ?? $exam->instructor?->year_level ?? 'Year 3';
        $examType    = $this->determineExamType($exam);
        $examCode    = $this->formatExamCode($exam);
        $settings    = $exam->settings ?? [];
        $isCancelled = $exam->isCancelled();

        // Real questions mapped from database
        $questionsList = $exam->questions->values()->map(function ($q, $index) {
            $rawType = $q->type ?: 'multiple_choice';
            $displayType = match($rawType) {
                'multiple_choice'                    => 'Multiple Choice',
                'true_false'                         => 'True/False',
                'short_answer'                       => 'Short Answer',
                'matching'                           => 'Matching',
                'fill_in_the_blank', 'fill_in_blank' => 'Fill in the Blanks',
                default                              => ucfirst(str_replace('_', ' ', $rawType)),
            };

            return [
                'id'              => $q->id,
                'number'          => $index + 1,
                'type'            => $displayType,
                'raw_type'        => $rawType,
                'text'            => $q->text ?: ($q->title ?: 'Question #' . ($index + 1)),
                'instruction'     => $q->instruction ?: ($rawType === 'multiple_choice' ? 'Choose the most appropriate answer from the options given below.' : ($rawType === 'true_false' ? 'Determine whether the statement is True or False.' : 'Write your answer in the space provided.')),
                'marks'           => $q->marks ?? 5,
                'options'         => $q->options ?? [],
                'correct_answer'  => $q->correct_answer,
                'expected_answer' => $q->correct_answer ?: '',
            ];
        });

        $detail = [
            'id'                  => $exam->id,
            'title'               => $exam->title,
            'code'                => $examCode,
            'examCode'            => $examCode,
            'course'              => $courseTitle,
            'courseName'          => $courseTitle,
            'courseCode'          => $courseCode,
            'course_code'         => $courseCode,
            'course_name'         => $courseTitle,
            'department'          => $deptName,
            'department_id'       => $exam->course?->department_id ?? $exam->instructor?->department_id,
            'year'                => $yearLevel,
            'year_level'          => $yearLevel,
            'semester'            => $semester,
            'section'             => $exam->section ?: 'Both',
            'instructor'          => $exam->instructor?->name ?: 'Administrator',
            'instructor_id'       => $exam->user_id,
            'instructorEmail'     => $exam->instructor?->email ?: '',
            'type'                => $examType,
            'exam_type'           => $examType,
            'totalMarks'          => $exam->total_marks ?? 100,
            'total_marks'         => $exam->total_marks ?? 100,
            'duration'            => $this->formatDuration($exam->duration_minutes),
            'duration_minutes'    => $exam->duration_minutes ?? 60,
            'status'              => $isCancelled ? 'cancelled' : strtolower($exam->status ?? 'draft'),
            'is_cancelled'        => $isCancelled,
            'cancellation_reason' => $exam->cancellation_reason,
            'cancelled_at'        => $exam->cancelled_at?->toISOString(),
            'cancelled_by_name'   => $exam->cancelledBy?->name,
            'is_paused'           => $exam->paused_at !== null,
            'paused_at'           => $exam->paused_at?->toISOString(),
            'paused_by_name'      => $exam->pausedBy?->name,
            'scheduled_at'        => $exam->scheduled_at?->toISOString(),
            'examDate'            => $exam->scheduled_at ? $exam->scheduled_at->format('M d, Y') : 'TBD',
            'examTime'            => $exam->scheduled_at ? $exam->scheduled_at->format('h:i A') : 'TBD',
            'createdAt'           => $exam->created_at?->toISOString(),
            'questions_count'     => $exam->questions_count ?? 0,
            'attempts_count'      => $exam->attempts_count ?? 0,
            'students_count'      => $exam->students_count ?? 0,
            
            // Exam Behavior & Security Matrix
            'shuffle_questions'   => (bool)($settings['shuffleQuestions'] ?? $settings['shuffle_questions'] ?? true),
            'show_review_screen'  => (bool)($settings['showReviewScreen'] ?? $settings['examReviewGroup'] ?? true),
            'shuffle_options'     => (bool)($settings['shuffleAnswers'] ?? $settings['shuffleOptions'] ?? true),
            'allow_backtracking'  => (bool)($settings['allowBacktracking'] ?? true),
            'show_one_question'   => (bool)($settings['showOneQuestionAtATime'] ?? $settings['showOneQuestion'] ?? false),
            'fullscreen_mode'     => (bool)($settings['enableFullscreenMode'] ?? true),
            'tab_monitoring'      => (bool)($settings['enableBrowserTabMonitoring'] ?? true),
            'disable_right_click' => (bool)($settings['disableRightClick'] ?? true),
            'allow_calculator'    => (bool)($settings['allowCalculator'] ?? false),
            'disable_copy_paste'  => (bool)($settings['disableCopyPaste'] ?? true),
            'webcam_monitoring'   => (bool)($settings['webcamMonitoring'] ?? false),

            'settings'            => $settings,
            'questions'           => $questionsList,
        ];

        return response()->json([
            'data' => $detail
        ]);
    }

    /**
     * Store a newly created exam.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'course_code'      => 'nullable|string|max:50',
            'course_name'      => 'nullable|string|max:255',
            'section'          => 'nullable|string|max:50',
            'duration_minutes' => 'required|integer|min:1',
            'total_marks'      => 'required|integer|min:1',
            'status'           => 'required|in:draft,published,scheduled,completed',
            'scheduled_at'     => 'nullable|date',
            'instructor_id'    => 'nullable|exists:users,id',
            'settings'         => 'nullable|array',
        ]);

        $userId = $validated['instructor_id'] ?? $request->user()->id;

        // Auto-resolve course_name if course_code provided
        $courseName = $validated['course_name'] ?? null;
        if (!$courseName && !empty($validated['course_code'])) {
            $matchedCourse = Course::where('code', $validated['course_code'])->first();
            if ($matchedCourse) {
                $courseName = $matchedCourse->title;
            }
        }

        $exam = Exam::create([
            'user_id'          => $userId,
            'course_code'      => $validated['course_code'] ?? 'GENERAL',
            'course_name'      => $courseName ?? 'General Course',
            'section'          => $validated['section'] ?? null,
            'title'            => $validated['title'],
            'duration_minutes' => $validated['duration_minutes'],
            'total_marks'      => $validated['total_marks'],
            'status'           => $validated['status'],
            'scheduled_at'     => !empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null,
            'settings'         => $validated['settings'] ?? [],
        ]);

        LogActivity::record(
            'Exam Created',
            'Examinations',
            "Administrator {$request->user()->name} created examination \"{$exam->title}\" (ID: {$exam->id})"
        );

        return response()->json([
            'message' => 'Exam created successfully',
            'data'    => $exam,
        ], 201);
    }

    /**
     * Update the specified exam.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);

        $validated = $request->validate([
            'title'            => 'sometimes|required|string|max:255',
            'course_code'      => 'nullable|string|max:50',
            'course_name'      => 'nullable|string|max:255',
            'section'          => 'nullable|string|max:50',
            'duration_minutes' => 'sometimes|required|integer|min:1',
            'total_marks'      => 'sometimes|required|integer|min:1',
            'status'           => 'sometimes|required|in:draft,published,scheduled,completed,cancelled',
            'scheduled_at'     => 'nullable|date',
            'instructor_id'    => 'nullable|exists:users,id',
            'settings'         => 'nullable|array',
        ]);

        if (isset($validated['instructor_id'])) {
            $exam->user_id = $validated['instructor_id'];
        }
        if (isset($validated['title'])) {
            $exam->title = $validated['title'];
        }
        if (array_key_exists('course_code', $validated)) {
            $exam->course_code = $validated['course_code'];
        }
        if (array_key_exists('course_name', $validated)) {
            $exam->course_name = $validated['course_name'];
        }
        if (array_key_exists('section', $validated)) {
            $exam->section = $validated['section'];
        }
        if (isset($validated['duration_minutes'])) {
            $exam->duration_minutes = $validated['duration_minutes'];
        }
        if (isset($validated['total_marks'])) {
            $exam->total_marks = $validated['total_marks'];
        }
        if (isset($validated['status'])) {
            $exam->status = $validated['status'];
            if ($validated['status'] !== 'cancelled' && $exam->cancelled_at) {
                $exam->cancelled_at = null;
                $exam->cancelled_by = null;
                $exam->cancellation_reason = null;
            }
        }
        if (array_key_exists('scheduled_at', $validated)) {
            $exam->scheduled_at = !empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null;
        }
        if (isset($validated['settings'])) {
            $exam->settings = array_merge($exam->settings ?? [], $validated['settings']);
        }

        $exam->save();

        LogActivity::record(
            'Exam Updated',
            'Examinations',
            "Administrator {$request->user()->name} updated examination \"{$exam->title}\" (ID: {$exam->id})"
        );

        return response()->json([
            'message' => 'Exam updated successfully',
            'data'    => $exam,
        ]);
    }

    /**
     * Remove the specified exam from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $title = $exam->title;
        $exam->delete();

        LogActivity::record(
            'Exam Deleted',
            'Examinations',
            "Administrator {$request->user()->name} deleted examination \"{$title}\" (ID: {$id})"
        );

        return response()->json(['message' => 'Exam deleted successfully']);
    }

    /**
     * Export exams as CSV or PDF report.
     */
    public function export(Request $request)
    {
        $format = $request->query('format', 'csv');
        $query = Exam::with([
            'instructor.department',
            'course.department',
        ])
        ->withCount(['questions', 'attempts'])
        ->latest('id');

        if ($request->filled('status') && $request->status !== 'all') {
            $status = strtolower($request->status);
            if ($status === 'cancelled') {
                $query->where(function ($q) {
                    $q->where('status', 'cancelled')->orWhereNotNull('cancelled_at');
                });
            } else {
                $query->where('status', $status)->whereNull('cancelled_at');
            }
        }

        if ($request->filled('department') && $request->department !== 'all') {
            $dept = $request->department;
            $query->where(function ($q) use ($dept) {
                $q->whereHas('course.department', function ($sub) use ($dept) {
                    $sub->where('name', $dept)->orWhere('id', $dept);
                })
                ->orWhereHas('instructor.department', function ($sub) use ($dept) {
                    $sub->where('name', $dept)->orWhere('id', $dept);
                });
            });
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('title', 'LIKE', "%{$s}%")
                  ->orWhere('course_code', 'LIKE', "%{$s}%")
                  ->orWhere('course_name', 'LIKE', "%{$s}%");
            });
        }

        $exams = $query->get();
        $fileName = 'wollo_university_exams_' . date('Y-m-d');

        if ($format === 'pdf') {
            return $this->exportExamsPdf($exams, $fileName);
        }

        return $this->exportExamsCsv($exams, $fileName);
    }

    private function exportExamsCsv($exams, string $fileName)
    {
        ob_start();
        $handle = fopen('php://output', 'w');
        fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM
        fputcsv($handle, [
            'ID', 'Exam Code', 'Title', 'Course Name', 'Course Code',
            'Department', 'Instructor', 'Year Level', 'Semester', 'Section',
            'Duration (min)', 'Total Marks', 'Questions', 'Attempts',
            'Status', 'Scheduled Date', 'Scheduled Time', 'Created At'
        ]);

        foreach ($exams as $exam) {
            $courseTitle = $exam->course?->title ?: ($exam->course_name ?: $exam->title);
            $courseCode  = $exam->course_code ?: ($exam->course?->code ?: 'N/A');
            $deptName    = $exam->course?->department?->name ?? $exam->instructor?->department?->name ?? 'Information Technology';
            $semester    = $exam->settings['semester'] ?? $exam->course?->semester ?? 'Semester 1';
            $yearLevel   = $exam->settings['year_level'] ?? $exam->course?->level ?? $exam->instructor?->year_level ?? 'Year 3';
            $status      = $exam->isCancelled() ? 'Cancelled' : ucfirst($exam->status ?? 'Draft');

            fputcsv($handle, [
                $exam->id,
                $this->formatExamCode($exam),
                $exam->title,
                $courseTitle,
                $courseCode,
                $deptName,
                $exam->instructor?->name ?? 'Administrator',
                $yearLevel,
                $semester,
                $exam->section ?: 'Both',
                $exam->duration_minutes ?? 60,
                $exam->total_marks ?? 100,
                $exam->questions_count ?? 0,
                $exam->attempts_count ?? 0,
                $status,
                $exam->scheduled_at ? $exam->scheduled_at->format('Y-m-d') : 'TBD',
                $exam->scheduled_at ? $exam->scheduled_at->format('h:i A') : 'TBD',
                $exam->created_at ? $exam->created_at->format('Y-m-d H:i') : '',
            ]);
        }

        fclose($handle);
        $csvContent = ob_get_clean();

        return response()->json([
            'file'     => base64_encode($csvContent),
            'filename' => $fileName . '.csv'
        ]);
    }

    private function exportExamsPdf($exams, string $fileName)
    {
        $generatedAt = now()->format('F j, Y  H:i');

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
            body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 20px; }
            .header { border-bottom: 2px solid #4338ca; padding-bottom: 12px; margin-bottom: 16px; }
            .inst-name { font-size: 18px; font-weight: bold; color: #4338ca; text-transform: uppercase; letter-spacing: 0.5px; }
            .doc-title { font-size: 13px; font-weight: bold; color: #0f172a; margin-top: 4px; }
            .meta { font-size: 9px; color: #64748b; margin-top: 4px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background-color: #f1f5f9; color: #334155; font-size: 9px; font-weight: bold; text-align: left; padding: 6px 8px; border: 1px solid #cbd5e1; }
            td { padding: 5px 8px; border: 1px solid #e2e8f0; font-size: 9px; vertical-align: top; }
            tr:nth-child(even) td { background-color: #f8fafc; }
            .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: bold; text-transform: uppercase; }
            .badge-published { background: #ecfdf5; color: #047857; }
            .badge-scheduled { background: #fffbeb; color: #b45309; }
            .badge-completed { background: #eef2ff; color: #4338ca; }
            .badge-cancelled { background: #fff1f2; color: #be123c; }
            .badge-draft { background: #f1f5f9; color: #475569; }
            .footer { margin-top: 20px; border-top: 1px solid #cbd5e1; padding-top: 8px; font-size: 8px; color: #94a3b8; display: flex; justify-content: space-between; }
        </style></head><body>';

        $html .= '<div class="header">
            <div class="inst-name">Wollo University</div>
            <div class="doc-title">Office of the Registrar &bull; Examination Center Official Report</div>
            <div class="meta">Generated: ' . $generatedAt . ' &bull; Total Exams: ' . $exams->count() . '</div>
        </div>';

        $html .= '<table>
            <thead>
                <tr>
                    <th>Ref Code</th>
                    <th>Exam Title</th>
                    <th>Course</th>
                    <th>Department</th>
                    <th>Instructor</th>
                    <th>Date & Time</th>
                    <th>Duration</th>
                    <th>Questions</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($exams as $exam) {
            $courseTitle = $exam->course?->title ?: ($exam->course_name ?: $exam->title);
            $courseCode  = $exam->course_code ?: ($exam->course?->code ?: 'N/A');
            $deptName    = $exam->course?->department?->name ?? $exam->instructor?->department?->name ?? 'Information Technology';
            $isCancelled = $exam->isCancelled();
            $status      = $isCancelled ? 'cancelled' : strtolower($exam->status ?? 'draft');
            $badgeCls    = 'badge-' . $status;

            $scheduleStr = $exam->scheduled_at ? $exam->scheduled_at->format('M d, Y h:i A') : 'TBD';

            $html .= '<tr>
                <td style="font-family:monospace;font-weight:bold;">' . htmlspecialchars($this->formatExamCode($exam)) . '</td>
                <td><strong>' . htmlspecialchars($exam->title) . '</strong></td>
                <td>' . htmlspecialchars($courseTitle) . ' <span style="color:#64748b;">(' . htmlspecialchars($courseCode) . ')</span></td>
                <td>' . htmlspecialchars($deptName) . '</td>
                <td>' . htmlspecialchars($exam->instructor?->name ?? 'Administrator') . '</td>
                <td>' . htmlspecialchars($scheduleStr) . '</td>
                <td>' . htmlspecialchars($this->formatDuration($exam->duration_minutes)) . '</td>
                <td>' . ($exam->questions_count ?? 0) . '</td>
                <td><span class="badge ' . $badgeCls . '">' . htmlspecialchars(ucfirst($status)) . '</span></td>
            </tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer"><div>Wollo University &bull; Examination Administration Management</div><div>Confidential & Official Document</div></div>';
        $html .= '</body></html>';

        return response()->json([
            'file'     => base64_encode($html),
            'filename' => $fileName . '.html'
        ]);
    }
}
