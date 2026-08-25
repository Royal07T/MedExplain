<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\Plan;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'organization_id',
    'mrn',
    'first_name',
    'last_name',
    'date_of_birth',
    'gender',
    'blood_type',
    'phone',
    'email',
    'address',
    'next_of_kin_name',
    'next_of_kin_phone',
    'emergency_contact_name',
    'emergency_contact_phone',
    'allergies',
    'immunizations',
])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'organization_id' => \App\Models\Organization::class,
        ];
    }

    /**
     * The user this patient belongs to (auth user).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The organization this patient belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The encounters for this patient.
     */
    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class, 'patient_id');
    }

    /**
     * The lab results for this patient.
     */
    public function labResults(): HasMany
    {
        return $this->hasMany(LabResult::class, 'patient_id');
    }

    /**
     * The medications for this patient.
     */
    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class, 'patient_id');
    }

    /**
     * The documents for this patient.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(MedicalDocument::class, 'user_id')->where('organization_id', $this->organization_id);
    }

    /**
     * Get the patient's full name.
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get the patient's age based on date of birth.
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }

        return date_diff(date_create($this->date_of_birth), date_create('now'))->y;
    }

    /**
     * Scope query to only include patients from the given organization.
     */
    public function scopeByOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Scope query to search by MRN, name, phone, or date of birth.
     */
    public function scopeSearch($query, $searchTerm)
    {
        if (!$searchTerm) {
            return $query;
        }

        return $query->where(function ($q) use ($searchTerm) {
            $q->where('mrn', 'like', '%' . $searchTerm . '%')
                ->orWhere('first_name', 'like', '%' . $searchTerm . '%')
                ->orWhere('last_name', 'like', '%' . $searchTerm . '%')
                ->orWhere('phone', 'like', '%' . $searchTerm . '%')
                ->orWhere('date_of_birth', $searchTerm);
        });
    }
}
