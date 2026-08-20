<?php

namespace Tests\Unit\HealthQuery;

use App\Models\LabResult;
use App\Services\HealthQuery\ReportComparisonService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ReportComparisonServiceTest extends TestCase
{
    private function service(): ReportComparisonService
    {
        return new ReportComparisonService();
    }

    private function labResult(array $attributes = []): LabResult
    {
        return new LabResult(array_merge([
            'name' => 'Glucose',
            'normalized_name' => 'glucose',
            'value' => '100',
            'unit' => 'mg/dL',
            'status' => 'within_range',
        ], $attributes));
    }

    public function test_detects_changed_numeric_result_with_delta_percent_and_direction(): void
    {
        $previous = collect([$this->labResult(['value' => '5.2', 'unit' => 'mmol/L'])]);
        $current = collect([$this->labResult(['value' => '6.4', 'unit' => 'mmol/L'])]);

        $result = $this->service()->compare($previous, $current);

        $this->assertSame('changed', $result['changes'][0]['change_type']);
        $this->assertSame(6.4, $result['changes'][0]['current_numeric']);
        $this->assertSame(1.2, $result['changes'][0]['change']);
        $this->assertSame(23.08, $result['changes'][0]['change_percent']);
        $this->assertSame('increased', $result['changes'][0]['direction']);
        $this->assertSame(['Glucose'], $result['changed_tests']);
    }

    public function test_detects_decreased_result(): void
    {
        $previous = collect([$this->labResult(['value' => '110', 'status' => 'above_range'])]);
        $current = collect([$this->labResult(['value' => '90', 'status' => 'within_range'])]);

        $result = $this->service()->compare($previous, $current);

        $this->assertSame(-20.0, $result['changes'][0]['change']);
        $this->assertSame(-18.18, $result['changes'][0]['change_percent']);
        $this->assertSame('decreased', $result['changes'][0]['direction']);
        $this->assertTrue($result['changes'][0]['status_changed']);
    }

    public function test_detects_new_and_removed_results(): void
    {
        $previous = collect([
            $this->labResult(['name' => 'Glucose', 'normalized_name' => 'glucose', 'value' => '95']),
            $this->labResult(['name' => 'Hemoglobin', 'normalized_name' => 'hemoglobin', 'value' => '14', 'unit' => 'g/dL']),
        ]);
        $current = collect([
            $this->labResult(['name' => 'Glucose', 'normalized_name' => 'glucose', 'value' => '95']),
            $this->labResult(['name' => 'Potassium', 'normalized_name' => 'potassium', 'value' => '4.0', 'unit' => 'mmol/L']),
        ]);

        $result = $this->service()->compare($previous, $current);

        $byTest = collect($result['changes'])->keyBy('test');
        $this->assertSame('new', $byTest['Potassium']['change_type']);
        $this->assertSame('removed', $byTest['Hemoglobin']['change_type']);
        $this->assertSame('unchanged', $byTest['Glucose']['change_type']);
        $this->assertSame(['Potassium'], $result['new_tests']);
        $this->assertSame(['Hemoglobin'], $result['removed_tests']);
    }

    public function test_unchanged_result_has_zero_delta_and_unchanged_direction(): void
    {
        $previous = collect([$this->labResult(['value' => '95'])]);
        $current = collect([$this->labResult(['value' => '95'])]);

        $result = $this->service()->compare($previous, $current);

        $this->assertSame('unchanged', $result['changes'][0]['change_type']);
        $this->assertSame(0.0, $result['changes'][0]['change']);
        $this->assertSame(0.0, $result['changes'][0]['change_percent']);
        $this->assertSame('unchanged', $result['changes'][0]['direction']);
    }

    public function test_non_numeric_values_compared_as_strings_without_invented_deltas(): void
    {
        $previous = collect([$this->labResult(['value' => 'positive', 'status' => 'positive'])]);
        $current = collect([$this->labResult(['value' => 'negative', 'status' => 'negative'])]);

        $result = $this->service()->compare($previous, $current);

        $this->assertSame('changed', $result['changes'][0]['change_type']);
        $this->assertNull($result['changes'][0]['change']);
        $this->assertNull($result['changes'][0]['change_percent']);
        $this->assertNull($result['changes'][0]['direction']);
        $this->assertTrue($result['changes'][0]['status_changed']);
    }

    public function test_guard_against_division_by_zero_percent(): void
    {
        $previous = collect([$this->labResult(['value' => '0'])]);
        $current = collect([$this->labResult(['value' => '10'])]);

        $result = $this->service()->compare($previous, $current);

        $this->assertSame(10.0, $result['changes'][0]['change']);
        $this->assertNull($result['changes'][0]['change_percent']);
        $this->assertSame('increased', $result['changes'][0]['direction']);
    }

    public function test_detects_unit_change(): void
    {
        $previous = collect([$this->labResult(['value' => '5.2', 'unit' => 'mmol/L'])]);
        $current = collect([$this->labResult(['value' => '94', 'unit' => 'mg/dL'])]);

        $result = $this->service()->compare($previous, $current);

        $this->assertTrue($result['changes'][0]['unit_changed']);
    }

    public function test_includes_report_dates(): void
    {
        $previous = collect([$this->labResult(['value' => '95'])]);
        $current = collect([$this->labResult(['value' => '100'])]);

        $result = $this->service()->compare(
            $previous,
            $current,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-08-20'),
        );

        $this->assertSame(['2026-07-01', '2026-08-20'], $result['report_dates']);
    }

    public function test_returns_empty_changes_for_empty_input(): void
    {
        $result = $this->service()->compare(new Collection(), new Collection());

        $this->assertSame([], $result['changes']);
        $this->assertSame([], $result['new_tests']);
        $this->assertSame([], $result['removed_tests']);
    }
}