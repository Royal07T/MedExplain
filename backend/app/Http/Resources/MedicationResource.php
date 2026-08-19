<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationResource extends JsonResource
{
    /**
     * Transform the medication into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'strength' => $this->strength,
            'dosage_form' => $this->dosage_form,
            'dose' => $this->dose,
            'frequency' => $this->frequency,
            'route' => $this->route,
            'prescriber' => $this->prescriber,
            'indications' => $this->indications,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'medical_document_id' => $this->medical_document_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}