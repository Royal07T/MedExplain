<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HealthController extends Controller
{
    public function __construct(private readonly HealthService $healthService) {}

    /**
     * The authenticated user's personal health timeline, newest first.
     */
    public function timeline(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->healthService->timeline($request->user()),
        ]);
    }

    /**
     * The authenticated user's aggregated personal health record.
     */
    public function record(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->healthService->record($request->user()),
        ]);
    }
}