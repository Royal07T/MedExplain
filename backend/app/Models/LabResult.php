<?php

namespace App\Models;

use App\Enums\LabResultStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_extraction_id',
    'name',
    'value',
    'unit',
    'reference_range',
    'status',
    'sort_order',
])]
class LabResult extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LabResultStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * The extraction this result was parsed from.
     */
    public function documentExtraction(): BelongsTo
    {
        return $this->belongsTo(DocumentExtraction::class);
    }
}