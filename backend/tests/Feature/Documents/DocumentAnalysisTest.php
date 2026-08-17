<?php

namespace Tests\Feature\Documents;

use App\Models\AiAnalysis;
use App\Models\AnalysisItem;
use App\Models\DocumentExtraction;
use App\Models\LabResult;
use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function createProcessedDocument(User $user): MedicalDocument
    {
        $document = MedicalDocument::factory()->for($user)->create(['status' => 'processed']);

        $extraction = DocumentExtraction::create([
            'medical_document_id' => $document->id,
            'extraction_method' => 'pdf_text',
            'raw_text' => "Glucose 95 mg/dL 70-99 Normal\n",
        ]);

        LabResult::create([
            'document_extraction_id' => $extraction->id,
            'name' => 'Glucose',
            'value' => '95',
            'unit' => 'mg/dL',
            'reference_range' => '70-99',
            'status' => 'within_range',
            'sort_order' => 0,
        ]);

        $analysis = AiAnalysis::create([
            'medical_document_id' => $document->id,
            'status' => 'completed',
            'summary' => 'Educational summary.',
            'disclaimer' => 'Not a diagnosis.',
            'concerns' => [],
            'processed_at' => now(),
        ]);

        AnalysisItem::create([
            'ai_analysis_id' => $analysis->id,
            'test_name' => 'Glucose',
            'explanation' => 'Within range.',
            'category' => 'reference_comparison',
            'sort_order' => 0,
        ]);

        return $document;
    }

    public function test_owner_can_view_analysis(): void
    {
        $user = User::factory()->create();
        $document = $this->createProcessedDocument($user);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/documents/{$document->id}/analysis")
            ->assertOk();

        $response->assertJsonPath('status', 'completed')
            ->assertJsonPath('summary', 'Educational summary.')
            ->assertJsonPath('disclaimer', 'Not a diagnosis.')
            ->assertJsonPath('items.0.test_name', 'Glucose')
            ->assertJsonPath('items.0.category', 'reference_comparison')
            ->assertJsonPath('lab_results.0.name', 'Glucose')
            ->assertJsonPath('lab_results.0.status', 'within_range')
            ->assertJsonMissingPath('storage_path');
    }

    public function test_other_user_cannot_view_analysis(): void
    {
        $owner = User::factory()->create();
        $document = $this->createProcessedDocument($owner);

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson("/api/v1/documents/{$document->id}/analysis")->assertForbidden();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $owner = User::factory()->create();
        $document = $this->createProcessedDocument($owner);

        $this->getJson("/api/v1/documents/{$document->id}/analysis")->assertUnauthorized();
    }

    public function test_missing_analysis_returns_404(): void
    {
        $user = User::factory()->create();
        $document = MedicalDocument::factory()->for($user)->create(['status' => 'uploaded']);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/documents/{$document->id}/analysis")
            ->assertNotFound()
            ->assertJsonPath('message', 'No analysis is available for this document yet.');
    }
}