<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase61InteroperabilitySmokeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create([
            'name' => 'Interop Clinic',
            'slug' => 'interop-clinic',
            'address' => '1 Main St',
            'phone' => '555-0100',
            'email' => 'clinic@example.test',
            'website' => 'https://example.test',
            'is_active' => true,
        ]);
    }

    private function clinician(): User
    {
        return User::factory()->create([
            'role' => 'clinician',
            'organization_id' => $this->organization->id,
        ]);
    }

    private function makePatient(User $user, string $mrn): Patient
    {
        return Patient::create([
            'user_id' => $user->id,
            'organization_id' => $this->organization->id,
            'mrn' => $mrn,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'date_of_birth' => '1815-12-10',
            'gender' => 'female',
            'phone' => '555-0199',
            'email' => 'ada@example.test',
            'address' => '21 Lab Lane',
        ]);
    }

    // ─── Terminology ──────────────────────────────────────

    public function test_terminology_lists_systems(): void
    {
        $this->actingAs($this->clinician(), 'sanctum')
            ->getJson('/api/v1/terminology/systems')
            ->assertOk()
            ->assertJsonPath('data.systems', ['icd10', 'snomed']);
    }

    public function test_terminology_search_icd10(): void
    {
        $this->actingAs($this->clinician(), 'sanctum')
            ->getJson('/api/v1/terminology/search?system=icd10&q=hypertension')
            ->assertOk()
            ->assertJsonPath('data.system', 'icd10')
            ->assertJsonFragment(['code' => 'I10', 'display' => 'Essential (primary) hypertension']);
    }

    public function test_terminology_search_snomed(): void
    {
        $this->actingAs($this->clinician(), 'sanctum')
            ->getJson('/api/v1/terminology/search?system=snomed&q=73211009')
            ->assertOk()
            ->assertJsonFragment(['code' => '73211009']);
    }

    public function test_terminology_search_rejects_unknown_system(): void
    {
        $this->actingAs($this->clinician(), 'sanctum')
            ->getJson('/api/v1/terminology/search?system=loinc&q=x')
            ->assertStatus(422);
    }

    public function test_terminology_validate_consistency(): void
    {
        // Valid code + consistent display
        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson('/api/v1/terminology/validate', [
                'system' => 'icd10',
                'code' => 'I10',
                'display' => 'Essential (primary) hypertension',
            ])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.code_found', true);

        // Unknown code
        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson('/api/v1/terminology/validate', [
                'system' => 'icd10',
                'code' => 'B99.99',
            ])
            ->assertOk()
            ->assertJsonPath('data.code_found', false)
            ->assertJsonPath('data.valid', false);

        // Known code but wildly mismatched display
        $this->actingAs($this->clinician(), 'sanctum')
            ->postJson('/api/v1/terminology/validate', [
                'system' => 'icd10',
                'code' => 'I10',
                'display' => 'Broken leg',
            ])
            ->assertOk()
            ->assertJsonPath('data.code_found', true)
            ->assertJsonPath('data.display_consistent', false)
            ->assertJsonPath('data.valid', false);
    }

    // ─── FHIR R4 read ─────────────────────────────────────

    public function test_fhir_metadata_returns_capability_statement(): void
    {
        $resp = $this->actingAs($this->clinician(), 'sanctum')
            ->getJson('/api/v1/fhir/metadata')
            ->assertOk();
        $this->assertSame('application/fhir+json', $resp->headers->get('Content-Type'));
        $this->assertSame('CapabilityStatement', $resp->json('resourceType'));
        $this->assertSame('4.0.1', $resp->json('fhirVersion'));
    }

    public function test_fhir_patient_read(): void
    {
        $patientUser = User::factory()->create([
            'role' => 'patient',
            'organization_id' => $this->organization->id,
        ]);
        $patient = $this->makePatient($patientUser, 'MRN-0001');

        $resp = $this->actingAs($this->clinician(), 'sanctum')
            ->getJson("/api/v1/fhir/Patient/{$patient->id}")
            ->assertOk();
        $this->assertSame('application/fhir+json', $resp->headers->get('Content-Type'));
        $this->assertSame('Patient', $resp->json('resourceType'));
        $this->assertSame((string) $patient->id, $resp->json('id'));
        $this->assertSame('female', $resp->json('gender'));
        $this->assertSame('Lovelace', $resp->json('name.0.family'));
        $this->assertSame('1815-12-10', $resp->json('birthDate'));
        $this->assertSame("Organization/{$this->organization->id}", $resp->json('managingOrganization.reference'));
    }

    public function test_fhir_patient_outside_org_returns_operation_outcome(): void
    {
        $otherOrg = Organization::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);
        $otherUser = User::factory()->create(['role' => 'patient', 'organization_id' => $otherOrg->id]);
        $foreign = Patient::create([
            'user_id' => $otherUser->id,
            'organization_id' => $otherOrg->id,
            'mrn' => 'MRN-9999',
            'first_name' => 'Other',
            'last_name' => 'Org',
        ]);

        $this->actingAs($this->clinician(), 'sanctum')
            ->getJson("/api/v1/fhir/Patient/{$foreign->id}")
            ->assertNotFound()
            ->assertJsonPath('resourceType', 'OperationOutcome');
    }

    public function test_fhir_organization_read(): void
    {
        $resp = $this->actingAs($this->clinician(), 'sanctum')
            ->getJson("/api/v1/fhir/Organization/{$this->organization->id}")
            ->assertOk();
        $this->assertSame('Organization', $resp->json('resourceType'));
        $this->assertSame('Interop Clinic', $resp->json('name'));
    }

    public function test_fhir_requires_authentication(): void
    {
        $patient = $this->makePatient($this->clinician(), 'MRN-0002');

        $this->getJson('/api/v1/fhir/metadata')->assertUnauthorized();
        $this->getJson("/api/v1/fhir/Patient/{$patient->id}")->assertUnauthorized();
    }
}
