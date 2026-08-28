<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PrescriptionRefill;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class PrescriptionRefillController extends Controller
{
    /**
     * Get patient's prescription refill requests.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $refills = PrescriptionRefill::where('patient_id', $user->id)
            ->where('organization_id', $organizationId)
            ->latest('requested_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $refills->map(function ($refill) {
                return [
                    'id' => $refill->id,
                    'medication_name' => $refill->medication_name,
                    'dosage' => $refill->dosage,
                    'frequency' => $refill->frequency,
                    'reason' => $refill->reason,
                    'status' => $refill->status,
                    'clinician_notes' => $refill->clinician_notes,
                    'requested_at' => $refill->requested_at?->toISOString(),
                    'responded_at' => $refill->responded_at?->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Create a new prescription refill request.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'clinician_id' => ['required', 'exists:users,id'],
            'medication_name' => ['required', 'string', 'max:255'],
            'dosage' => ['sometimes', 'string', 'max:100'],
            'frequency' => ['sometimes', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:500'],
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

        $refill = PrescriptionRefill::create([
            'patient_id' => $user->id,
            'clinician_id' => $request->clinician_id,
            'organization_id' => $organizationId,
            'medication_name' => $request->medication_name,
            'dosage' => $request->dosage,
            'frequency' => $request->frequency,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $refill->id,
                'status' => $refill->status,
                'requested_at' => $refill->requested_at?->toISOString(),
                'message' => 'Prescription refill request submitted successfully',
            ],
        ], 201);
    }

    /**
     * Get a single prescription refill request.
     */
    public function show($id): JsonResponse
    {
        $user = request()->user();
        $organizationId = $user?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $refill = PrescriptionRefill::where('id', $id)
            ->where('organization_id', $organizationId)
            ->where('patient_id', $user->id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $refill->id,
                'medication_name' => $refill->medication_name,
                'dosage' => $refill->dosage,
                'frequency' => $refill->frequency,
                'reason' => $refill->reason,
                'status' => $refill->status,
                'clinician_notes' => $refill->clinician_notes,
                'requested_at' => $refill->requested_at?->toISOString(),
                'responded_at' => $refill->responded_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Clinician responds to prescription refill request.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:approved,denied,filled'],
            'clinician_notes' => ['sometimes', 'string', 'max:1000'],
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

        $refill = PrescriptionRefill::where('id', $id)
            ->where('organization_id', $organizationId)
            ->where('clinician_id', $user->id)
            ->firstOrFail();

        $refill->status = $request->status;
        $refill->clinician_notes = $request->clinician_notes;
        $refill->responded_at = now();
        $refill->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $refill->id,
                'status' => $refill->status,
                'responded_at' => $refill->responded_at?->toISOString(),
                'message' => 'Prescription refill request updated successfully',
            ],
        ]);
    }
}
