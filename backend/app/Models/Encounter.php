<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\Plan;
use App\Models\Patient;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'patient_id',
    'organization_id',
    'clinician_id',
    'triage_id',
    'chief_complaint',
    'symptoms',
    'clinical_observations',
    'acuity_level',
    'queue_status',
    'check_in_time',
    'check_out_time',
    'vitals_summary',
])]
class Encounter extends Model
{
    /** @use HasFactory<EncounterFactory> */
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'check_in_time' => 'datetime',
            'check_out_time' => 'datetime',
            'vitals_summary' => 'decimal:2',
            'acuity_level' => 'enum:resuscitation,emergent,urgent,non-urgent',
        ];
    }

    /**
     * The patient this encounter belongs to.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * The organization this encounter belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The clinician who attended this encounter.
     */
    public function clinician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinician_id');
    }

    /**
     * The triage nurse who assessed this encounter.
     */
    public function triage(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triage_id');
    }

    /**
     * The medications ordered/prescribed during this encounter.
     */
    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class, 'encounter_id');
    }

    /**
     * Scope to active encounters only.
     */
    public function scopeActive($query)
    {
        return $query->where('check_out_time', null);
    }

    /**
     * Scope to encounters by acuity level.
     */
    public function scopeByAcuity($query, $level)
    {
        return $query->where('acuity_level', $level);
    }

    /**
     * Scope to encounters by queue status.
     */
    public function scopeByQueue($query, $status)
    {
        return $query->where('queue_status', $status);
    }

    /**
     * Get the encounter's display title.
     */
    public function getDisplayTitleAttribute(): string
    {
        $name = $this->patient?->full_name ?? 'Unknown Patient';
        $acuity = $this->acuity_level ?? 'non-urgent';
        $status = $this->queue_status ?? 'waiting';

        return "{$name} - {$acuity} ({$status})";
    }
}
