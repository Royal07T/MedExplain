<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase51AIIntegrationSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create([
            'name' => 'Test Clinic',
            'slug' => 'test-clinic',
            'is_active' => true,
        ]);
    }

    private function clinician(): User
    {
        $user = User::factory()->create([
            'role' => 'clinician',
            'organization_id' => $this->organization->id,
        ]);
        $user->refresh();
        return $user;
    }

    private function patient(): User
    {
        return User::factory()->create([
            'role' => 'patient',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_nlp_summarize_round_trips_to_ai_service(): void
    {
        Http::fake([
            '*/api/v1/nlp/summarize' => Http::response([
                'summary' => 'Patient stable on current management.',
                'original_sentence_count' => 4,
                'retained_sentence_count' => 2,
            ], 200),
        ]);

        $clinician = $this->clinician();

        $response = $this->actingAs($clinician, 'sanctum')
            ->postJson('/api/v1/clinician/ai/nlp/summarize', [
                'text' => 'First sentence. Second sentence. Third. Fourth.',
                'max_sentences' => 2,
            ])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.summary', 'Patient stable on current management.')
            ->assertJsonPath('data.retained_sentence_count', 2);

        $this->assertNotNull($response->json('data.summary'));

        Http::assertSent(function ($request): bool {
            return $request->url() === config('fastapi.base_url').'/api/v1/nlp/summarize'
                && $request['text'] === 'First sentence. Second sentence. Third. Fourth.'
                && $request['max_sentences'] === 2;
        });
    }

    public function test_nlp_concepts_and_sentiment_endpoints(): void
    {
        Http::fake([
            '*/api/v1/nlp/concepts' => Http::response([
                'concepts' => [
                    ['type' => 'diagnosis', 'value' => 'hypertension', 'confidence' => 1.0],
                    ['type' => 'medication', 'value' => 'lisinopril', 'confidence' => 1.0],
                ],
            ], 200),
            '*/api/v1/nlp/sentiment' => Http::response([
                'label' => 'positive',
                'score' => 0.6,
                'positive_hits' => 3,
                'negative_hits' => 1,
            ], 200),
        ]);

        $clinician = $this->clinician();

        $this->actingAs($clinician, 'sanctum')
            ->postJson('/api/v1/clinician/ai/nlp/concepts', ['text' => 'hypertension on lisinopril'])
            ->assertOk()
            ->assertJsonCount(2, 'data.concepts')
            ->assertJsonPath('data.concepts.0.type', 'diagnosis');

        $this->actingAs($clinician, 'sanctum')
            ->postJson('/api/v1/clinician/ai/nlp/sentiment', ['text' => 'great care'])->assertOk()
            ->assertJsonPath('data.label', 'positive')
            ->assertJsonPath('data.score', 0.6);
    }

    public function test_predictive_readmission_los_and_deterioration(): void
    {
        Http::fake([
            '*/api/v1/predictive/readmission' => Http::response([
                'score' => 45, 'level' => 'high', 'contributors' => ['polypharmacy'],
            ], 200),
            '*/api/v1/predictive/length-of-stay' => Http::response([
                'predicted_days' => 4.5, 'range_min' => 3.0, 'range_max' => 6.0,
                'model' => 'heuristic', 'confidence' => 0.7, 'drivers' => ['acuity urgent'],
            ], 200),
            '*/api/v1/predictive/deterioration' => Http::response([
                'score' => 8, 'level' => 'critical',
                'components' => ['heart_rate' => 3], 'red_flags' => ['abnormal heart rate'],
            ], 200),
        ]);

        $clinician = $this->clinician();

        $this->actingAs($clinician, 'sanctum')
            ->postJson('/api/v1/clinician/ai/predictive/readmission', [
                'prior_admissions_90d' => 2,
                'comorbidities' => ['heart failure'],
                'length_of_stay_days' => 8,
                'polypharmacy' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.score', 45)
            ->assertJsonPath('data.level', 'high');

        $this->actingAs($clinician, 'sanctum')
            ->postJson('/api/v1/clinician/ai/predictive/length-of-stay', [
                'acuity' => 'urgent', 'admission_type' => 'emergency',
            ])
            ->assertOk()
            ->assertJsonPath('data.predicted_days', 4.5);

        $this->actingAs($clinician, 'sanctum')
            ->postJson('/api/v1/clinician/ai/predictive/deterioration', [
                'vitals' => ['heart_rate' => 135, 'respiratory_rate' => 8, 'spo2' => 88, 'conscious' => false],
            ])
            ->assertOk()
            ->assertJsonPath('data.score', 8)
            ->assertJsonPath('data.level', 'critical');
    }

    public function test_requires_clinician_role(): void
    {
        $patient = $this->patient();

        $this->actingAs($patient, 'sanctum')
            ->postJson('/api/v1/clinician/ai/nlp/summarize', ['text' => 'hello'])
            ->assertForbidden();
    }
}
