<?php

namespace App\Models;

use App\Enums\ImagingOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'organization_id',
    'clinician_id',
    'modality',
    'body_region',
    'clinical_indication',
    'priority',
    'status',
    'icd_code',
    'scheduled_at',
    'radiation_dose_mgy',
    'image_count',
    'notes',
])]
class ImagingOrder extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ImagingOrderStatus::class,
            'ordered_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function report(): HasOne
    {
        return $this->hasOne(RadiologyReport::class);
    }
}
