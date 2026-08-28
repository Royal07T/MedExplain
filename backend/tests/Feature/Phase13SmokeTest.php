<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase13SmokeTest extends TestCase
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
        return User::factory()->clinician()->create(['organization_id' => $this->organization->id]);
    }

    private function patient(): User
    {
        return User::factory()->create([
            'role' => 'patient',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_patient_can_book_view_and_cancel_appointment(): void
    {
        $patient = $this->patient();
        $clinician = $this->clinician();

        $response = $this->actingAs($patient, 'sanctum')->postJson('/api/v1/patient/appointments', [
            'clinician_id' => $clinician->id,
            'chief_complaint' => 'Chest pain',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
            'duration_minutes' => 30,
        ]);

        $response->assertCreated()->assertJson(['success' => true]);
        $appointmentId = $response->json('data.id');
        $this->assertNotNull($appointmentId);

        $this->actingAs($patient, 'sanctum')
            ->getJson('/api/v1/patient/appointments')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($patient, 'sanctum')
            ->deleteJson("/api/v1/patient/appointments/{$appointmentId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_clinician_can_record_patient_vital_signs(): void
    {
        $patient = $this->patient();
        $clinician = $this->clinician();
        $clinician->clinicianPatients()->attach($patient->id);

        $response = $this->actingAs($clinician, 'sanctum')->postJson('/api/v1/clinician/clinical/vital-signs', [
            'patient_id' => $patient->id,
            'temperature' => 37.2,
            'heart_rate' => 76,
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'oxygen_saturation' => 98,
            'pain_score' => 2,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $response->assertCreated()->assertJson(['heart_rate' => 76]);

        $this->actingAs($clinician, 'sanctum')
            ->getJson("/api/v1/clinician/clinical/patients/{$patient->id}/vital-signs")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.heart_rate', 76);
    }

    public function test_clinical_decision_support_comprehensive_check_runs(): void
    {
        $patient = $this->patient();
        $clinician = $this->clinician();
        $clinician->clinicianPatients()->attach($patient->id);

        $this->actingAs($clinician, 'sanctum')
            ->postJson('/api/v1/clinician/clinical/cds/comprehensive', [
                'patient_id' => $patient->id,
                'medications' => [],
                'age' => 45,
                'vital_signs' => [
                    [
                        'name' => 'systolic_bp',
                        'value' => 180,
                        'unit' => 'mmHg',
                        'gender' => 'male',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonStructure([
                'alerts',
                'count',
                'summary' => ['severe', 'moderate', 'mild'],
            ]);
    }
}
