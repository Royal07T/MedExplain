<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PopulationHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Organization-scoped population health analytics.
 *
 * Admin / super_admin / nursing staff see the whole organization's
 * population. Clinicians are scoped to the patients explicitly granted to
 * them (clinician_patient_access), consistent with the rest of the app.
 */
final class PopulationHealthController extends Controller
{
    public function __construct(private readonly PopulationHealthService $health) {}

    public function dashboard(Request $request): JsonResponse
    {
        $patientIds = $this->resolvePatientIds($request);

        return response()->json([
            'success' => true,
            'data' => $this->health->populationDashboard($patientIds),
        ]);
    }

    public function registry(Request $request): JsonResponse
    {
        $patientIds = $this->resolvePatientIds($request);

        return response()->json([
            'success' => true,
            'data' => $this->health->diseaseRegistry($patientIds),
        ]);
    }

    public function risk(Request $request): JsonResponse
    {
        $patientIds = $this->resolvePatientIds($request);

        return response()->json([
            'success' => true,
            'data' => $this->health->riskStratification($patientIds),
        ]);
    }

    /**
     * Determine the population scope for the authenticated user.
     *
     * @return int[]
     */
    private function resolvePatientIds(Request $request): array
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        if (!$organizationId) {
            return [];
        }

        if ($user?->isClinician()) {
            return $user->clinicianPatients()
                ->where('users.organization_id', $organizationId)
                ->pluck('clinician_patient_access.patient_user_id')
                ->all();
        }

        return $this->health->organizationPatientIds($organizationId);
    }
}
