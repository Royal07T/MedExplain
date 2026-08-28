<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase43NursingDocumentationSmokeTest extends TestCase
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

    private function nurse(): User
    {
        $user = User::factory()->create([
            'role' => 'nursing_staff',
            'organization_id' => $this->organization->id,
        ]);
        $user->refresh();
        return $user;
    }

    private function nurseTwo(): User
    {
        $user = User::factory()->create([
            'role' => 'nursing_staff',
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

    public function test_care_plan_lifecycle_from_create_to_completed(): void
    {
        $nurse = $this->nurse();
        $patient = $this->patient();

        $planId = $this->actingAs($nurse, 'sanctum')
            ->postJson('/api/v1/nursing/care-plans', [
                'patient_id' => $patient->id,
                'title' => 'Fall prevention plan',
                'description' => 'Prevent falls during hospitalization',
                'goals' => ['No falls during stay', 'Patient educated'],
                'interventions' => ['Bed rails up', 'Hourly rounding'],
            ])
            ->assertCreated()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.status', 'active')
            ->json('data.id');

        $this->actingAs($nurse, 'sanctum')
            ->putJson("/api/v1/nursing/care-plans/{$planId}", ['title' => 'Fall prevention plan (revised)'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Fall prevention plan (revised)');

        $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/v1/nursing/care-plans/{$planId}/status", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $list = $this->actingAs($nurse, 'sanctum')
            ->getJson('/api/v1/nursing/care-plans?patient_id='.$patient->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertNotNull($list->json('data.0.completed_at'));
    }

    public function test_mar_record_and_administration_workflow(): void
    {
        $nurse = $this->nurse();
        $patient = $this->patient();

        $marId = $this->actingAs($nurse, 'sanctum')
            ->postJson('/api/v1/nursing/mar', [
                'patient_id' => $patient->id,
                'medication_name' => 'Paracetamol',
                'dose' => '500',
                'dose_unit' => 'mg',
                'route' => 'PO',
                'scheduled_time' => now()->addHour(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'not_given')
            ->json('data.id');

        $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/v1/nursing/mar/{$marId}/status", ['status' => 'given'])
            ->assertOk()
            ->assertJsonPath('data.status', 'given')
            ->assertJsonPath('data.administered_by_name', $nurse->name);

        $marList = $this->actingAs($nurse, 'sanctum')
            ->getJson('/api/v1/nursing/mar?patient_id='.$patient->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertNotNull($marList->json('data.0.administered_time'));
    }

    public function test_assessments_and_shift_handoff_workflow(): void
    {
        $nurse = $this->nurse();
        $toNurse = $this->nurseTwo();
        $patient = $this->patient();

        $this->actingAs($nurse, 'sanctum')
            ->postJson('/api/v1/nursing/assessments', [
                'patient_id' => $patient->id,
                'assessment_type' => 'falls',
                'template_name' => 'Morse Fall Scale',
                'fall_risk_score' => 55,
                'assessment_data' => ['mobility' => 'impaired', 'sedation' => true],
            ])
            ->assertCreated()
            ->assertJsonPath('data.fall_risk_level', 'high');

        $this->actingAs($nurse, 'sanctum')
            ->postJson('/api/v1/nursing/assessments', [
                'patient_id' => $patient->id,
                'assessment_type' => 'pressure_ulcer',
                'template_name' => 'Braden Scale',
                'pressure_ulcer_stage' => 'stage_2',
                'findings' => 'Sacral redness',
            ])
            ->assertCreated()
            ->assertJsonPath('data.pressure_ulcer_stage', 'stage_2');

        $this->actingAs($nurse, 'sanctum')
            ->getJson('/api/v1/nursing/fall-risk')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.fall_risk_level', 'high');

        $this->actingAs($nurse, 'sanctum')
            ->postJson('/api/v1/nursing/handoffs', [
                'patient_id' => $patient->id,
                'to_nurse_id' => $toNurse->id,
                'unit' => '3N',
                'clinical_summary' => 'Stable, ambulates with assistance',
                'tasks_to_complete' => '10am meds',
                'safety_concerns' => 'High fall risk',
            ])
            ->assertCreated()
            ->assertJsonPath('data.to_nurse_name', $toNurse->name)
            ->assertJsonPath('data.is_complete', true);

        $this->actingAs($nurse, 'sanctum')
            ->getJson('/api/v1/nursing/assessments?patient_id='.$patient->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($nurse, 'sanctum')
            ->getJson('/api/v1/nursing/handoffs?patient_id='.$patient->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
