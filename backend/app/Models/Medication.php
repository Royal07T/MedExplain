<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'medical_document_id',
    'name',
    'strength',
    'dosage_form',
    'dose',
    'frequency',
    'route',
    'prescriber',
    'indications',
    'start_date',
    'end_date',
    'sort_order',
    'status',
])]
class Medication extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'sort_order' => 'integer',
            'status' => MedicationStatus::class,
        ];
    }

    /**
     * The user who owns this medication record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The document the medication was extracted from, if any.
     */
    public function medicalDocument(): BelongsTo
    {
        return $this->belongsTo(MedicalDocument::class);
    }
}