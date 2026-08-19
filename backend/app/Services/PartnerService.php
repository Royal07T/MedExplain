<?php

namespace App\Services;

use App\Enums\PartnerScope;
use App\Models\ApiPartner;
use App\Models\PatientConsent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Partner (healthtech provider) integration: token issuance, patient consent,
 * and consent-scoped access to health records.
 *
 * Safety: a partner can never see a patient's data without an active,
 * scope-specific consent granted by that patient. Consent is the single
 * source of truth for authorization.
 */
final class PartnerService
{
    /**
     * Validate client credentials and return the partner, or null on failure.
     */
    public function authenticate(string $clientId, string $clientSecret): ?ApiPartner
    {
        $partner = ApiPartner::query()
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->first();

        if ($partner === null || ! Hash::check($clientSecret, $partner->client_secret)) {
            return null;
        }

        return $partner;
    }

    /**
     * Issue a bearer token for the partner with its configured scopes.
     */
    public function issueToken(ApiPartner $partner): string
    {
        return $partner->createToken('partner', $partner->scopes ?? [])->plainTextToken;
    }

    /**
     * Patients who have an active consent with this partner.
     *
     * @return Collection<int, array{id: int, name: string, scopes: list<string>}>
     */
    public function consentedPatients(ApiPartner $partner): Collection
    {
        return PatientConsent::query()
            ->where('partner_id', $partner->id)
            ->whereNull('revoked_at')
            ->with('patient')
            ->get()
            ->map(fn (PatientConsent $consent): array => [
                'id' => $consent->patient->id,
                'name' => $consent->patient->name,
                'scopes' => $consent->scopes ?? [],
            ])
            ->values();
    }

    /**
     * Whether the patient has an active consent for the given scope.
     */
    public function hasConsent(ApiPartner $partner, User $patient, PartnerScope $scope): bool
    {
        return PatientConsent::query()
            ->where('partner_id', $partner->id)
            ->where('patient_user_id', $patient->id)
            ->whereNull('revoked_at')
            ->get()
            ->contains(fn (PatientConsent $consent): bool => $consent->isActiveFor($scope));
    }

    /**
     * Grant (or re-activate) a consent from the patient to the partner for the
     * partner's configured scopes. Returns the active consent.
     */
    public function grantConsent(User $patient, ApiPartner $partner): PatientConsent
    {
        return PatientConsent::updateOrCreate(
            [
                'partner_id' => $partner->id,
                'patient_user_id' => $patient->id,
            ],
            [
                'scopes' => $partner->scopes ?? [],
                'granted_at' => now(),
                'revoked_at' => null,
            ],
        );
    }

    /**
     * Revoke the patient's consent to the partner.
     */
    public function revokeConsent(User $patient, ApiPartner $partner): void
    {
        PatientConsent::query()
            ->where('partner_id', $partner->id)
            ->where('patient_user_id', $patient->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}