<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\AcademicEvent;
use App\Models\EventCategory;
use App\Models\SystemSetting;
use Carbon\Carbon;
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
     * Export academic events as CSV or Printable HTML/PDF report.
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

        if ($format === 'pdf' || $format === 'html' || $format === 'print') {
            return $this->generatePrintableCalendar($events);
        }

        // CSV Export
        $filename = 'wollo_university_academic_calendar_' . date('Y_m_d_His') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($events) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($output, [
                'ID',
                'Event Title',
                'Category',
                'Academic Year',
                'Semester',
                'Start Date',
                'End Date',
                'All Day',
                'Start Time',
                'End Time',
                'Status',
                'Description',
                'Created By'
            ]);

            foreach ($events as $e) {
                fputcsv($output, [
                    $e->id,
                    $e->title,
                    $e->category?->name ?? 'General',
                    $e->academic_year,
                    $e->semester,
                    $e->start_date?->toDateString(),
                    $e->end_date?->toDateString(),
                    $e->all_day ? 'Yes' : 'No',
                    $e->start_time ?? '-',
                    $e->end_time ?? '-',
                    ucfirst($e->status),
                    $e->description ?? '-',
                    $e->creator?->name ?? 'System',
                ]);
            }

            fclose($output);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Generate an official Wollo University printable HTML/PDF Academic Calendar report.
     */
    private function generatePrintableCalendar($events)
    {
        $generatedAt = Carbon::now()->format('F j, Y, g:i A');
        $currentYear = SystemSetting::where('key', 'academicYear')->value('value') ?: '2026';
        $currentSemester = SystemSetting::where('key', 'semester')->value('value') ?: 'Second Semester';

        $rowsHtml = '';
        foreach ($events as $e) {
            $statusColor = match($e->status) {
                'upcoming'  => '#4338ca',
                'ongoing'   => '#059669',
                'completed' => '#64748b',
                'cancelled' => '#dc2626',
                default     => '#334155',
            };

            $title = htmlspecialchars($e->title);
            $cat = htmlspecialchars($e->category?->name ?? 'General');
            $yearSem = htmlspecialchars("{$e->academic_year} • {$e->semester}");
            $dates = htmlspecialchars("{$e->start_date?->toDateString()} to {$e->end_date?->toDateString()}");
            $desc = htmlspecialchars($e->description ?? '-');
            $status = htmlspecialchars(ucfirst($e->status));

            $rowsHtml .= "
                <tr style='border-bottom: 1px solid #e2e8f0; font-size: 11px;'>
                    <td style='padding: 8px 10px; font-weight:700; color:#1e293b;'>{$title}</td>
                    <td style='padding: 8px 10px; color:#475569;'>{$cat}</td>
                    <td style='padding: 8px 10px; color:#4338ca; font-weight:600;'>{$yearSem}</td>
                    <td style='padding: 8px 10px; font-weight:600;'>{$dates}</td>
                    <td style='padding: 8px 10px; color:{$statusColor}; font-weight:700;'>{$status}</td>
                    <td style='padding: 8px 10px; color:#64748b;'>{$desc}</td>
                </tr>
            ";
        }

        $totalCount = $events->count();

        $html = "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Wollo University - Academic Calendar & Schedule</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; margin: 0; padding: 24px; background: #fff; }
                .header { border-bottom: 2px solid #4338ca; padding-bottom: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
                .title { font-size: 20px; font-weight: 800; color: #1e1b4b; text-transform: uppercase; letter-spacing: 0.5px; }
                .subtitle { font-size: 12px; color: #64748b; margin-top: 4px; font-weight: 600; }
                .meta { text-align: right; font-size: 11px; color: #475569; }
                .summary-bar { display: flex; gap: 20px; margin-bottom: 20px; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12px; font-weight: 600; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th { background: #f1f5f9; color: #475569; text-align: left; padding: 8px 10px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; border-bottom: 2px solid #cbd5e1; }
                @media print {
                    body { padding: 10px; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class='no-print' style='margin-bottom: 16px;'>
                <button onclick='window.print()' style='background: #4338ca; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 700; cursor: pointer;'>🖨️ Print / Save as PDF</button>
            </div>
            <div class='header'>
                <div>
                    <div class='title'>Wollo University</div>
                    <div class='subtitle'>OFFICIAL ACADEMIC CALENDAR &amp; PLANNING SCHEDULE &bull; {$currentYear} {$currentSemester}</div>
                </div>
                <div class='meta'>
                    <div><strong>Generated:</strong> {$generatedAt}</div>
                    <div><strong>Document:</strong> Official University Schedule</div>
                </div>
            </div>
            <div class='summary-bar'>
                <div>Academic Term: <strong style='color:#4338ca;'>{$currentYear} {$currentSemester}</strong></div>
                <div>Total Scheduled Events: <strong>{$totalCount}</strong></div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Event Title</th>
                        <th>Category</th>
                        <th>Academic Period</th>
                        <th>Scheduled Dates</th>
                        <th>Status</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
            </table>
            <div style='margin-top: 30px; border-top: 1px solid #cbd5e1; padding-top: 12px; font-size: 10px; color: #94a3b8; text-align: center;'>
                Official academic calendar issued by the Wollo University Office of the Academic Registrar and System Administration.
            </div>
        </body>
        </html>";

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
