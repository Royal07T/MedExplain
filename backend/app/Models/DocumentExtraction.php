<?php

namespace App\Models;

use App\Enums\ExtractionMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'medical_document_id',
    'extraction_method',
    'raw_text',
])]
class DocumentExtraction extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extraction_method' => ExtractionMethod::class,
        ];
    }

    /**
     * The document this extraction belongs to.
     */
    public function medicalDocument(): BelongsTo
    {
        return $this->belongsTo(MedicalDocument::class);
    }

    /**
     * The laboratory tests parsed from this extraction.
     */
    public function labResults(): HasMany
    {
        return $this->hasMany(LabResult::class);
    }
}