<?php

namespace App\Models;

use App\Enums\Plan;
use App\Enums\UserRole;
use App\Models\Message;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'role', 'plan', 'organization_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, MustVerifyEmailTrait, Notifiable;

    protected $guard_name = 'web';

    /**
     * The user's basic profile information.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * The organization the user belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The Patient record associated with this auth user.
     */
    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'plan' => Plan::class,
            'organization_id' => 'integer',
        ];
    }

    /**
     * Whether this user is on a paid (subscribed) plan.
     */
    public function isPro(): bool
    {
        return $this->plan === Plan::Pro;
    }

    public function isPatient(): bool
    {
        return $this->role === UserRole::Patient;
    }

    /**
     * Whether this user is a clinician.
     */
    public function isClinician(): bool
    {
        return $this->role === UserRole::Clinician;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isNursingStaff(): bool
    {
        return $this->role === UserRole::NursingStaff;
    }

    /**
     * Departments this staff member is assigned to.
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'user_department', 'user_id', 'department_id');
    }

    /**
     * Patients this clinician is authorized to view.
     */
    public function clinicianPatients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'clinician_patient_access', 'clinician_user_id', 'patient_user_id');
    }

    /**
     * Consents the patient has granted to partner applications.
     */
    public function consents(): HasMany
    {
        return $this->hasMany(PatientConsent::class, 'patient_user_id');
    }

    /**
     * Messages sent by this user.
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Messages received by this user.
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    /**
     * Get all permissions (from roles) as a flat array for the frontend.
     */
    public function getAllPermissionsNames(): array
    {
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    /**
     * Keep the Spatie role in sync with the legacy `role` column.
     *
     * The application exposes roles through both the `role` column (used by
     * the isPatient()/isClinician() helpers and the user factory) and the
     * Spatie permission system (used by the `role:` and `has.permission:`
     * middlewares). Whenever a user is saved, assign the matching Spatie
     * role so that both access paths agree.
     */
    protected static function booted(): void
    {
        static::saved(function (User $user): void {
            $role = $user->role;
            $roleName = $role instanceof UserRole ? $role->value : $role;

            if (! is_string($roleName) || $roleName === '') {
                return;
            }

            if (! $user->hasAnyRole([$roleName])) {
                $user->assignRole($roleName);
            }
        });
    }
}