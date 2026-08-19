<?php

namespace Tests\Feature\Health;

use App\Models\DocumentExtraction;
use App\Models\LabResult;
use App\Models\MedicalDocument;
use App\Models\Medication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HealthRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregates_latest_lab_per_test_and_medications(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $document = MedicalDocument::factory()->for($user)->create(['status' => 'processed']);
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
            'unit' => 'mg/dL',
            'status' => 'within_range',
            'reference_range' => '70-100 mg/dL',
            'collected_at' => now()->subDays(5),
            'sort_order' => 0,
        ]);
        LabResult::create([
            'document_extraction_id' => $extraction->id,
            'user_id' => $user->id,
            'name' => 'Glucose',
            'normalized_name' => 'glucose',
            'value' => '110',
            'unit' => 'mg/dL',
            'status' => 'above_range',
            'reference_range' => '70-100 mg/dL',
            'collected_at' => now(),
            'sort_order' => 1,
        ]);

        Medication::create([
            'user_id' => $user->id,
            'medical_document_id' => $document->id,
            'name' => 'Metformin',
            'dose' => '500',
            'frequency' => 'twice daily',
            'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/v1/health/record')
            ->assertOk()
            ->assertJsonCount(1, 'data.labs');

        $lab = $response->json('data.labs.0');
        $this->assertSame('Glucose', $lab['name']);
        $this->assertSame('110', $lab['value']);
        $this->assertSame('above_range', $lab['status']);

        $response->assertJsonCount(1, 'data.medications')
            ->assertJsonPath('data.medications.0.name', 'Metformin')
            ->assertJsonPath('data.profile.name', $user->name)
            ->assertJsonPath('data.profile.email', $user->email);
    }

    public function test_does_not_leak_other_users_data(): void
    {
        $owner = User::factory()->create();
        $document = MedicalDocument::factory()->for($owner)->create(['status' => 'processed']);
        $extraction = DocumentExtraction::create([
            'medical_document_id' => $document->id,
            'extraction_method' => 'pdf_text',
            'raw_text' => '',
        ]);
        LabResult::create([
            'document_extraction_id' => $extraction->id,
            'user_id' => $owner->id,
            'name' => 'Glucose',
            'normalized_name' => 'glucose',
            'value' => '95',
            'unit' => 'mg/dL',
            'status' => 'within_range',
            'collected_at' => now(),
            'sort_order' => 0,
        ]);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson('/api/v1/health/record')
            ->assertOk()
            ->assertJsonCount(0, 'data.labs')
            ->assertJsonCount(0, 'data.medications');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/health/record')->assertUnauthorized();
    }
}