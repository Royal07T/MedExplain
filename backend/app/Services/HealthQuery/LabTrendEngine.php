<?php

namespace App\Services\HealthQuery;

use App\Models\LabResult;
use Illuminate\Support\Collection;

/**
 * Computes a deterministic time-series trend for one lab test.
 *
 * Ordering, grouping, deltas, percentage changes, and direction are all
 * calculated in PHP from the user's stored observations. The engine never makes
 * medical conclusions — the LLM may only explain what the trend means
 * educationally after receiving this deterministic result.
 */
final class LabTrendEngine
{
    private const EPSILON = 0.0000001;

    /**
     * @param  Collection<int, LabResult>  $observations
     * @return array<string, mixed>
     */
    public function trend(Collection $observations, ?string $requestedName = null): array
    {
        $results = $observations
            ->sortBy(fn (LabResult $r): array => [
                $r->collected_at?->getTimestamp() ?? 0,
                $r->getKey() ?? 0,
            ])
            ->values();

        $series = $results
            ->map(fn (LabResult $result): array => $this->seriesEntry($result))
            ->values()
            ->all();

        $first = $results->first();
        $last = $results->last();

        $units = $results
            ->map(fn (LabResult $r): ?string => $r->unit)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'test' => $requestedName ?? $first?->name,
            'unit' => $first?->unit,
            'units' => $units,
            'unit_consistent' => count($units) <= 1,
            'observation_count' => $results->count(),
            'date_range' => $this->dateRange($first, $last),
            'series' => $series,
            'summary' => $this->summary($first, $last),
            'between_observations' => $this->between($series),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seriesEntry(LabResult $result): array
    {
        $document = $result->documentExtraction?->medicalDocument;

        return [
            'date' => $result->collected_at?->toISOString()
                ?? $document?->created_at?->toISOString(),
            'value' => $this->numeric($result->value),
            'raw_value' => $result->value,
            'unit' => $result->unit,
            'status' => $result->status?->value,
            'reference_range' => $result->reference_range,
            'document_id' => $document?->id,
            'document_filename' => $document?->original_filename,
        ];
    }

    /**
     * @return array{first: string|null, last: string|null}|null
     */
    private function dateRange(?LabResult $first, ?LabResult $last): ?array
    {
        if ($first === null || $last === null) {
            return null;
        }

        $firstDate = $first->collected_at ?? $first->documentExtraction?->medicalDocument?->created_at;
        $lastDate = $last->collected_at ?? $last->documentExtraction?->medicalDocument?->created_at;

        if ($firstDate === null || $lastDate === null) {
            return null;
        }

        return [
            'first' => $firstDate->toDateString(),
            'last' => $lastDate->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(?LabResult $first, ?LabResult $last): array
    {
        if ($first === null || $last === null) {
            return [
                'first_value' => null,
                'last_value' => null,
                'net_change' => null,
                'net_change_percent' => null,
                'direction' => null,
            ];
        }

        $firstNumber = $this->numeric($first->value);
        $lastNumber = $this->numeric($last->value);
        $sameUnit = ($first->unit ?? null) === ($last->unit ?? null);

        if ($firstNumber === null || $lastNumber === null || ! $sameUnit) {
            return [
                'first_value' => $firstNumber,
                'last_value' => $lastNumber,
                'net_change' => null,
                'net_change_percent' => null,
                'direction' => null,
            ];
        }

        $change = round($lastNumber - $firstNumber, 4);
        $percent = $firstNumber != 0
            ? round((($lastNumber - $firstNumber) / abs($firstNumber)) * 100, 2)
            : null;

        return [
            'first_value' => $firstNumber,
            'last_value' => $lastNumber,
            'net_change' => $change,
            'net_change_percent' => $percent,
            'direction' => $this->direction($firstNumber, $lastNumber),
        ];
    }

    /**
     * Consecutive deltas between numeric observations that share a unit.
     *
     * @param  list<array<string, mixed>>  $series
     * @return list<array<string, mixed>>
     */
    private function between(array $series): array
    {
        $changes = [];

        for ($i = 1, $count = count($series); $i < $count; $i++) {
            $from = $series[$i - 1];
            $to = $series[$i];

            if ($from['value'] === null || $to['value'] === null) {
                continue;
            }
            if (($from['unit'] ?? null) !== ($to['unit'] ?? null)) {
                continue;
            }

            $change = round($to['value'] - $from['value'], 4);
            $percent = $from['value'] != 0
                ? round((($to['value'] - $from['value']) / abs($from['value'])) * 100, 2)
                : null;

            $changes[] = [
                'from_date' => $from['date'],
                'to_date' => $to['date'],
                'change' => $change,
                'change_percent' => $percent,
                'direction' => $this->direction($from['value'], $to['value']),
            ];
        }

        return $changes;
    }

    private function direction(float $from, float $to): string
    {
        if (abs($to - $from) <= self::EPSILON) {
            return 'unchanged';
        }

        return $to > $from ? 'increased' : 'decreased';
    }

    private function numeric(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return is_numeric(trim($value)) ? (float) trim($value) : null;
    }
}