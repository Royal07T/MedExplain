<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase33ImagingSmokeTest extends TestCase
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

    public function test_clinician_can_create_and_list_imaging_orders(): void
    {
        $patient = $this->patient();
        $clinician = $this->clinician();
        $clinician->clinicianPatients()->attach($patient->id);

        $response = $this->actingAs($clinician, 'sanctum')->postJson('/api/v1/clinician/imaging/orders', [
            'patient_id' => $patient->id,
            'modality' => 'xray',
            'body_region' => 'chest',
            'clinical_indication' => 'Persistent cough',
            'priority' => 'urgent',
            'icd_code' => 'R05',
            'notes' => 'PA and lateral views',
        ]);

        $response->assertCreated()->assertJson(['success' => true]);
        $orderId = $response->json('data.id');
        $this->assertNotNull($orderId);
        $response->assertJsonPath('data.modality', 'xray');
        $response->assertJsonPath('data.status', 'pending');

        $this->actingAs($clinician, 'sanctum')
            ->getJson("/api/v1/clinician/imaging/patients/{$patient->id}/orders")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body_region', 'chest');

        $this->actingAs($clinician, 'sanctum')
            ->getJson("/api/v1/clinician/imaging/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.id', $orderId);
    }

    public function test_clinician_can_update_status_and_record_result(): void
    {
        $patient = $this->patient();
        $clinician = $this->clinician();
        $clinician->clinicianPatients()->attach($patient->id);

        $orderId = $this->actingAs($clinician, 'sanctum')
            ->postJson('/api/v1/clinician/imaging/orders', [
                'patient_id' => $patient->id,
                'modality' => 'ct',
                'body_region' => 'head',
            ])
            ->json('data.id');

        $this->actingAs($clinician, 'sanctum')
            ->postJson("/api/v1/clinician/imaging/orders/{$orderId}/status", [
                'status' => 'scheduled',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled');

        $this->actingAs($clinician, 'sanctum')
            ->postJson("/api/v1/clinician/imaging/orders/{$orderId}/result", [
                'radiation_dose_mgy' => 2.1,
                'image_count' => 48,
                'findings' => 'No acute intracranial abnormality.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.image_count', 48);
    }

    public function test_clinician_can_attach_radiology_report_and_cancel(): void
    {
        $patient = $this->patient();
        $clinician = $this->clinician();
        $clinician->clinicianPatients()->attach($patient->id);

        $orderId = $this->actingAs($clinician, 'sanctum')
            ->postJson('/api/v1/clinician/imaging/orders', [
                'patient_id' => $patient->id,
                'modality' => 'mri',
                'body_region' => 'lumbar spine',
            ])
            ->json('data.id');

        $this->actingAs($clinician, 'sanctum')
            ->postJson("/api/v1/clinician/imaging/orders/{$orderId}/report", [
                'findings' => 'Mild disc degeneration at L4-L5.',
                'impression' => 'Degenerative changes; no stenosis.',
                'status' => 'final',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'final');

        $this->actingAs($clinician, 'sanctum')
            ->getJson("/api/v1/clinician/imaging/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.report.impression', 'Degenerative changes; no stenosis.');

        $this->actingAs($clinician, 'sanctum')
            ->postJson("/api/v1/clinician/imaging/orders/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }
}
