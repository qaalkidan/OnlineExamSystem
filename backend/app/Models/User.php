<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'username',
        'phone',
        'gender',
        'status',
        'id_no',
        'password',
        'role',
        'department_id',
        'course_code',
        'course_name',
        'academic_year',
        'year_level',
        'semester',
        'section',
        'profile_picture',
        'created_by',
        'employment_type',
        'office',
        'notification_preferences',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'profile_picture_url',
    ];

    public function getProfilePictureUrlAttribute(): ?string
    {
        if (!$this->profile_picture) return null;
        if (str_starts_with($this->profile_picture, 'http://') || str_starts_with($this->profile_picture, 'https://')) {
            return $this->profile_picture;
        }
        $root = request()->getSchemeAndHttpHost() ?: config('app.url');
        return rtrim($root, '/') . '/storage/' . ltrim($this->profile_picture, '/');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
            'preferences' => 'array',
        ];
    }

    /** Exams created by this instructor — always filtered to their one course */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /** Question banks owned by this instructor */
    public function questionBanks(): HasMany
    {
        return $this->hasMany(QuestionBank::class);
    }

    /** Courses assigned to this instructor */
    public function assignedCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    /** Courses where this instructor is a co-instructor */
    public function coInstructorCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'co_instructor_id');
    }

    /** Exam attempts by this user (student role) */
    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function isInstructor(): bool
    {
        return $this->role === 'instructor';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Resolves the instructor-assigned academic year, semester, course, and instructor for a student.
     */
    public function resolveStudentAcademicContext(): array
    {
        $systemSettings = SystemSetting::pluck('value', 'key');
        $defaultYear = $systemSettings['academicYear'] ?? '2025/2026';
        $defaultSem = $systemSettings['semester'] ?? 'First Semester';

        $course = null;
        $instructor = null;

        if ($this->course_code) {
            $course = Course::where('code', $this->course_code)->first();
        }

        if (!$course && $this->department_id) {
            $course = Course::where('department_id', $this->department_id)
                ->where(function ($q) {
                    if ($this->year_level) {
                        $q->where('level', $this->year_level)->orWhereNull('level');
                    }
                })
                ->where(function ($q) {
                    if ($this->section) {
                        $cleanSec = trim(str_ireplace('Section', '', $this->section));
                        $q->where('section', $this->section)
                          ->orWhere('section', $cleanSec)
                          ->orWhere('section', "Section $cleanSec")
                          ->orWhereIn('section', ['Both Sections', 'All Sections'])
                          ->orWhereNull('section');
                    }
                })
                ->first();
        }

        if (!$course && $this->department_id) {
            $course = Course::where('department_id', $this->department_id)->first();
        }

        if ($course && $course->instructor_id) {
            $instructor = User::find($course->instructor_id);
        }

        if (!$instructor && $this->course_code) {
            $instructor = User::where('role', 'instructor')
                ->where('course_code', $this->course_code)
                ->first();
        }

        if (!$instructor && $this->department_id) {
            $instructor = User::where('role', 'instructor')
                ->where('department_id', $this->department_id)
                ->where(function ($q) {
                    if ($this->year_level && $this->year_level !== 'None') {
                        $q->where('year_level', $this->year_level)
                          ->orWhere('year_level', 'None')
                          ->orWhereNull('year_level');
                    }
                })
                ->first();
        }

        if (!$instructor && $this->department_id) {
            $instructor = User::where('role', 'instructor')
                ->where('department_id', $this->department_id)
                ->first();
        }

        if (!$instructor) {
            $exam = Exam::where('course_code', $this->course_code)
                ->orWhere('department_id', $this->department_id)
                ->latest()
                ->first();
            if ($exam && $exam->user_id) {
                $instructor = User::find($exam->user_id);
            }
        }

        // Academic year priority:
        // 1. Instructor's assigned academic year (if set)
        // 2. Student's academic year (if set)
        // 3. System setting academic year
        $resolvedYear = $instructor?->academic_year
            ?: ($this->academic_year ?: $defaultYear);

        // Semester priority:
        // 1. Course semester (if set)
        // 2. Instructor's semester (if set)
        // 3. Student's semester (if set)
        // 4. System setting semester
        $resolvedSemester = $course?->semester
            ?: ($instructor?->semester ?: ($this->semester ?: $defaultSem));

        return [
            'academic_year'            => $resolvedYear,
            'semester'                 => $resolvedSemester,
            'instructor_academic_year' => $instructor?->academic_year ?: $resolvedYear,
            'instructor_semester'      => $instructor?->semester ?: $resolvedSemester,
            'course_semester'          => $course?->semester,
            'instructor'               => $instructor ? [
                'id'            => $instructor->id,
                'name'          => $instructor->name,
                'email'         => $instructor->email,
                'academic_year' => $instructor->academic_year,
                'semester'      => $instructor->semester,
                'course_code'   => $instructor->course_code,
                'course_name'   => $instructor->course_name,
            ] : null,
            'course'                   => $course ? [
                'id'            => $course->id,
                'title'         => $course->title,
                'code'          => $course->code,
                'semester'      => $course->semester,
                'level'         => $course->level,
                'section'       => $course->section,
            ] : null,
        ];
    }
}
