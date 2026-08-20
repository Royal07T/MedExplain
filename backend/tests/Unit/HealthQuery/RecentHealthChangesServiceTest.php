<?php

namespace Tests\Unit\HealthQuery;

use App\Models\DocumentExtraction;
use App\Models\LabResult;
use App\Models\MedicalDocument;
use App\Models\Medication;
use App\Models\User;
use App\Services\HealthQuery\HealthContextService;
use App\Services\HealthQuery\RecentHealthChangesService;
use App\Services\HealthService;
use App\Services\MedicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecentHealthChangesServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(User $user): RecentHealthChangesService
    {
        $context = new HealthContextService(
            app(HealthService::class),
            app(MedicationService::class),
        );

        return new RecentHealthChangesService($context);
    }

    public function test_aggregates_and_orders_changes_newest_first(): void
    {
        $user = User::factory()->create();
        $document = MedicalDocument::factory()->for($user)->create([
            'status' => 'processed',
            'processed_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
        ]);
        $extraction = DocumentExtraction::create([
            'medical_document_id' => $document->id,
            'extraction_method' => 'pdf_text',
            'raw_text' => '',
        ]);
        LabResult::create([
            'document_extraction_id' => $extraction->id,
            'user_id' => $user->id,
            'name' => 'Glucose',
            'normalized_name' => 'glucose',
            'value' => '95',
            'status' => 'within_range',
            'collected_at' => now()->subDay(),
            'sort_order' => 0,
        ]);
        Medication::create([
            'user_id' => $user->id,
            'medical_document_id' => $document->id,
            'name' => 'Metformin',
            'dose' => '500',
            'created_at' => now(),
        ]);

        $changes = $this->service($user)->changes($user, 10);

        $this->assertSame('medication_added', $changes[0]['type']);
        $this->assertNotEmpty($changes);
        $occurredAt = array_map(fn (array $event): string => $event['occurred_at'], $changes);
        $sorted = $occurredAt;
        rsort($sorted);
        $this->assertSame($sorted, $occurredAt);
    }

    public function test_includes_medication_ended_events(): void
    {
        $user = User::factory()->create();
        $document = MedicalDocument::factory()->for($user)->create([
            'status' => 'processed',
            'processed_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
        ]);
        Medication::create([
            'user_id' => $user->id,
            'medical_document_id' => $document->id,
            'name' => 'Lisinopril',
            'start_date' => '2026-01-01',
            'end_date' => now()->subDays(3)->toDateString(),
            'created_at' => now()->subDays(60),
        ]);

        $changes = $this->service($user)->changes($user, 50);

        $ended = collect($changes)->firstWhere('type', 'medication_ended');
        $this->assertNotNull($ended);
        $this->assertSame('Lisinopril', $ended['description']);
    }

    public function test_future_end_dates_do_not_produce_ended_events(): void
    {
        $user = User::factory()->create();
        $document = MedicalDocument::factory()->for($user)->create([
            'status' => 'processed',
            'processed_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
        ]);
        Medication::create([
            'user_id' => $user->id,
            'medical_document_id' => $document->id,
            'name' => 'Metformin',
            'start_date' => '2026-01-01',
            'end_date' => now()->addDays(30)->toDateString(),
            'created_at' => now()->subDays(60),
        ]);

        $changes = $this->service($user)->changes($user, 50);

        $this->assertNull(collect($changes)->firstWhere('type', 'medication_ended'));
    }

    public function test_does_not_leak_other_users_changes(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        MedicalDocument::factory()->for($owner)->create([
            'status' => 'processed',
            'processed_at' => now(),
            'created_at' => now(),
        ]);
        Medication::create([
            'user_id' => $owner->id,
            'name' => 'Metformin',
            'created_at' => now(),
        ]);

        $changes = $this->service($other)->changes($other, 10);

        $this->assertSame([], $changes);
    }
}