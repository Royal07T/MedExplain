<?php

namespace Tests\Unit\HealthQuery;

use App\Enums\DocumentStatus;
use App\Models\DocumentExtraction;
use App\Models\LabResult;
use App\Models\MedicalDocument;
use App\Models\Medication;
use App\Models\Profile;
use App\Models\User;
use App\Services\HealthQuery\HealthContextService;
use App\Services\HealthService;
use App\Services\MedicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthContextServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): HealthContextService
    {
        return new HealthContextService(
            app(HealthService::class),
            app(MedicationService::class),
        );
    }

    private function processedDocument(User $user, int $daysAgo): MedicalDocument
    {
        return MedicalDocument::factory()->for($user)->create([
            'status' => DocumentStatus::Processed,
            'processed_at' => now()->subDays($daysAgo),
        ]);
    }

    private function extractionFor(MedicalDocument $document): DocumentExtraction
    {
        return DocumentExtraction::create([
            'medical_document_id' => $document->id,
            'extraction_method' => 'pdf_text',
            'raw_text' => '',
        ]);
    }

    public function test_latest_report_returns_newest_processed_report(): void
    {
        $user = User::factory()->create();
        $older = $this->processedDocument($user, 10);
        $newer = $this->processedDocument($user, 1);

        $latest = $this->service()->latestReport($user);

        $this->assertNotNull($latest);
        $this->assertSame($newer->id, $latest->id);
    }

    public function test_latest_report_ignores_pending_and_failed_reports(): void
    {
        $user = User::factory()->create();
        $processed = $this->processedDocument($user, 1);
        MedicalDocument::factory()->for($user)->create([
            'status' => DocumentStatus::Uploaded,
            'processed_at' => null,
        ]);
        MedicalDocument::factory()->for($user)->create([
            'status' => DocumentStatus::Failed,
            'processed_at' => null,
        ]);

        $latest = $this->service()->latestReport($user);

        $this->assertNotNull($latest);
        $this->assertSame($processed->id, $latest->id);
    }

    public function test_previous_report_returns_second_newest_excluding_the_given_one(): void
    {
        $user = User::factory()->create();
        $oldest = $this->processedDocument($user, 30);
        $middle = $this->processedDocument($user, 20);
        $newest = $this->processedDocument($user, 1);

        $previous = $this->service()->previousReport($user, $newest->id);

        $this->assertNotNull($previous);
        $this->assertSame($middle->id, $previous->id);
    }

    public function test_report_observations_returns_lab_results_in_order(): void
    {
        $user = User::factory()->create();
        $document = $this->processedDocument($user, 1);
        $extraction = $this->extractionFor($document);

        LabResult::create([
            'document_extraction_id' => $extraction->id,
            'user_id' => $user->id,
            'name' => 'Glucose',
            'normalized_name' => 'glucose',
            'value' => '95',
            'unit' => 'mg/dL',
            'status' => 'within_range',
            'collected_at' => now(),
            'sort_order' => 1,
        ]);
        LabResult::create([
            'document_extraction_id' => $extraction->id,
            'user_id' => $user->id,
            'name' => 'Hemoglobin',
            'normalized_name' => 'hemoglobin',
            'value' => '14',
            'unit' => 'g/dL',
            'status' => 'within_range',
            'collected_at' => now(),
            'sort_order' => 0,
        ]);

        $observations = $this->service()->reportObservations($user, $document);

        $this->assertCount(2, $observations);
        $this->assertSame('Hemoglobin', $observations->first()->name);
    }

    public function test_report_observations_returns_empty_for_another_users_document(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $document = $this->processedDocument($owner, 1);
        $extraction = $this->extractionFor($document);
        LabResult::create([
            'document_extraction_id' => $extraction->id,
            'user_id' => $owner->id,
            'name' => 'Glucose',
            'normalized_name' => 'glucose',
            'value' => '95',
            'status' => 'within_range',
            'collected_at' => now(),
            'sort_order' => 0,
        ]);

        $observations = $this->service()->reportObservations($other, $document);

        $this->assertTrue($observations->isEmpty());
    }

    public function test_lab_history_filters_by_normalized_name_and_orders_newest_first(): void
    {
        $user = User::factory()->create();
        $document = $this->processedDocument($user, 1);
        $extraction = $this->extractionFor($document);

        LabResult::create([
            'document_extraction_id' => $extraction->id,
            'user_id' => $user->id,
            'name' => 'Glucose',
            'normalized_name' => 'glucose',
            'value' => '90',
            'status' => 'within_range',
            'collected_at' => now()->subDays(3),
            'sort_order' => 0,
        ]);
        LabResult::create([
            'document_extraction_id' => $extraction->id,
            'user_id' => $user->id,
            'name' => 'Glucose',
            'normalized_name' => 'glucose',
            'value' => '110',
            'status' => 'above_range',
            'collected_at' => now()->subDay(),
            'sort_order' => 1,
        ]);
        LabResult::create([
            'document_extraction_id' => $extraction->id,
            'user_id' => $user->id,
            'name' => 'Hemoglobin',
            'normalized_name' => 'hemoglobin',
            'value' => '14',
            'status' => 'within_range',
            'collected_at' => now(),
            'sort_order' => 2,
        ]);

        $glucose = $this->service()->labHistory($user, '  Glucose  ');

        $this->assertCount(2, $glucose);
        $this->assertSame('110', $glucose->first()->value);
        $this->assertTrue($glucose->every(fn (LabResult $r): bool => $r->name === 'Glucose'));
    }

    public function test_lab_history_is_scoped_to_the_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $document = $this->processedDocument($owner, 1);
        $extraction = $this->extractionFor($document);
        LabResult::create([
            'document_extraction_id' => $extraction->id,
            'user_id' => $owner->id,
            'name' => 'Glucose',
            'normalized_name' => 'glucose',
            'value' => '95',
            'status' => 'within_range',
            'collected_at' => now(),
            'sort_order' => 0,
        ]);

        $history = $this->service()->labHistory($other);

        $this->assertTrue($history->isEmpty());
    }

    public function test_medications_are_scoped_to_the_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Medication::create([
            'user_id' => $owner->id,
            'name' => 'Metformin',
            'dose' => '500',
            'frequency' => 'twice daily',
            'sort_order' => 0,
        ]);

        $medications = $this->service()->medications($other);

        $this->assertTrue($medications->isEmpty());
    }

    public function test_recent_health_events_returns_owned_events_newest_first(): void
    {
        $user = User::factory()->create();
        $this->processedDocument($user, 1);

        $events = $this->service()->recentHealthEvents($user);

        $this->assertNotEmpty($events);
        $this->assertSame('document_uploaded', $events->first()['type']);
    }

    public function test_patient_context_only_contains_age_and_sex(): void
    {
        $user = User::factory()->create();
        Profile::create([
            'user_id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'date_of_birth' => now()->subYears(34),
            'gender' => 'female',
        ]);

        $context = $this->service()->patientContext($user);

        $this->assertSame(34, $context['age']);
        $this->assertSame('female', $context['sex']);
        $this->assertSame(['age', 'sex'], array_keys($context));
    }

    public function test_patient_context_handles_missing_profile(): void
    {
        $user = User::factory()->create();

        $context = $this->service()->patientContext($user);

        $this->assertNull($context['age']);
        $this->assertNull($context['sex']);
    }
}