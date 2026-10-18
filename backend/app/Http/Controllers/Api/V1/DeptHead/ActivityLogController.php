<?php

namespace App\Http\Controllers\Api\V1\DeptHead;

use App\Http\Controllers\Controller;
use App\Helpers\LogActivity;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    /**
     * Resolve the department ID for the logged-in Department Head.
     */
    private function resolveDeptId(Request $request): ?int
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        if ($user->department_id) {
            return $user->department_id;
        }

        $dept = Department::where('head_id', $user->id)->first();
        if ($dept) {
            $user->update(['department_id' => $dept->id]);
            return $dept->id;
        }

        return null;
    }

    /**
     * Get paginated activity logs for department head with dynamic summary metrics and top users.
     */
    public function index(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();

        // Base query scoped to department or system events
        $baseQuery = ActivityLog::with(['user:id,name,email,role,department_id,profile_picture', 'department:id,name,code']);

        if ($deptId) {
            $baseQuery->where(function ($q) use ($deptId, $user) {
                $q->where('department_id', $deptId)
                  ->orWhere('user_id', $user->id)
                  ->orWhereHas('user', function ($uq) use ($deptId) {
                      $uq->where('department_id', $deptId);
                  });
            });
        }

        // Summary counts from real database data
        $allCount = (clone $baseQuery)->count();
        $todayCount = (clone $baseQuery)->whereDate('created_at', Carbon::today())->count();
        $successfulCount = (clone $baseQuery)->where('log_status', 'Success')->count();
        $failedCount = (clone $baseQuery)->where('log_status', 'Failed')->count();
        $securityCount = (clone $baseQuery)->where(function ($q) {
            $q->whereIn('type', ['Login', 'Login Failed', 'Security', 'Password Changed'])
              ->orWhere('module', 'Authentication');
        })->count();
        $loginsCount = (clone $baseQuery)->where(function ($q) {
            $q->whereIn('type', ['Login', 'Login Failed'])
              ->orWhere('module', 'Authentication');
        })->count();
        $dataChangesCount = (clone $baseQuery)->whereIn('type', [
            'Created', 'Updated', 'Deleted', 'Exported', 'Submitted', 'Assigned', 'Status Changed'
        ])->count();
        $systemEventsCount = (clone $baseQuery)->where(function ($q) {
            $q->whereIn('type', ['System Event', 'Backup'])
              ->orWhere('module', 'System');
        })->count();

        // Distinct available modules, actions, and roles from real logs
        $availableModules = (clone $baseQuery)->whereNotNull('module')->distinct()->pluck('module')->filter()->values();
        $availableActions = (clone $baseQuery)->whereNotNull('type')->distinct()->pluck('type')->filter()->values();
        $availableRoles = (clone $baseQuery)->whereNotNull('actor_role')->distinct()->pluck('actor_role')->filter()->values();

        // Top Active Users
        $topUsersGroup = (clone $baseQuery)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, count(*) as total_actions')
            ->groupBy('user_id')
            ->orderByDesc('total_actions')
            ->limit(5)
            ->get();

        $topActiveUsers = $topUsersGroup->map(function ($item) {
            $u = User::find($item->user_id);
            if (!$u) {
                return null;
            }
            $roleLabel = match($u->role) {
                'admin'      => 'Super Admin',
                'dept_head'  => 'Department Head',
                'instructor' => 'Instructor',
                'student'    => 'Student',
                default      => ucfirst($u->role),
            };
            return [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
                'role'  => $roleLabel,
                'count' => (int)$item->total_actions,
            ];
        })->filter()->values();

        // Apply filters
        $filteredQuery = clone $baseQuery;

        // Activity category filter
        $filter = $request->query('filter', 'All Activities');
        if ($filter === 'Successful') {
            $filteredQuery->where('log_status', 'Success');
        } elseif ($filter === 'Failed') {
            $filteredQuery->where('log_status', 'Failed');
        } elseif ($filter === 'Logins') {
            $filteredQuery->where(function ($q) {
                $q->whereIn('type', ['Login', 'Login Failed'])
                  ->orWhere('module', 'Authentication');
            });
        } elseif ($filter === 'Data Changes') {
            $filteredQuery->whereIn('type', [
                'Created', 'Updated', 'Deleted', 'Exported', 'Submitted', 'Assigned', 'Status Changed'
            ]);
        } elseif ($filter === 'System Events') {
            $filteredQuery->where(function ($q) {
                $q->whereIn('type', ['System Event', 'Backup'])
                  ->orWhere('module', 'System');
            });
        }

        // Granular Module Filter
        if ($request->filled('module') && $request->query('module') !== 'All Modules') {
            $filteredQuery->where('module', $request->query('module'));
        }

        // Granular Action/Type Filter
        if ($request->filled('action_type') && $request->query('action_type') !== 'All Actions') {
            $filteredQuery->where('type', $request->query('action_type'));
        }

        // Granular Status Filter
        if ($request->filled('status') && $request->query('status') !== 'All Statuses') {
            $filteredQuery->where('log_status', $request->query('status'));
        }

        // Granular Role Filter
        if ($request->filled('role') && $request->query('role') !== 'All Roles') {
            $role = strtolower($request->query('role'));
            $filteredQuery->where(function ($q) use ($role) {
                $q->where('actor_role', $role)
                  ->orWhereHas('user', function ($uq) use ($role) {
                      $uq->where('role', $role);
                  });
            });
        }

        // Date Range Filtering
        if ($request->filled('date_from')) {
            $filteredQuery->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $filteredQuery->whereDate('created_at', '<=', $request->query('date_to'));
        }

        // Search Query
        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $filteredQuery->where(function ($q) use ($search) {
                $q->where('action', 'like', $search)
                  ->orWhere('type', 'like', $search)
                  ->orWhere('module', 'like', $search)
                  ->orWhere('details', 'like', $search)
                  ->orWhere('ip_address', 'like', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', $search)
                         ->orWhere('email', 'like', $search);
                  });
            });
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = strtolower($request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (in_array($sortBy, ['created_at', 'type', 'module', 'log_status', 'id'])) {
            $filteredQuery->orderBy($sortBy, $sortOrder);
        } else {
            $filteredQuery->latest();
        }

        // Pagination
        $perPage = (int)$request->query('per_page', 10);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 10;
        }
        $paginated = $filteredQuery->paginate($perPage);

        // Format logs for frontend
        $mappedLogs = collect($paginated->items())->map(function ($log) {
            $rawRole = $log->actor_role ?? ($log->user ? $log->user->role : 'system');
            $roleLabel = match($rawRole) {
                'admin'      => 'Super Admin',
                'dept_head'  => 'Department Head',
                'instructor' => 'Instructor',
                'student'    => 'Student',
                default      => 'System',
            };

            $createdAt = $log->created_at ? Carbon::parse($log->created_at) : Carbon::now();
            $dateStr = $createdAt->format('M d, Y');
            $timeStr = $createdAt->format('h:i:s A');
            $timeFormatted = "{$dateStr}\n{$timeStr}";

            return [
                'id'                  => $log->id,
                'time'                => $timeFormatted,
                'date'                => $dateStr,
                'time_only'           => $timeStr,
                'raw_time'            => $createdAt->toIso8601String(),
                'user'                => $log->user ? $log->user->name : ($rawRole === 'system' ? 'System' : 'Unknown User'),
                'email'               => $log->user ? $log->user->email : ($rawRole === 'system' ? 'system@wollo.edu.et' : ''),
                'profile_picture_url' => $log->user?->profile_picture_url,
                'role'                => $roleLabel,
                'raw_role'            => $rawRole,
                'action'              => $log->type ?: 'Activity',
                'actionType'          => $log->type ?: 'System Event',
                'module'              => $log->module ?: 'General',
                'description'         => $log->details ?: $log->action ?: 'System activity recorded',
                'ipAddress'           => $log->ip_address ?: '127.0.0.1',
                'status'              => $log->log_status ?: 'Success',
                'is_read'             => (bool)$log->is_read,
                'department'          => $log->department ? $log->department->name : ($log->user?->department?->name ?? null),
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
            'summary' => [
                'all'          => $allCount,
                'today'        => $todayCount,
                'successful'   => $successfulCount,
                'failed'       => $failedCount,
                'security'     => $securityCount,
                'logins'       => $loginsCount,
                'dataChanges'  => $dataChangesCount,
                'systemEvents' => $systemEventsCount,
            ],
            'filters' => [
                'modules' => $availableModules,
                'actions' => $availableActions,
                'roles'   => $availableRoles,
            ],
            'topActiveUsers' => $topActiveUsers,
        ]);
    }

    /**
     * Get the count of unread activity logs for the department head.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();

        $query = ActivityLog::where('is_read', false);

        if ($deptId) {
            $query->where(function ($q) use ($deptId, $user) {
                $q->where('department_id', $deptId)
                  ->orWhere('user_id', $user->id)
                  ->orWhereHas('user', function ($uq) use ($deptId) {
                      $uq->where('department_id', $deptId);
                  });
            });
        }

        $count = $query->count();

        return response()->json([
            'status' => 'success',
            'count'  => $count,
        ]);
    }

    /**
     * Mark a specific activity log as read for the department head.
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();

        $query = ActivityLog::query();
        if ($deptId) {
            $query->where(function ($q) use ($deptId, $user) {
                $q->where('department_id', $deptId)
                  ->orWhere('user_id', $user->id)
                  ->orWhereHas('user', function ($uq) use ($deptId) {
                      $uq->where('department_id', $deptId);
                  });
            });
        }

        $log = $query->findOrFail($id);
        if (!$log->is_read) {
            $log->update(['is_read' => true]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Marked as read successfully.',
        ]);
    }

    /**
     * Mark all unread activity logs as read for this department head.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();

        $query = ActivityLog::where('is_read', false);
        if ($deptId) {
            $query->where(function ($q) use ($deptId, $user) {
                $q->where('department_id', $deptId)
                  ->orWhere('user_id', $user->id)
                  ->orWhereHas('user', function ($uq) use ($deptId) {
                      $uq->where('department_id', $deptId);
                  });
            });
        }

        $updated = $query->update(['is_read' => true]);

        return response()->json([
            'status'  => 'success',
            'message' => "Marked {$updated} logs as read.",
            'count'   => 0,
        ]);
    }

    /**
     * Get single log details.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();

        $query = ActivityLog::with(['user:id,name,email,role,department_id,profile_picture', 'department:id,name,code']);

        if ($deptId) {
            $query->where(function ($q) use ($deptId, $user) {
                $q->where('department_id', $deptId)
                  ->orWhere('user_id', $user->id)
                  ->orWhereHas('user', function ($uq) use ($deptId) {
                      $uq->where('department_id', $deptId);
                  });
            });
        }

        $log = $query->findOrFail($id);

        if (!$log->is_read) {
            $log->update(['is_read' => true]);
        }

        $rawRole = $log->actor_role ?? ($log->user ? $log->user->role : 'system');
        $roleLabel = match($rawRole) {
            'admin'      => 'Super Admin',
            'dept_head'  => 'Department Head',
            'instructor' => 'Instructor',
            'student'    => 'Student',
            default      => 'System',
        };

        $createdAt = $log->created_at ? Carbon::parse($log->created_at) : Carbon::now();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'                  => $log->id,
                'time'                => $createdAt->format('M d, Y h:i:s A'),
                'date'                => $createdAt->format('M d, Y'),
                'time_only'           => $createdAt->format('h:i:s A'),
                'created_at'          => $createdAt->toIso8601String(),
                'user'                => $log->user ? $log->user->name : 'System',
                'email'               => $log->user ? $log->user->email : '',
                'profile_picture_url' => $log->user?->profile_picture_url,
                'role'                => $roleLabel,
                'raw_role'            => $rawRole,
                'action'              => $log->type ?: 'Activity',
                'module'              => $log->module ?: 'General',
                'description'         => $log->details ?: $log->action ?: 'System activity recorded',
                'ip_address'          => $log->ip_address ?: '127.0.0.1',
                'status'              => $log->log_status ?: 'Success',
                'department'          => $log->department ? $log->department->name : ($log->user?->department?->name ?? 'General / Department Head Operations'),
                'is_read'             => (bool)$log->is_read,
            ]
        ]);
    }

    /**
     * Export activity logs as PDF or CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $user = $request->user();
        $dept = $deptId ? Department::find($deptId) : null;
        $deptName = $dept ? $dept->name : 'Department';

        $query = ActivityLog::with(['user:id,name,email,role,department_id', 'department:id,name']);

        if ($deptId) {
            $query->where(function ($q) use ($deptId, $user) {
                $q->where('department_id', $deptId)
                  ->orWhere('user_id', $user?->id)
                  ->orWhereHas('user', function ($uq) use ($deptId) {
                      $uq->where('department_id', $deptId);
                  });
            });
        }

        $filter = $request->query('filter');
        if ($filter && $filter !== 'All Activities') {
            if ($filter === 'Successful') {
                $query->where('log_status', 'Success');
            } elseif ($filter === 'Failed') {
                $query->where('log_status', 'Failed');
            } elseif ($filter === 'Logins') {
                $query->where(function ($q) {
                    $q->whereIn('type', ['Login', 'Login Failed'])
                      ->orWhere('module', 'Authentication');
                });
            } elseif ($filter === 'Data Changes') {
                $query->whereIn('type', ['Created', 'Updated', 'Deleted', 'Exported', 'Submitted', 'Assigned', 'Status Changed']);
            } elseif ($filter === 'System Events') {
                $query->where(function ($q) {
                    $q->whereIn('type', ['System Event', 'Backup'])
                      ->orWhere('module', 'System');
                });
            }
        }

        if ($request->filled('module') && $request->query('module') !== 'All Modules') {
            $query->where('module', $request->query('module'));
        }

        if ($request->filled('action_type') && $request->query('action_type') !== 'All Actions') {
            $query->where('type', $request->query('action_type'));
        }

        if ($request->filled('status') && $request->query('status') !== 'All Statuses') {
            $query->where('log_status', $request->query('status'));
        }

        if ($request->filled('role') && $request->query('role') !== 'All Roles') {
            $role = strtolower($request->query('role'));
            $query->where(function ($q) use ($role) {
                $q->where('actor_role', $role)
                  ->orWhereHas('user', function ($uq) use ($role) {
                      $uq->where('role', $role);
                  });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', $search)
                  ->orWhere('type', 'like', $search)
                  ->orWhere('module', 'like', $search)
                  ->orWhere('details', 'like', $search)
                  ->orWhere('ip_address', 'like', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', $search)
                         ->orWhere('email', 'like', $search);
                  });
            });
        }

        $logs = $query->latest()->get();
        $format = strtolower($request->query('format', 'csv'));

        if ($format === 'pdf') {
            return $this->exportPdf($logs, $deptName, $deptId, $user);
        }

        return $this->exportCsv($logs, $deptName, $deptId);
    }

    /**
     * Export activity logs as a styled official Wollo University PDF report.
     */
    private function exportPdf($logs, string $deptName, ?int $deptId, ?User $user): JsonResponse
    {
        $generatedAt = Carbon::now()->format('F j, Y  H:i');
        $totalRecords = $logs->count();
        $successfulCount = $logs->where('log_status', 'Success')->count();
        $failedCount = $logs->where('log_status', 'Failed')->count();
        $actorName = $user ? htmlspecialchars($user->name) : 'Department Head';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<title>Wollo University — Activity &amp; Audit Logs (' . htmlspecialchars($deptName) . ')</title>';
        $html .= '<style>
            @page { margin: 25px 25px 35px 25px; }
            body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 8px; color: #1e293b; }
            .header-table { width: 100%; border-bottom: 2px solid #5138ed; padding-bottom: 10px; margin-bottom: 12px; }
            .university-title { font-size: 15px; font-weight: bold; color: #1e1b4b; text-transform: uppercase; letter-spacing: 0.5px; }
            .dept-title { font-size: 11px; font-weight: bold; color: #5138ed; margin-top: 2px; }
            .meta { font-size: 8px; color: #64748b; text-align: right; line-height: 1.4; }
            .summary-table { width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 12px; margin-bottom: 10px; font-size: 8px; }
            table.data-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
            table.data-table th { background: #5138ed; color: #ffffff; padding: 6px 4px; text-align: left; font-size: 7.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; }
            table.data-table td { padding: 5px 4px; border-bottom: 1px solid #e2e8f0; font-size: 7px; color: #334155; vertical-align: top; }
            table.data-table tr:nth-child(even) td { background: #f8fafc; }
            .badge-success { display: inline-block; padding: 2px 5px; border-radius: 4px; font-weight: bold; color: #15803d; background: #dcfce7; font-size: 6.5px; }
            .badge-failed { display: inline-block; padding: 2px 5px; border-radius: 4px; font-weight: bold; color: #be123c; background: #ffe4e6; font-size: 6.5px; }
            .action-badge { display: inline-block; padding: 2px 4px; border-radius: 4px; font-weight: bold; color: #4338ca; background: #e0e7ff; font-size: 6.5px; }
            .footer { position: fixed; bottom: 10px; left: 25px; right: 25px; font-size: 7.5px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 4px; }
        </style></head><body>';

        $html .= '<table class="header-table"><tr>';
        $html .= '<td><div class="university-title">Wollo University</div>';
        $html .= '<div class="dept-title">Department of ' . htmlspecialchars($deptName) . ' — Activity &amp; Audit Trail</div></td>';
        $html .= '<td class="meta"><strong>Date Generated:</strong> ' . $generatedAt . '<br><strong>Generated By:</strong> ' . $actorName . '<br><strong>Total Records:</strong> ' . $totalRecords . '</td>';
        $html .= '</tr></table>';

        $html .= '<table class="summary-table"><tr>';
        $html .= '<td>Total Audited Activities: <strong style="color: #5138ed;">' . $totalRecords . '</strong></td>';
        $html .= '<td>Successful: <strong style="color: #15803d;">' . $successfulCount . '</strong></td>';
        $html .= '<td>Failed / Warnings: <strong style="color: #be123c;">' . $failedCount . '</strong></td>';
        $html .= '<td style="text-align: right; color: #64748b;">Classification: <em>Official Institutional Department Record</em></td>';
        $html .= '</tr></table>';

        $html .= '<table class="data-table"><thead><tr>';
        $html .= '<th style="width: 25px;">#</th>';
        $html .= '<th style="width: 85px;">Date &amp; Time</th>';
        $html .= '<th style="width: 100px;">User</th>';
        $html .= '<th style="width: 75px;">Role</th>';
        $html .= '<th style="width: 70px;">Action</th>';
        $html .= '<th style="width: 70px;">Module</th>';
        $html .= '<th>Description / Event Details</th>';
        $html .= '<th style="width: 70px;">IP Address</th>';
        $html .= '<th style="width: 50px;">Status</th>';
        $html .= '</tr></thead><tbody>';

        if ($logs->isEmpty()) {
            $html .= '<tr><td colspan="9" style="text-align: center; padding: 18px; color: #94a3b8;">No activity log records found for this criteria.</td></tr>';
        } else {
            foreach ($logs as $i => $log) {
                $rawRole = $log->actor_role ?? ($log->user ? $log->user->role : 'system');
                $roleLabel = match($rawRole) {
                    'admin'      => 'Super Admin',
                    'dept_head'  => 'Dept Head',
                    'instructor' => 'Instructor',
                    'student'    => 'Student',
                    default      => 'System',
                };

                $statusBadge = ($log->log_status === 'Success')
                    ? '<span class="badge-success">Success</span>'
                    : '<span class="badge-failed">Failed</span>';

                $createdAt = $log->created_at ? $log->created_at->format('M d, Y H:i:s') : '—';
                $userName = htmlspecialchars($log->user ? $log->user->name : 'System');
                $userEmail = htmlspecialchars($log->user ? $log->user->email : '');
                $action = htmlspecialchars($log->type ?: 'Activity');
                $module = htmlspecialchars($log->module ?: 'General');
                $desc = htmlspecialchars($log->details ?: $log->action ?: 'System activity recorded');
                $ip = htmlspecialchars($log->ip_address ?: '127.0.0.1');

                $html .= '<tr>';
                $html .= '<td>' . ($i + 1) . '</td>';
                $html .= '<td>' . $createdAt . '</td>';
                $html .= '<td><strong>' . $userName . '</strong>' . ($userEmail ? '<br><span style="color:#64748b; font-size:6.5px;">' . $userEmail . '</span>' : '') . '</td>';
                $html .= '<td>' . $roleLabel . '</td>';
                $html .= '<td><span class="action-badge">' . $action . '</span></td>';
                $html .= '<td>' . $module . '</td>';
                $html .= '<td>' . $desc . '</td>';
                $html .= '<td style="font-family: monospace;">' . $ip . '</td>';
                $html .= '<td>' . $statusBadge . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">Wollo University Online Examination System &bull; Confidential Department Audit Record &bull; Generated by Department Head</div>';
        $html .= '</body></html>';

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $pdfOutput = $dompdf->output();

        LogActivity::record('Exported', 'Activity Logs', "Exported {$totalRecords} activity logs as PDF ({$deptName})", 'Success', $deptId);

        $baseFileName = 'activity_logs_' . date('Y_m_d_His');

        return response()->json([
            'status'   => 'success',
            'file'     => base64_encode($pdfOutput),
            'filename' => $baseFileName . '.pdf',
            'format'   => 'pdf',
            'count'    => $totalRecords,
        ]);
    }

    /**
     * Export activity logs as CSV.
     */
    private function exportCsv($logs, string $deptName, ?int $deptId): JsonResponse
    {
        ob_start();
        $output = fopen('php://output', 'w');
        // UTF-8 BOM for Microsoft Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, ['ID', 'Date & Time', 'User', 'Email', 'Role', 'Action', 'Module', 'Description', 'IP Address', 'Status']);

        foreach ($logs as $log) {
            $rawRole = $log->actor_role ?? ($log->user ? $log->user->role : 'system');
            $roleLabel = match($rawRole) {
                'admin'      => 'Super Admin',
                'dept_head'  => 'Department Head',
                'instructor' => 'Instructor',
                'student'    => 'Student',
                default      => 'System',
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
        $csvContent = ob_get_clean();

        LogActivity::record('Exported', 'Activity Logs', "Exported {$logs->count()} activity logs as CSV ({$deptName})", 'Success', $deptId);

        $filename = 'activity_logs_' . date('Y_m_d_His') . '.csv';

        return response()->json([
            'status'   => 'success',
            'file'     => base64_encode($csvContent),
            'filename' => $filename,
            'format'   => 'csv',
            'count'    => $logs->count(),
        ]);
    }

    /**
     * Clear old activity logs for this department.
     */
    public function clearOldLogs(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $days = (int)$request->input('days', 30);
        $cutoff = Carbon::now()->subDays($days);

        $query = ActivityLog::where('created_at', '<', $cutoff);
        if ($deptId) {
            $query->where('department_id', $deptId);
        }

        $deletedCount = $query->delete();

        LogActivity::record('Deleted', 'System', "Cleared {$deletedCount} old activity logs older than {$days} days", 'Success', $deptId);

        return response()->json([
            'status'  => 'success',
            'message' => "Successfully cleared {$deletedCount} old activity log records.",
            'count'   => $deletedCount,
        ]);
    }
}
