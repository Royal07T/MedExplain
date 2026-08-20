<?php

namespace Tests\Unit\HealthQuery;

use App\Enums\HealthQueryIntent;
use App\Services\HealthQuery\IntentRegistry;
use Tests\TestCase;

class IntentRegistryTest extends TestCase
{
    private function registry(): IntentRegistry
    {
        return new IntentRegistry();
    }

    public function test_detects_report_comparison(): void
    {
        $this->assertSame(
            HealthQueryIntent::ReportComparison,
            $this->registry()->detect('What changed between my last two reports?'),
        );
    }

    public function test_detects_lab_trend(): void
    {
        $this->assertSame(
            HealthQueryIntent::LabTrend,
            $this->registry()->detect('Show me how my glucose has changed over time.'),
        );
        $this->assertSame(
            HealthQueryIntent::LabTrend,
            $this->registry()->detect('How has my glucose changed over the last year?'),
        );
    }

    public function test_detects_medication_context(): void
    {
        $this->assertSame(
            HealthQueryIntent::MedicationContext,
            $this->registry()->detect('Which medications were active when this result was recorded?'),
        );
    }

    public function test_detects_recent_health_changes(): void
    {
        $this->assertSame(
            HealthQueryIntent::RecentHealthChanges,
            $this->registry()->detect('What are the most recent changes in my health record?'),
        );

        $this->assertSame(
            HealthQueryIntent::RecentHealthChanges,
            $this->registry()->detect("What's new in my health record recently?"),
        );
    }

    public function test_detects_current_vs_previous(): void
    {
        $this->assertSame(
            HealthQueryIntent::CurrentVsPrevious,
            $this->registry()->detect('Explain my current results in the context of my previous results.'),
        );
    }

    public function test_detects_health_timeline(): void
    {
        $this->assertSame(
            HealthQueryIntent::HealthTimeline,
            $this->registry()->detect('Show me my health timeline'),
        );
    }

    public function test_detects_lab_history(): void
    {
        $this->assertSame(
            HealthQueryIntent::LabHistory,
            $this->registry()->detect('Show me my glucose results'),
        );
    }

    public function test_detects_medication_history(): void
    {
        $this->assertSame(
            HealthQueryIntent::MedicationHistory,
            $this->registry()->detect('What medications am I taking?'),
        );
    }

    public function test_unmatched_question_falls_back_to_general(): void
    {
        $this->assertSame(
            HealthQueryIntent::GeneralHealthQuestion,
            $this->registry()->detect('What is HbA1c?'),
        );
    }

    public function test_detection_is_case_insensitive(): void
    {
        $this->assertSame(
            HealthQueryIntent::ReportComparison,
            $this->registry()->detect('WHAT CHANGED BETWEEN MY LAST TWO REPORTS?'),
        );
    }

    public function test_definitions_expose_requires_rag_flags(): void
    {
        $registry = $this->registry();

        $this->assertTrue($registry->definition(HealthQueryIntent::ReportComparison)->requiresRag);
        $this->assertFalse($registry->definition(HealthQueryIntent::LabTrend)->requiresRag);
        $this->assertFalse($registry->definition(HealthQueryIntent::MedicationContext)->requiresRag);
        $this->assertTrue($registry->definition(HealthQueryIntent::GeneralHealthQuestion)->requiresRag);
    }

    public function test_all_known_intents_have_definitions(): void
    {
        $registry = $this->registry();
        $keys = array_keys($registry->all());

        foreach (HealthQueryIntent::cases() as $intent) {
            $this->assertContains($intent->value, $keys);
        }
    }
}