<?php

namespace Tests\Feature\Access;

use App\Enums\AuditEvent;
use App\Models\DocumentExtraction;
use App\Models\LabResult;
use App\Models\MedicalDocument;
use App\Models\Medication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClinicianAccessTest extends TestCase
{
    use RefreshDatabase;

    private function patientWithData(User $patient): void
    {
        $document = MedicalDocument::factory()->for($patient)->create(['status' => 'processed']);
        $extraction = DocumentExtraction::create([
            'medical_document_id' => $document->id,
            'extraction_method' => 'pdf_text',
            'raw_text' => '',
        ]);
        LabResult::create([
            'document_extraction_id' => $extraction->id,
            'user_id' => $patient->id,
            'name' => 'Glucose',
            'normalized_name' => 'glucose',
            'value' => '95',
            'unit' => 'mg/dL',
            'status' => 'within_range',
            'collected_at' => now(),
            'sort_order' => 0,
        ]);
        Medication::create([
            'user_id' => $patient->id,
            'medical_document_id' => $document->id,
            'name' => 'Metformin',
            'dose' => '500',
            'sort_order' => 0,
        ]);
    }

    public function test_clinician_can_grant_access_and_view_patient_record(): void
    {
        $clinician = User::factory()->clinician()->create();
        $patient = User::factory()->create();
        $this->patientWithData($patient);
        Sanctum::actingAs($clinician);

        $this->postJson('/api/v1/clinician/patients', ['email' => $patient->email])
            ->assertCreated()
            ->assertJsonPath('data.email', $patient->email);

        $response = $this->getJson('/api/v1/clinician/patients/'.$patient->id.'/record')
            ->assertOk();

        $this->assertSame($patient->name, $response->json('data.profile.name'));
        $this->assertCount(1, $response->json('data.labs'));
        $this->assertSame('Glucose', $response->json('data.labs.0.name'));
        $this->assertSame('Metformin', $response->json('data.medications.0.name'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditEvent::ClinicianAccessGranted->value,
            'auditable_id' => $patient->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditEvent::ClinicianRecordViewed->value,
            'auditable_id' => $patient->id,
        ]);
    }

    public function test_clinician_cannot_view_unassigned_patient(): void
    {
        $clinician = User::factory()->clinician()->create();
        $patient = User::factory()->create();
        $this->patientWithData($patient);
        Sanctum::actingAs($clinician);

        $this->getJson('/api/v1/clinician/patients/'.$patient->id.'/record')
            ->assertForbidden();
    }

    public function test_patient_role_is_denied_clinician_endpoints(): void
    {
        $patient = User::factory()->create();
        Sanctum::actingAs($patient);

        $this->getJson('/api/v1/clinician/patients')->assertForbidden();
        $this->postJson('/api/v1/clinician/patients', ['email' => 'x@example.com'])
            ->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/clinician/patients')->assertUnauthorized();
    }

    public function test_clinician_patient_list_includes_last_lab_date(): void
    {
        $clinician = User::factory()->clinician()->create();
        $patient = User::factory()->create();
        $this->patientWithData($patient);
        $clinician->clinicianPatients()->attach($patient->id);
        Sanctum::actingAs($clinician);

        $this->getJson('/api/v1/clinician/patients')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $patient->name)
            ->assertJsonPath('data.0.last_lab_date', LabResult::where('user_id', $patient->id)->value('collected_at')->toISOString());
    }
}