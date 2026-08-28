<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Ward;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase41BedManagementSmokeTest extends TestCase
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
        // Persist the Spatie role synchronously.
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

    public function test_nurse_can_create_ward_and_add_beds(): void
    {
        $nurse = $this->nurse();

        $response = $this->actingAs($nurse, 'sanctum')->postJson('/api/v1/nursing/wards', [
            'name' => 'East Wing',
            'code' => 'EAST',
            'floor' => '2',
            'location' => 'Building A',
            'capacity' => 10,
        ]);

        $response->assertCreated()->assertJson(['success' => true]);
        $wardId = $response->json('data.id');
        $this->assertNotNull($wardId);

        $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/v1/nursing/wards/{$wardId}/beds", ['count' => 3, 'bed_type' => 'standard'])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 3)
            ->assertJsonPath('data.first_bed_number', 1);

        $this->actingAs($nurse, 'sanctum')
            ->getJson("/api/v1/nursing/wards/{$wardId}/beds")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->actingAs($nurse, 'sanctum')
            ->getJson('/api/v1/nursing/wards')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.beds_count', 3);
    }

    public function test_nurse_can_assign_and_discharge_patient_from_bed(): void
    {
        $nurse = $this->nurse();
        $patient = $this->patient();

        $wardId = $this->actingAs($nurse, 'sanctum')
            ->postJson('/api/v1/nursing/wards', ['name' => 'Intensive Care', 'code' => 'ICU'])
            ->json('data.id');

        $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/v1/nursing/wards/{$wardId}/beds", ['count' => 1])
            ->assertCreated();
        $bedId = Bed::where('ward_id', $wardId)->first()->id;

        $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/v1/nursing/beds/{$bedId}/assign", ['patient_id' => $patient->id])
            ->assertOk()
            ->assertJsonPath('data.is_occupied', true)
            ->assertJsonPath('data.current_patient.id', $patient->id);

        $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/v1/nursing/beds/{$bedId}/discharge")
            ->assertOk()
            ->assertJsonPath('data.is_occupied', false)
            ->assertJsonPath('data.cleaning_status', 'needs_cleaning');

        $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/v1/nursing/beds/{$bedId}/cleaning", ['cleaning_status' => 'clean'])
            ->assertOk()
            ->assertJsonPath('data.cleaning_status', 'clean');
    }

    public function test_bed_utilization_analytics_are_correct(): void
    {
        $nurse = $this->nurse();
        $patient = $this->patient();

        $wardId = $this->actingAs($nurse, 'sanctum')
            ->postJson('/api/v1/nursing/wards', ['name' => 'Surgical', 'code' => 'SUR'])
            ->json('data.id');

        $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/v1/nursing/wards/{$wardId}/beds", ['count' => 4]);

        $bedId = Bed::where('ward_id', $wardId)->first()->id;

        $this->actingAs($nurse, 'sanctum')
            ->postJson("/api/v1/nursing/beds/{$bedId}/assign", ['patient_id' => $patient->id])
            ->assertOk();

        $this->actingAs($nurse, 'sanctum')
            ->getJson('/api/v1/nursing/utilization')
            ->assertOk()
            ->assertJsonPath('data.total_beds', 4)
            ->assertJsonPath('data.occupied_beds', 1)
            ->assertJsonPath('data.available_beds', 3)
            ->assertJsonPath('data.utilization_rate', 25);
    }
}
