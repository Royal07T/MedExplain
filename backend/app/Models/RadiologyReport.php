<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'imaging_order_id',
    'radiologist_id',
    'findings',
    'impression',
    'report_text',
    'status',
])]
class RadiologyReport extends Model
{
    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
        ];
    }

    public function imagingOrder(): BelongsTo
    {
        return $this->belongsTo(ImagingOrder::class);
    }

    public function radiologist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'radiologist_id');
    }
}
