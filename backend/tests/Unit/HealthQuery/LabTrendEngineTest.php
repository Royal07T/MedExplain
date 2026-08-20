<?php

namespace Tests\Unit\HealthQuery;

use App\Models\LabResult;
use App\Services\HealthQuery\LabTrendEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class LabTrendEngineTest extends TestCase
{
    private function engine(): LabTrendEngine
    {
        return new LabTrendEngine();
    }

    private function observation(array $attributes = []): LabResult
    {
        return new LabResult(array_merge([
            'name' => 'Glucose',
            'normalized_name' => 'glucose',
            'value' => '95',
            'unit' => 'mg/dL',
            'status' => 'within_range',
            'collected_at' => now()->subDays(30),
        ], $attributes));
    }

    public function test_orders_observations_by_collected_at_ascending(): void
    {
        $observations = collect([
            $this->observation(['value' => '110', 'collected_at' => now()->subDay()]),
            $this->observation(['value' => '90', 'collected_at' => now()->subDays(30)]),
            $this->observation(['value' => '100', 'collected_at' => now()->subDays(15)]),
        ]);

        $trend = $this->engine()->trend($observations, 'Glucose');

        $this->assertSame(['90', '100', '110'], array_column($trend['series'], 'raw_value'));
    }

    public function test_computes_summary_change_percent_and_direction(): void
    {
        $observations = collect([
            $this->observation(['value' => '5.2', 'unit' => 'mmol/L', 'collected_at' => now()->subDays(30)]),
            $this->observation(['value' => '6.4', 'unit' => 'mmol/L', 'collected_at' => now()]),
        ]);

        $trend = $this->engine()->trend($observations, 'Glucose');

        $this->assertSame(2, $trend['observation_count']);
        $this->assertSame(5.2, $trend['summary']['first_value']);
        $this->assertSame(6.4, $trend['summary']['last_value']);
        $this->assertSame(1.2, $trend['summary']['net_change']);
        $this->assertSame(23.08, $trend['summary']['net_change_percent']);
        $this->assertSame('increased', $trend['summary']['direction']);
    }

    public function test_multiple_units_are_marked_inconsistent_without_numeric_summary(): void
    {
        $observations = collect([
            $this->observation(['value' => '5.2', 'unit' => 'mmol/L', 'collected_at' => now()->subDays(30)]),
            $this->observation(['value' => '94', 'unit' => 'mg/dL', 'collected_at' => now()]),
        ]);

        $trend = $this->engine()->trend($observations, 'Glucose');

        $this->assertFalse($trend['unit_consistent']);
        $this->assertSame(['mmol/L', 'mg/dL'], $trend['units']);
        $this->assertNull($trend['summary']['net_change']);
        $this->assertNull($trend['summary']['net_change_percent']);
        $this->assertNull($trend['summary']['direction']);
    }

    public function test_non_numeric_values_are_included_without_numeric_summary(): void
    {
        $observations = collect([
            $this->observation(['value' => 'positive', 'status' => 'positive', 'collected_at' => now()->subDays(30)]),
            $this->observation(['value' => 'positive', 'status' => 'positive', 'collected_at' => now()]),
        ]);

        $trend = $this->engine()->trend($observations, 'COVID PCR');

        $this->assertNull($trend['series'][0]['value']);
        $this->assertSame('positive', $trend['series'][0]['raw_value']);
        $this->assertNull($trend['summary']['net_change']);
        $this->assertNull($trend['summary']['direction']);
    }

    public function test_reports_observation_count_and_date_range(): void
    {
        $observations = collect([
            $this->observation(['value' => '90', 'collected_at' => Carbon::parse('2026-01-10')]),
            $this->observation(['value' => '100', 'collected_at' => Carbon::parse('2026-08-20')]),
        ]);

        $trend = $this->engine()->trend($observations, 'Glucose');

        $this->assertSame(2, $trend['observation_count']);
        $this->assertSame(['first' => '2026-01-10', 'last' => '2026-08-20'], $trend['date_range']);
    }

    public function test_between_observations_computes_consecutive_deltas(): void
    {
        $observations = collect([
            $this->observation(['value' => '90', 'collected_at' => now()->subDays(30)]),
            $this->observation(['value' => '100', 'collected_at' => now()->subDays(15)]),
            $this->observation(['value' => '95', 'collected_at' => now()]),
        ]);

        $trend = $this->engine()->trend($observations, 'Glucose');

        $this->assertCount(2, $trend['between_observations']);
        $this->assertSame(10.0, $trend['between_observations'][0]['change']);
        $this->assertSame('increased', $trend['between_observations'][0]['direction']);
        $this->assertSame(-5.0, $trend['between_observations'][1]['change']);
        $this->assertSame('decreased', $trend['between_observations'][1]['direction']);
    }

    public function test_skips_consecutive_delta_when_units_differ(): void
    {
        $observations = collect([
            $this->observation(['value' => '5.2', 'unit' => 'mmol/L', 'collected_at' => now()->subDays(30)]),
            $this->observation(['value' => '94', 'unit' => 'mg/dL', 'collected_at' => now()]),
        ]);

        $trend = $this->engine()->trend($observations, 'Glucose');

        $this->assertSame([], $trend['between_observations']);
    }

    public function test_empty_input_yields_empty_trend(): void
    {
        $trend = $this->engine()->trend(new Collection(), 'Glucose');

        $this->assertSame(0, $trend['observation_count']);
        $this->assertSame([], $trend['series']);
        $this->assertNull($trend['date_range']);
        $this->assertNull($trend['summary']['net_change']);
    }
}