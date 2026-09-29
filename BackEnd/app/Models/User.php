<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** صفة «موظف تواصل»: صلاحية تُمنح لأشخاص بأعيانهم، لا دور ولا عمود. */
    public const COMMUNICATION_PERMISSION = 'tasks.communication';

    /** سلطة وصف موظف بأنه موظف تواصل — غير الصفة نفسها. */
    public const ASSIGN_COMMUNICATION_PERMISSION = 'tasks.communication.assign';

    public function isCommunicationOfficer(): bool
    {
        return $this->hasPermissionTo(self::COMMUNICATION_PERMISSION);
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'job_title', 'employee_number',
        'employment_type', 'branch_id', 'department_id', 'office_id', 'api_token',
        'digital_signature_path', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token', 'api_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean'];
    }

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function office(): BelongsTo { return $this->belongsTo(Office::class); }
    public function reports(): HasMany { return $this->hasMany(Report::class, 'employee_id'); }
    public function address(): HasOne { return $this->hasOne(UserAddress::class); }
    public function familyDetails(): HasOne { return $this->hasOne(UserFamilyDetail::class); }
    public function personalDetails(): HasOne { return $this->hasOne(UserPersonalDetail::class); }
    public function customForms(): HasMany { return $this->hasMany(CustomForm::class, 'creator_id'); }
    public function formPublications(): HasMany { return $this->hasMany(CustomFormPublication::class, 'issuer_id'); }
    public function formSubmissions(): HasMany { return $this->hasMany(CustomFormSubmission::class); }
    public function createdTasks(): HasMany { return $this->hasMany(Task::class, 'creator_id'); }
    public function assignedTasks(): HasMany { return $this->hasMany(Task::class, 'assignee_id'); }
    public function taskActivities(): HasMany { return $this->hasMany(TaskActivity::class, 'actor_id'); }
    public function driveFiles(): HasMany { return $this->hasMany(DriveFile::class, 'uploader_id'); }
    public function driveFolders(): HasMany { return $this->hasMany(DriveFolder::class, 'owner_id'); }
    public function primaryRole(): string { return $this->getRoleNames()->first() ?? $this->role; }
}
