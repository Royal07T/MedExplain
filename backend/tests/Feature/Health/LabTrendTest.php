<?php

namespace Tests\Feature\Health;

use App\Models\DocumentExtraction;
use App\Models\LabResult;
use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabTrendTest extends TestCase
{
    use RefreshDatabase;

    private function addResult(User $user, MedicalDocument $document, array $attributes): LabResult
    {
        $extraction = DocumentExtraction::create([
            'medical_document_id' => $document->id,
            'extraction_method' => 'pdf_text',
            'raw_text' => '',
        ]);

        return LabResult::create(array_merge([
            'document_extraction_id' => $extraction->id,
            'user_id' => $user->id,
            'name' => 'Glucose',
            'normalized_name' => 'glucose',
            'value' => '95',
            'unit' => 'mg/dL',
            'reference_range' => '70-99',
            'status' => 'within_range',
            'sort_order' => 0,
        ], $attributes));
    }

    public function test_returns_ordered_series_across_reports(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $older = MedicalDocument::factory()->for($user)->create([
            'status' => 'processed',
            'original_filename' => 'first.pdf',
            'created_at' => now()->subDays(30),
        ]);
        $this->addResult($user, $older, [
            'value' => '90',
            'collected_at' => now()->subDays(30),
            'created_at' => now()->subDays(30),
        ]);

        $newer = MedicalDocument::factory()->for($user)->create([
            'status' => 'processed',
            'original_filename' => 'second.pdf',
            'created_at' => now(),
        ]);
        $this->addResult($user, $newer, [
            'value' => '105',
            'status' => 'above_range',
            'collected_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/labs/trends?name=Glucose')
            ->assertOk();

        $response->assertJsonPath('test', 'Glucose')
            ->assertJsonPath('unit', 'mg/dL')
            ->assertJsonCount(2, 'series')
            ->assertJsonPath('series.0.value', '90')
            ->assertJsonPath('series.0.status', 'within_range')
            ->assertJsonPath('series.1.value', '105')
            ->assertJsonPath('series.1.status', 'above_range')
            ->assertJsonPath('series.1.document_filename', 'second.pdf')
            ->assertJsonMissingPath('storage_path');
    }

    public function test_unknown_test_returns_empty_series(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/labs/trends?name=Phlebotinum')
            ->assertOk()
            ->assertJsonPath('test', 'Phlebotinum')
            ->assertJsonCount(0, 'series');
    }

    public function test_other_users_results_are_excluded(): void
    {
        $owner = User::factory()->create();
        $document = MedicalDocument::factory()->for($owner)->create(['status' => 'processed']);
        $this->addResult($owner, $document, []);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson('/api/v1/labs/trends?name=Glucose')
            ->assertOk()
            ->assertJsonCount(0, 'series');
    }

    public function test_requires_name_parameter(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/labs/trends')->assertUnprocessable();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/labs/trends?name=Glucose')->assertUnauthorized();
    }

    public function test_lists_distinct_test_names_with_metadata(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $document = MedicalDocument::factory()->for($user)->create(['status' => 'processed']);
        $this->addResult($user, $document, ['value' => '95']);
        $this->addResult($user, $document, [
            'name' => 'Cholesterol',
            'normalized_name' => 'cholesterol',
            'value' => '240',
            'unit' => 'mg/dL',
            'status' => 'above_range',
        ]);

        $this->getJson('/api/v1/labs/names')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Cholesterol')
            ->assertJsonPath('data.0.count', 1)
            ->assertJsonPath('data.1.name', 'Glucose');
    }
}