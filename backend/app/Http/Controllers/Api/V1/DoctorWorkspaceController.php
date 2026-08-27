<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Organization;
use App\Models\LabResult;
use App\Models\Medication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DoctorWorkspaceController extends Controller
{
    /**
     * Order a new laboratory test for a patient.
     */
    public function orderLabTest(Request $request, $patientId)
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        $patient = Patient::byOrganization($organizationId)->findOrFail($patientId);

        // Verify access (same logic as other endpoints)
        if ($request->user()->isClinician()) {
            if (!$request->user()->clinicianPatients()->where('patient_user_id', $patient->user_id)->exists()) {
                return $this->errorResponse('No access to this patient', 403);
            }
        } elseif ($request->user()->id !== $patient->user_id) {
            return $this->errorResponse('No access to this patient', 403);
        }

        $validator = Validator::make($request->all(), [
            'test_name' => 'required|string|max:255',
            'test_code' => 'sometimes|string|max:50',
            'result_due_date' => 'sometimes|date',
            'notes' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 400, $validator->errors());
        }

        $labOrder = \App\Models\LabOrder::create([
            'user_id' => $patient->user_id,
            'organization_id' => $organizationId,
            'clinician_id' => $request->user()->id,
            'test_name' => $request->test_name,
            'test_code' => $request->test_code,
            'status' => 'pending',
            'result_due_date' => $request->result_due_date,
            'notes' => $request->notes,
        ]);

        return $this->successResponse([
            'lab_order' => [
                'id' => $labOrder->id,
                'test_name' => $labOrder->test_name,
                'test_code' => $labOrder->test_code,
                'status' => $labOrder->status,
                'ordered_at' => $labOrder->ordered_at?->toISOString(),
                'result_due_date' => $labOrder->result_due_date,
                'notes' => $labOrder->notes,
            ],
            'message' => 'Lab test ordered successfully',
        ]);
    }

    /**
     * View triage queue - patients waiting, organized by acuity level.
     */
    public function triageQueue(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        // Only clinicians can view the triage queue
        if (!$request->user()->isClinician()) {
            return $this->errorResponse('No permission', 403);
        }

        // Get active/checked-in encounters organized by acuity
        $queue = Encounter::where('organization_id', $organizationId)
            ->where('check_out_time', null)
            ->where('status', 'active')
            ->latest('check_in_time')
            ->get();

        // Group by acuity level
        $grouped = [];
        foreach (['resuscitation', 'emergent', 'urgent', 'non-urgent'] as $acuity) {
            $grouped[$acuity] = $queue->where('acuity_level', $acuity)->map(function ($enc) {
                return [
                    'id' => $enc->id,
                    'patient_id' => $enc->patient_id,
                    'patient_name' => $enc->patient->first_name . ' ' . $enc->patient->last_name,
                    'acuity_level' => $enc->acuity_level,
                    'queue_status' => $enc->queue_status,
                    'check_in_time' => $enc->check_in_time?->toISOString(),
                    'chief_complaint' => $enc->chief_complaint,
                    'symptoms' => $enc->symptoms,
                ];
            })->values();
        }

        return $this->successResponse([
            'triage_queue' => $grouped,
            'total_waiting' => $queue->count(),
        ]);
    }

    /**
     * View Patient 360 / Unified Health Record for authorized patient.
     */
    public function patient360(Request $request, $patientId)
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        $patient = Patient::byOrganization($organizationId)->findOrFail($patientId);

        // Verify access
        if ($request->user()->isClinician()) {
            if (!$request->user()->clinicianPatients()->where('patient_user_id', $patient->user_id)->exists()) {
                return $this->errorResponse('No access to this patient', 403);
            }
        } elseif ($request->user()->id !== $patient->user_id) {
            return $this->errorResponse('No access to this patient', 403);
        }

        // Build comprehensive Patient 360 view
        $encounters = Encounter::where('patient_id', $patient->id)
            ->where('organization_id', $organizationId)
            ->with(['clinician', 'triage'])
            ->latest('check_in_time')
            ->get();

        // Recent lab results
        $recentLabs = LabResult::where('patient_id', $patient->id)
            ->where('organization_id', $organizationId)
            ->latest('collected_at')
            ->take(10)
            ->get(['*']);

        // Active medications
        $activeMeds = Medication::where('patient_id', $patient->id)
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->latest('start_date')
            ->take(10)
            ->get(['*']);

        return $this->successResponse([
            'patient' => [
                'id' => $patient->id,
                'mrn' => $patient->mrn,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'date_of_birth' => $patient->date_of_birth,
                'gender' => $patient->gender,
                'phone' => $patient->phone,
                'email' => $patient->email,
                'address' => $patient->address,
            ],
            'demographics' => [
                'next_of_kin_name' => $patient->next_of_kin_name,
                'next_of_kin_phone' => $patient->next_of_kin_phone,
                'emergency_contact_name' => $patient->emergency_contact_name,
                'emergency_contact_phone' => $patient->emergency_contact_phone,
                'allergies' => $patient->allergies ? json_decode($patient->allergies, true) : [],
                'immunizations' => $patient->immunizations ? json_decode($patient->immunizations, true) : [],
            ],
            'encounters' => $encounters->map(function ($encounter) {
                return [
                    'id' => $encounter->id,
                    'chief_complaint' => $encounter->chief_complaint,
                    'acuity_level' => $encounter->acuity_level,
                    'queue_status' => $encounter->queue_status,
                    'check_in_time' => $encounter->check_in_time,
                    'check_out_time' => $encounter->check_out_time,
                    'duration_minutes' => $encounter->check_out_time
                        ? $encounter->check_out_time->diffInMinutes($encounter->check_in_time) : null,
                    'vitals' => $encounter->vitals_summary,
                ];
            }),
            'recent_lab_results' => $recentLabs->map(function ($lab) {
                return [
                    'id' => $lab->id,
                    'test_name' => $lab->test_name,
                    'result' => $lab->result,
                    'unit' => $lab->unit,
                    'reference_range' => $lab->reference_range,
                    'abnormal_flag' => $lab->abnormal_flag,
                    'collected_at' => $lab->collected_at,
                    'result_timestamp' => $lab->result_timestamp,
                    'status' => $lab->status,
                ];
            }),
            'active_medications' => $activeMeds->map(function ($med) {
                return [
                    'id' => $med->id,
                    'name' => $med->name,
                    'strength' => $med->strength,
                    'dosage_form' => $med->dosage_form,
                    'dose' => $med->dose,
                    'frequency' => $med->frequency,
                    'route' => $med->route,
                    'start_date' => $med->start_date,
                    'end_date' => $med->end_date,
                    'status' => $med->status,
                ];
            }),
            'organization_id' => $organizationId,
        ]);
    }

    /**
     * Start a new encounter for a patient.
     */
    public function startEncounter(Request $request, $patientId)
    {
        $validator = Validator::make($request->all(), [
            'chief_complaint' => 'sometimes|string|max:500',
            'symptoms' => 'sometimes|string|max:1000',
            'acuity_level' => 'sometimes|in:resuscitation,emergent,urgent,non-urgent',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 400, $validator->errors());
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        $patient = Patient::byOrganization($organizationId)->findOrFail($patientId);

        // Verify access
        if (!$request->user()->isClinician() || 
            !$request->user()->clinicianPatients()->where('patient_user_id', $patient->user_id)->exists()) {
            return $this->errorResponse('No access to this patient', 403);
        }

        // Check for existing active encounter
        $existing = Encounter::where('patient_id', $patient->id)
            ->where('organization_id', $organizationId)
            ->where('check_out_time', null)
            ->first();

        if ($existing) {
            return $this->errorResponse('Patient already has an active encounter', 400);
        }

        $encounter = new Encounter([
            'patient_id' => $patient->id,
            'organization_id' => $organizationId,
            'clinician_id' => $request->user()->id,
            'chief_complaint' => $request->chief_complaint ?? null,
            'symptoms' => $request->symptoms ?? null,
            'acuity_level' => $request->acuity_level ?? 'non-urgent',
            'queue_status' => 'seeing',
            'check_in_time' => now(),
            'vitals_summary' => null,
            'clinical_observations' => null,
        ]);

        $encounter->save();

        return $this->successResponse([
            'encounter' => [
                'id' => $encounter->id,
                'chief_complaint' => $encounter->chief_complaint,
                'symptoms' => $encounter->symptoms,
                'acuity_level' => $encounter->acuity_level,
                'queue_status' => $encounter->queue_status,
                'check_in_time' => $encounter->check_in_time,
            ],
            'message' => 'New encounter started successfully',
        ]);
    }

    /**
     * Review previous encounters for a patient.
     */
    public function previousEncounters(Request $request, $patientId)
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        $patient = Patient::byOrganization($organizationId)->findOrFail($patientId);

        // Verify access (same logic)
        if ($request->user()->isClinician()) {
            if (!$request->user()->clinicianPatients()->where('patient_user_id', $patient->user_id)->exists()) {
                return $this->errorResponse('No access to this patient', 403);
            }
        } elseif ($request->user()->id !== $patient->user_id) {
            return $this->errorResponse('No access to this patient', 403);
        }

        $encounters = Encounter::where('patient_id', $patient->id)
            ->where('organization_id', $organizationId)
            ->where('check_out_time', '!=', null)
            ->latest('check_out_time')
            ->take(10)
            ->get(['*']);

        return $this->successResponse([
            'previous_encounters' => $encounters->map(function ($enc) {
                return [
                    'id' => $enc->id,
                    'chief_complaint' => $enc->chief_complaint,
                    'symptoms' => $enc->symptoms,
                    'acuity_level' => $enc->acuity_level,
                    'check_in_time' => $enc->check_in_time,
                    'check_out_time' => $enc->check_out_time,
                    'duration_minutes' => $enc->check_out_time
                        ? $enc->check_out_time->diffInMinutes($enc->check_in_time) : null,
                ];
            }),
        ]);
    }

    /**
     * Review vitals trend for a patient.
     */
    public function vitalsTrend(Request $request, $patientId)
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        $patient = Patient::byOrganization($organizationId)->findOrFail($patientId);

        // Verify access
        if ($request->user()->isClinician()) {
            if (!$request->user()->clinicianPatients()->where('patient_user_id', $patient->user_id)->exists()) {
                return $this->errorResponse('No access to this patient', 403);
            }
        } elseif ($request->user()->id !== $patient->user_id) {
            return $this->errorResponse('No access to this patient', 403);
        }

        $labResults = LabResult::where('patient_id', $patient->id)
            ->where('organization_id', $organizationId)
            ->whereNotNull('collected_at')
            ->latest('collected_at')
            ->take(20)
            ->get(['collected_at', 'test_name', 'result', 'reference_range', 'status']);

        return $this->successResponse([
            'patient_id' => $patient->id,
            'mrn' => $patient->mrn,
            'vitals_trend' => $labResults->map(function ($lab) {
                return [
                    'date' => $lab->collected_at?->toISOString() ?? null,
                    'test' => $lab->test_name,
                    'result' => $lab->result,
                    'unit' => $lab->unit,
                    'reference_range' => $lab->reference_range,
                    'abnormal' => $lab->abnormal_flag,
                    'status' => $lab->status,
                ];
            }),
        ]);
    }

    /**
     * List patients assigned to this clinician.
     */
    public function assignedPatients(Request $request)
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        if (!$request->user()->isClinician()) {
            return $this->errorResponse('No access', 403);
        }

        $patients = $request->user()->clinicianPatients()
            ->whereHas('patient', function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId);
            })
            ->with(['patient' => fn($q) => $q->select('id', 'mrn', 'first_name', 'last_name', 'date_of_birth')])
            ->get();

        return $this->successResponse([
            'assigned_patients' => $patients->map(function ($user) {
                return [
                    'id' => $user->id,
                    'mrn' => $user->patient->first?->mrn ?? 'N/A',
                    'name' => $user->patient->first?->first_name . ' ' . $user->patient->last?->last_name,
                    'date_of_birth' => $user->patient->first?->date_of_birth,
                    'full_name' => $user->name,
                ];
            }),
        ]);
    }

    /**
     * Error response helper.
     */
    private function errorResponse($message, $code, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => $code,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Success response helper.
     */
    private function successResponse($data)
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
