<?php

namespace App\Models;

use App\Enums\Plan;
use App\Enums\UserRole;
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

#[Fillable(['name', 'email', 'password', 'role', 'plan', 'organization_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

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
            'organization_id' => Organization::class,
        ];
    }

    /**
     * Whether this user is on a paid (subscribed) plan.
     */
    public function isPro(): bool
    {
        return $this->plan === Plan::Pro;
    }

    /**
     * Whether this user is a clinician.
     */
    public function isClinician(): bool
    {
        return $this->role === UserRole::Clinician;
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
}