<?php

namespace App\Services;

use App\DTOs\AiAnalysisDto;
use App\DTOs\ExtractionDto;
use App\DTOs\LabResultDto;
use App\Enums\AiAnalysisStatus;
use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\FastApiConnectionException;
use App\Exceptions\FastApiResponseException;
use App\Models\AiAnalysis;
use App\Models\DocumentExtraction;
use App\Models\MedicalDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates document processing against the FastAPI service.
 *
 * Safety: failures mark the document failed with a safe technical message.
 * Full stack traces stay in the application logs; document contents and test
 * values are never logged.
 */
final class DocumentProcessor
{
    public function __construct(
        private readonly FastApiClient $client,
        private readonly AuditService $auditService,
    ) {}

    /**
     * Process a document end-to-end.
     *
     * Throws {@see FastApiConnectionException} for transient transport
     * failures so the job can retry; permanent failures mark the document
     * failed and return normally.
     */
    public function process(MedicalDocument $document): void
    {
        $document->update([
            'status' => DocumentStatus::Processing,
            'error_message' => null,
        ]);

        $extractionDto = null;

        try {
            DB::transaction(function () use ($document, &$extractionDto): void {
                $extractionDto = $this->client->extract($document);
                $extractionRow = $this->storeExtraction($document, $extractionDto);

                $labTests = $this->client->parseLabReport($extractionDto);
                $this->storeLabResults($extractionRow, $labTests);

                $analysis = $this->client->explain($extractionDto, $labTests);
                $this->storeAnalysis($document, $analysis);
            });
        } catch (FastApiConnectionException $e) {
            Log::warning('document.processing_retryable', [
                'document_id' => $document->id,
            ]);

            throw $e;
        } catch (\Throwable $e) {
            $this->markFailed($document, $e);

            return;
        }

        $document->update([
            'status' => DocumentStatus::Processed,
            'document_type' => $this->resolveDocumentType($document, $extractionDto),
            'processed_at' => now(),
            'error_message' => null,
        ]);

        $this->auditService->record(AuditEvent::AnalysisCreated, $document->user, $document);
    }

    private function storeExtraction(MedicalDocument $document, ExtractionDto $extraction): DocumentExtraction
    {
        $document->extraction()?->delete();

        return $document->extraction()->create([
            'extraction_method' => $extraction->extractionMethod,
            'raw_text' => $extraction->rawText,
        ]);
    }

    /**
     * @param  list<LabResultDto>  $labTests
     */
    private function storeLabResults(DocumentExtraction $extraction, array $labTests): void
    {
        $extraction->labResults()->delete();

        foreach (array_values($labTests) as $index => $test) {
            $extraction->labResults()->create([
                'name' => $test->name,
                'value' => $test->value,
                'unit' => $test->unit,
                'reference_range' => $test->referenceRange,
                'status' => $test->status,
                'sort_order' => $index,
            ]);
        }
    }

    private function storeAnalysis(MedicalDocument $document, AiAnalysisDto $analysis): AiAnalysis
    {
        $document->analysis()?->delete();

        $analysisRow = $document->analysis()->create([
            'status' => AiAnalysisStatus::Completed,
            'summary' => $analysis->summary,
            'disclaimer' => $analysis->disclaimer,
            'concerns' => $analysis->concerns,
            'processed_at' => now(),
        ]);

        foreach (array_values($analysis->items) as $index => $item) {
            $analysisRow->items()->create([
                'test_name' => $item->testName,
                'explanation' => $item->explanation,
                'category' => $item->category,
                'sort_order' => $index,
            ]);
        }

        return $analysisRow;
    }

    private function resolveDocumentType(MedicalDocument $document, ?ExtractionDto $extraction): DocumentType
    {
        if (! $extraction || $extraction->documentType === DocumentType::Unknown->value) {
            return $document->document_type;
        }

        return DocumentType::tryFrom($extraction->documentType) ?? $document->document_type;
    }

    private function markFailed(MedicalDocument $document, \Throwable $e): void
    {
        $document->update([
            'status' => DocumentStatus::Failed,
            'error_message' => $this->safeMessage($e),
            'processed_at' => now(),
        ]);

        Log::error('document.processing_failed', [
            'document_id' => $document->id,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);
    }

    private function safeMessage(\Throwable $e): string
    {
        if ($e instanceof FastApiResponseException) {
            return Str::limit($e->getMessage(), 450, '…');
        }

        return 'Document processing failed. Please try again later.';
    }
}