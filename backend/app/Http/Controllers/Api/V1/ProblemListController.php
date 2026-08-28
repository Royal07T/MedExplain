<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProblemList;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class ProblemListController extends Controller
{
    /**
     * List problems for a patient.
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

        $problems = ProblemList::where('patient_id', $patient->id)
            ->where('organization_id', $organizationId)
            ->latest('onset_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $problems->map(function ($problem) {
                return [
                    'id' => $problem->id,
                    'icd10_code' => $problem->icd10_code,
                    'icd10_description' => $problem->icd10_description,
                    'clinical_notes' => $problem->clinical_notes,
                    'status' => $problem->status,
                    'onset_date' => $problem->onset_date?->toISOString(),
                    'resolved_date' => $problem->resolved_date?->toISOString(),
                    'created_at' => $problem->created_at?->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Create a new problem.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'exists:users,id'],
            'icd10_code' => ['required', 'string', 'max:10'],
            'icd10_description' => ['required', 'string', 'max:255'],
            'clinical_notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['required', 'in:active,chronic,resolved'],
            'onset_date' => ['required', 'date'],
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

        $problem = ProblemList::create([
            'patient_id' => $patient->id,
            'organization_id' => $organizationId,
            'icd10_code' => $request->icd10_code,
            'icd10_description' => $request->icd10_description,
            'clinical_notes' => $request->clinical_notes,
            'status' => $request->status,
            'onset_date' => $request->onset_date,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $problem->id,
                'icd10_code' => $problem->icd10_code,
                'icd10_description' => $problem->icd10_description,
                'status' => $problem->status,
                'message' => 'Problem added successfully',
            ],
        ], 201);
    }

    /**
     * Update a problem.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'icd10_code' => ['sometimes', 'string', 'max:10'],
            'icd10_description' => ['sometimes', 'string', 'max:255'],
            'clinical_notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'in:active,chronic,resolved'],
            'resolved_date' => ['sometimes', 'nullable', 'date'],
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

        $problem = ProblemList::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Verify access
        if (!$request->user()->isClinician()) {
            return response()->json([
                'success' => false,
                'message' => 'No permission to update problems',
            ], 403);
        }

        if ($request->has('icd10_code')) {
            $problem->icd10_code = $request->icd10_code;
        }
        if ($request->has('icd10_description')) {
            $problem->icd10_description = $request->icd10_description;
        }
        if ($request->has('clinical_notes')) {
            $problem->clinical_notes = $request->clinical_notes;
        }
        if ($request->has('status')) {
            $problem->status = $request->status;
            if ($request->status === 'resolved') {
                $problem->resolved_date = now();
            }
        }
        if ($request->has('resolved_date')) {
            $problem->resolved_date = $request->resolved_date;
        }
        $problem->updated_by = $request->user()->id;
        $problem->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $problem->id,
                'status' => $problem->status,
                'message' => 'Problem updated successfully',
            ],
        ]);
    }

    /**
     * Delete a problem.
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

        $problem = ProblemList::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Verify access
        if (!$request->user()->isClinician()) {
            return response()->json([
                'success' => false,
                'message' => 'No permission to delete problems',
            ], 403);
        }

        $problem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Problem deleted successfully',
        ]);
    }
}
