<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisResource extends JsonResource
{
    /**
     * Transform the analysis into an array.
     *
     * Returns the AI explanation, its items, and the lab results it was
     * derived from. Never exposes storage paths or raw document text.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'summary' => $this->summary,
            'disclaimer' => $this->disclaimer,
            'concerns' => $this->concerns ?? [],
            'error_message' => $this->error_message,
            'processed_at' => $this->processed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'items' => $this->items->map(fn ($item): array => [
                'test_name' => $item->test_name,
                'explanation' => $item->explanation,
                'category' => $item->category->value,
            ])->values(),
            'lab_results' => $this->medicalDocument?->extraction?->labResults
                ->map(fn ($result): array => [
                    'name' => $result->name,
                    'value' => $result->value,
                    'unit' => $result->unit,
                    'reference_range' => $result->reference_range,
                    'status' => $result->status->value,
                ])
                ->values(),
        ];
    }
}