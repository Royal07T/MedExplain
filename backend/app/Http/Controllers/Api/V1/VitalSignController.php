<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class VitalSignController extends Controller
{
    /**
     * List vital signs for a patient.
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

        $vitalSigns = VitalSign::where('patient_id', $patient->id)
            ->where('organization_id', $organizationId)
            ->latest('recorded_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $vitalSigns->map(function ($vitalSign) {
                return [
                    'id' => $vitalSign->id,
                    'patient_id' => $vitalSign->patient_id,
                    'encounter_id' => $vitalSign->encounter_id,
                    'temperature' => $vitalSign->temperature,
                    'temperature_unit' => $vitalSign->temperature_unit,
                    'heart_rate' => $vitalSign->heart_rate,
                    'blood_pressure_systolic' => $vitalSign->blood_pressure_systolic,
                    'blood_pressure_diastolic' => $vitalSign->blood_pressure_diastolic,
                    'respiratory_rate' => $vitalSign->respiratory_rate,
                    'oxygen_saturation' => $vitalSign->oxygen_saturation,
                    'weight' => $vitalSign->weight,
                    'weight_unit' => $vitalSign->weight_unit,
                    'height' => $vitalSign->height,
                    'height_unit' => $vitalSign->height_unit,
                    'bmi' => $vitalSign->bmi,
                    'pain_score' => $vitalSign->pain_score,
                    'notes' => $vitalSign->notes,
                    'recorded_by' => $vitalSign->recorded_by,
                    'recorded_at' => $vitalSign->recorded_at?->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Create a new vital sign record.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'exists:users,id'],
            'encounter_id' => ['sometimes', 'nullable', 'exists:encounters,id'],
            'temperature' => ['sometimes', 'nullable', 'numeric'],
            'temperature_unit' => ['sometimes', 'in:C,F'],
            'heart_rate' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'blood_pressure_systolic' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'blood_pressure_diastolic' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'respiratory_rate' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'oxygen_saturation' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'weight_unit' => ['sometimes', 'in:kg,lb'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height_unit' => ['sometimes', 'in:cm,in'],
            'pain_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'recorded_at' => ['sometimes', 'date'],
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
        if (!$request->user()->isClinician() &&
            !$request->user()->isNurseStaff() &&
            $request->user()->id !== $patient->id) {
            return response()->json([
                'success' => false,
                'message' => 'No permission to record vital signs',
            ], 403);
        }

        if ($request->user()->isClinician()) {
            if (!$request->user()->clinicianPatients()->where('patient_user_id', $patient->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No access to this patient',
                ], 403);
            }
        }

        $vitalSign = VitalSign::create([
            'patient_id' => $patient->id,
            'organization_id' => $organizationId,
            'encounter_id' => $request->encounter_id,
            'temperature' => $request->temperature,
            'temperature_unit' => $request->temperature_unit ?? 'C',
            'heart_rate' => $request->heart_rate,
            'blood_pressure_systolic' => $request->blood_pressure_systolic,
            'blood_pressure_diastolic' => $request->blood_pressure_diastolic,
            'respiratory_rate' => $request->respiratory_rate,
            'oxygen_saturation' => $request->oxygen_saturation,
            'weight' => $request->weight,
            'weight_unit' => $request->weight_unit ?? 'kg',
            'height' => $request->height,
            'height_unit' => $request->height_unit ?? 'cm',
            'pain_score' => $request->pain_score,
            'notes' => $request->notes,
            'recorded_by' => $request->user()->id,
            'recorded_at' => $request->recorded_at ?? now(),
        ]);

        // Calculate BMI if weight and height are provided
        if ($vitalSign->weight && $vitalSign->height) {
            $vitalSign->calculateBMI();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $vitalSign->id,
                'bmi' => $vitalSign->bmi,
                'recorded_at' => $vitalSign->recorded_at?->toISOString(),
                'message' => 'Vital signs recorded successfully',
            ],
        ], 201);
    }

    /**
     * Update a vital sign record.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'temperature' => ['sometimes', 'nullable', 'numeric'],
            'temperature_unit' => ['sometimes', 'in:C,F'],
            'heart_rate' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'blood_pressure_systolic' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'blood_pressure_diastolic' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'respiratory_rate' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'oxygen_saturation' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'weight_unit' => ['sometimes', 'in:kg,lb'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height_unit' => ['sometimes', 'in:cm,in'],
            'pain_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
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

        $vitalSign = VitalSign::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Verify access
        if (!$request->user()->isClinician() && !$request->user()->isNurseStaff()) {
            return response()->json([
                'success' => false,
                'message' => 'No permission to update vital signs',
            ], 403);
        }

        if ($request->has('temperature')) {
            $vitalSign->temperature = $request->temperature;
        }
        if ($request->has('temperature_unit')) {
            $vitalSign->temperature_unit = $request->temperature_unit;
        }
        if ($request->has('heart_rate')) {
            $vitalSign->heart_rate = $request->heart_rate;
        }
        if ($request->has('blood_pressure_systolic')) {
            $vitalSign->blood_pressure_systolic = $request->blood_pressure_systolic;
        }
        if ($request->has('blood_pressure_diastolic')) {
            $vitalSign->blood_pressure_diastolic = $request->blood_pressure_diastolic;
        }
        if ($request->has('respiratory_rate')) {
            $vitalSign->respiratory_rate = $request->respiratory_rate;
        }
        if ($request->has('oxygen_saturation')) {
            $vitalSign->oxygen_saturation = $request->oxygen_saturation;
        }
        if ($request->has('weight')) {
            $vitalSign->weight = $request->weight;
        }
        if ($request->has('weight_unit')) {
            $vitalSign->weight_unit = $request->weight_unit;
        }
        if ($request->has('height')) {
            $vitalSign->height = $request->height;
        }
        if ($request->has('height_unit')) {
            $vitalSign->height_unit = $request->height_unit;
        }
        if ($request->has('pain_score')) {
            $vitalSign->pain_score = $request->pain_score;
        }
        if ($request->has('notes')) {
            $vitalSign->notes = $request->notes;
        }

        // Recalculate BMI if weight or height changed
        if ($request->has('weight') || $request->has('height')) {
            $vitalSign->calculateBMI();
        }

        $vitalSign->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $vitalSign->id,
                'bmi' => $vitalSign->bmi,
                'message' => 'Vital signs updated successfully',
            ],
        ]);
    }

    /**
     * Delete a vital sign record.
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

        $vitalSign = VitalSign::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Verify access
        if (!$request->user()->isClinician() && !$request->user()->isNurseStaff()) {
            return response()->json([
                'success' => false,
                'message' => 'No permission to delete vital signs',
            ], 403);
        }

        $vitalSign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vital signs deleted successfully',
        ]);
    }
}
