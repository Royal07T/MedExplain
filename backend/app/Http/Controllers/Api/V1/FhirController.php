<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Patient;
use App\Services\Fhir\FhirResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Minimal FHIR R4 read endpoints.
 *
 * Returns raw FHIR resources with the `application/fhir+json` media type
 * (not the app's `success`/`data` envelope) so the output is spec-compliant
 * for interop. Read-only, organization-scoped.
 */
final class FhirController extends Controller
{
    public function __construct(private readonly FhirResourceService $fhir) {}

    public function metadata(Request $request): JsonResponse
    {
        return response()->json(
            $this->fhir->capabilityStatement($request->root()),
            200,
            ['Content-Type' => 'application/fhir+json'],
        );
    }

    public function patient(Request $request, $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        $patient = Patient::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->with('organization')
            ->find((int) $id);

        if (!$patient) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'not-found',
                    'diagnostics' => "No Patient with id {$id} is accessible.",
                ]],
            ], 404, ['Content-Type' => 'application/fhir+json']);
        }

        return response()->json(
            $this->fhir->patient($patient),
            200,
            ['Content-Type' => 'application/fhir+json'],
        );
    }

    public function organization(Request $request, $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        $organization = Organization::find((int) $id);

        if (!$organization || ($organizationId && (int) $organization->id !== (int) $organizationId)) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'not-found',
                    'diagnostics' => "No Organization with id {$id} is accessible.",
                ]],
            ], 404, ['Content-Type' => 'application/fhir+json']);
        }

        return response()->json(
            $this->fhir->organization($organization),
            200,
            ['Content-Type' => 'application/fhir+json'],
        );
    }
}
