<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'patient_id',
    'medication_name',
    'dose',
    'route',
    'frequency',
    'scheduled_time',
    'notes',
    'active',
    'last_taken_at',
])]
class MedicationReminder extends Model
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'scheduled_time' => 'datetime',
            'last_taken_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }
}
