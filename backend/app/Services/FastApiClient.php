<?php

namespace App\Services;

use App\DTOs\AiAnalysisDto;
use App\DTOs\ExtractionDto;
use App\DTOs\HealthQueryResponseDto;
use App\DTOs\LabResultDto;
use App\DTOs\MedicationDto;
use App\Exceptions\FastApiConnectionException;
use App\Exceptions\FastApiResponseException;
use App\Models\MedicalDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * HTTP client for the MedExplain FastAPI service.
 *
 * Safety: this client never logs document contents, extracted text, or test
 * values. Errors contain status codes and FastAPI's own technical detail only.
 */
final class FastApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: config('fastapi.base_url'),
            apiKey: config('fastapi.api_key'),
            timeout: config('fastapi.timeout'),
        );
    }

    /**
     * Send the stored file to FastAPI for text extraction.
     */
    public function extract(MedicalDocument $document): ExtractionDto
    {
        $response = $this->attempt(function () use ($document): Response {
            return Http::baseUrl($this->baseUrl)
                ->withHeaders($this->headers())
                ->timeout($this->timeout)
                ->attach(
                    'file',
                    (string) Storage::disk('documents')->get($document->storage_path),
                    $document->original_filename,
                )
                ->post('/api/v1/documents/extract');
        });

        return ExtractionDto::fromArray($this->decode($response));
    }

    /**
     * Ask FastAPI to parse extracted text into structured lab tests.
     *
     * @return list<LabResultDto>
     */
    public function parseLabReport(ExtractionDto $extraction): array
    {
        $response = $this->attempt(function () use ($extraction): Response {
            return Http::baseUrl($this->baseUrl)
                ->withHeaders($this->headers())
                ->timeout($this->timeout)
                ->asJson()
                ->post('/api/v1/documents/parse-lab-report', [
                    'raw_text' => $extraction->rawText,
                    'document_type' => $extraction->documentType,
                    'extraction_method' => $extraction->extractionMethod,
                ]);
        });

        return array_map(
            static fn (array $test): LabResultDto => LabResultDto::fromArray($test),
            $this->decode($response)['lab_tests'] ?? [],
        );
    }

    /**
     * Ask FastAPI to produce an educational analysis for the document.
     *
     * @param  list<LabResultDto>  $labTests
     */
    public function explain(
        ExtractionDto $extraction,
        array $labTests,
    ): AiAnalysisDto {
        $response = $this->attempt(function () use ($extraction, $labTests): Response {
            return Http::baseUrl($this->baseUrl)
                ->withHeaders($this->headers())
                ->timeout($this->timeout)
                ->asJson()
                ->post('/api/v1/analysis/explain', [
                    'document_type' => $extraction->documentType,
                    'raw_text' => $extraction->rawText,
                    'lab_tests' => array_map(
                        static fn (LabResultDto $test): array => $test->toArray(),
                        $labTests,
                    ),
                ]);
        });

        return AiAnalysisDto::fromArray($this->decode($response));
    }

    /**
     * Ask FastAPI to extract medications from the extracted text.
     *
     * @return list<MedicationDto>
     */
    public function extractMedications(ExtractionDto $extraction): array
    {
        $response = $this->attempt(function () use ($extraction): Response {
            return Http::baseUrl($this->baseUrl)
                ->withHeaders($this->headers())
                ->timeout($this->timeout)
                ->asJson()
                ->post('/api/v1/medications/extract', [
                    'raw_text' => $extraction->rawText,
                    'llm_fallback' => false,
                ]);
        });

        return array_map(
            static fn (array $medication): MedicationDto => MedicationDto::fromArray($medication),
            $this->decode($response)['medications'] ?? [],
        );
    }

    /**
     * Ask FastAPI for a structured health-intelligence answer to a question.
     *
     * The payload is the deterministic, ownership-scoped context computed by the
     * backend; FastAPI never receives raw documents, full text, or identifiers.
     *
     * @param  array<string, mixed>  $context
     */
    public function healthQuery(
        string $queryId,
        string $question,
        string $intent,
        array $context,
    ): HealthQueryResponseDto {
        $response = $this->attempt(function () use ($queryId, $question, $intent, $context): Response {
            return Http::baseUrl($this->baseUrl)
                ->withHeaders($this->headers())
                ->timeout($this->timeout)
                ->asJson()
                ->post('/api/v1/health/query', [
                    'query_id' => $queryId,
                    'question' => $question,
                    'intent' => $intent,
                    ...$context,
                ]);
        });

        return HealthQueryResponseDto::fromArray($this->decode($response));
    }

    /**
     * Ask FastAPI to summarize a clinical note (extractive, offline).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function nlpSummarize(array $payload): array
    {
        return $this->postJson('/api/v1/nlp/summarize', $payload);
    }

    /**
     * Ask FastAPI to extract medications/diagnoses from free text.
     *
     * @return array<string, mixed>
     */
    public function nlpExtractConcepts(string $text): array
    {
        return $this->postJson('/api/v1/nlp/concepts', ['text' => $text]);
    }

    /**
     * Ask FastAPI for lexicon-based sentiment of patient feedback.
     *
     * @return array<string, mixed>
     */
    public function nlpAnalyzeSentiment(string $text): array
    {
        return $this->postJson('/api/v1/nlp/sentiment', ['text' => $text]);
    }

    /**
     * Ask FastAPI to estimate 30-day readmission risk (heuristic).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function predictReadmission(array $payload): array
    {
        return $this->postJson('/api/v1/predictive/readmission', $payload);
    }

    /**
     * Ask FastAPI to predict length of stay (heuristic).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function predictLengthOfStay(array $payload): array
    {
        return $this->postJson('/api/v1/predictive/length-of-stay', $payload);
    }

    /**
     * Ask FastAPI for an early-warning deterioration score from vitals.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function predictDeterioration(array $payload): array
    {
        return $this->postJson('/api/v1/predictive/deterioration', $payload);
    }

    /**
     * Ask FastAPI for a deterministic symptom-triage assessment.
     *
     * @return array<string, mixed>
     */
    public function symptomCheck(string $text): array
    {
        return $this->postJson('/api/v1/assistant/symptom-check', ['text' => $text]);
    }

    /**
     * Ask FastAPI for a deterministic imaging-order reading analysis.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function imagingAnalyze(array $payload): array
    {
        return $this->postJson('/api/v1/imaging/analyze', $payload);
    }

    /**
     * Check the FastAPI service health.
     *
     * @return array<string, mixed>
     */
    public function health(): array
    {
        $response = $this->attempt(function (): Response {
            return Http::baseUrl($this->baseUrl)
                ->withHeaders($this->headers())
                ->timeout($this->timeout)
                ->get('/api/v1/health');
        });

        return $this->decode($response);
    }

    /**
     * POST JSON to the FastAPI service and return the decoded payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postJson(string $path, array $payload): array
    {
        $response = $this->attempt(function () use ($path, $payload): Response {
            return Http::baseUrl($this->baseUrl)
                ->withHeaders($this->headers())
                ->timeout($this->timeout)
                ->asJson()
                ->post($path, $payload);
        });

        return $this->decode($response);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'X-Service-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Wrap the HTTP call, mapping transport failures to a retryable error.
     */
    private function attempt(\Closure $callback): Response
    {
        try {
            return $callback();
        } catch (ConnectionException $e) {
            throw new FastApiConnectionException('FastAPI service is unreachable.', 0, $e);
        }
    }

    /**
     * Validate the response status and payload shape.
     *
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $status = $response->status();
        $json = $response->json();

        if ($status < 400 && is_array($json)) {
            return $json;
        }

        throw new FastApiResponseException($this->errorMessage($json, $status));
    }

    /**
     * Build a safe technical error message from a FastAPI failure.
     */
    private function errorMessage(mixed $json, int $status): string
    {
        if (is_array($json) && isset($json['detail']) && is_string($json['detail'])) {
            return "FastAPI responded with status {$status}: {$json['detail']}";
        }

        return "FastAPI responded with status {$status}.";
    }
}
