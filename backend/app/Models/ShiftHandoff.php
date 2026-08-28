<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'patient_id',
    'from_nurse_id',
    'to_nurse_id',
    'unit',
    'shift_start',
    'shift_end',
    'clinical_summary',
    'tasks_to_complete',
    'medication_review',
    'safety_concerns',
    'is_complete',
    'handoff_time',
])]
class ShiftHandoff extends Model
{
    protected function casts(): array
    {
        return [
            'is_complete' => 'boolean',
            'shift_start' => 'datetime',
            'shift_end' => 'datetime',
            'handoff_time' => 'datetime',
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

    public function fromNurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_nurse_id');
    }

    public function toNurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_nurse_id');
    }
}
