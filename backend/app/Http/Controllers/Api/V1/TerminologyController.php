<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Terminology\TerminologyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Read-only, offline medical terminology endpoints (ICD-10-CM / SNOMED CT).
 */
final class TerminologyController extends Controller
{
    public function __construct(private readonly TerminologyService $terminology) {}

    public function systems(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'systems' => $this->terminology->supportedSystems(),
                'counts' => [
                    'icd10' => count($this->terminology->search('icd10', '')['results']),
                    'snomed' => count($this->terminology->search('snomed', '')['results']),
                ],
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'system' => ['required', 'string', 'in:icd10,snomed'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $this->terminology->search(
            $request->query('system'),
            (string) $request->query('q', ''),
        );

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function check(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'system' => ['required', 'string', 'in:icd10,snomed'],
            'code' => ['required', 'string', 'max:50'],
            'display' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->terminology->validate(
            $request->input('system'),
            $request->input('code'),
            $request->input('display'),
        );

        return response()->json(['success' => true, 'data' => $result]);
    }
}
