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
}
