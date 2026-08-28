<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Allergy;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class AllergyController extends Controller
{
    /**
     * List allergies for a patient.
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

        // Verify access
        if ($request->user()->isClinician()) {
            if (!$request->user()->clinicianPatients()->where('patient_user_id', $patient->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No access to this patient',
                ], 403);
            }
        } elseif ($request->user()->id !== $patient->id) {
            return response()->json([
                'success' => false,
                'message' => 'No access to this patient',
            ], 403);
        }

        $allergies = Allergy::where('patient_id', $patient->id)
            ->where('organization_id', $organizationId)
            ->latest('onset_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $allergies->map(function ($allergy) {
                return [
                    'id' => $allergy->id,
                    'allergen_type' => $allergy->allergen_type,
                    'allergen_name' => $allergy->allergen_name,
                    'reaction_description' => $allergy->reaction_description,
                    'severity' => $allergy->severity,
                    'status' => $allergy->status,
                    'onset_date' => $allergy->onset_date?->toISOString(),
                    'notes' => $allergy->notes,
                    'created_at' => $allergy->created_at?->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Create a new allergy.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'exists:users,id'],
            'allergen_type' => ['required', 'in:drug,food,environmental,other'],
            'allergen_name' => ['required', 'string', 'max:255'],
            'reaction_description' => ['required', 'string', 'max:500'],
            'severity' => ['required', 'in:mild,moderate,severe,life_threatening'],
            'status' => ['required', 'in:active,resolved'],
            'onset_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
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

        // Verify access
        if (!$request->user()->isClinician() ||
            !$request->user()->clinicianPatients()->where('patient_user_id', $patient->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No access to this patient',
            ], 403);
        }

        $allergy = Allergy::create([
            'patient_id' => $patient->id,
            'organization_id' => $organizationId,
            'allergen_type' => $request->allergen_type,
            'allergen_name' => $request->allergen_name,
            'reaction_description' => $request->reaction_description,
            'severity' => $request->severity,
            'status' => $request->status,
            'onset_date' => $request->onset_date,
            'notes' => $request->notes,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $allergy->id,
                'allergen_name' => $allergy->allergen_name,
                'severity' => $allergy->severity,
                'status' => $allergy->status,
                'message' => 'Allergy added successfully',
            ],
        ], 201);
    }

    /**
     * Update an allergy.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'allergen_type' => ['sometimes', 'in:drug,food,environmental,other'],
            'allergen_name' => ['sometimes', 'string', 'max:255'],
            'reaction_description' => ['sometimes', 'string', 'max:500'],
            'severity' => ['sometimes', 'in:mild,moderate,severe,life_threatening'],
            'status' => ['sometimes', 'in:active,resolved'],
            'notes' => ['sometimes', 'nullable', 'string'],
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

        $allergy = Allergy::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Verify access
        if (!$request->user()->isClinician()) {
            return response()->json([
                'success' => false,
                'message' => 'No permission to update allergies',
            ], 403);
        }

        if ($request->has('allergen_type')) {
            $allergy->allergen_type = $request->allergen_type;
        }
        if ($request->has('allergen_name')) {
            $allergy->allergen_name = $request->allergen_name;
        }
        if ($request->has('reaction_description')) {
            $allergy->reaction_description = $request->reaction_description;
        }
        if ($request->has('severity')) {
            $allergy->severity = $request->severity;
        }
        if ($request->has('status')) {
            $allergy->status = $request->status;
        }
        if ($request->has('notes')) {
            $allergy->notes = $request->notes;
        }
        $allergy->updated_by = $request->user()->id;
        $allergy->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $allergy->id,
                'status' => $allergy->status,
                'message' => 'Allergy updated successfully',
            ],
        ]);
    }

    /**
     * Delete an allergy.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $allergy = Allergy::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Verify access
        if (!$request->user()->isClinician()) {
            return response()->json([
                'success' => false,
                'message' => 'No permission to delete allergies',
            ], 403);
        }

        $allergy->delete();

        return response()->json([
            'success' => true,
            'message' => 'Allergy deleted successfully',
        ]);
    }
}
