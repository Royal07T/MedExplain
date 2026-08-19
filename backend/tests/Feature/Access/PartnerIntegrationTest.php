<?php

namespace Tests\Feature\Access;

use App\Enums\AuditEvent;
use App\Models\ApiPartner;
use App\Models\DocumentExtraction;
use App\Models\LabResult;
use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-partner-secret';

    private function makePartner(array $overrides = []): ApiPartner
    {
        return ApiPartner::factory()->create(array_merge([
            'client_secret' => \Illuminate\Support\Facades\Hash::make(self::SECRET),
        ], $overrides));
    }

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
    }

    private function requestToken(ApiPartner $partner): string
    {
        $response = $this->postJson('/api/v1/partner/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $partner->client_id,
            'client_secret' => self::SECRET,
        ])->assertOk();

        return $response->json('access_token');
    }

    public function test_issues_oauth_token_with_valid_credentials(): void
    {
        $partner = $this->makePartner();

        $this->postJson('/api/v1/partner/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $partner->client_id,
            'client_secret' => self::SECRET,
        ])->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'scope'])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('scope', 'health_record:read');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditEvent::PartnerTokenIssued->value,
        ]);
    }

    public function test_rejects_invalid_credentials(): void
    {
        $partner = $this->makePartner();

        $this->postJson('/api/v1/partner/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $partner->client_id,
            'client_secret' => 'wrong-secret',
        ])->assertStatus(401)
            ->assertJsonPath('error', 'invalid_client');
    }

    public function test_rejects_inactive_partner(): void
    {
        $partner = $this->makePartner(['is_active' => false]);

        $this->postJson('/api/v1/partner/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $partner->client_id,
            'client_secret' => self::SECRET,
        ])->assertStatus(401);
    }

    public function test_partner_can_read_record_with_consent(): void
    {
        $partner = $this->makePartner();
        $patient = User::factory()->create();
        $this->patientWithData($patient);
        $patient->consents()->create([
            'partner_id' => $partner->id,
            'scopes' => ['health_record:read'],
            'granted_at' => now(),
        ]);

        $token = $this->requestToken($partner);

        $this->getJson('/api/v1/partner/patients', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $patient->id);

        $this->getJson('/api/v1/partner/patients/'.$patient->id.'/record', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.profile.name', $patient->name)
            ->assertJsonCount(1, 'data.labs')
            ->assertJsonPath('data.labs.0.name', 'Glucose');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditEvent::PartnerRecordAccessed->value,
            'auditable_id' => $patient->id,
        ]);
    }

    public function test_partner_cannot_read_without_consent(): void
    {
        $partner = $this->makePartner();
        $patient = User::factory()->create();
        $this->patientWithData($patient);

        $token = $this->requestToken($partner);

        $this->getJson('/api/v1/partner/patients', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/partner/patients/'.$patient->id.'/record', ['Authorization' => 'Bearer '.$token])
            ->assertForbidden();
    }

    public function test_revoked_consent_blocks_access(): void
    {
        $partner = $this->makePartner();
        $patient = User::factory()->create();
        $this->patientWithData($patient);
        $patient->consents()->create([
            'partner_id' => $partner->id,
            'scopes' => ['health_record:read'],
            'granted_at' => now()->subDay(),
            'revoked_at' => now(),
        ]);

        $token = $this->requestToken($partner);

        $this->getJson('/api/v1/partner/patients/'.$patient->id.'/record', ['Authorization' => 'Bearer '.$token])
            ->assertForbidden();
    }

    public function test_token_without_scope_is_denied(): void
    {
        $partner = $this->makePartner(['scopes' => []]);
        $token = $this->requestToken($partner);

        $this->getJson('/api/v1/partner/patients', ['Authorization' => 'Bearer '.$token])
            ->assertForbidden();
    }

    public function test_patient_can_grant_and_revoke_consent(): void
    {
        $partner = $this->makePartner();
        $patient = User::factory()->create();
        Sanctum::actingAs($patient);

        $this->postJson('/api/v1/partner/consents/'.$partner->id)
            ->assertOk()
            ->assertJsonPath('data.partner_id', $partner->id)
            ->assertJsonPath('data.scopes', ['health_record:read']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditEvent::PatientConsentGranted->value,
            'user_id' => $patient->id,
        ]);

        $this->deleteJson('/api/v1/partner/consents/'.$partner->id)
            ->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditEvent::PatientConsentRevoked->value,
            'user_id' => $patient->id,
        ]);

        $this->getJson('/api/v1/partner/consents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.revoked_at', fn ($value) => $value !== null);
    }

    public function test_partner_quota_is_enforced_per_minute(): void
    {
        $partner = $this->makePartner(['quota_per_minute' => 1]);
        $token = $this->requestToken($partner);

        $this->getJson('/api/v1/partner/patients', ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        $this->getJson('/api/v1/partner/patients', ['Authorization' => 'Bearer '.$token])
            ->assertTooManyRequests();
    }

    public function test_requires_auth_for_partner_endpoints(): void
    {
        $this->getJson('/api/v1/partner/patients')->assertUnauthorized();
    }
}