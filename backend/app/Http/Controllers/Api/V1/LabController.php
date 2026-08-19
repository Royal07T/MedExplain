<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LabController extends Controller
{
    public function __construct(private readonly HealthService $healthService) {}

    /**
     * Distinct test names recorded for the authenticated user.
     */
    public function names(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->healthService->testNames($request->user()),
        ]);
    }

    /**
     * Time-series for one lab test across the user's reports.
     */
    public function trends(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $trend = $this->healthService->trends($request->user(), $validated['name']);

        return response()->json(
            $trend ?? ['test' => $validated['name'], 'unit' => null, 'series' => []]
        );
    }
}