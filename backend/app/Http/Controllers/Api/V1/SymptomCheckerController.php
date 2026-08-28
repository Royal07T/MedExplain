<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FastApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Deterministic symptom-triage assistant for patients.
 *
 * Never diagnoses — it only advises on the urgency of seeking professional
 * care, delegating the rule-based triage to the AI service.
 */
final class SymptomCheckerController extends Controller
{
    public function __construct(private readonly FastApiClient $client) {}

    public function check(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->client->symptomCheck($request->input('text')),
        ]);
    }
}
