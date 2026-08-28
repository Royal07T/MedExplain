<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase54VirtualHealthAssistantSmokeTest extends TestCase
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

    private function patient(): User
    {
        return User::factory()->create([
            'role' => 'patient',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_symptom_check_round_trips_to_ai_service(): void
    {
        Http::fake([
            '*/api/v1/assistant/symptom-check' => Http::response([
                'urgency' => 'emergency',
                'message' => 'Please seek medical care promptly.',
                'red_flags' => ['chest pain'],
                'matched' => [
                    ['symptom' => 'chest pain', 'category' => 'emergency', 'urgent' => true],
                ],
                'disclaimer' => 'Educational only.',
            ], 200),
        ]);

        $patient = $this->patient();

        $this->actingAs($patient, 'sanctum')
            ->postJson('/api/v1/assistant/symptom-check', ['text' => 'chest pain'])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.urgency', 'emergency')
            ->assertJsonPath('data.red_flags', ['chest pain']);

        Http::assertSent(function ($request): bool {
            return $request->url() === config('fastapi.base_url').'/api/v1/assistant/symptom-check'
                && $request['text'] === 'chest pain';
        });
    }

    public function test_symptom_check_requires_patient_role(): void
    {
        $clinician = User::factory()->create([
            'role' => 'clinician',
            'organization_id' => $this->organization->id,
        ]);
        $clinician->refresh();

        $this->actingAs($clinician, 'sanctum')
            ->postJson('/api/v1/assistant/symptom-check', ['text' => 'headache'])
            ->assertForbidden();
    }

    public function test_medication_reminder_lifecycle(): void
    {
        $patient = $this->patient();

        $reminderId = $this->actingAs($patient, 'sanctum')
            ->postJson('/api/v1/patient/medication-reminders', [
                'medication_name' => 'Metformin',
                'dose' => '500 mg',
                'frequency' => 'twice daily',
                'scheduled_time' => '08:00',
            ])
            ->assertCreated()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.medication_name', 'Metformin')
            ->assertJsonPath('data.active', true)
            ->json('data.id');

        $taken = $this->actingAs($patient, 'sanctum')
            ->postJson("/api/v1/patient/medication-reminders/{$reminderId}/taken")
            ->assertOk();
        $this->assertNotNull($taken->json('data.last_taken_at'));

        $this->actingAs($patient, 'sanctum')
            ->postJson("/api/v1/patient/medication-reminders/{$reminderId}/toggle")
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $list = $this->actingAs($patient, 'sanctum')
            ->getJson('/api/v1/patient/medication-reminders')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertNotNull($list->json('data.0.last_taken_at'));

        $this->actingAs($patient, 'sanctum')
            ->deleteJson("/api/v1/patient/medication-reminders/{$reminderId}")
            ->assertOk();

        $this->actingAs($patient, 'sanctum')
            ->getJson('/api/v1/patient/medication-reminders')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_patient_cannot_touch_another_patients_reminder(): void
    {
        $patientA = $this->patient();
        $patientB = $this->patient();

        $reminderId = $this->actingAs($patientA, 'sanctum')
            ->postJson('/api/v1/patient/medication-reminders', ['medication_name' => 'Aspirin'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($patientB, 'sanctum')
            ->postJson("/api/v1/patient/medication-reminders/{$reminderId}/taken")
            ->assertNotFound();
    }
}
