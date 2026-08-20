<?php

namespace App\Services\HealthQuery;

use App\Models\Medication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Deterministically resolves whether each of a user's medications was active on
 * a given date.
 *
 * Handles open-ended courses, missing end dates, same-day start/end, and
 * overlapping periods. Each medication is evaluated independently against the
 * target date; dates are normalized to calendar days so timezones cannot skew
 * the result. When timing cannot be confirmed from the data, the resolver says
 * so rather than guessing.
 */
final class MedicationAtDateResolver
{
    public const ACTIVE = 'active_at_result_date';
    public const ENDED = 'ended_before_result_date';
    public const NOT_STARTED = 'not_started_at_result_date';
    public const UNKNOWN = 'unknown_timing';

    /**
     * @param  Collection<int, Medication>  $medications
     * @return list<array{
     *     medication: string,
     *     active: bool,
     *     status: string,
     *     result_date: string,
     *     start_date: string|null,
     *     end_date: string|null,
     * }>
     */
    public function resolve(Collection $medications, Carbon $resultDate): array
    {
        $target = $resultDate->copy()->startOfDay();

        return $medications
            ->map(function (Medication $medication) use ($target): array {
                $start = $medication->start_date?->copy()->startOfDay();
                $end = $medication->end_date?->copy()->startOfDay();

                [$active, $status] = $this->classify($start, $end, $target);

                return [
                    'medication' => $medication->name,
                    'active' => $active,
                    'status' => $status,
                    'result_date' => $target->toDateString(),
                    'start_date' => $start?->toDateString(),
                    'end_date' => $end?->toDateString(),
                ];
            })
            ->sortBy('medication')
            ->values()
            ->all();
    }

    /**
     * @return array{bool, string}
     */
    private function classify(?Carbon $start, ?Carbon $end, Carbon $target): array
    {
        if ($end !== null && $end->lt($target)) {
            return [false, self::ENDED];
        }

        if ($start !== null && $start->gt($target)) {
            return [false, self::NOT_STARTED];
        }

        if ($start === null && $end === null) {
            return [false, self::UNKNOWN];
        }

        return [true, self::ACTIVE];
    }
}