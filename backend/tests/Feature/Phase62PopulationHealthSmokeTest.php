<?php

namespace Tests\Feature;

use App\Models\LabResult;
use App\Models\DocumentExtraction;
use App\Models\MedicalDocument;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\ProblemList;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase62PopulationHealthSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create([
            'name' => 'Population Clinic',
            'slug' => 'population-clinic',
            'is_active' => true,
        ]);
    }

    private function patient(): User
    {
        return User::factory()->create(['role' => 'patient', 'organization_id' => $this->organization->id]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'organization_id' => $this->organization->id]);
        $user->refresh();

        return $user;
    }

    private function clinician(): User
    {
        $user = User::factory()->create(['role' => 'clinician', 'organization_id' => $this->organization->id]);
        $user->refresh();

        return $user;
    }

    private function issueProblem(User $patient, string $code, string $desc, string $status = 'active'): void
    {
        ProblemList::create([
            'patient_id' => $patient->id,
            'organization_id' => $this->organization->id,
            'icd10_code' => $code,
            'icd10_description' => $desc,
            'status' => $status,
            'created_by' => $patient->id,
            'updated_by' => $patient->id,
        ]);
    }

    private function addLab(User $patient, string $status): void
    {
        $document = MedicalDocument::factory()
            ->for($patient, 'user')
            ->create(['status' => 'processed']);

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
            'value' => '10.0',
            'unit' => 'mmol/L',
            'status' => $status,
            'collected_at' => now(),
        ]);
    }

    private function addVitals(User $patient, array $overrides = []): void
    {
        VitalSign::create(array_merge([
            'patient_id' => $patient->id,
            'organization_id' => $this->organization->id,
            'heart_rate' => 75,
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'oxygen_saturation' => 98.0,
            'temperature' => 36.7,
            'recorded_at' => now(),
            'recorded_by' => $patient->id,
        ], $overrides));
    }

    private function demographics(User $patient, array $overrides = []): void
    {
        Patient::create(array_merge([
            'user_id' => $patient->id,
            'organization_id' => $this->organization->id,
            'mrn' => 'MRN-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'date_of_birth' => '1985-01-01',
            'gender' => 'female',
        ], $overrides));
    }

    // ─── Disease registry ────────────────────────────────

    public function test_registry_groups_by_icd10_and_counts_distinct_patients(): void
    {
        $a = $this->patient();
        $b = $this->patient();
        $this->issueProblem($a, 'E11.9', 'Type 2 diabetes', 'chronic');
        $this->issueProblem($b, 'E11.9', 'Type 2 diabetes', 'active');
        $this->issueProblem($b, 'I10', 'Hypertension', 'active');

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/population-health/registry')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonFragment(['code' => 'E11.9', 'count' => 2])
            ->assertJsonFragment(['code' => 'I10', 'count' => 1]);
    }

    // ─── Risk stratification ─────────────────────────────

    public function test_risk_stratification_tiers_patients(): void
    {
        $low = $this->patient();
        $this->demographics($low, ['date_of_birth' => '1990-01-01']);
        $this->addVitals($low);

        $high = $this->patient();
        $this->demographics($high, ['date_of_birth' => '1950-01-01']);
        $this->issueProblem($high, 'I10', 'Hypertension', 'active');
        $this->issueProblem($high, 'E11.9', 'Diabetes', 'chronic');
        $this->issueProblem($high, 'I48.91', 'Atrial fibrillation', 'chronic');
        $this->addLab($high, 'above_range');
        $this->addVitals($high, ['blood_pressure_systolic' => 190, 'oxygen_saturation' => 88]);

        $resp = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/population-health/risk')
            ->assertOk();

        $this->assertSame('low', collect($resp->json('data.patients'))->firstWhere('user_id', $low->id)['tier']);
        $this->assertSame('high', collect($resp->json('data.patients'))->firstWhere('user_id', $high->id)['tier']);
        $this->assertGreaterThanOrEqual(1, $resp->json('data.summary.high'));
    }

    // ─── Population dashboard ────────────────────────────

    public function test_dashboard_aggregates_population_stats(): void
    {
        $a = $this->patient();
        $b = $this->patient();
        $this->demographics($a, ['gender' => 'female', 'date_of_birth' => '1990-01-01']);
        $this->demographics($b, ['gender' => 'male', 'date_of_birth' => '1950-01-01']);
        $this->issueProblem($a, 'E11.9', 'Diabetes', 'active');
        $this->addLab($a, 'above_range');
        $this->addLab($b, 'within_range');

        $data = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/population-health/dashboard')
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $data['total_patients']);
        $this->assertSame(1, $data['gender_breakdown']['female']);
        $this->assertSame(1, $data['gender_breakdown']['male']);
        $this->assertSame(1, $data['age_band_breakdown']['18-39']);
        $this->assertSame(1, $data['age_band_breakdown']['65+']);
        $this->assertSame(1, $data['patients_with_abnormal_labs']);
        $this->assertEquals(50.0, (float) $data['abnormal_lab_rate']);
        $this->assertCount(1, $data['top_conditions']);
    }

    // ─── Access scoping ──────────────────────────────────

    public function test_clinician_sees_only_granted_patients(): void
    {
        $granted = $this->patient();
        $notGranted = $this->patient();
        $this->issueProblem($granted, 'I10', 'Hypertension', 'active');

        $clinician = $this->clinician();
        $clinician->clinicianPatients()->attach($granted->id);

        $this->actingAs($clinician, 'sanctum')
            ->getJson('/api/v1/population-health/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_patients', 1);
    }

    public function test_patient_role_is_denied(): void
    {
        $this->actingAs($this->patient(), 'sanctum')
            ->getJson('/api/v1/population-health/dashboard')
            ->assertForbidden();
    }
}
