<?php

namespace App\Http\Controllers\Api\V1\Patient;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\LabResult;
use App\Models\Medication;
use App\Models\MedicalDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $upcomingAppointments = Appointment::where('patient_id', $user->id)
            ->where('status', 'scheduled')
            ->where('check_in_time', '>=', now())
            ->orderBy('check_in_time')
            ->limit(5)
            ->get();

        $recentLabs = LabResult::where('user_id', $user->id)
            ->orderByDesc('collected_at')
            ->limit(5)
            ->get();

        $medications = Medication::where('user_id', $user->id)
            ->where('status', 'active')
            ->limit(10)
            ->get();

        $recentDocuments = MedicalDocument::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'upcoming_appointments' => $upcomingAppointments,
                'recent_labs' => $recentLabs,
                'medications' => $medications,
                'recent_documents' => $recentDocuments,
                'health_summary' => [
                    'total_labs' => LabResult::where('user_id', $user->id)->count(),
                    'active_medications' => Medication::where('user_id', $user->id)->where('status', 'active')->count(),
                    'recent_documents' => MedicalDocument::where('user_id', $user->id)->count(),
                ],
            ],
        ]);
    }
}
