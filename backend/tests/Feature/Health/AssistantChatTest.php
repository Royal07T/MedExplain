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

class AssistantChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_assistant_reply_with_guardrails(): void
    {
        Http::fake([
            '*/api/v1/assistant/chat' => Http::response([
                'reply' => 'Educational answer about glucose.',
                'disclaimer' => 'Not a diagnosis.',
                'sources' => ['Understanding your cholesterol panel'],
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

        $response = $this->postJson('/api/v1/assistant/chat', [
            'message' => 'What does my glucose mean?',
        ])->assertOk();

        $response->assertJsonPath('reply', 'Educational answer about glucose.')
            ->assertJsonPath('disclaimer', 'Not a diagnosis.')
            ->assertJsonCount(1, 'sources');

        Http::assertSent(function ($request) use ($user) {
            $payload = $request->data();

            return $request->url() === config('fastapi.base_url').'/api/v1/assistant/chat'
                && $payload['message'] === 'What does my glucose mean?'
                && $payload['lab_tests'][0]['name'] === 'Glucose';
        });
    }

    public function test_requires_message_parameter(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/assistant/chat', [])->assertUnprocessable();
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/assistant/chat', ['message' => 'hello'])
            ->assertUnauthorized();
    }
}