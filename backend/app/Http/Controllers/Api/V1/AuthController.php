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

        \App\Helpers\LogActivity::record(
            'Login',
            'Authentication',
            "Logged in successfully"
        );

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

        \App\Helpers\LogActivity::record(
            'Updated',
            'Authentication',
            "Changed account password"
        );

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
     * Get the authenticated user's complete profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('department');

        $defaultPreferences = [
            'language'    => 'English',
            'timezone'    => '(UTC+03:00) Addis Ababa',
            'date_format' => 'MMM DD, YYYY',
            'dark_mode'   => false,
        ];

        $defaultNotifications = [
            'email_notifications'    => true,
            'exam_notifications'     => true,
            'result_notifications'   => true,
            'system_notifications'   => true,
            'announcements'          => true,
            'security_notifications' => true,
        ];

        $prefs = array_merge($defaultPreferences, $user->preferences ?? []);
        $notifs = array_merge($defaultNotifications, $user->notification_preferences ?? []);

        // Calculate account statistics
        $examIds = \App\Models\Exam::where('user_id', $user->id)->pluck('id');
        $examsCount = $examIds->count();
        $bankIds = \App\Models\QuestionBank::where('user_id', $user->id)->pluck('id');
        $questionsCount = \App\Models\Question::whereIn('exam_id', $examIds)
            ->orWhereIn('question_bank_id', $bankIds)
            ->count();
        $studentsCount = \Illuminate\Support\Facades\DB::table('exam_student')
            ->whereIn('exam_id', $examIds)
            ->distinct('user_id')
            ->count('user_id');
        $reportsCount = \App\Models\SemesterSubmission::where('instructor_id', $user->id)->count();
        $hoursSaved = round(($examsCount * 4) + ($questionsCount * 0.5) + ($studentsCount * 0.2));

        $stats = [
            'exams_created'     => $examsCount,
            'questions_created' => $questionsCount,
            'students_assessed' => $studentsCount,
            'reports_generated' => $reportsCount,
            'hours_saved'       => max(1, (int)$hoursSaved),
        ];

        return response()->json([
            'data' => [
                'id'                       => $user->id,
                'name'                     => $user->name,
                'email'                    => $user->email,
                'username'                 => $user->username ?: ($user->email ? explode('@', $user->email)[0] : 'instructor'),
                'id_no'                    => $user->id_no ?: 'WU-IN-' . str_pad((string)$user->id, 4, '0', STR_PAD_LEFT),
                'phone'                    => $user->phone ?: '+251 91 234 5678',
                'office'                   => $user->office ?: 'Wollo University Main Campus, Dessie',
                'location'                 => $user->office ?: 'Wollo University Main Campus, Dessie',
                'gender'                   => $user->gender ?: 'Not Specified',
                'status'                   => $user->status ?: 'Active',
                'role'                     => $user->role ?: 'Instructor',
                'department_id'            => $user->department_id,
                'department'               => $user->department,
                'department_name'          => $user->department?->name ?? 'Computer Science',
                'course_code'              => $user->course_code,
                'course_name'              => $user->course_name,
                'academic_year'            => $user->academic_year,
                'year_level'               => $user->year_level,
                'semester'                 => $user->semester,
                'section'                  => $user->section,
                'employment_type'          => $user->employment_type ?: 'Full Time',
                'member_since'             => $user->created_at ? $user->created_at->format('M d, Y') : 'Jan 15, 2023',
                'created_at'               => $user->created_at?->toIso8601String(),
                'profile_picture'          => $user->profile_picture,
                'profile_picture_url'      => $user->profile_picture_url,
                'notification_preferences' => $notifs,
                'preferences'              => $prefs,
                'stats'                    => $stats,
            ]
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
            'username'                 => 'nullable|string|max:50|unique:users,username,' . $user->id,
            'email'                    => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'phone'                    => 'nullable|string|max:30',
            'gender'                   => 'nullable|string|in:male,female,other,Male,Female,Other',
            'office'                   => 'nullable|string|max:255',
            'location'                 => 'nullable|string|max:255',
            'address'                  => 'nullable|string|max:255',
            'notification_preferences' => 'nullable|array',
            'preferences'              => 'nullable|array',
        ]);

        if (isset($validated['address']) && !isset($validated['office'])) {
            $validated['office'] = $validated['address'];
        }
        unset($validated['address']);

        if (isset($validated['location']) && !isset($validated['office'])) {
            $validated['office'] = $validated['location'];
        }
        unset($validated['location']);

        if (isset($validated['gender'])) {
            $validated['gender'] = ucfirst(strtolower($validated['gender']));
        }

        if (isset($validated['preferences'])) {
            $current = $user->preferences ?? [];
            $validated['preferences'] = array_merge($current, $validated['preferences']);
        }

        if (isset($validated['notification_preferences'])) {
            $currentNotifs = $user->notification_preferences ?? [];
            $validated['notification_preferences'] = array_merge($currentNotifs, $validated['notification_preferences']);
        }

        $user->fill($validated);
        $user->save();
        $user->load('department.head');

        \App\Helpers\LogActivity::record(
            'Updated',
            'Users',
            "Updated profile information for \"{$user->name}\""
        );

        return $this->me($request);
    }

    /**
     * Get activity logs for the authenticated user.
     */
    public function activityLogs(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ensure at least one initial log exists for this user
        $count = \App\Models\ActivityLog::where('user_id', $user->id)->count();
        if ($count === 0) {
            \App\Helpers\LogActivity::record(
                'Login',
                'Authentication',
                "Logged in successfully"
            );
        }

        $perPage = (int) $request->input('per_page', 5);
        $logs = \App\Models\ActivityLog::where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => collect($logs->items())->map(function ($log) {
                $created = $log->created_at;
                $relativeTime = 'Today';
                if ($created) {
                    if ($created->isToday()) {
                        $relativeTime = 'Today';
                    } elseif ($created->isYesterday()) {
                        $relativeTime = 'Yesterday';
                    } else {
                        $relativeTime = $created->format('M d, Y');
                    }
                }

                return [
                    'id'             => $log->id,
                    'action'         => $log->type ?: 'Action',
                    'module'         => $log->module ?: 'Account',
                    'description'    => $log->details ?: ($log->action ?: 'Activity performed'),
                    'created_at'     => $created ? $created->toISOString() : null,
                    'formatted_time' => $created ? $created->format('M d, Y h:i A') : '',
                    'relative_time'  => $relativeTime,
                ];
            }),
            'pagination' => [
                'total'        => $logs->total(),
                'per_page'     => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
            ],
        ]);
    }
}
