<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $platformOverview = [
            'organizations' => Organization::count(),
            'total_users' => User::count(),
            'active_sessions' => DB::table('personal_access_tokens')
                ->where('last_used_at', '>=', now()->subMinutes(30))
                ->count(),
        ];

        $aiUsage = [
            'queries_today' => 0,
            'cost_today' => 0,
            'avg_latency' => 0,
        ];

        $systemHealth = [
            'uptime' => '99.9%',
            'response_time' => '120ms',
            'error_rate' => '0.1%',
        ];

        return response()->json([
            'data' => [
                'platform_overview' => $platformOverview,
                'ai_usage' => $aiUsage,
                'system_health' => $systemHealth,
            ],
        ]);
    }
}
