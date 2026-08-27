<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $user->organization_id;

        $patientCount = [
            'total' => Patient::where('organization_id', $orgId)->count(),
            'new_today' => Patient::where('organization_id', $orgId)
                ->whereDate('created_at', today())
                ->count(),
        ];

        $appointments = [
            'scheduled' => Appointment::where('organization_id', $orgId)
                ->where('status', 'scheduled')
                ->whereDate('check_in_time', today())
                ->count(),
            'completed' => Appointment::where('organization_id', $orgId)
                ->where('status', 'completed')
                ->whereDate('check_in_time', today())
                ->count(),
            'no_shows' => Appointment::where('organization_id', $orgId)
                ->where('status', 'no_show')
                ->whereDate('check_in_time', today())
                ->count(),
        ];

        $staff = [
            'on_duty' => User::where('organization_id', $orgId)
                ->whereIn('role', ['clinician', 'nursing_staff'])
                ->count(),
            'available' => User::where('organization_id', $orgId)
                ->where('role', 'clinician')
                ->count(),
        ];

        $billing = [
            'revenue' => Invoice::where('organization_id', $orgId)
                ->where('status', 'paid')
                ->whereDate('paid_at', today())
                ->sum('paid_amount'),
            'outstanding' => Invoice::where('organization_id', $orgId)
                ->whereIn('status', ['pending', 'partial'])
                ->sum(DB::raw('amount - paid_amount')),
        ];

        return response()->json([
            'data' => [
                'patient_count' => $patientCount,
                'appointments' => $appointments,
                'admissions' => ['today' => 0, 'this_week' => 0],
                'staff' => $staff,
                'laboratory' => ['ordered' => 0, 'completed' => 0, 'pending' => 0],
                'pharmacy' => ['filled' => 0, 'pending' => 0],
                'billing' => $billing,
            ],
        ]);
    }
}
