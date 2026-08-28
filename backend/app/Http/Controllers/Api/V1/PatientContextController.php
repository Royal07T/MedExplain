<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\PatientContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientContextController extends Controller
{
    public function __construct(
        private PatientContextService $contextService,
        private AuditService $auditService,
    ) {}

    /**
     * Select a patient context.
     */
    public function select(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:users,id',
        ]);

        $user = $request->user();
        $patientId = $validated['patient_id'];

        if (! $this->contextService->canAccessPatient($user, $patientId)) {
            abort(403, 'No access to this patient.');
        }

        $context = $this->contextService->setContext($user, $patientId);

        $this->auditService->record(
            \App\Enums\AuditEvent::PatientRecordAccessed,
            $user,
            ['patient_id' => $patientId, 'action' => 'context_select']
        );

        return response()->json(['data' => $context]);
    }

    /**
     * Clear patient context.
     */
    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->contextService->clearContext($user);

        return response()->json(['message' => 'Patient context cleared.']);
    }

    /**
     * Get current patient context.
     */
    public function current(Request $request): JsonResponse
    {
        $context = $this->contextService->getContext($request->user());

        return response()->json(['data' => $context]);
    }

    /**
     * Search patients for context selection.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $patients = $this->contextService->searchPatients(
            $request->user(),
            $validated['query']
        );

        return response()->json(['data' => $patients]);
    }
}
