<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Log in a user and issue a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
            'role'     => 'required|string|in:student,instructor,staff',
        ]);

        $loginField = $request->login;
        $requestedRole = $request->role;

        // Try to find user by email first, then by username
        $user = User::where('email', $loginField)->first()
              ?? User::where('username', $loginField)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        $effectiveRole = $user->role;

        // Role Validation Logic
        if ($requestedRole === 'student') {
            if ($user->role !== 'student') {
                throw ValidationException::withMessages(['login' => ['Invalid credentials or role mismatch.']]);
            }
        } elseif ($requestedRole === 'instructor') {
            if (!in_array($user->role, ['instructor', 'dept_head'])) {
                throw ValidationException::withMessages(['login' => ['Invalid credentials or role mismatch.']]);
            }
            $effectiveRole = 'instructor';
        } elseif ($requestedRole === 'staff') {
            if (!in_array($user->role, ['admin', 'dept_head'])) {
                throw ValidationException::withMessages(['login' => ['Invalid credentials or role mismatch.']]);
            }
            $effectiveRole = $user->role; // either admin or dept_head
        }

        // Revoke old tokens (single-session policy)
        $user->tokens()->delete();

        $assignedOptions = null;
        if ($effectiveRole === 'instructor') {
            $coursesQuery = \App\Models\Course::where('instructor_id', $user->id)
                ->orWhere('co_instructor_id', $user->id)
                ->with('department:id,name')
                ->get();

            if ($coursesQuery->isEmpty()) {
                return response()->json([
                    'message'    => 'Access denied. You have not been assigned to any courses yet. Please wait for an administrator to assign you to a course.',
                    'error_type' => 'instructor_not_assigned',
                    'instructor' => [
                        'name'       => $user->name,
                        'email'      => $user->email,
                        'department' => $user->department?->name ?? 'Unassigned',
                    ],
                    'errors'     => [
                        'login' => ['Access denied. You have not been assigned to any courses yet. Please wait for an administrator to assign you to a course.'],
                    ],
                ], 422);
            }

            // Extract unique departments
            $departmentsMap = [];
            if ($user->department) {
                $departmentsMap[$user->department->id] = [
                    'id'   => $user->department->id,
                    'name' => $user->department->name,
                ];
            }

            $coursesData = [];
            $sectionsSet = [];

            if ($user->section && $user->section !== 'N/A') {
                $sectionsSet[$user->section] = true;
            }

            foreach ($coursesQuery as $c) {
                if ($c->department) {
                    $departmentsMap[$c->department->id] = [
                        'id'   => $c->department->id,
                        'name' => $c->department->name,
                    ];
                }
                $sec = $c->section ?: ($user->section ?: 'Section A');
                $sectionsSet[$sec] = true;

                $coursesData[] = [
                    'id'            => $c->id,
                    'title'         => $c->title,
                    'code'          => $c->code,
                    'department_id' => $c->department_id,
                    'department'    => $c->department?->name ?? 'N/A',
                    'section'       => $sec,
                ];
            }

            $assignedOptions = [
                'departments' => array_values($departmentsMap),
                'courses'     => $coursesData,
                'sections'    => array_keys($sectionsSet),
            ];
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Load department so the frontend has it immediately upon login
        $user->load('department.head');

        return response()->json([
            'data' => [
                'user'  => [
                    'id'            => $user->id,
                    'name'          => $user->name,
                    'email'         => $user->email,
                    'username'      => $user->username,
                    'role'          => $effectiveRole,
                    'department_id' => $user->department_id,
                    'department'    => $user->department,
                    'profile_picture'     => $user->profile_picture,
                    'profile_picture_url' => $user->profile_picture_url,
                    'course_code'         => $user->course_code,
                    'course_name'         => $user->course_name,
                    'section'             => $user->section,
                    'year_level'          => $user->year_level,
                ],
                'token'            => $token,
                'assigned_options' => $assignedOptions,
            ],
            'message' => 'Login successful.',
        ]);
    }

    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role'     => 'sometimes|in:instructor,student',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password,
            'role'     => $request->role ?? 'student',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'data' => [
                'user'  => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'role'  => $user->role,
                ],
                'token' => $token,
            ],
            'message' => 'Registration successful.',
        ], 201);
    }

    /**
     * Log out by revoking all tokens.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?`~]/',
                'confirmed',
            ],
        ], [
            'new_password.min' => 'New password must be at least 8 characters long.',
            'new_password.regex' => 'New password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'new_password.confirmed' => 'New password confirmation does not match.',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    /**
     * Update user profile photo.
     */
    public function updateProfilePhoto(Request $request): JsonResponse
    {
        $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $user = $request->user();

        // Delete old profile picture if exists
        if ($user->profile_picture && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_picture)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_picture);
        }

        $path = $request->file('profile_picture')->store('avatars', 'public');
        $user->profile_picture = $path;
        $user->save();

        $photoUrl = asset('storage/' . $path);

        \App\Helpers\LogActivity::record(
            'Updated',
            'Users',
            "Updated profile photo for \"{$user->name}\""
        );

        return response()->json([
            'message'             => 'Profile photo updated successfully.',
            'profile_picture'     => $path,
            'profile_picture_url' => $photoUrl,
            'user'                => [
                'id'                  => $user->id,
                'name'                => $user->name,
                'email'               => $user->email,
                'username'            => $user->username,
                'role'                => $user->role,
                'department_id'       => $user->department_id,
                'profile_picture'     => $path,
                'profile_picture_url' => $photoUrl,
            ],
        ]);
    }

    /**
     * Remove user profile photo.
     */
    public function removeProfilePhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profile_picture && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_picture)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_picture);
        }

        $user->profile_picture = null;
        $user->save();

        \App\Helpers\LogActivity::record(
            'Updated',
            'Users',
            "Removed profile photo for \"{$user->name}\""
        );

        return response()->json([
            'message'             => 'Profile photo removed successfully.',
            'profile_picture'     => null,
            'profile_picture_url' => null,
            'user'                => [
                'id'                  => $user->id,
                'name'                => $user->name,
                'email'               => $user->email,
                'username'            => $user->username,
                'role'                => $user->role,
                'department_id'       => $user->department_id,
                'profile_picture'     => null,
                'profile_picture_url' => null,
            ],
        ]);
    }

    /**
     * Update user profile information.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'                     => 'sometimes|required|string|max:255',
            'phone'                    => 'nullable|string|max:30',
            'gender'                   => 'nullable|string|in:male,female,other,Male,Female,Other',
            'office'                   => 'nullable|string|max:255',
            'notification_preferences' => 'nullable|array',
        ]);

        if (isset($validated['gender'])) {
            $validated['gender'] = strtolower($validated['gender']);
        }

        $user->fill($validated);
        $user->save();
        $user->load('department.head');

        \App\Helpers\LogActivity::record(
            'Updated',
            'Users',
            "Updated profile information for \"{$user->name}\""
        );

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data'    => $user,
        ]);
    }
}
