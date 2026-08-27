<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminAIController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        $totalUsers = User::count();
        $clinicianCount = User::where('role', 'clinician')->count();

        return response()->json([
            'data' => [
                'total_users' => $totalUsers,
                'eligible_users' => $clinicianCount,
                'queries_today' => 0,
                'cost_today' => 0,
                'avg_latency' => 0,
                'total_queries' => 0,
                'total_cost' => 0,
                'provider' => config('services.ai.provider', 'openai'),
                'model' => config('services.ai.model', 'gpt-4'),
            ],
        ]);
    }

    public function usage(Request $request): JsonResponse
    {
        $dailyUsage = collect();

        for ($i = 29; $i >= 0; $i--) {
            $dailyUsage->push([
                'date' => now()->subDays($i)->toDateString(),
                'queries' => 0,
                'cost' => 0,
                'avg_latency' => 0,
            ]);
        }

        return response()->json([
            'data' => [
                'daily' => $dailyUsage,
                'totals' => [
                    'queries' => 0,
                    'cost' => 0,
                    'avg_latency' => 0,
                ],
            ],
        ]);
    }
}
