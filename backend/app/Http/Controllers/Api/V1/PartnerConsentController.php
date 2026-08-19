<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\ApiPartner;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\PartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Patient-managed consent for partner access. Only the patient can grant or
 * revoke consent, and both actions are audited.
 */
final class PartnerConsentController extends Controller
{
    public function __construct(
        private readonly PartnerService $partnerService,
        private readonly AuditService $auditService,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * List the patient's consents to partner applications.
     */
    public function index(Request $request): JsonResponse
    {
        $consents = $request->user()->consents()
            ->with('partner')
            ->get()
            ->map(fn ($consent): array => [
                'partner_id' => $consent->partner_id,
                'partner_name' => $consent->partner->name,
                'scopes' => $consent->scopes ?? [],
                'granted_at' => $consent->granted_at?->toISOString(),
                'revoked_at' => $consent->revoked_at?->toISOString(),
            ])
            ->values();

        return response()->json(['data' => $consents]);
    }

    /**
     * Grant consent to a partner for the partner's configured scopes.
     */
    public function grant(Request $request, ApiPartner $partner): JsonResponse
    {
        $consent = $this->partnerService->grantConsent($request->user(), $partner);

        $this->auditService->record(
            AuditEvent::PatientConsentGranted,
            $request->user(),
            $partner,
            ['scopes' => $consent->scopes ?? []],
        );

        $this->notifications->notify(
            $request->user(),
            sprintf('%s connected', $partner->name),
            sprintf('%s can now access your health record.', $partner->name),
            'consent',
            ['partner_id' => $partner->id],
        );

        return response()->json(['data' => [
            'partner_id' => $partner->id,
            'partner_name' => $partner->name,
            'scopes' => $consent->scopes ?? [],
            'granted_at' => $consent->granted_at?->toISOString(),
            'revoked_at' => $consent->revoked_at?->toISOString(),
        ]]);
    }

    /**
     * Revoke the patient's consent to a partner.
     */
    public function revoke(Request $request, ApiPartner $partner): JsonResponse
    {
        $this->partnerService->revokeConsent($request->user(), $partner);

        $this->auditService->record(
            AuditEvent::PatientConsentRevoked,
            $request->user(),
            $partner,
        );

        $this->notifications->notify(
            $request->user(),
            sprintf('%s disconnected', $partner->name),
            sprintf('Your consent to %s was revoked.', $partner->name),
            'consent',
            ['partner_id' => $partner->id],
        );

        return response()->json(null, 204);
    }
}