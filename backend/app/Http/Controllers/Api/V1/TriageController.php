<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TriageController extends Controller
{
    /**
     * Check in a patient and assign triage.
     */
    public function checkIn(Request $request, $patientId)
    {
        $validator = Validator::make($request->all(), [
            'chief_complaint' => 'sometimes|string|max:500',
            'symptoms' => 'sometimes|string|max:1000',
            'acuity_level' => 'sometimes|in:resuscitation,emergent,urgent,non-urgent',
            'vitals' => 'sometimes|string|max:500',
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
        if ($request->user()->isClinician()) {
            if (!$request->user()->clinicianPatients()->where('patient_user_id', $patient->user_id)->exists()) {
                return $this->errorResponse('No access to this patient', 403);
            }
        } elseif ($request->user()->id !== $patient->user_id) {
            return $this->errorResponse('No access to this patient', 403);
        }

        // Check if there's already an active encounter
        $existingEncounter = Encounter::where('patient_id', $patient->id)
            ->where('organization_id', $organizationId)
            ->where('check_out_time', null)
            ->first();

        if ($existingEncounter) {
            return $this->errorResponse('Patient already has an active encounter', 400);
        }

        // Create encounter
        $encounter = new Encounter([
            'patient_id' => $patient->id,
            'organization_id' => $organizationId,
            'clinician_id' => $request->user()->id,
            'chief_complaint' => $request->chief_complaint ?? null,
            'symptoms' => $request->symptoms ?? null,
            'acuity_level' => $request->acuity_level ?? 'non-urgent',
            'queue_status' => 'waiting',
            'check_in_time' => now(),
            'vitals_summary' => null,
        ]);

        if ($request->filled('vitals')) {
            $encounter->vitals_summary = $request->vitals;
        }

        $encounter->save();

        return $this->successResponse([
            'encounter' => [
                'id' => $encounter->id,
                'chief_complaint' => $encounter->chief_complaint,
                'symptoms' => $encounter->symptoms,
                'acuity_level' => $encounter->acuity_level,
                'queue_status' => $encounter->queue_status,
                'check_in_time' => $encounter->check_in_time,
                'vitals' => $encounter->vitals_summary,
            ],
            'message' => 'Patient checked in successfully',
        ]);
    }

    /**
     * Update encounter with vitals and clinical observations.
     */
    public function updateVitals(Request $request, $encounterId)
    {
        $validator = Validator::make($request->all(), [
            'vitals' => 'sometimes|string|max:500',
            'clinical_observations' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 400, $validator->errors());
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        $encounter = Encounter::where('id', $encounterId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Verify access
        $patient = $encounter->patient;
        if ($request->user()->isClinician()) {
            if (!$request->user()->clinicianPatients()->where('patient_user_id', $patient->user_id)->exists()) {
                return $this->errorResponse('No access to this patient', 403);
            }
        } elseif ($request->user()->id !== $patient->user_id) {
            return $this->errorResponse('No access to this patient', 403);
        }

        if ($request->filled('vitals')) {
            $encounter->vitals_summary = $request->vitals;
        }

        if ($request->filled('clinical_observations')) {
            $encounter->clinical_observations = $request->clinical_observations;
        }

        $encounter->save();

        return $this->successResponse([
            'encounter' => [
                'id' => $encounter->id,
                'vitals' => $encounter->vitals_summary,
                'clinical_observations' => $encounter->clinical_observations,
            ],
            'message' => 'Encounter updated successfully',
        ]);
    }

    /**
     * Check out a patient (end encounter).
     */
    public function checkOut(Request $request, $encounterId)
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        $encounter = Encounter::where('id', $encounterId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Verify access
        $patient = $encounter->patient;
        if ($request->user()->isClinician()) {
            if (!$request->user()->clinicianPatients()->where('patient_user_id', $patient->user_id)->exists()) {
                return $this->errorResponse('No access to this patient', 403);
            }
        } elseif ($request->user()->id !== $patient->user_id) {
            return $this->errorResponse('No access to this patient', 403);
        }

        $encounter->check_out_time = now();
        $encounter->save();

        return $this->successResponse([
            'encounter' => [
                'id' => $encounter->id,
                'check_in_time' => $encounter->check_in_time,
                'check_out_time' => $encounter->check_out_time,
                'duration_minutes' => $encounter->check_out_time->diffInMinutes($encounter->check_in_time),
            ],
            'message' => 'Patient checked out successfully',
        ]);
    }

    /**
     * Update encounter acuity level.
     */
    public function updateAcuity(Request $request, $encounterId)
    {
        $validator = Validator::make($request->all(), [
            'acuity_level' => 'required|in:resuscitation,emergent,urgent,non-urgent',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 400, $validator->errors());
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        $encounter = Encounter::where('id', $encounterId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Verify access (clinician only)
        if (!$request->user()->isClinician() || !$request->user()->clinicianPatients()->where('patient_user_id', $encounter->patient->user_id)->exists()) {
            return $this->errorResponse('No access to this encounter', 403);
        }

        $encounter->acuity_level = $request->acuity_level;
        $encounter->save();

        return $this->successResponse([
            'encounter' => [
                'id' => $encounter->id,
                'acuity_level' => $encounter->acuity_level,
            ],
            'message' => 'Acuity level updated successfully',
        ]);
    }

    /**
     * Update queue status.
     */
    public function updateQueue(Request $request, $encounterId)
    {
        $validator = Validator::make($request->all(), [
            'queue_status' => 'required|string|in:waiting,in_triage,seeing,discharged',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 400, $validator->errors());
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        $encounter = Encounter::where('id', $encounterId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Verify access (clinician only)
        if (!$request->user()->isClinician() || !$request->user()->clinicianPatients()->where('patient_user_id', $encounter->patient->user_id)->exists()) {
            return $this->errorResponse('No access to this encounter', 403);
        }

        $encounter->queue_status = $request->queue_status;
        $encounter->save();

        return $this->successResponse([
            'encounter' => [
                'id' => $encounter->id,
                'queue_status' => $encounter->queue_status,
            ],
            'message' => 'Queue status updated successfully',
        ]);
    }

    /**
     * List patients waiting in queue, organized by acuity.
     */
    public function listWaitingQueue(Request $request)
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        // Verify clinician access
        if (!$request->user()->isClinician()) {
            return $this->errorResponse('No access', 403);
        }

        $patients = Encounter::where('organization_id', $organizationId)
            ->where('check_out_time', null)
            ->where('queue_status', '!=' , 'discharged')
            ->with(['patient' => fn($q) => $q->select('id', 'mrn', 'first_name', 'last_name')])
            ->orderByRaw('FIELD(acuity_level, \"resuscitation\", \"emergent\", \"urgent\", \"non-urgent\")')
            ->orderBy('check_in_time', 'asc')
            ->get();

        return $this->successResponse([
            'queue' => $patients->map(function ($encounter) {
                return [
                    'id' => $encounter->id,
                    'patient_id' => $encounter->patient_id,
                    'patient_name' => $encounter->patient->full_name,
                    'mrn' => $encounter->patient->mrn,
                    'acuity_level' => $encounter->acuity_level,
                    'queue_status' => $encounter->queue_status,
                    'check_in_time' => $encounter->check_in_time,
                    'chief_complaint' => $encounter->chief_complaint,
                ];
            }),
        ]);
    }

    /**
     * Get statistics about the triage queue.
     */
    public function queueStatistics(Request $request)
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return $this->errorResponse('No organization context', 403);
        }

        if (!$request->user()->isClinician()) {
            return $this->errorResponse('No access', 403);
        }

        $stats = Encounter::where('organization_id', $organizationId)
            ->where('check_out_time', null)
            ->get();

        $acuityCounts = $stats->groupBy('acuity_level')
            ->count();

        $statusCounts = $stats->groupBy('queue_status')->count();

        $totalWaitTime = 0;
        $counted = 0;
        foreach ($stats as $encounter) {
            if ($encounter->check_in_time) {
                $wait = $encounter->check_out_time?->diffInMinutes($encounter->check_in_time) ?? 0;
                $totalWaitTime += $wait;
                $counted++;
            }
        }

        $avgWaitTime = $counted > 0 ? $totalWaitTime / $counted : 0;

        return $this->successResponse([
            'statistics' => [
                'total_active_encounters' => $stats->count(),
                'acuity_breakdown' => $acuityCounts->toArray(),
                'queue_status_breakdown' => $statusCounts->toArray(),
                'average_wait_minutes' => $avgWaitTime,
            ],
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
