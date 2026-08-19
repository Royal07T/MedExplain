<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\PartnerTokenRequest;
use App\Services\AuditService;
use App\Services\PartnerService;
use Illuminate\Http\JsonResponse;

/**
 * OAuth 2.0 client-credentials token endpoint for partner applications.
 *
 * Credentials are validated against the active partner registry before any
 * token is issued, and every issuance is written to the audit log.
 */
final class PartnerOAuthController extends Controller
{
    public function __construct(
        private readonly PartnerService $partnerService,
        private readonly AuditService $auditService,
    ) {}

    public function token(PartnerTokenRequest $request): JsonResponse
    {
        $partner = $this->partnerService->authenticate(
            $request->validated('client_id'),
            $request->validated('client_secret'),
        );

        if ($partner === null) {
            return response()->json([
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed.',
            ], 401);
        }

        $token = $this->partnerService->issueToken($partner);

        $this->auditService->record(
            AuditEvent::PartnerTokenIssued,
            auditable: $partner,
            metadata: ['scope' => $partner->scopes ?? []],
        );

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => null,
            'scope' => implode(' ', $partner->scopes ?? []),
        ]);
    }
}