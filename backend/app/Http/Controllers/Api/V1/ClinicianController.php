<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\GrantClinicianAccessRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ClinicianService;
use App\Services\HealthService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Clinician-facing endpoints for the clinician portal.
 *
 * All access is consent-scoped: a clinician can only view the records of
 * patients they have been explicitly granted access to, and every access is
 * written to the audit log. Ownership and authorization are never derived
 * from request input.
 */
final class ClinicianController extends Controller
{
    public function __construct(
        private readonly ClinicianService $clinicianService,
        private readonly HealthService $healthService,
        private readonly AuditService $auditService,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * List the patients this clinician is authorized to view.
     */
    public function patients(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->clinicianService->assignedPatients($request->user()),
        ]);
    }

    /**
     * Grant the clinician access to a patient by email. Audited.
     */
    public function grantAccess(GrantClinicianAccessRequest $request): JsonResponse
    {
        $patient = User::where('email', $request->validated('email'))->firstOrFail();

        if ($patient->id === $request->user()->id) {
            abort(422, 'You cannot grant yourself access as a patient.');
        }

        $created = $this->clinicianService->grantAccess($request->user(), $patient);

        $this->auditService->record(
            AuditEvent::ClinicianAccessGranted,
            $request->user(),
            $patient,
            ['created' => $created],
        );

        if ($created) {
            $this->notifications->notify(
                $patient,
                'Clinician access granted',
                sprintf('%s can now view your health record.', $request->user()->name),
                'clinician',
                ['clinician_id' => $request->user()->id],
            );
        }

        return response()->json([
            'data' => [
                'id' => $patient->id,
                'name' => $patient->name,
                'email' => $patient->email,
                'last_lab_date' => null,
            ],
            'created' => $created,
        ], $created ? 201 : 200);
    }

    /**
     * View a patient's aggregated health record. Requires explicit access.
     */
    public function record(Request $request, User $patient): JsonResponse
    {
        if (! $this->clinicianService->hasAccess($request->user(), $patient)) {
            abort(403, 'You do not have access to this patient.');
        }

        $this->auditService->record(
            AuditEvent::ClinicianRecordViewed,
            $request->user(),
            $patient,
        );

        return response()->json([
            'data' => $this->healthService->record($patient),
        ]);
    }
}