<?php

namespace App\Models;

use App\Enums\CarePlanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'patient_id',
    'title',
    'description',
    'goals',
    'interventions',
    'status',
    'assigned_to',
    'created_by',
    'started_at',
    'due_date',
    'completed_at',
])]
class CarePlan extends Model
{
    protected function casts(): array
    {
        return [
            'goals' => 'array',
            'interventions' => 'array',
            'status' => CarePlanStatus::class,
            'started_at' => 'date',
            'due_date' => 'date',
            'completed_at' => 'date',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
