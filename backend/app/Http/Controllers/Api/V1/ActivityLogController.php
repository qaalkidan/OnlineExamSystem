<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    /**
     * Get paginated activity logs with real statistics and filter options.
     */
    public function index(Request $request): JsonResponse
    {
        // ── Real Global Statistics (Source of Truth) ────────────────────────
        $totalLogs = ActivityLog::count();
        $todayLogs = ActivityLog::whereDate('created_at', Carbon::today())->count();
        $successfulLogs = ActivityLog::where('log_status', 'Success')->count();
        $failedLogs = ActivityLog::where('log_status', 'Failed')->count();
        $securityLogs = ActivityLog::where(function ($q) {
            $q->whereIn('module', ['Authentication', 'Exam Recovery'])
              ->orWhereIn('type', ['Login', 'Login Failed', 'Approved Recovery', 'Connection Restored']);
        })->count();
        $authLogs = ActivityLog::where(function ($q) {
            $q->where('module', 'Authentication')
              ->orWhereIn('type', ['Login', 'Login Failed']);
        })->count();
        $dataChangesLogs = ActivityLog::whereIn('type', [
            'Created', 'Updated', 'Deleted', 'Assigned', 'Status Changed', 'Exported'
        ])->count();

        // ── Dynamic Filter Options from Database ─────────────────────────────
        $dbModules = ActivityLog::whereNotNull('module')
            ->where('module', '!=', '')
            ->distinct()
            ->pluck('module')
            ->filter()
            ->values();

        $dbActions = ActivityLog::whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->pluck('type')
            ->filter()
            ->values();

        $activeUserIds = ActivityLog::whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $dbUsers = User::whereIn('id', $activeUserIds)
            ->select('id', 'name', 'email', 'role')
            ->orderBy('name')
            ->get();

        // ── Query Builder with Relationships ─────────────────────────────────
        $query = ActivityLog::with([
            'user:id,name,email,role,department_id',
            'user.department:id,name,code',
            'department:id,name,code'
        ]);

        // ── Server-Side Filters ──────────────────────────────────────────────
        // 1. Search (user name, email, details, action, module, IP)
        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('details', 'like', $search)
                  ->orWhere('action', 'like', $search)
                  ->orWhere('type', 'like', $search)
                  ->orWhere('module', 'like', $search)
                  ->orWhere('ip_address', 'like', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', $search)
                         ->orWhere('email', 'like', $search);
                  });
            });
        }

        // 2. Module Filter
        if ($request->filled('module') && $request->query('module') !== 'all') {
            $query->where('module', $request->query('module'));
        }

        // 3. Action Filter
        if ($request->filled('action') && $request->query('action') !== 'all') {
            $query->where('type', $request->query('action'));
        }

        // 4. Role Filter
        if ($request->filled('role') && $request->query('role') !== 'all') {
            $role = $request->query('role');
            $query->where(function ($q) use ($role) {
                $q->where('actor_role', $role)
                  ->orWhereHas('user', function ($uq) use ($role) {
                      $uq->where('role', $role);
                  });
            });
        }

        // 5. Specific User Filter
        if ($request->filled('user_id') && $request->query('user_id') !== 'all') {
            $query->where('user_id', $request->query('user_id'));
        }

        // 6. Status Filter
        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('log_status', $request->query('status'));
        }

        // 7. Category / KPI Shortcut Filter
        if ($request->filled('category') && $request->query('category') !== 'all') {
            $cat = $request->query('category');
            if ($cat === 'today') {
                $query->whereDate('created_at', Carbon::today());
            } elseif ($cat === 'successful') {
                $query->where('log_status', 'Success');
            } elseif ($cat === 'failed') {
                $query->where('log_status', 'Failed');
            } elseif ($cat === 'security') {
                $query->where(function ($q) {
                    $q->whereIn('module', ['Authentication', 'Exam Recovery'])
                      ->orWhereIn('type', ['Login', 'Login Failed', 'Approved Recovery', 'Connection Restored']);
                });
            } elseif ($cat === 'data_changes') {
                $query->whereIn('type', ['Created', 'Updated', 'Deleted', 'Assigned', 'Status Changed', 'Exported']);
            }
        }

        // 8. Date Range / Quick Date
        if ($request->filled('quick_date')) {
            $qd = $request->query('quick_date');
            if ($qd === 'today') {
                $query->whereDate('created_at', Carbon::today());
            } elseif ($qd === 'yesterday') {
                $query->whereDate('created_at', Carbon::yesterday());
            } elseif ($qd === '7days') {
                $query->where('created_at', '>=', Carbon::now()->subDays(7));
            } elseif ($qd === '30days') {
                $query->where('created_at', '>=', Carbon::now()->subDays(30));
            }
        } elseif ($request->filled('date_from') || $request->filled('date_to')) {
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->query('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->query('date_to'));
            }
        }

        // ── Sorting ──────────────────────────────────────────────────────────
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = strtolower($request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'user') {
            $query->leftJoin('users', 'activity_logs.user_id', '=', 'users.id')
                  ->select('activity_logs.*')
                  ->orderBy('users.name', $sortOrder);
        } elseif (in_array($sortBy, ['created_at', 'type', 'module', 'log_status'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest('created_at');
        }

        // ── Pagination ───────────────────────────────────────────────────────
        $perPageParam = $request->query('per_page', 10);
        if ($perPageParam === 'all') {
            $count = (clone $query)->count();
            $paginated = $query->paginate($count > 0 ? $count : 10);
        } else {
            $perPage = max(1, min(100, (int)$perPageParam));
            $paginated = $query->paginate($perPage);
        }

        // ── Format Output Logs ───────────────────────────────────────────────
        $mappedLogs = collect($paginated->items())->map(function ($log) {
            $rawRole = $log->actor_role ?? ($log->user ? $log->user->role : 'system');
            $byWho = match($rawRole) {
                'admin'     => 'Super Admin',
                'dept_head' => $log->user && $log->user->department ? $log->user->department->name . ' Department Head' : 'Department Head',
                'instructor'=> 'Instructor',
                'student'   => 'Student',
                default     => 'System',
            };

            // Extract affected resource if mentioned in quotes (e.g. Created course "Networking")
            $resource = null;
            if (preg_match('/"([^"]+)"/', $log->details ?: '', $matches)) {
                $resource = $matches[1];
            }

            // Severity mapping based on actual log status and action type
            $severity = 'INFO';
            if ($log->log_status === 'Failed' || $log->type === 'Login Failed') {
                $severity = 'ERROR';
            } elseif (in_array($log->type, ['Deleted', 'Cancelled', 'Paused'])) {
                $severity = 'WARNING';
            } elseif (in_array($log->type, ['Approved Recovery', 'Connection Restored'])) {
                $severity = 'SECURITY';
            } elseif ($log->type === 'Created' || $log->log_status === 'Success') {
                $severity = 'SUCCESS';
            }

            $createdAt = $log->created_at ? Carbon::parse($log->created_at) : Carbon::now();

            return [
                'id'          => $log->id,
                'time'        => $createdAt->format('M d, Y') . "\n" . $createdAt->format('h:i:s A'),
                'raw_time'    => $createdAt->toIso8601String(),
                'user'        => $log->user ? $log->user->name : ($rawRole === 'system' ? 'System' : 'Unknown User'),
                'user_id'     => $log->user_id,
                'email'       => $log->user ? $log->user->email : ($rawRole === 'system' ? 'system@wollo.edu.et' : ''),
                'role'        => $byWho,
                'raw_role'    => $rawRole,
                'by_who'      => $byWho,
                'action'      => $log->type ?: 'Activity',
                'actionType'  => $log->type ?: 'Activity',
                'module'      => $log->module ?? 'System',
                'description' => $log->details ?: $log->action ?: '',
                'resource'    => $resource,
                'ip_address'  => $log->ip_address ?? '127.0.0.1',
                'status'      => $log->log_status ?? 'Success',
                'severity'    => $severity,
                'is_read'     => (bool)$log->is_read,
                'department'  => $log->department ? $log->department->name : ($log->user && $log->user->department ? $log->user->department->name : null),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $mappedLogs,
            'pagination' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
            'stats' => [
                'total'           => $totalLogs,
                'today'           => $todayLogs,
                'successful'      => $successfulLogs,
                'failed'          => $failedLogs,
                'security_events' => $securityLogs,
                'auth_events'     => $authLogs,
                'data_changes'    => $dataChangesLogs,
            ],
            'filter_options' => [
                'modules' => $dbModules,
                'actions' => $dbActions,
                'roles'   => [
                    ['value' => 'admin', 'label' => 'Super Admin'],
                    ['value' => 'dept_head', 'label' => 'Department Head'],
                    ['value' => 'instructor', 'label' => 'Instructor'],
                    ['value' => 'student', 'label' => 'Student'],
                ],
                'users'   => $dbUsers,
            ],
        ]);
    }

    /**
     * Get single log details.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $log = ActivityLog::with([
            'user:id,name,email,role,department_id',
            'user.department:id,name,code',
            'department:id,name,code'
        ])->findOrFail($id);

        $rawRole = $log->actor_role ?? ($log->user ? $log->user->role : 'system');
        $byWho = match($rawRole) {
            'admin'     => 'Super Admin',
            'dept_head' => $log->user && $log->user->department ? $log->user->department->name . ' Department Head' : 'Department Head',
            'instructor'=> 'Instructor',
            'student'   => 'Student',
            default     => 'System',
        };

        $resource = null;
        if (preg_match('/"([^"]+)"/', $log->details ?: '', $matches)) {
            $resource = $matches[1];
        }

        $severity = 'INFO';
        if ($log->log_status === 'Failed' || $log->type === 'Login Failed') {
            $severity = 'ERROR';
        } elseif (in_array($log->type, ['Deleted', 'Cancelled', 'Paused'])) {
            $severity = 'WARNING';
        } elseif (in_array($log->type, ['Approved Recovery', 'Connection Restored'])) {
            $severity = 'SECURITY';
        } elseif ($log->type === 'Created' || $log->log_status === 'Success') {
            $severity = 'SUCCESS';
        }

        $createdAt = $log->created_at ? Carbon::parse($log->created_at) : Carbon::now();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'          => $log->id,
                'time'        => $createdAt->format('M d, Y h:i:s A'),
                'raw_time'    => $createdAt->toIso8601String(),
                'user'        => $log->user ? $log->user->name : 'System',
                'user_id'     => $log->user_id,
                'email'       => $log->user ? $log->user->email : '',
                'role'        => $byWho,
                'raw_role'    => $rawRole,
                'by_who'      => $byWho,
                'action'      => $log->type ?: 'Activity',
                'module'      => $log->module ?? 'System',
                'description' => $log->details ?: $log->action ?: '',
                'resource'    => $resource,
                'ip_address'  => $log->ip_address ?? '127.0.0.1',
                'status'      => $log->log_status ?? 'Success',
                'severity'    => $severity,
                'department'  => $log->department ? $log->department->name : ($log->user && $log->user->department ? $log->user->department->name : 'General'),
                'is_read'     => (bool)$log->is_read,
            ]
        ]);
    }

    /**
     * Export activity logs as CSV or Printable HTML/PDF report.
     */
    public function export(Request $request)
    {
        $query = ActivityLog::with([
            'user:id,name,email,role,department_id',
            'department:id,name,code'
        ]);

        // Apply same filters as index
        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('details', 'like', $search)
                  ->orWhere('action', 'like', $search)
                  ->orWhere('type', 'like', $search)
                  ->orWhere('module', 'like', $search)
                  ->orWhere('ip_address', 'like', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', $search)
                         ->orWhere('email', 'like', $search);
                  });
            });
        }

        if ($request->filled('module') && $request->query('module') !== 'all') {
            $query->where('module', $request->query('module'));
        }

        if ($request->filled('action') && $request->query('action') !== 'all') {
            $query->where('type', $request->query('action'));
        }

        if ($request->filled('role') && $request->query('role') !== 'all') {
            $role = $request->query('role');
            $query->where(function ($q) use ($role) {
                $q->where('actor_role', $role)
                  ->orWhereHas('user', function ($uq) use ($role) {
                      $uq->where('role', $role);
                  });
            });
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('log_status', $request->query('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        $logs = $query->latest('created_at')->get();
        $format = strtolower($request->query('format', 'csv'));

        // Record the export action in activity logs
        LogActivity::record('Exported', 'Activity Logs', "Exported {$logs->count()} activity logs as {$format} (Super Admin Audit)");

        if ($format === 'pdf' || $format === 'html' || $format === 'print') {
            return $this->generatePrintableReport($logs, $request);
        }

        // CSV Export
        $filename = 'wollo_university_audit_logs_' . date('Y_m_d_His') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($logs) {
            $output = fopen('php://output', 'w');
            // Write UTF-8 BOM for Microsoft Excel compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, [
                'Log ID',
                'Timestamp',
                'Actor Name',
                'Email',
                'Role',
                'Action Type',
                'Module',
                'Description / Event Details',
                'IP Address',
                'Status'
            ]);

            foreach ($logs as $log) {
                $rawRole = $log->actor_role ?? ($log->user ? $log->user->role : 'system');
                $roleLabel = match($rawRole) {
                    'admin'     => 'Super Admin',
                    'dept_head' => 'Department Head',
                    'instructor'=> 'Instructor',
                    'student'   => 'Student',
                    default     => 'System',
                };

                fputcsv($output, [
                    $log->id,
                    $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '',
                    $log->user ? $log->user->name : 'System',
                    $log->user ? $log->user->email : '',
                    $roleLabel,
                    $log->type,
                    $log->module,
                    $log->details ?: $log->action,
                    $log->ip_address,
                    $log->log_status,
                ]);
            }

            fclose($output);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    /**
     * Generate an official Wollo University printable HTML/PDF audit log report.
     */
    private function generatePrintableReport($logs, Request $request)
    {
        $generatedAt = Carbon::now()->format('F j, Y, g:i A');
        $totalCount = $logs->count();
        $successfulCount = $logs->where('log_status', 'Success')->count();
        $failedCount = $logs->where('log_status', 'Failed')->count();

        $rowsHtml = '';
        foreach ($logs as $log) {
            $rawRole = $log->actor_role ?? ($log->user ? $log->user->role : 'system');
            $roleLabel = match($rawRole) {
                'admin'     => 'Super Admin',
                'dept_head' => 'Dept Head',
                'instructor'=> 'Instructor',
                'student'   => 'Student',
                default     => 'System',
            };

            $statusBadge = $log->log_status === 'Success'
                ? '<span style="color:#059669; font-weight:700;">Success</span>'
                : '<span style="color:#dc2626; font-weight:700;">Failed</span>';

            $timeStr = $log->created_at ? $log->created_at->format('M d, Y h:i A') : '-';
            $userName = htmlspecialchars($log->user ? $log->user->name : 'System');
            $userEmail = htmlspecialchars($log->user ? $log->user->email : '-');
            $action = htmlspecialchars($log->type);
            $module = htmlspecialchars($log->module ?? 'General');
            $details = htmlspecialchars($log->details ?: $log->action ?: '-');
            $ip = htmlspecialchars($log->ip_address ?? '127.0.0.1');

            $rowsHtml .= "
                <tr style='border-bottom: 1px solid #e2e8f0; font-size: 11px;'>
                    <td style='padding: 8px 10px; color: #475569; font-weight:600;'>{$timeStr}</td>
                    <td style='padding: 8px 10px;'>
                        <div style='font-weight:700; color:#1e293b;'>{$userName}</div>
                        <div style='color:#64748b; font-size:10px;'>{$userEmail}</div>
                    </td>
                    <td style='padding: 8px 10px; color:#4338ca; font-weight:600;'>{$roleLabel}</td>
                    <td style='padding: 8px 10px; font-weight:700; color:#1e293b;'>{$action}</td>
                    <td style='padding: 8px 10px; color:#475569;'>{$module}</td>
                    <td style='padding: 8px 10px; color:#334155;'>{$details}</td>
                    <td style='padding: 8px 10px; font-family: monospace; color:#64748b;'>{$ip}</td>
                    <td style='padding: 8px 10px;'>{$statusBadge}</td>
                </tr>
            ";
        }

        $html = "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Wollo University - Institutional Audit & Activity Report</title>
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
                    <div class='subtitle'>SYSTEM ADMINISTRATION &bull; INSTITUTIONAL AUDIT &amp; ACTIVITY LOG REPORT</div>
                </div>
                <div class='meta'>
                    <div><strong>Generated:</strong> {$generatedAt}</div>
                    <div><strong>Classification:</strong> Official University Record</div>
                </div>
            </div>
            <div class='summary-bar'>
                <div>Total Recorded Activities: <strong style='color:#4338ca;'>{$totalCount}</strong></div>
                <div>Successful Actions: <strong style='color:#059669;'>{$successfulCount}</strong></div>
                <div>Failed Actions: <strong style='color:#dc2626;'>{$failedCount}</strong></div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
            </table>
            <div style='margin-top: 30px; border-top: 1px solid #cbd5e1; padding-top: 12px; font-size: 10px; color: #94a3b8; text-align: center;'>
                This is an immutable electronic institutional record generated from the Wollo University Online Examination &amp; Academic Administration System.
            </div>
        </body>
        </html>";

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Get the count of unread activity logs.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = ActivityLog::where('is_read', false)->count();
        return response()->json(['count' => $count]);
    }

    /**
     * Mark a specific activity log as read.
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $log = ActivityLog::findOrFail($id);
        $log->update(['is_read' => true]);

        return response()->json(['message' => 'Marked as read successfully.']);
    }

    /**
     * Mark all unread activity logs as read at once.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = ActivityLog::where('is_read', false)->update(['is_read' => true]);
        return response()->json(['message' => "Marked {$updated} logs as read."]);
    }
}
