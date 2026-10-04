<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Models\ActivityLog;
use App\Helpers\LogActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class DepartmentController extends Controller
{
    /**
     * List all departments with their head info.
     */
    public function index(): JsonResponse
    {
        $departments = Department::with('head:id,name,email')
            ->withCount([
                'users as instructors_count' => function ($query) {
                    $query->whereIn('role', ['instructor', 'dept_head']);
                },
                'users as students_count' => function ($query) {
                    $query->where('role', 'student');
                },
                'courses as courses_count'
            ])
            ->get();

        return response()->json([
            'data' => $departments,
            'message' => 'Departments retrieved successfully.',
        ]);
    }

    /**
     * Get details for a specific department (including instructors, students, courses).
     */
    public function show(Department $department): JsonResponse
    {
        $department->load([
            'head:id,name,email,created_at,role,department_id,phone',
            'users' => function($q) {
                $q->select('id', 'name', 'email', 'role', 'department_id', 'phone', 'created_at');
            },
            'courses' => function($q) {
                $q->with('instructor:id,name');
            }
        ]);

        $activityLogs = ActivityLog::with('user:id,name')
            ->where('department_id', $department->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Separate users into instructors and students
        $instructors = $department->users->filter(fn($u) => in_array($u->role, ['instructor', 'dept_head']))->values();
        $students = $department->users->filter(fn($u) => $u->role === 'student')->values();

        // Calculate counts
        $department->instructors_count = $instructors->count();
        $department->students_count = $students->count();
        $department->courses_count = $department->courses->count();
        $department->exams_count = 0; // exams count can be added later if needed

        return response()->json([
            'data' => [
                'department' => $department,
                'instructors' => $instructors,
                'students' => $students,
                'courses' => $department->courses,
                'activities' => $activityLogs,
            ],
            'message' => 'Department details retrieved successfully.',
        ]);
    }

    /**
     * Create a new department (without requiring a head).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:20|unique:departments,code',
            'college'     => 'required|string|max:255',
            'established' => 'nullable|max:4',
        ], [
            'name.required'    => 'Department Name is required.',
            'code.required'    => 'Department Code is required.',
            'code.unique'      => 'A department with this code already exists.',
            'college.required' => 'College/School is required.',
        ]);

        $department = Department::create([
            'name'        => $request->name,
            'code'        => $request->code,
            'college'     => $request->college,
            'established' => $request->established,
            'status'      => 'active',
        ]);

        LogActivity::record(
            'Created',
            'Departments',
            "Created a new department \"{$department->name}\"",
            'Success',
            $department->id
        );

        return response()->json([
            'data'    => $department->load('head:id,name,email'),
            'message' => 'Department created successfully.',
        ], 201);
    }

    /**
     * Assign an existing instructor as the department head.
     */
    public function assignHead(Request $request, Department $department): JsonResponse
    {
        $request->validate([
            'instructor_id' => 'required|exists:users,id',
        ]);

        $instructor = User::findOrFail($request->instructor_id);

        // Ensure the instructor belongs to this department
        if ($instructor->department_id != $department->id) {
            return response()->json([
                'message' => 'The selected instructor does not belong to this department.',
                'errors' => [
                    'instructor_id' => ['Only instructors belonging to this department can be assigned as Department Head.']
                ]
            ], 422);
        }

        DB::transaction(function () use ($department, $instructor) {
            // If there was a previous head, revert them to instructor role
            if ($department->head_id && $department->head_id !== $instructor->id) {
                User::where('id', $department->head_id)->update(['role' => 'instructor']);
            }

            // Promote the instructor to dept_head
            $instructor->update([
                'role'          => 'dept_head',
                'department_id' => $department->id,
            ]);

            // Link the head to the department
            $department->update(['head_id' => $instructor->id]);

            LogActivity::record(
                'Updated',
                'Departments',
                "Assigned department head for \"{$department->name}\" to \"{$instructor->name}\"",
                'Success',
                $department->id
            );
        });

        return response()->json([
            'data'    => $department->load('head:id,name,email'),
            'message' => 'Department head assigned successfully.',
        ]);
    }

    /**
     * Update a department.
     */
    public function update(Request $request, Department $department): JsonResponse
    {
        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'code'        => 'sometimes|string|max:20|unique:departments,code,' . $department->id,
            'college'     => 'nullable|string|max:255',
            'established' => 'nullable|max:4',
            'status'      => 'sometimes|in:active,inactive',
        ]);

        $department->update($request->only(['name', 'code', 'college', 'established', 'status']));

        if ($request->has('status')) {
            LogActivity::record(
                'Updated',
                'Departments',
                "Changed status of department \"{$department->name}\" to " . ucfirst($request->status),
                'Success',
                $department->id
            );
        } else {
            LogActivity::record(
                'Updated',
                'Departments',
                "Updated department \"{$department->name}\"",
                'Success',
                $department->id
            );
        }

        return response()->json([
            'data'    => $department->load('head:id,name,email'),
            'message' => 'Department updated successfully.',
        ]);
    }

    /**
     * Delete a department.
     */
    public function destroy(Department $department): JsonResponse
    {
        $deptName = $department->name;
        
        DB::transaction(function () use ($department) {
            // Revert head back to instructor if exists
            if ($department->head_id) {
                User::where('id', $department->head_id)->update(['role' => 'instructor']);
            }
            $department->delete();
        });

        LogActivity::record(
            'Deleted',
            'Departments',
            "Deleted department \"$deptName\"",
            'Success',
            $department->id
        );

        return response()->json([
            'message' => 'Department deleted successfully.',
        ]);
    }
}

