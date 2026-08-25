<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Models\Medication;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class PrescriptionController extends Controller
{
    /**
     * List prescriptions for a patient.
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

        $prescriptions = Prescription::where('user_id', $patient->id)
            ->where('organization_id', $organizationId)
            ->latest('ordered_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $prescriptions->map(function ($prescription) {
                return [
                    'id' => $prescription->id,
                    'medication_name' => $prescription->medication?->name,
                    'status' => $prescription->status,
                    'ordered_at' => $prescription->ordered_at?->toISOString(),
                    'expires_at' => $prescription->expires_at?->toISOString(),
                    'dispensed_at' => $prescription->dispensed_at?->toISOString(),
                    'notes' => $prescription->notes,
                    'clinician_name' => $prescription->clinician?->name,
                ];
            }),
        ]);
    }

    /**
     * Create a new prescription (order medication).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'exists:users,user_id'],
            'medication_id' => ['required', 'exists:medications,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
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

        // Check if medication belongs to organization/patient
        $medication = Medication::where('id', $request->medication_id)
            ->where('user_id', $patient->id)
            ->firstOrFail();

        $prescription = Prescription::create([
            'user_id' => $patient->id,
            'organization_id' => $organizationId,
            'clinician_id' => $request->user()->id,
            'medication_id' => $request->medication_id,
            'status' => $request->status ?? MedicationStatus::Prescribed->value,
            'notes' => $request->notes,
            'expires_at' => $request->expires_at,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $prescription->id,
                'medication_name' => $prescription->medication?->name,
                'status' => $prescription->status,
                'ordered_at' => $prescription->ordered_at?->toISOString(),
                'expires_at' => $prescription->expires_at?->toISOString(),
                'notes' => $prescription->notes,
            ],
            'message' => 'Prescription created successfully',
        ], 201);
    }

    /**
     * Update prescription status (approve/dispense).
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

        $prescription = Prescription::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Only clinician or admin can update
        if ($request->user()->id !== $prescription->clinician_id &&
            !$request->user()->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'No permission to update this prescription',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate status transition
        $currentStatus = $prescription->status;
        $newStatus = $request->status;

        $validTransitions = [
            'prescribed' => ['approved', 'cancelled'],
            'approved' => ['dispensed', 'cancelled'],
            'dispensed' => ['active', 'discontinued'],
            'active' => ['discontinued'],
        ];

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            return response()->json([
                'success' => false,
                'message' => "Invalid status transition from {$currentStatus} to {$newStatus}",
            ], 400);
        }

        $prescription->status = $newStatus;

        if ($newStatus === 'dispensed') {
            $prescription->dispensed_at = now();
        }

        if ($newStatus === 'discontinued') {
            $prescription->expires_at = now();
        }

        $prescription->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $prescription->id,
                'status' => $prescription->status,
                'dispensed_at' => $prescription->dispensed_at?->toISOString(),
                'expires_at' => $prescription->expires_at?->toISOString(),
            ],
            'message' => 'Prescription status updated successfully',
        ]);
    }

    /**
     * Request a refill.
     */
    public function refillRequest(Request $request, $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $prescription = Prescription::where('id', $id)
            ->where('organization_id', $organizationId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Only the patient can request refills, and only for active medications
        if ($request->user()->id !== $prescription->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'No permission to request refill for this prescription',
            ], 403);
        }

        if ($prescription->status !== MedicationStatus::Active->value) {
            return response()->json([
                'success' => false,
                'message' => 'Refills can only be requested for active medications',
            ], 400);
        }

        $prescription->notes = ($prescription->notes ?? '') . "\nRefill requested on " . now()->toString() . ' by ' . $request->user()->name;

        // Auto-approve refill request - move to approved, then dispensed
        // In a full system, this would require clinician approval
        $prescription->status = MedicationStatus::Approved->value;
        $prescription->dispensed_at = now();
        $prescription->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $prescription->id,
                'status' => $prescription->status,
                'dispensed_at' => $prescription->dispensed_at?->toISOString(),
                'message' => 'Refill request processed and approved',
            ],
            'message' => 'Refill request processed successfully',
        ]);
    }

    /**
     * Get a single prescription.
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

        $prescription = Prescription::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $prescription->id,
                'medication_name' => $prescription->medication?->name,
                'status' => $prescription->status,
                'ordered_at' => $prescription->ordered_at?->toISOString(),
                'expires_at' => $prescription->expires_at?->toISOString(),
                'dispensed_at' => $prescription->dispensed_at?->toISOString(),
                'notes' => $prescription->notes,
                'clinician_name' => $prescription->clinician?->name,
                'user_name' => $prescription->user?->name,
            ],
        ]);
    }
}