<?php

namespace App\Models;

use App\Enums\LabOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'organization_id',
    'clinician_id',
    'test_name',
    'test_code',
    'status',
    'notes',
])]
class LabOrder extends Model
{
    protected function casts(): array
    {
        return [
            'status' => LabOrderStatus::class,
            'ordered_at' => 'datetime',
            'result_received_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinician_id');
    }
}