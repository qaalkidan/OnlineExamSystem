<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\AcademicEvent;
use App\Models\EventCategory;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCalendarController extends Controller
{
    // -------------------------------------------------------
    // EVENT CATEGORIES
    // -------------------------------------------------------

    public function index()
    {
        $categories = EventCategory::orderBy('type')->orderBy('name')->get();

        $stats = [
            'total'  => $categories->count(),
            'active' => $categories->where('status', 'active')->count(),
            'system' => $categories->where('type', 'system')->count(),
            'custom' => $categories->where('type', 'custom')->count(),
        ];

        return response()->json(['data' => $categories, 'stats' => $stats]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:event_categories,name',
            'description' => 'nullable|string|max:200',
            'color'       => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'type'        => ['required', Rule::in(['system', 'custom'])],
            'status'      => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $category = EventCategory::create([
            ...$validated,
            'status'     => $validated['status'] ?? 'active',
            'created_by' => $request->user()?->id,
        ]);

        LogActivity::record('Created', 'Academic Calendar', "Created event category \"{$category->name}\"");

        return response()->json(['data' => $category], 201);
    }

    public function update(Request $request, string $id)
    {
        $category = EventCategory::findOrFail($id);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100', Rule::unique('event_categories', 'name')->ignore($category->id)],
            'description' => 'nullable|string|max:200',
            'color'       => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'type'        => ['required', Rule::in(['system', 'custom'])],
            'status'      => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $category->update($validated);

        LogActivity::record('Updated', 'Academic Calendar', "Updated event category \"{$category->name}\"");

        return response()->json(['data' => $category]);
    }

    public function destroy(string $id)
    {
        $category = EventCategory::findOrFail($id);

        if ($category->type === 'system') {
            return response()->json(['message' => 'System categories cannot be deleted.'], 403);
        }

        $categoryName = $category->name;
        $category->delete();

        LogActivity::record('Deleted', 'Academic Calendar', "Deleted event category \"{$categoryName}\"");

        return response()->json(['message' => 'Category deleted successfully.']);
    }

    // -------------------------------------------------------
    // ACADEMIC EVENTS
    // -------------------------------------------------------

    /**
     * List all academic events with real period statistics and category details.
     */
    public function indexEvents(Request $request): JsonResponse
    {
        $query = AcademicEvent::with(['category', 'creator:id,name,email,role']);

        // Optional filtering by academic year
        if ($request->filled('academic_year') && $request->academic_year !== 'all') {
            $query->where('academic_year', $request->academic_year);
        }

        // Optional filtering by semester
        if ($request->filled('semester') && $request->semester !== 'all') {
            $query->where('semester', $request->semester);
        }

        // Optional filtering by category
        if ($request->filled('category_id') && $request->category_id !== 'all') {
            $query->where('category_id', $request->category_id);
        }

        // Optional filtering by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search query
        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        $allDbEvents = AcademicEvent::with('category')->get();

        $events = $query->orderBy('start_date')->get()->map(function ($e) {
            return [
                'id'            => $e->id,
                'title'         => $e->title,
                'description'   => $e->description,
                'category_id'   => $e->category_id,
                'category_name' => $e->category?->name,
                'category_color'=> $e->category?->color,
                'academic_year' => $e->academic_year,
                'semester'      => $e->semester,
                'start_date'    => $e->start_date?->toDateString(),
                'end_date'      => $e->end_date?->toDateString(),
                'all_day'       => (bool)$e->all_day,
                'start_time'    => $e->start_time,
                'end_time'      => $e->end_time,
                'status'        => $e->status,
                'color'         => $e->color,
                'is_recurring'  => (bool)$e->is_recurring,
                'created_by'    => $e->creator?->name,
                'created_at'    => $e->created_at,
            ];
        });

        // Current system configuration (Source of Truth)
        $currentYear = SystemSetting::where('key', 'academicYear')->value('value') ?: '2026';
        $currentSemester = SystemSetting::where('key', 'semester')->value('value') ?: 'Second Semester';

        // Available academic years from DB + current setting
        $dbYears = AcademicEvent::distinct()->pluck('academic_year')->filter()->toArray();
        if (!in_array($currentYear, $dbYears)) {
            $dbYears[] = $currentYear;
        }
        rsort($dbYears);

        // Available semesters
        $dbSemesters = ['First Semester', 'Second Semester', 'Summer Term'];

        // Real active semester period calculations (Earliest start and latest end dates in DB)
        $activeEvents = $allDbEvents->filter(function ($e) use ($currentYear, $currentSemester) {
            return $e->academic_year === $currentYear || $e->semester === $currentSemester;
        });

        $periodStart = $activeEvents->min('start_date')?->toDateString() ?? $allDbEvents->min('start_date')?->toDateString();
        $periodEnd = $activeEvents->max('end_date')?->toDateString() ?? $allDbEvents->max('end_date')?->toDateString();

        $academicWeeks = null;
        $daysRemaining = null;
        if ($periodStart && $periodEnd) {
            $startC = Carbon::parse($periodStart);
            $endC = Carbon::parse($periodEnd);
            $academicWeeks = max(1, (int)$startC->diffInWeeks($endC));
            $daysRemaining = max(0, (int)Carbon::now()->diffInDays($endC, false));
        }

        $now = now()->toDateString();

        $stats = [
            'total'          => $allDbEvents->count(),
            'filtered_total' => $events->count(),
            'upcoming'       => $allDbEvents->where('status', 'upcoming')->count(),
            'ongoing'        => $allDbEvents->where('status', 'ongoing')->count(),
            'completed'      => $allDbEvents->where('status', 'completed')->count(),
            'cancelled'      => $allDbEvents->where('status', 'cancelled')->count(),
            'holidays'       => $allDbEvents->filter(fn($e) => str_contains(strtolower($e->category?->name ?? ''), 'holiday'))->count(),
            'exams'          => $allDbEvents->filter(fn($e) => str_contains(strtolower($e->category?->name ?? ''), 'exam'))->count(),
            'deadlines'      => $allDbEvents->filter(fn($e) => str_contains(strtolower($e->title ?? ''), 'deadline') || str_contains(strtolower($e->title ?? ''), 'submission'))->count(),
            'academic_weeks' => $academicWeeks,
            'days_remaining' => $daysRemaining,
            'period_start'   => $periodStart,
            'period_end'     => $periodEnd,
            'current_year'   => $currentYear,
            'current_semester'=> $currentSemester,
            'available_years'=> array_values(array_unique($dbYears)),
            'available_semesters' => $dbSemesters,
        ];

        return response()->json(['data' => $events, 'stats' => $stats]);
    }

    /**
     * Create a new academic event.
     */
    public function storeEvent(Request $request)
    {
        $validated = $request->validate([
            'title'         => 'required|string|max:200',
            'description'   => 'nullable|string|max:500',
            'category_id'   => 'nullable|exists:event_categories,id',
            'academic_year' => 'required|string|max:20',
            'semester'      => 'required|string|max:50',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'all_day'       => 'boolean',
            'start_time'    => 'nullable|date_format:H:i',
            'end_time'      => 'nullable|date_format:H:i',
            'status'        => ['required', Rule::in(['upcoming', 'ongoing', 'completed', 'cancelled'])],
            'color'         => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_recurring'  => 'boolean',
        ]);

        // Prevent duplicate academic event title within the same academic year and semester
        $candidateTitle = trim($validated['title']);
        $isDuplicate = AcademicEvent::where('academic_year', $validated['academic_year'])
            ->where('semester', $validated['semester'])
            ->where(function ($q) use ($candidateTitle) {
                $q->whereRaw('LOWER(TRIM(title)) = ?', [strtolower($candidateTitle)]);
                if (strtolower($candidateTitle) === 'class duration') {
                    $q->orWhereRaw('LOWER(TRIM(title)) = ?', ['class start']);
                } elseif (strtolower($candidateTitle) === 'class start') {
                    $q->orWhereRaw('LOWER(TRIM(title)) = ?', ['class duration']);
                }
            })
            ->exists();

        if ($isDuplicate) {
            return response()->json([
                'message' => "An academic event titled \"{$validated['title']}\" is already scheduled for {$validated['academic_year']} ({$validated['semester']}). Each event title can only be scheduled once per term.",
                'errors' => [
                    'title' => ["An academic event titled \"{$validated['title']}\" is already scheduled for {$validated['academic_year']} ({$validated['semester']}). Each milestone title can only exist once per term."]
                ]
            ], 422);
        }

        $event = AcademicEvent::create([
            ...$validated,
            'created_by' => $request->user()?->id,
        ]);

        $event->load('category');

        LogActivity::record('Created', 'Academic Calendar', "Created academic event \"{$event->title}\" ({$event->academic_year} {$event->semester})");

        return response()->json([
            'data' => [
                'id'            => $event->id,
                'title'         => $event->title,
                'description'   => $event->description,
                'category_id'   => $event->category_id,
                'category_name' => $event->category?->name,
                'category_color'=> $event->category?->color,
                'academic_year' => $event->academic_year,
                'semester'      => $event->semester,
                'start_date'    => $event->start_date?->toDateString(),
                'end_date'      => $event->end_date?->toDateString(),
                'all_day'       => (bool)$event->all_day,
                'start_time'    => $event->start_time,
                'end_time'      => $event->end_time,
                'status'        => $event->status,
                'color'         => $event->color,
                'is_recurring'  => (bool)$event->is_recurring,
            ]
        ], 201);
    }

    /**
     * Update an academic event.
     */
    public function updateEvent(Request $request, string $id)
    {
        $event = AcademicEvent::findOrFail($id);

        $validated = $request->validate([
            'title'         => 'required|string|max:200',
            'description'   => 'nullable|string|max:500',
            'category_id'   => 'nullable|exists:event_categories,id',
            'academic_year' => 'required|string|max:20',
            'semester'      => 'required|string|max:50',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'all_day'       => 'boolean',
            'start_time'    => 'nullable|date_format:H:i',
            'end_time'      => 'nullable|date_format:H:i',
            'status'        => ['required', Rule::in(['upcoming', 'ongoing', 'completed', 'cancelled'])],
            'color'         => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_recurring'  => 'boolean',
        ]);

        // Prevent duplicate academic event title within the same academic year and semester excluding this event
        $candidateTitle = trim($validated['title']);
        $isDuplicate = AcademicEvent::where('academic_year', $validated['academic_year'])
            ->where('semester', $validated['semester'])
            ->where('id', '!=', $id)
            ->where(function ($q) use ($candidateTitle) {
                $q->whereRaw('LOWER(TRIM(title)) = ?', [strtolower($candidateTitle)]);
                if (strtolower($candidateTitle) === 'class duration') {
                    $q->orWhereRaw('LOWER(TRIM(title)) = ?', ['class start']);
                } elseif (strtolower($candidateTitle) === 'class start') {
                    $q->orWhereRaw('LOWER(TRIM(title)) = ?', ['class duration']);
                }
            })
            ->exists();

        if ($isDuplicate) {
            return response()->json([
                'message' => "An academic event titled \"{$validated['title']}\" is already scheduled for {$validated['academic_year']} ({$validated['semester']}). Each event title can only be scheduled once per term.",
                'errors' => [
                    'title' => ["An academic event titled \"{$validated['title']}\" is already scheduled for {$validated['academic_year']} ({$validated['semester']}). Each milestone title can only exist once per term."]
                ]
            ], 422);
        }

        $event->update($validated);
        $event->load('category');

        LogActivity::record('Updated', 'Academic Calendar', "Updated academic event \"{$event->title}\"");

        return response()->json([
            'data' => array_merge($validated, [
                'id'            => $event->id,
                'category_name' => $event->category?->name,
                'category_color'=> $event->category?->color,
            ])
        ]);
    }

    /**
     * Delete an academic event.
     */
    public function destroyEvent(string $id)
    {
        $event = AcademicEvent::findOrFail($id);
        $title = $event->title;
        $event->delete();

        LogActivity::record('Deleted', 'Academic Calendar', "Deleted academic event \"{$title}\"");

        return response()->json(['message' => 'Event deleted successfully.']);
    }

    /**
     * Export academic events as CSV, PDF, or Printable HTML report.
     */
    public function exportEvents(Request $request)
    {
        $query = AcademicEvent::with(['category', 'creator:id,name,email']);

        if ($request->filled('academic_year') && $request->academic_year !== 'all') {
            $query->where('academic_year', $request->academic_year);
        }
        if ($request->filled('semester') && $request->semester !== 'all') {
            $query->where('semester', $request->semester);
        }
        if ($request->filled('category_id') && $request->category_id !== 'all') {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $events = $query->orderBy('start_date')->get();
        $format = strtolower($request->query('format', 'csv'));

        LogActivity::record('Exported', 'Academic Calendar', "Exported {$events->count()} academic calendar events as {$format}");

        $dateStr = date('Y_m_d_His');
        $fileName = 'wollo_university_academic_calendar_' . $dateStr;

        if ($format === 'pdf') {
            return $this->exportEventsPdf($events, $fileName);
        }

        if ($format === 'html' || $format === 'print') {
            $html = $this->renderCalendarHtml($events);
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status'   => 'success',
                    'html'     => $html,
                    'filename' => $fileName . '.html',
                ]);
            }
            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return $this->exportEventsCsv($events, $fileName);
    }

    /**
     * Export academic calendar as CSV with UTF-8 BOM.
     */
    private function exportEventsCsv($events, string $fileName)
    {
        ob_start();
        $handle = fopen('php://output', 'w');
        fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

        fputcsv($handle, [
            'ID',
            'Event Title',
            'Category',
            'Academic Year',
            'Semester',
            'Start Date',
            'End Date',
            'Duration (Days)',
            'All Day',
            'Start Time',
            'End Time',
            'Status',
            'Description',
            'Scheduled By'
        ]);

        foreach ($events as $e) {
            $startDate = $e->start_date ? Carbon::parse($e->start_date) : null;
            $endDate = $e->end_date ? Carbon::parse($e->end_date) : null;
            $days = ($startDate && $endDate) ? ($startDate->diffInDays($endDate) + 1) : 1;

            fputcsv($handle, [
                $e->id,
                $e->title,
                $e->category?->name ?? 'General',
                $e->academic_year,
                $e->semester,
                $e->start_date?->toDateString() ?? '',
                $e->end_date?->toDateString() ?? '',
                $days,
                $e->all_day ? 'Yes' : 'No',
                $e->start_time ?? '-',
                $e->end_time ?? '-',
                ucfirst($e->status ?? 'upcoming'),
                $e->description ?? '-',
                $e->creator?->name ?? 'System Administrator',
            ]);
        }

        fclose($handle);
        $csvContent = ob_get_clean();

        return response()->json([
            'status'   => 'success',
            'file'     => base64_encode($csvContent),
            'filename' => $fileName . '.csv'
        ]);
    }

    /**
     * Export academic calendar as official Wollo University PDF.
     */
    private function exportEventsPdf($events, string $fileName)
    {
        $html = $this->renderCalendarHtml($events);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return response()->json([
            'status'   => 'success',
            'file'     => base64_encode($dompdf->output()),
            'filename' => $fileName . '.pdf'
        ]);
    }

    /**
     * Render the official Wollo University academic calendar HTML report.
     */
    private function renderCalendarHtml($events): string
    {
        $generatedAt = Carbon::now()->format('F j, Y, g:i A');
        $currentYear = SystemSetting::where('key', 'academicYear')->value('value') ?: '2026';
        $currentSemester = SystemSetting::where('key', 'semester')->value('value') ?: 'Second Semester';

        $rowsHtml = '';
        foreach ($events as $index => $e) {
            $badgeBg = match($e->status) {
                'upcoming'  => '#e0e7ff',
                'ongoing'   => '#dcfce7',
                'completed' => '#f1f5f9',
                'cancelled' => '#fee2e2',
                default     => '#f1f5f9',
            };
            $badgeColor = match($e->status) {
                'upcoming'  => '#4338ca',
                'ongoing'   => '#15803d',
                'completed' => '#475569',
                'cancelled' => '#b91c1c',
                default     => '#475569',
            };

            $startDate = $e->start_date ? Carbon::parse($e->start_date) : null;
            $endDate = $e->end_date ? Carbon::parse($e->end_date) : null;
            $days = ($startDate && $endDate) ? ($startDate->diffInDays($endDate) + 1) : 1;
            $dateRange = ($startDate && $endDate)
                ? ($startDate->toDateString() === $endDate->toDateString() ? $startDate->format('M d, Y') : ($startDate->format('M d, Y') . ' — ' . $endDate->format('M d, Y')))
                : 'TBD';

            $timeStr = $e->all_day ? 'All Day' : (($e->start_time ?: '') . ($e->end_time ? ' - ' . $e->end_time : ''));
            $title = htmlspecialchars($e->title);
            $cat = htmlspecialchars($e->category?->name ?? 'General');
            $yearSem = htmlspecialchars("{$e->academic_year} • {$e->semester}");
            $desc = htmlspecialchars($e->description ?? '—');
            $status = htmlspecialchars(ucfirst($e->status ?? 'upcoming'));

            $rowsHtml .= "
                <tr style='border-bottom: 1px solid #e2e8f0; font-size: 10px;'>
                    <td style='padding: 7px 8px; text-align: center; color: #64748b;'>" . ($index + 1) . "</td>
                    <td style='padding: 7px 8px; font-weight: bold; color: #0f172a;'>{$title}</td>
                    <td style='padding: 7px 8px; color: #475569;'>{$cat}</td>
                    <td style='padding: 7px 8px; color: #4338ca; font-weight: 600;'>{$yearSem}</td>
                    <td style='padding: 7px 8px; font-weight: 600; color: #1e293b;'>{$dateRange}</td>
                    <td style='padding: 7px 8px; color: #64748b; text-align: center;'>{$days} d</td>
                    <td style='padding: 7px 8px; color: #475569;'>{$timeStr}</td>
                    <td style='padding: 7px 8px; text-align: center;'>
                        <span style='background-color: {$badgeBg}; color: {$badgeColor}; padding: 2px 7px; border-radius: 4px; font-weight: bold; font-size: 9px; text-transform: uppercase;'>
                            {$status}
                        </span>
                    </td>
                    <td style='padding: 7px 8px; color: #64748b;'>{$desc}</td>
                </tr>
            ";
        }

        $totalCount = $events->count();

        return "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Wollo University - Official Academic Calendar</title>
            <style>
                body { font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif; color: #1e293b; margin: 0; padding: 18px; background: #fff; font-size: 10px; }
                .header { border-bottom: 2px solid #4338ca; padding-bottom: 12px; margin-bottom: 14px; }
                .inst-title { font-size: 18px; font-weight: bold; color: #4338ca; text-transform: uppercase; letter-spacing: 0.5px; }
                .doc-subtitle { font-size: 12px; font-weight: bold; color: #0f172a; margin-top: 3px; }
                .meta { font-size: 9px; color: #64748b; margin-top: 4px; }
                .summary-table { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
                .summary-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; font-size: 10px; font-weight: 600; }
                table.data-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
                table.data-table th { background: #4338ca; color: #ffffff; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.3px; font-weight: bold; }
                table.data-table tr:nth-child(even) td { background-color: #f8fafc; }
                .footer { margin-top: 24px; border-top: 1px solid #cbd5e1; padding-top: 8px; font-size: 8px; color: #94a3b8; text-align: center; }
                .no-print { margin-bottom: 12px; }
                .print-btn { background: #4338ca; color: #ffffff; border: none; padding: 6px 14px; border-radius: 6px; font-weight: bold; font-size: 11px; cursor: pointer; }
                @media print {
                    .no-print { display: none !important; }
                    body { padding: 0 !important; }
                }
            </style>
        </head>
        <body>
            <div class='no-print'>
                <button onclick='window.print()' class='print-btn'>🖨️ Print / Save as PDF</button>
            </div>
            <div class='header'>
                <div class='inst-title'>Wollo University</div>
                <div class='doc-subtitle'>Official University Academic Calendar &amp; Milestone Schedule</div>
                <div class='meta'>Generated: {$generatedAt} &bull; Academic Term: {$currentYear} {$currentSemester} &bull; Total Scheduled Events: {$totalCount}</div>
            </div>
            <table class='summary-table'>
                <tr>
                    <td style='width: 50%; padding-right: 6px;'>
                        <div class='summary-card'>Active Term: <strong style='color:#4338ca;'>{$currentYear} {$currentSemester}</strong></div>
                    </td>
                    <td style='width: 50%; padding-left: 6px;'>
                        <div class='summary-card'>Scheduled Milestones: <strong>{$totalCount}</strong> Events Recorded</div>
                    </td>
                </tr>
            </table>
            <table class='data-table'>
                <thead>
                    <tr>
                        <th style='text-align: center; width: 25px;'>#</th>
                        <th>Event Title</th>
                        <th>Category</th>
                        <th>Academic Period</th>
                        <th>Dates</th>
                        <th style='text-align: center;'>Length</th>
                        <th>Time</th>
                        <th style='text-align: center;'>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
            </table>
            <div class='footer'>
                Official Academic Calendar issued by Wollo University &bull; Office of the Academic Registrar and System Administration &bull; Confidential
            </div>
        </body>
        </html>";
    }
}
