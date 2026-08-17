<?php

namespace App\Models;

use App\Enums\AnalysisItemCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ai_analysis_id',
    'test_name',
    'explanation',
    'category',
    'sort_order',
])]
class AnalysisItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => AnalysisItemCategory::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * The analysis this item belongs to.
     */
    public function aiAnalysis(): BelongsTo
    {
        return $this->belongsTo(AiAnalysis::class);
    }
}