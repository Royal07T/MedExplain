<?php

namespace Tests\Feature\Health;

use App\Models\AiAnalysis;
use App\Models\DocumentExtraction;
use App\Models\LabResult;
use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_documented_events_newest_first(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $older = MedicalDocument::factory()->for($user)->create([
            'status' => 'processed',
            'original_filename' => 'older.pdf',
            'created_at' => now()->subDays(10),
            'processed_at' => now()->subDays(9),
        ]);
        $extraction = DocumentExtraction::create([
            'medical_document_id' => $older->id,
            'extraction_method' => 'pdf_text',
            'raw_text' => '',
        ]);
        LabResult::create([
            'document_extraction_id' => $extraction->id,
            'user_id' => $user->id,
            'name' => 'Glucose',
            'normalized_name' => 'glucose',
            'value' => '95',
            'unit' => 'mg/dL',
            'status' => 'within_range',
            'collected_at' => now()->subDays(9),
            'sort_order' => 0,
        ]);

        $newer = MedicalDocument::factory()->for($user)->create([
            'status' => 'processed',
            'original_filename' => 'newer.pdf',
            'created_at' => now(),
            'processed_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/health/timeline')
            ->assertOk();

        $data = $response->json('data');

        $this->assertCount(5, $data);
        $this->assertSame('document_uploaded', $data[0]['type']);
        $this->assertSame('newer.pdf', $data[0]['description']);

        $types = array_column($data, 'type');
        $this->assertContains('document_uploaded', $types);
        $this->assertContains('document_processed', $types);
        $this->assertContains('lab_result', $types);

        $labResult = collect($data)->first(fn (array $event): bool => $event['type'] === 'lab_result');
        $this->assertSame('Glucose recorded', $labResult['title']);

        $dates = array_map(fn (array $event): string => $event['occurred_at'], $data);
        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates);
    }

    public function test_excludes_other_users_events(): void
    {
        $owner = User::factory()->create();
        MedicalDocument::factory()->for($owner)->create(['status' => 'processed']);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson('/api/v1/health/timeline')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/health/timeline')->assertUnauthorized();
    }
}