<?php

namespace App\Models;

use App\Enums\LabResultStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_extraction_id',
    'user_id',
    'name',
    'normalized_name',
    'value',
    'unit',
    'loinc',
    'reference_range',
    'status',
    'collected_at',
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
            'collected_at' => 'datetime',
        ];
    }

    /**
     * The extraction this result was parsed from.
     */
    public function documentExtraction(): BelongsTo
    {
        return $this->belongsTo(DocumentExtraction::class);
    }

    /**
     * The user who owns the source document for this result.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}