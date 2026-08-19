<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Database\Factories\MedicalDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'original_filename',
    'storage_path',
    'mime_type',
    'file_size',
    'document_type',
    'status',
    'error_message',
    'processed_at',
])]
class MedicalDocument extends Model
{
    /** @use HasFactory<MedicalDocumentFactory> */
    use HasFactory;
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'document_type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    /**
     * The user who owns this document.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The extraction produced for this document.
     */
    public function extraction(): HasOne
    {
        return $this->hasOne(DocumentExtraction::class);
    }

    /**
     * The AI analysis produced for this document.
     */
    public function analysis(): HasOne
    {
        return $this->hasOne(AiAnalysis::class);
    }

    /**
     * Medications extracted from this document.
     */
    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class);
    }
}