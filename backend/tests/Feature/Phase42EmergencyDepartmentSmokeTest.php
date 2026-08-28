<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase42EmergencyDepartmentSmokeTest extends TestCase
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

    public function test_ed_visit_full_workflow_from_triage_to_disposition(): void
    {
        $nurse = $this->nurse();
        $clinician = $this->clinician();
        $patient = $this->patient();

        $visitId = $this->actingAs($nurse, 'sanctum')
            ->postJson('/api/v1/nursing/ed/visits', [
                'patient_id' => $patient->id,
                'chief_complaint' => 'Chest pain',
                'vitals_summary' => 'BP 150/90, HR 110',
            ])
            ->assertCreated()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.queue_status', 'waiting')
            ->json('data.id');

        $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/v1/nursing/ed/visits/{$visitId}/triage", ['acuity_level' => 'emergent'])
            ->assertOk()
            ->assertJsonPath('data.acuity_level', 'emergent')
            ->assertJsonPath('data.queue_status', 'in_triage');

        $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/v1/nursing/ed/visits/{$visitId}/assign", ['clinician_id' => $clinician->id])
            ->assertOk()
            ->assertJsonPath('data.clinician_name', $clinician->name)
            ->assertJsonPath('data.queue_status', 'being_seen');

        $response = $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/v1/nursing/ed/visits/{$visitId}/disposition", ['disposition' => 'admitted'])
            ->assertOk()
            ->assertJsonPath('data.disposition', 'admitted')
            ->assertJsonPath('data.queue_status', 'admitted');
        $this->assertNotNull($response->json('data.departure_time'));
    }

    public function test_ed_track_board_lists_active_visits_by_acuity(): void
    {
        $nurse = $this->nurse();
        $p1 = $this->patient();
        $p2 = $this->patient();

        $this->actingAs($nurse, 'sanctum')->postJson('/api/v1/nursing/ed/visits', ['patient_id' => $p1->id]);
        $visit2 = $this->actingAs($nurse, 'sanctum')->postJson('/api/v1/nursing/ed/visits', ['patient_id' => $p2->id])->json('data.id');

        $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/v1/nursing/ed/visits/{$visit2}/triage", ['acuity_level' => 'resuscitation'])
            ->assertOk();

        $this->actingAs($nurse, 'sanctum')
            ->getJson('/api/v1/nursing/ed/track-board')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.acuity_level', 'resuscitation');
    }

    public function test_ambulance_dispatch_and_dashboard_analytics(): void
    {
        $nurse = $this->nurse();
        $patient = $this->patient();

        $this->actingAs($nurse, 'sanctum')
            ->postJson('/api/v1/nursing/ed/visits', ['patient_id' => $patient->id])
            ->assertCreated();

        $dispatchId = $this->actingAs($nurse, 'sanctum')
            ->postJson('/api/v1/nursing/ed/ambulance', [
                'patient_id' => $patient->id,
                'pickup_location' => '42 High St',
                'destination_hospital' => 'General Hospital',
                'vehicle_id' => 'AMB-1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'dispatched')
            ->json('data.id');

        $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/v1/nursing/ed/ambulance/{$dispatchId}/status", ['status' => 'en_route'])
            ->assertOk()
            ->assertJsonPath('data.status', 'en_route');

        $this->actingAs($nurse, 'sanctum')
            ->getJson('/api/v1/nursing/ed/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'active_visits',
                    'arrivals_today',
                    'acuity_breakdown',
                    'average_los_minutes',
                    'crowding_ratio',
                    'active_ambulances',
                ],
            ])
            ->assertJsonPath('data.active_visits', 1)
            ->assertJsonPath('data.active_ambulances.0.vehicle_id', 'AMB-1');
    }
}
