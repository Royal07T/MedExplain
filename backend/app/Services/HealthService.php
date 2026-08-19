<?php

namespace App\Services;

use App\Models\LabResult;
use App\Models\MedicalDocument;
use App\Models\Medication;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates the user's laboratory results into time-series trends and a
 * personal health timeline. All queries are scoped to the authenticated user —
 * ownership is enforced at the query level, never by trusting request input.
 */
final class HealthService
{
    /**
     * Distinct test names the user has recorded, newest sample first.
     *
     * @return Collection<int, array{name: string, unit: string|null, last_collected_at: string|null, count: int}>
     */
    public function testNames(User $user): Collection
    {
        return LabResult::query()
            ->where('user_id', $user->id)
            ->selectRaw('name, MAX(unit) AS unit, MAX(collected_at) AS last_collected_at, COUNT(*) AS count')
            ->groupBy('name')
            ->orderBy('name')
            ->get()
            ->map(fn (LabResult $result): array => [
                'name' => $result->name,
                'unit' => $result->unit,
                'last_collected_at' => $result->last_collected_at
                    ? Carbon::parse($result->last_collected_at)->toISOString()
                    : null,
                'count' => (int) $result->count,
            ])
            ->values();
    }

    /**
     * Time-series for one test across the user's reports.
     *
     * Returns null when the user has no results for the test so the caller can
     * respond with an explicit empty series.
     *
     * @return array{test: string, unit: string|null, series: list<array<string, mixed>>}|null
     */
    public function trends(User $user, string $name): ?array
    {
        $results = LabResult::query()
            ->where('user_id', $user->id)
            ->where('normalized_name', $this->normalizeName($name))
            ->with(['documentExtraction.medicalDocument'])
            ->orderBy('collected_at')
            ->orderBy('id')
            ->get();

        if ($results->isEmpty()) {
            return null;
        }

        $first = $results->first();

        $series = $results->map(function (LabResult $result): array {
            $document = $result->documentExtraction?->medicalDocument;

            return [
                'date' => ($result->collected_at ?? $document?->created_at)?->toISOString(),
                'value' => $result->value,
                'status' => $result->status->value,
                'reference_range' => $result->reference_range,
                'document_id' => $document?->id,
                'document_filename' => $document?->original_filename,
            ];
        });

        return [
            'test' => $first->name,
            'unit' => $first->unit,
            'series' => $series->values()->all(),
        ];
    }

    /**
     * The user's personal health timeline, newest first.
     *
     * @return Collection<int, array{type: string, occurred_at: string, title: string, description: string|null, document_id: int}>
     */
    public function timeline(User $user): Collection
    {
        $documents = MedicalDocument::query()
            ->where('user_id', $user->id)
            ->with(['analysis', 'extraction.labResults'])
            ->get();

        $events = collect();

        foreach ($documents as $document) {
            $events->push([
                'type' => 'document_uploaded',
                'occurred_at' => $document->created_at,
                'title' => 'Report uploaded',
                'description' => $document->original_filename,
                'document_id' => $document->id,
            ]);

            if ($document->processed_at !== null) {
                $events->push([
                    'type' => 'document_processed',
                    'occurred_at' => $document->processed_at,
                    'title' => 'Report analyzed',
                    'description' => $document->original_filename,
                    'document_id' => $document->id,
                ]);
            }

            if ($document->analysis?->processed_at !== null) {
                $events->push([
                    'type' => 'analysis_completed',
                    'occurred_at' => $document->analysis->processed_at,
                    'title' => 'AI explanation ready',
                    'description' => $document->original_filename,
                    'document_id' => $document->id,
                ]);
            }

            foreach ($document->extraction?->labResults ?? [] as $result) {
                $events->push([
                    'type' => 'lab_result',
                    'occurred_at' => $result->collected_at ?? $document->created_at,
                    'title' => $result->name.' recorded',
                    'description' => trim(($result->value ?? '').' '.($result->unit ?? '')),
                    'document_id' => $document->id,
                ]);
            }
        }

        return $events
            ->sortByDesc('occurred_at')
            ->map(fn (array $event): array => [
                'type' => $event['type'],
                'occurred_at' => Carbon::parse($event['occurred_at'])->toISOString(),
                'title' => $event['title'],
                'description' => $event['description'],
                'document_id' => $event['document_id'],
            ])
            ->values();
    }

    /**
     * The user's aggregated personal health record: profile overview, the
     * latest result per lab test, current medications, and recent timeline
     * events. All data is scoped to the provided user.
     *
     * @return array{profile: array<string, mixed>, labs: list<array<string, mixed>>, medications: list<array<string, mixed>>, timeline: list<array<string, mixed>>}
     */
    public function record(User $user): array
    {
        $profile = $user->profile;

        $latestPerTest = LabResult::query()
            ->where('user_id', $user->id)
            ->with(['documentExtraction.medicalDocument'])
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('normalized_name')
            ->map(function (Collection $results): array {
                $latest = $results->first();

                return [
                    'name' => $latest->name,
                    'value' => $latest->value,
                    'unit' => $latest->unit,
                    'status' => $latest->status->value,
                    'reference_range' => $latest->reference_range,
                    'last_collected_at' => $latest->collected_at?->toISOString(),
                ];
            })
            ->values()
            ->sortBy('name')
            ->values();

        $medications = Medication::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Medication $medication): array => [
                'id' => $medication->id,
                'name' => $medication->name,
                'strength' => $medication->strength,
                'dosage_form' => $medication->dosage_form,
                'dose' => $medication->dose,
                'frequency' => $medication->frequency,
                'route' => $medication->route,
                'indications' => $medication->indications,
                'medical_document_id' => $medication->medical_document_id,
            ])
            ->values();

        return [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'date_of_birth' => $profile?->date_of_birth?->toISOString(),
                'gender' => $profile?->gender,
            ],
            'labs' => $latestPerTest->all(),
            'medications' => $medications->all(),
            'timeline' => $this->timeline($user)->take(10)->values()->all(),
        ];
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($name)));
    }
}