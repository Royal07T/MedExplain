<?php

namespace App\Models;

use App\Enums\AiAnalysisStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'medical_document_id',
    'status',
    'summary',
    'disclaimer',
    'concerns',
    'error_message',
    'processed_at',
])]
class AiAnalysis extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AiAnalysisStatus::class,
            'concerns' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * The document this analysis belongs to.
     */
    public function medicalDocument(): BelongsTo
    {
        return $this->belongsTo(MedicalDocument::class);
    }

    /**
     * The per-test explanation items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(AnalysisItem::class);
    }
}