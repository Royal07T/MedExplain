<?php

namespace Tests\Feature\Health;

use App\Models\DocumentExtraction;
use App\Models\LabResult;
use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HealthQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_structured_health_intelligence_answer(): void
    {
        Http::fake([
            '*/api/v1/health/query' => Http::response([
                'summary' => 'Your glucose rose between the two reports.',
                'facts' => ['Your glucose changed from 90 to 110 mg/dL.'],
                'changes' => [],
                'context' => [],
                'educational_explanation' => [],
                'questions_for_professional' => [],
                'sources' => ['Understanding glucose'],
                'disclaimer' => 'Educational only. Not a diagnosis.',
                'data_used' => [
                    ['type' => 'report', 'label' => 'Report from 2026-08-20', 'reference' => '1'],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
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
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/health/query', [
            'question' => 'What changed between my last two reports?',
        ])->assertOk();

        $response->assertJsonPath('intent', 'REPORT_COMPARISON')
            ->assertJsonStructure([
                'query_id',
                'intent',
                'answer' => [
                    'summary',
                    'facts',
                    'changes',
                    'context',
                    'educational_explanation',
                    'questions_for_professional',
                    'sources',
                    'disclaimer',
                    'data_used',
                ],
            ])
            ->assertJsonPath('answer.summary', 'Your glucose rose between the two reports.')
            ->assertJsonPath('answer.disclaimer', 'Educational only. Not a diagnosis.');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $response->json('query_id'),
        );

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === config('fastapi.base_url').'/api/v1/health/query'
                && $payload['intent'] === 'REPORT_COMPARISON'
                && $payload['question'] === 'What changed between my last two reports?'
                && is_string($payload['query_id']);
        });
    }

    public function test_requires_question_parameter(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/health/query', [])->assertUnprocessable();
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/health/query', ['question' => 'hello'])
            ->assertUnauthorized();
    }
}