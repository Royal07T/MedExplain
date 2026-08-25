<?php

namespace App\Models;

use App\Enums\MedicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'organization_id',
    'clinician_id',
    'medication_id',
    'status',
    'notes',
    'expires_at',
])]
class Prescription extends Model
{
    protected function casts(): array
    {
        return [
            'status' => MedicationStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }
}