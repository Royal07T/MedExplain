<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase53ImagingAIIntegrationSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create([
            'name' => 'Imaging Clinic',
            'slug' => 'imaging-clinic',
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

    private function createOrder(User $clinician, User $patient, array $overrides = []): int
    {
        return $this->actingAs($clinician, 'sanctum')
            ->postJson('/api/v1/clinician/imaging/orders', array_merge([
                'patient_id' => $patient->id,
                'modality' => 'ct',
                'body_region' => 'head',
                'clinical_indication' => 'Persistent headache',
                'priority' => 'routine',
                'icd_code' => 'G44.1',
            ], $overrides))
            ->assertCreated()
            ->json('data.id');
    }

    public function test_clinician_can_analyze_imaging_order(): void
    {
        Http::fake([
            '*/api/v1/imaging/analyze' => Http::response([
                'priority_level' => 'routine',
                'rationale' => 'No acute red-flag signals.',
                'recommendations' => [
                    ['title' => 'Priority standard', 'detail' => 'Nothing acute.', 'priority_impact' => 'low'],
                ],
                'quality_hints' => ['No anomalies.'],
                'disclaimer' => 'AI-assisted, not a diagnosis.',
                'analyzed_modality' => 'ct',
            ], 200),
        ]);

        $clinician = $this->clinician();
        $patient = $this->patient();
        $clinician->clinicianPatients()->attach($patient->id);
        $orderId = $this->createOrder($clinician, $patient);

        $response = $this->actingAs($clinician, 'sanctum')
            ->postJson('/api/v1/clinician/ai/imaging/analyze', [
                'imaging_order_id' => $orderId,
            ])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.priority_level', 'routine')
            ->assertJsonPath('data.analyzed_modality', 'ct');

        $this->assertNotNull($response->json('data.disclaimer'));

        Http::assertSent(function ($request) use ($orderId): bool {
            return $request->url() === config('fastapi.base_url').'/api/v1/imaging/analyze'
                && $request['imaging_order_id'] === $orderId
                && $request['modality'] === 'ct'
                && $request['priority'] === 'routine';
        });
    }

    public function test_stat_order_passes_priority_to_ai_service(): void
    {
        Http::fake([
            '*/api/v1/imaging/analyze' => Http::response([
                'priority_level' => 'stat',
                'rationale' => 'Acute event.',
                'recommendations' => [],
                'quality_hints' => [],
                'disclaimer' => 'AI-assisted.',
                'analyzed_modality' => 'ct',
            ], 200),
        ]);

        $clinician = $this->clinician();
        $patient = $this->patient();
        $clinician->clinicianPatients()->attach($patient->id);
        $orderId = $this->createOrder($clinician, $patient, [
            'priority' => 'stat',
            'clinical_indication' => 'Suspected acute stroke',
        ]);

        $this->actingAs($clinician, 'sanctum')
            ->postJson('/api/v1/clinician/ai/imaging/analyze', [
                'imaging_order_id' => $orderId,
            ])
            ->assertOk()
            ->assertJsonPath('data.priority_level', 'stat');

        Http::assertSent(fn ($request) => $request['priority'] === 'stat'
            && $request['clinical_indication'] === 'Suspected acute stroke');
    }

    public function test_requires_clinician_access_to_analyze_order(): void
    {
        $clinician = $this->clinician();
        $otherClinician = $this->clinician();
        $patient = $this->patient();
        $otherClinician->clinicianPatients()->attach($patient->id);
        $orderId = $this->createOrder($otherClinician, $patient);

        // This clinician has no access to that patient.
        $this->actingAs($clinician, 'sanctum')
            ->postJson('/api/v1/clinician/ai/imaging/analyze', [
                'imaging_order_id' => $orderId,
            ])
            ->assertForbidden();
    }

    public function test_order_from_other_organization_not_found(): void
    {
        Http::fake(['*/api/v1/imaging/analyze' => Http::response([], 500)]);

        $clinician = $this->clinician();
        $patient = $this->patient();
        $clinician->clinicianPatients()->attach($patient->id);
        $orderId = $this->createOrder($clinician, $patient);

        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'slug' => 'other-org',
            'is_active' => true,
        ]);
        $otherClinician = User::factory()->create([
            'role' => 'clinician',
            'organization_id' => $otherOrg->id,
        ])->refresh();

        $this->actingAs($otherClinician, 'sanctum')
            ->postJson('/api/v1/clinician/ai/imaging/analyze', [
                'imaging_order_id' => $orderId,
            ])
            ->assertNotFound();
    }

    public function test_requires_clinician_role(): void
    {
        Http::fake(['*/api/v1/imaging/analyze' => Http::response([], 500)]);

        $patient = $this->patient();
        $this->actingAs($patient, 'sanctum')
            ->postJson('/api/v1/clinician/ai/imaging/analyze', ['imaging_order_id' => 1])
            ->assertForbidden();
    }
}
