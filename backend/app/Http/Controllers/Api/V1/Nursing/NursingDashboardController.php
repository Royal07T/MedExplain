<?php

namespace App\Http\Controllers\Api\V1\Nursing;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Encounter;
use App\Models\Medication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NursingDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->organization_id;

        $assignedPatients = Patient::where('organization_id', $orgId)
            ->whereHas('encounters', function ($q) {
                $q->where('queue_status', '!=', 'completed');
            })
            ->limit(20)
            ->get();

        $pendingVitals = Patient::where('organization_id', $orgId)
            ->whereHas('encounters', function ($q) {
                $q->where('queue_status', 'in_progress')
                    ->whereNull('vitals_summary');
            })
            ->limit(20)
            ->get();

        $medicationRounds = Medication::where('organization_id', $orgId)
            ->where('status', 'active')
            ->whereNotNull('frequency')
            ->limit(20)
            ->get();

        $activeAlerts = [];

        $admissionsDischarges = Encounter::where('organization_id', $orgId)
            ->whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => [
                'assigned_patients' => $assignedPatients,
                'pending_vitals' => $pendingVitals,
                'medication_rounds' => $medicationRounds,
                'nursing_tasks' => [],
                'active_alerts' => $activeAlerts,
                'admissions_discharges' => $admissionsDischarges,
            ],
        ]);
    }
}
