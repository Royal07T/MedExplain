<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FastApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * AI-assisted clinical tools: NLP (note summarization, concept extraction,
 * sentiment) and predictive analytics (readmission, length of stay,
 * deterioration early-warning).
 *
 * Safety: every leaning on these tools is a decision-support estimate, not a
 * diagnosis. The FastAPI service applies its own deterministic heuristics and
 * safety gates before anything is returned.
 */
final class ClinicalAIController extends Controller
{
    public function __construct(private readonly FastApiClient $client) {}

    public function summarizeNote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => ['required', 'string', 'min:1', 'max:50000'],
            'max_sentences' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        return $this->ok(
            $this->client->nlpSummarize([
                'text' => $request->input('text'),
                'max_sentences' => (int) ($request->input('max_sentences') ?? 4),
            ])
        );
    }

    public function extractConcepts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => ['required', 'string', 'min:1', 'max:50000'],
        ]);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        return $this->ok($this->client->nlpExtractConcepts($request->input('text')));
    }

    public function analyzeSentiment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => ['required', 'string', 'min:1', 'max:20000'],
        ]);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        return $this->ok($this->client->nlpAnalyzeSentiment($request->input('text')));
    }

    public function predictReadmission(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'prior_admissions_90d' => ['nullable', 'integer', 'min:0'],
            'prior_admissions_12m' => ['nullable', 'integer', 'min:0'],
            'comorbidities' => ['nullable', 'array'],
            'length_of_stay_days' => ['nullable', 'numeric', 'min:0'],
            'polypharmacy' => ['nullable', 'boolean'],
            'hba1c_uncontrolled' => ['nullable', 'boolean'],
            'hemoglobin_low' => ['nullable', 'boolean'],
            'discharge_to_home' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        return $this->ok(
            $this->client->predictReadmission($this->nullableFill($request, [
                'age', 'prior_admissions_90d', 'prior_admissions_12m',
                'comorbidities', 'length_of_stay_days', 'polypharmacy',
                'hba1c_uncontrolled', 'hemoglobin_low', 'discharge_to_home',
            ]))
        );
    }

    public function predictLengthOfStay(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'admission_type' => ['nullable', 'string', 'max:50'],
            'acuity' => ['nullable', 'in:non-urgent,urgent,emergent,resuscitation'],
            'comorbidities' => ['nullable', 'array'],
            'icu_required' => ['nullable', 'boolean'],
            'surgery_required' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        return $this->ok(
            $this->client->predictLengthOfStay($this->nullableFill($request, [
                'age', 'admission_type', 'acuity', 'comorbidities',
                'icu_required', 'surgery_required',
            ]))
        );
    }

    public function predictDeterioration(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'vitals' => ['required', 'array'],
            'vitals.heart_rate' => ['nullable', 'integer', 'min:0', 'max:300'],
            'vitals.respiratory_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'vitals.temperature_c' => ['nullable', 'numeric'],
            'vitals.systolic_bp' => ['nullable', 'integer', 'min:0', 'max:300'],
            'vitals.diastolic_bp' => ['nullable', 'integer', 'min:0', 'max:300'],
            'vitals.spo2' => ['nullable', 'integer', 'min:0', 'max:100'],
            'vitals.conscious' => ['nullable', 'boolean'],
            'vitals.on_oxygen' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        return $this->ok(
            $this->client->predictDeterioration([
                'age' => $request->input('age'),
                'vitals' => $request->input('vitals'),
            ])
        );
    }

    /**
     * Build a 200 response wrapping the FastAPI result.
     *
     * @param  array<string, mixed>  $data
     */
    private function ok(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Build a 422 validation response.
     */
    private function invalid(\Illuminate\Contracts\Validation\Validator $validator): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }

    /**
     * Send only the keys present in the request to FastAPI.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function nullableFill(Request $request, array $keys): array
    {
        $payload = [];
        foreach ($keys as $key) {
            if ($request->has($key)) {
                $payload[$key] = $request->input($key);
            }
        }
        return $payload;
    }
}
