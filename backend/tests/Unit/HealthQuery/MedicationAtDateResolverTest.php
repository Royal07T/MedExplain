<?php

namespace Tests\Unit\HealthQuery;

use App\Models\Medication;
use App\Services\HealthQuery\MedicationAtDateResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MedicationAtDateResolverTest extends TestCase
{
    private function resolver(): MedicationAtDateResolver
    {
        return new MedicationAtDateResolver();
    }

    private function medication(array $attributes = []): Medication
    {
        return new Medication(array_merge([
            'name' => 'Metformin',
            'dose' => '500',
            'frequency' => 'twice daily',
        ], $attributes));
    }

    public function test_active_within_date_range(): void
    {
        $medications = collect([
            $this->medication(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']),
        ]);

        $result = $this->resolver()->resolve($medications, Carbon::parse('2026-06-01'));

        $this->assertTrue($result[0]['active']);
        $this->assertSame(MedicationAtDateResolver::ACTIVE, $result[0]['status']);
    }

    public function test_open_ended_medication_is_active(): void
    {
        $medications = collect([
            $this->medication(['start_date' => '2026-01-01', 'end_date' => null]),
        ]);

        $result = $this->resolver()->resolve($medications, Carbon::parse('2026-06-01'));

        $this->assertTrue($result[0]['active']);
        $this->assertSame(MedicationAtDateResolver::ACTIVE, $result[0]['status']);
    }

    public function test_missing_dates_are_unknown_not_guessed(): void
    {
        $medications = collect([
            $this->medication(['start_date' => null, 'end_date' => null]),
        ]);

        $result = $this->resolver()->resolve($medications, Carbon::parse('2026-06-01'));

        $this->assertFalse($result[0]['active']);
        $this->assertSame(MedicationAtDateResolver::UNKNOWN, $result[0]['status']);
    }

    public function test_ended_before_result_date(): void
    {
        $medications = collect([
            $this->medication(['start_date' => '2026-01-01', 'end_date' => '2026-05-31']),
        ]);

        $result = $this->resolver()->resolve($medications, Carbon::parse('2026-06-15'));

        $this->assertFalse($result[0]['active']);
        $this->assertSame(MedicationAtDateResolver::ENDED, $result[0]['status']);
    }

    public function test_not_started_on_result_date(): void
    {
        $medications = collect([
            $this->medication(['start_date' => '2026-07-01', 'end_date' => '2026-12-31']),
        ]);

        $result = $this->resolver()->resolve($medications, Carbon::parse('2026-06-01'));

        $this->assertFalse($result[0]['active']);
        $this->assertSame(MedicationAtDateResolver::NOT_STARTED, $result[0]['status']);
    }

    public function test_same_day_start_and_end_is_active_on_that_day(): void
    {
        $medications = collect([
            $this->medication(['start_date' => '2026-06-01', 'end_date' => '2026-06-01']),
        ]);

        $result = $this->resolver()->resolve($medications, Carbon::parse('2026-06-01'));

        $this->assertTrue($result[0]['active']);
        $this->assertSame(MedicationAtDateResolver::ACTIVE, $result[0]['status']);
    }

    public function test_same_day_course_ended_the_next_day(): void
    {
        $medications = collect([
            $this->medication(['start_date' => '2026-06-01', 'end_date' => '2026-06-01']),
        ]);

        $result = $this->resolver()->resolve($medications, Carbon::parse('2026-06-02'));

        $this->assertFalse($result[0]['active']);
        $this->assertSame(MedicationAtDateResolver::ENDED, $result[0]['status']);
    }

    public function test_overlapping_medications_are_each_resolved(): void
    {
        $medications = collect([
            $this->medication(['name' => 'Metformin', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']),
            $this->medication(['name' => 'Lisinopril', 'start_date' => '2026-03-01', 'end_date' => '2026-09-30']),
        ]);

        $result = $this->resolver()->resolve($medications, Carbon::parse('2026-06-01'));

        $this->assertCount(2, $result);
        $this->assertTrue($result[0]['active']);
        $this->assertTrue($result[1]['active']);
        $this->assertSame('Lisinopril', $result[0]['medication']);
    }

    public function test_dates_are_normalized_to_calendar_days(): void
    {
        $medications = collect([
            $this->medication(['start_date' => '2026-01-01', 'end_date' => '2026-05-31']),
        ]);

        $result = $this->resolver()->resolve($medications, Carbon::parse('2026-06-01 23:59:59'));

        $this->assertSame(MedicationAtDateResolver::ENDED, $result[0]['status']);
    }

    public function test_results_include_medication_details(): void
    {
        $medications = collect([
            $this->medication(['name' => 'Metformin', 'start_date' => '2026-01-01', 'end_date' => null]),
        ]);

        $result = $this->resolver()->resolve($medications, Carbon::parse('2026-06-01'));

        $this->assertSame('Metformin', $result[0]['medication']);
        $this->assertSame('2026-06-01', $result[0]['result_date']);
        $this->assertSame('2026-01-01', $result[0]['start_date']);
        $this->assertNull($result[0]['end_date']);
    }
}