<?php

namespace App\Http\Controllers\Api\V1\Clinician;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Encounter;
use App\Models\LabOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicianDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->organization_id;

        $todayAppointments = Appointment::where('clinician_id', $user->id)
            ->whereDate('check_in_time', today())
            ->where('status', '!=', 'cancelled')
            ->orderBy('check_in_time')
            ->get();

        $waitingPatients = Appointment::where('clinician_id', $user->id)
            ->where('status', 'checked_in')
            ->orderBy('check_in_time')
            ->get();

        $recentEncounters = Encounter::where('clinician_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $pendingLabs = LabOrder::where('clinician_id', $user->id)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'today_appointments' => $todayAppointments,
                'waiting_patients' => $waitingPatients,
                'recent_encounters' => $recentEncounters,
                'pending_labs' => $pendingLabs,
                'patients_requiring_attention' => [],
                'stats' => [
                    'patients_today' => $todayAppointments->count(),
                    'encounters_completed' => Encounter::where('clinician_id', $user->id)
                        ->whereDate('created_at', today())
                        ->where('queue_status', 'completed')
                        ->count(),
                    'pending_reviews' => $pendingLabs->count(),
                ],
            ],
        ]);
    }
}
