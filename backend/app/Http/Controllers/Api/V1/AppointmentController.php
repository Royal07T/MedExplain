<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class AppointmentController extends Controller
{
    /**
     * List appointments for a patient.
     */
    public function index(Request $request, $patientId): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $patient = User::findOrFail($patientId);

        // Verify access - clinician must have this patient
        if (!$request->user()->isClinician() ||
            !$request->user()->clinicianPatients()->where('patient_user_id', $patient->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No access to this patient',
            ], 403);
        }

        $appointments = Appointment::where('patient_id', $patient->id)
            ->where('organization_id', $organizationId)
            ->latest('scheduled_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                    'acuity_level' => $appointment->acuity_level,
                    'chief_complaint' => $appointment->chief_complaint,
                    'scheduled_at' => $appointment->scheduled_at?->toISOString(),
                    'check_in_time' => $appointment->check_in_time?->toISOString(),
                    'check_out_time' => $appointment->check_out_time?->toISOString(),
                    'duration_minutes' => $appointment->duration_minutes,
                ];
            }),
        ]);
    }

    /**
     * Create a new appointment.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'exists:users,id'],
            'clinician_id' => ['required', 'exists:users,id'],
            'status' => ['required', 'in:scheduled,checked_in,in_progress,completed,cancelled,no_show'],
            'acuity_level' => ['required', 'in:resuscitation,emergent,urgent,non-urgent'],
            'chief_complaint' => ['sometimes', 'string', 'max:500'],
            'symptoms' => ['sometimes', 'string', 'max:1000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:240'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $patient = User::findOrFail($request->patient_id);

        // Verify access - clinician must have this patient
        if (!$request->user()->isClinician() ||
            !$request->user()->clinicianPatients()->where('patient_user_id', $patient->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No access to this patient',
            ], 403);
        }

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'organization_id' => $organizationId,
            'clinician_id' => $request->clinician_id,
            'status' => $request->status,
            'acuity_level' => $request->acuity_level,
            'chief_complaint' => $request->chief_complaint,
            'symptoms' => $request->symptoms,
            'scheduled_at' => $request->scheduled_at,
            'duration_minutes' => $request->duration_minutes ?? 30,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'acuity_level' => $appointment->acuity_level,
                'chief_complaint' => $appointment->chief_complaint,
                'scheduled_at' => $appointment->scheduled_at?->toISOString(),
                'duration_minutes' => $appointment->duration_minutes,
                'message' => 'Appointment scheduled successfully',
            ],
        ], 201);
    }

    /**
     * Update appointment check-in status.
     */
    public function checkIn(Request $request, $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $appointment = Appointment::where('id', $id)
            ->where('organization_id', $organizationId)
            ->where('patient_id', $request->user()->id)
            ->firstOrFail();

        $appointment->status = 'checked_in';
        $appointment->check_in_time = now();
        $appointment->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'check_in_time' => $appointment->check_in_time?->toISOString(),
                'message' => 'Patient checked in successfully',
            ],
        ]);
    }

    /**
     * Update appointment status.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $appointment = Appointment::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:scheduled,checked_in,in_progress,completed,cancelled,no_show'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $appointment->status = $request->status;

        if ($request->status === 'completed' || $request->status === 'cancelled' || $request->status === 'no_show') {
            $appointment->check_out_time = now();
        }

        $appointment->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'check_out_time' => $appointment->check_out_time?->toISOString(),
                'duration_minutes' => $appointment->duration_minutes,
            ],
            'message' => 'Appointment status updated successfully',
        ]);
    }

    /**
     * Get a single appointment.
     */
    public function show($id): JsonResponse
    {
        $organizationId = request()->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $appointment = Appointment::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'clinician_id' => $appointment->clinician_id,
                'status' => $appointment->status,
                'acuity_level' => $appointment->acuity_level,
                'chief_complaint' => $appointment->chief_complaint,
                'symptoms' => $appointment->symptoms,
                'scheduled_at' => $appointment->scheduled_at?->toISOString(),
                'check_in_time' => $appointment->check_in_time?->toISOString(),
                'check_out_time' => $appointment->check_out_time?->toISOString(),
                'duration_minutes' => $appointment->duration_minutes,
            ],
        ]);
    }

    /**
     * Get patient's own appointments.
     */
    public function patientAppointments(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $appointments = Appointment::where('patient_id', $user->id)
            ->where('organization_id', $organizationId)
            ->latest('scheduled_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'clinician_id' => $appointment->clinician_id,
                    'status' => $appointment->status,
                    'acuity_level' => $appointment->acuity_level,
                    'chief_complaint' => $appointment->chief_complaint,
                    'scheduled_at' => $appointment->scheduled_at?->toISOString(),
                    'duration_minutes' => $appointment->duration_minutes,
                ];
            }),
        ]);
    }

    /**
     * Patient books an appointment.
     */
    public function patientBookAppointment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'clinician_id' => ['required', 'exists:users,id'],
            'chief_complaint' => ['required', 'string', 'max:500'],
            'symptoms' => ['sometimes', 'string', 'max:1000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:240'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $organizationId = $user?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $appointment = Appointment::create([
            'patient_id' => $user->id,
            'organization_id' => $organizationId,
            'clinician_id' => $request->clinician_id,
            'status' => 'scheduled',
            'acuity_level' => 'non-urgent',
            'chief_complaint' => $request->chief_complaint,
            'symptoms' => $request->symptoms,
            'scheduled_at' => $request->scheduled_at,
            'duration_minutes' => $request->duration_minutes ?? 30,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'chief_complaint' => $appointment->chief_complaint,
                'scheduled_at' => $appointment->scheduled_at?->toISOString(),
                'duration_minutes' => $appointment->duration_minutes,
                'message' => 'Appointment booked successfully',
            ],
        ], 201);
    }

    /**
     * Patient cancels an appointment.
     */
    public function patientCancelAppointment(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $appointment = Appointment::where('id', $id)
            ->where('organization_id', $organizationId)
            ->where('patient_id', $user->id)
            ->firstOrFail();

        if ($appointment->status !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Can only cancel scheduled appointments',
            ], 400);
        }

        $appointment->status = 'cancelled';
        $appointment->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'message' => 'Appointment cancelled successfully',
            ],
        ]);
    }
}