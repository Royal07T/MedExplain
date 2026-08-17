<?php

namespace Tests\Unit;

use App\DTOs\ExtractionDto;
use App\Exceptions\FastApiConnectionException;
use App\Exceptions\FastApiResponseException;
use App\Models\MedicalDocument;
use App\Services\FastApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FastApiClientTest extends TestCase
{
    private function makeDocument(): MedicalDocument
    {
        Storage::fake('documents');

        $document = new MedicalDocument([
            'storage_path' => 'u1/report.pdf',
            'original_filename' => 'report.pdf',
        ]);

        Storage::disk('documents')->put('u1/report.pdf', 'pdf-bytes');

        return $document;
    }

    public function test_extract_parses_response(): void
    {
        Http::fake([
            '*/api/v1/documents/extract' => Http::response([
                'document_type' => 'lab_report',
                'extraction_method' => 'pdf_text',
                'raw_text' => 'Glucose 95',
                'lab_tests' => [],
                'warnings' => [],
            ], 200),
        ]);

        $dto = FastApiClient::fromConfig()->extract($this->makeDocument());

        $this->assertSame('lab_report', $dto->documentType);
        $this->assertSame('pdf_text', $dto->extractionMethod);
        $this->assertSame('Glucose 95', $dto->rawText);
    }

    public function test_extract_sends_service_key_header(): void
    {
        Http::fake([
            '*/api/v1/documents/extract' => Http::response([
                'document_type' => 'unknown',
                'extraction_method' => 'none',
                'raw_text' => '',
                'lab_tests' => [],
                'warnings' => [],
            ], 200),
        ]);

        FastApiClient::fromConfig()->extract($this->makeDocument());

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('X-Service-Key', 'dev-secret-change-me');
        });
    }

    public function test_parse_lab_report_returns_dtos(): void
    {
        Http::fake([
            '*/api/v1/documents/parse-lab-report' => Http::response([
                'document_type' => 'lab_report',
                'extraction_method' => 'pdf_text',
                'raw_text' => '',
                'lab_tests' => [
                    ['name' => 'Glucose', 'value' => '95', 'unit' => 'mg/dL', 'reference_range' => '70-99', 'status' => 'within_range'],
                ],
                'warnings' => [],
            ], 200),
        ]);

        $extraction = ExtractionDto::fromArray([
            'document_type' => 'lab_report',
            'extraction_method' => 'pdf_text',
            'raw_text' => 'Glucose 95',
        ]);

        $results = FastApiClient::fromConfig()->parseLabReport($extraction);

        $this->assertCount(1, $results);
        $this->assertSame('Glucose', $results[0]->name);
        $this->assertSame('within_range', $results[0]->status);
        $this->assertSame('70-99', $results[0]->referenceRange);
    }

    public function test_explain_returns_analysis_dto(): void
    {
        Http::fake([
            '*/api/v1/analysis/explain' => Http::response([
                'summary' => 'Summary.',
                'disclaimer' => 'Disclaimer.',
                'concerns' => [],
                'items' => [
                    ['test_name' => 'Glucose', 'explanation' => 'Ok.', 'category' => 'reference_comparison'],
                ],
            ], 200),
        ]);

        $extraction = ExtractionDto::fromArray([
            'document_type' => 'lab_report',
            'extraction_method' => 'pdf_text',
            'raw_text' => 'Glucose 95',
        ]);

        $analysis = FastApiClient::fromConfig()->explain($extraction, []);

        $this->assertSame('Summary.', $analysis->summary);
        $this->assertCount(1, $analysis->items);
        $this->assertSame('Glucose', $analysis->items[0]->testName);
    }

    public function test_connection_failure_maps_to_fast_api_connection_exception(): void
    {
        Http::fake([
            '*' => static function (): never {
                throw new ConnectionException('Connection refused');
            },
        ]);

        $this->expectException(FastApiConnectionException::class);

        FastApiClient::fromConfig()->extract($this->makeDocument());
    }

    public function test_error_response_maps_to_fast_api_response_exception(): void
    {
        Http::fake([
            '*/api/v1/documents/extract' => Http::response([
                'detail' => 'Unsupported file type',
            ], 415),
        ]);

        $this->expectException(FastApiResponseException::class);
        $this->expectExceptionMessage('415');

        FastApiClient::fromConfig()->extract($this->makeDocument());
    }

    public function test_health_returns_payload(): void
    {
        Http::fake([
            '*/api/v1/health' => Http::response([
                'status' => 'ok',
                'version' => '0.1.0',
            ], 200),
        ]);

        $health = FastApiClient::fromConfig()->health();

        $this->assertSame('ok', $health['status']);
    }
}