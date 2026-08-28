<?php

namespace App\Models;

use App\Enums\AcuityLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'patient_id',
    'clinician_id',
    'triage_nurse_id',
    'chief_complaint',
    'acuity_level',
    'queue_status',
    'disposition',
    'arrival_time',
    'seen_by_clinician_at',
    'departure_time',
    'vitals_summary',
    'notes',
])]
class EmergencyVisit extends Model
{
    protected function casts(): array
    {
        return [
            'acuity_level' => AcuityLevel::class,
            'arrival_time' => 'datetime',
            'seen_by_clinician_at' => 'datetime',
            'departure_time' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinician_id');
    }

    public function triageNurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triage_nurse_id');
    }

    /**
     * Length of stay in minutes (until departure, or current time if still admitted).
     */
    public function getLengthOfStayMinutesAttribute(): int
    {
        $start = $this->arrival_time ?? $this->created_at;
        $end = $this->departure_time ?? now();

        return max(0, $start->diffInMinutes($end));
    }

    /**
     * Numeric acuity priority for sorting (0 = most urgent).
     */
    public function getAcuityPriorityAttribute(): int
    {
        return match ($this->acuity_level?->value) {
            'resuscitation' => 0,
            'emergent' => 1,
            'urgent' => 2,
            'non-urgent' => 3,
            default => 9,
        };
    }
}
