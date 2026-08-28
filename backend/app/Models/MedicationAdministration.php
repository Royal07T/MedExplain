<?php

namespace App\Models;

use App\Enums\MedicationAdminStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'patient_id',
    'prescription_id',
    'medication_name',
    'dose',
    'dose_unit',
    'route',
    'scheduled_time',
    'administered_time',
    'status',
    'administered_by',
    'notes',
    'vitals_before',
])]
class MedicationAdministration extends Model
{
    protected function casts(): array
    {
        return [
            'status' => MedicationAdminStatus::class,
            'scheduled_time' => 'datetime',
            'administered_time' => 'datetime',
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

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }
}
