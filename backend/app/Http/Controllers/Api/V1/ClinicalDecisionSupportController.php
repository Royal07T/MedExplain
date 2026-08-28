<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ClinicalDecisionSupportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClinicalDecisionSupportController extends Controller
{
    public function __construct(
        private readonly ClinicalDecisionSupportService $cdsService
    ) {}

    /**
     * Check for drug-allergy interactions
     */
    public function checkDrugAllergy(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'patient_id' => 'required|exists:users,id',
            'medications' => 'required|array',
            'medications.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $alerts = $this->cdsService->checkDrugAllergyInteractions(
            $request->patient_id,
            $request->medications
        );

        return response()->json([
            'alerts' => $alerts,
            'count' => $alerts->count(),
        ]);
    }

    /**
     * Check for drug-drug interactions
     */
    public function checkDrugInteractions(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'medications' => 'required|array',
            'medications.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $alerts = $this->cdsService->checkDrugDrugInteractions($request->medications);

        return response()->json([
            'alerts' => $alerts,
            'count' => $alerts->count(),
        ]);
    }

    /**
     * Check for dose adjustments
     */
    public function checkDoseAdjustments(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'patient_id' => 'required|exists:users,id',
            'medications' => 'required|array',
            'medications.*.name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $alerts = $this->cdsService->checkDoseAdjustments(
            $request->patient_id,
            $request->medications
        );

        return response()->json([
            'alerts' => $alerts,
            'count' => $alerts->count(),
        ]);
    }

    /**
     * Check vital signs for critical values
     */
    public function checkVitalSigns(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'patient_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $alerts = $this->cdsService->checkVitalSigns($request->patient_id);

        return response()->json([
            'alerts' => $alerts,
            'count' => $alerts->count(),
        ]);
    }

    /**
     * Get guideline reminders for patient conditions
     */
    public function getGuidelineReminders(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'patient_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $alerts = $this->cdsService->getGuidelineReminders($request->patient_id);

        return response()->json([
            'alerts' => $alerts,
            'count' => $alerts->count(),
        ]);
    }

    /**
     * Get preventive care reminders
     */
    public function getPreventiveCareReminders(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'patient_id' => 'required|exists:users,id',
            'age' => 'required|integer|min:0|max:150',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $alerts = $this->cdsService->getPreventiveCareReminders(
            $request->patient_id,
            $request->age
        );

        return response()->json([
            'alerts' => $alerts,
            'count' => $alerts->count(),
        ]);
    }

    /**
     * Run comprehensive clinical decision support check
     */
    public function comprehensiveCheck(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'patient_id' => 'required|exists:users,id',
            'medications' => 'sometimes|array',
            'medications.*' => 'string',
            'age' => 'sometimes|integer|min:0|max:150',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $allAlerts = collect();

        // Check vital signs
        $vitalAlerts = $this->cdsService->checkVitalSigns($request->patient_id);
        $allAlerts = $allAlerts->concat($vitalAlerts);

        // Check guideline reminders
        $guidelineAlerts = $this->cdsService->getGuidelineReminders($request->patient_id);
        $allAlerts = $allAlerts->concat($guidelineAlerts);

        // Check drug interactions if medications provided
        if ($request->has('medications')) {
            $drugInteractionAlerts = $this->cdsService->checkDrugDrugInteractions($request->medications);
            $allAlerts = $allAlerts->concat($drugInteractionAlerts);

            $drugAllergyAlerts = $this->cdsService->checkDrugAllergyInteractions(
                $request->patient_id,
                $request->medications
            );
            $allAlerts = $allAlerts->concat($drugAllergyAlerts);
        }

        // Check preventive care if age provided
        if ($request->has('age')) {
            $preventiveAlerts = $this->cdsService->getPreventiveCareReminders(
                $request->patient_id,
                $request->age
            );
            $allAlerts = $allAlerts->concat($preventiveAlerts);
        }

        // Sort by severity
        $severityOrder = ['severe' => 0, 'moderate' => 1, 'mild' => 2];
        $allAlerts = $allAlerts->sortBy(function ($alert) use ($severityOrder) {
            return $severityOrder[$alert['severity']] ?? 3;
        });

        return response()->json([
            'alerts' => $allAlerts->values(),
            'count' => $allAlerts->count(),
            'summary' => [
                'severe' => $allAlerts->where('severity', 'severe')->count(),
                'moderate' => $allAlerts->where('severity', 'moderate')->count(),
                'mild' => $allAlerts->where('severity', 'mild')->count(),
            ],
        ]);
    }
}
