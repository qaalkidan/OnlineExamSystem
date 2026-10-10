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
     * Export activity logs as CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $deptId = $this->resolveDeptId($request);
        $dept = $deptId ? Department::find($deptId) : null;
        $deptName = $dept ? $dept->name : 'Department';

        $query = ActivityLog::with(['user:id,name,email,role,department_id', 'department:id,name']);

        if ($deptId) {
            $query->where(function ($q) use ($deptId, $user) {
                $q->where('department_id', $deptId)
                  ->orWhere('user_id', $user->id)
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

        ob_start();
        $output = fopen('php://output', 'w');
        // UTF-8 BOM
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

        // Record the export action in activity log
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
