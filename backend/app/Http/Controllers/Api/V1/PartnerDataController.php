<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditEvent;
use App\Enums\PartnerScope;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\HealthService;
use App\Services\PartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Partner-scoped data access.
 *
 * Only patients with an active, scope-specific consent can be returned or
 * read, and every read is audited with the requesting partner recorded.
 */
final class PartnerDataController extends Controller
{
    public function __construct(
        private readonly PartnerService $partnerService,
        private readonly HealthService $healthService,
        private readonly AuditService $auditService,
    ) {}

    /**
     * List patients who have an active consent with this partner.
     */
    public function patients(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->partnerService->consentedPatients($request->user()),
        ]);
    }

    /**
     * Read a patient's health record, consent-permitting.
     */
    public function record(Request $request, User $patient): JsonResponse
    {
        $partner = $request->user();

        if (! $this->partnerService->hasConsent($partner, $patient, PartnerScope::HealthRecordRead)) {
            abort(403, 'No active consent for this patient.');
        }

        $this->auditService->record(
            AuditEvent::PartnerRecordAccessed,
            auditable: $patient,
            metadata: [
                'partner_id' => $partner->id,
                'scope' => PartnerScope::HealthRecordRead->value,
            ],
        );

        return response()->json([
            'data' => $this->healthService->record($patient),
        ]);
    }
}