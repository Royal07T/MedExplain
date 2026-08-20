<?php

namespace App\Services\HealthQuery;

use App\Models\LabResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Deterministically compares two reports' laboratory observations.
 *
 * The comparison is computed entirely in PHP — the LLM never calculates value
 * deltas, percentages, or directions. Non-numeric values (e.g. "<0.01" or
 * "positive") are compared as strings and never produce invented deltas.
 */
final class ReportComparisonService
{
    private const EPSILON = 0.0000001;

    /**
     * Compare the observations of two reports, grouped by normalized test name.
     *
     * @param  Collection<int, LabResult>  $previous
     * @param  Collection<int, LabResult>  $current
     * @return array{
     *     report_dates: array{string|null, string|null},
     *     changes: list<array<string, mixed>>,
     *     new_tests: list<string>,
     *     removed_tests: list<string>,
     *     changed_tests: list<string>,
     * }
     */
    public function compare(
        Collection $previous,
        Collection $current,
        ?Carbon $previousDate = null,
        ?Carbon $currentDate = null,
    ): array {
        $previousByTest = $this->indexByTest($previous);
        $currentByTest = $this->indexByTest($current);

        $changes = [];
        $newTests = [];
        $removedTests = [];
        $changedTests = [];

        foreach ($previousByTest as $name => $previousResult) {
            $currentResult = $currentByTest[$name] ?? null;

            if ($currentResult === null) {
                $changes[] = $this->entry($name, $previousResult, null);
                $removedTests[] = $previousResult->name;

                continue;
            }

            $entry = $this->entry($name, $previousResult, $currentResult);
            $changes[] = $entry;

            if ($entry['change_type'] === 'changed') {
                $changedTests[] = $previousResult->name;
            }
        }

        foreach ($currentByTest as $name => $currentResult) {
            if (! isset($previousByTest[$name])) {
                $changes[] = $this->entry($name, null, $currentResult);
                $newTests[] = $currentResult->name;
            }
        }

        $sortKey = array_map(
            fn (array $change): string => (string) ($change['test'] ?? ''),
            $changes,
        );
        array_multisort($sortKey, SORT_ASC, SORT_STRING, $changes);

        return [
            'report_dates' => [
                $previousDate?->toDateString(),
                $currentDate?->toDateString(),
            ],
            'changes' => $changes,
            'new_tests' => $newTests,
            'removed_tests' => $removedTests,
            'changed_tests' => $changedTests,
        ];
    }

    /**
     * @param  Collection<int, LabResult>  $results
     * @return array<string, LabResult>
     */
    private function indexByTest(Collection $results): array
    {
        $index = [];
        foreach ($results as $result) {
            $index[$result->normalized_name ?? $this->normalize($result->name)] = $result;
        }

        return $index;
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(string $name, ?LabResult $previous, ?LabResult $current): array
    {
        $testName = $current?->name ?? $previous->name;
        $unit = $current?->unit ?? $previous?->unit;

        $previousValue = $previous?->value;
        $currentValue = $current?->value;

        $changeType = $this->changeType($previousValue, $currentValue);

        return [
            'test' => $testName,
            'unit' => $unit,
            'change_type' => $changeType,
            'previous_value' => $previousValue,
            'current_value' => $currentValue,
            'previous_numeric' => $this->numeric($previousValue),
            'current_numeric' => $this->numeric($currentValue),
            'change' => $this->delta($previousValue, $currentValue),
            'change_percent' => $this->percent($previousValue, $currentValue),
            'direction' => $this->direction($previousValue, $currentValue),
            'previous_status' => $previous?->status?->value,
            'current_status' => $current?->status?->value,
            'status_changed' => $this->statusChanged($previous, $current),
            'unit_changed' => $previous !== null
                && $current !== null
                && $previous->unit !== null
                && $current->unit !== null
                && $previous->unit !== $current->unit,
        ];
    }

    private function changeType(?string $previous, ?string $current): string
    {
        if ($previous === null) {
            return 'new';
        }
        if ($current === null) {
            return 'removed';
        }

        $previousNumber = $this->numeric($previous);
        $currentNumber = $this->numeric($current);

        if ($previousNumber !== null && $currentNumber !== null) {
            return abs($currentNumber - $previousNumber) > self::EPSILON ? 'changed' : 'unchanged';
        }

        return trim((string) $previous) === trim((string) $current) ? 'unchanged' : 'changed';
    }

    private function numeric(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        if (is_numeric(trim($value))) {
            return (float) trim($value);
        }

        return null;
    }

    private function delta(?string $previous, ?string $current): ?float
    {
        $previousNumber = $this->numeric($previous);
        $currentNumber = $this->numeric($current);

        if ($previousNumber === null || $currentNumber === null) {
            return null;
        }

        return round($currentNumber - $previousNumber, 4);
    }

    private function percent(?string $previous, ?string $current): ?float
    {
        $previousNumber = $this->numeric($previous);
        $currentNumber = $this->numeric($current);

        if ($previousNumber === null || $currentNumber === null || $previousNumber == 0) {
            return null;
        }

        return round((($currentNumber - $previousNumber) / abs($previousNumber)) * 100, 2);
    }

    private function direction(?string $previous, ?string $current): ?string
    {
        $previousNumber = $this->numeric($previous);
        $currentNumber = $this->numeric($current);

        if ($previousNumber === null || $currentNumber === null) {
            return null;
        }

        if (abs($currentNumber - $previousNumber) <= self::EPSILON) {
            return 'unchanged';
        }

        return $currentNumber > $previousNumber ? 'increased' : 'decreased';
    }

    private function statusChanged(?LabResult $previous, ?LabResult $current): bool
    {
        if ($previous === null || $current === null) {
            return false;
        }
        if ($previous->status === null || $current->status === null) {
            return false;
        }

        return $previous->status->value !== $current->status->value;
    }

    private function normalize(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($name)) ?? '');
    }
}