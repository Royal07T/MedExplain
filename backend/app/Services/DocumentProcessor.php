<?php

namespace App\Services;

use App\DTOs\AiAnalysisDto;
use App\DTOs\ExtractionDto;
use App\DTOs\LabResultDto;
use App\DTOs\MedicationDto;
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
        private readonly NotificationService $notifications,
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
                $this->storeLabResults($document, $extractionRow, $labTests);

                $this->extractAndStoreMedications($document, $extractionDto);

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

        $this->notifications->notify(
            $document->user,
            'Analysis ready',
            sprintf('The analysis for "%s" is ready to view.', $document->original_filename),
            'analysis',
            ['document_id' => $document->id],
        );
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
    private function storeLabResults(MedicalDocument $document, DocumentExtraction $extraction, array $labTests): void
    {
        $extraction->labResults()->delete();

        foreach (array_values($labTests) as $index => $test) {
            $extraction->labResults()->create([
                'name' => $test->name,
                'normalized_name' => $this->normalizeName($test->name),
                'value' => $test->value,
                'unit' => $test->unit,
                'reference_range' => $test->referenceRange,
                'status' => $test->status,
                'collected_at' => $document->created_at,
                'user_id' => $document->user_id,
                'sort_order' => $index,
            ]);
        }
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($name)));
    }

    /**
     * Best-effort medication extraction.
     *
     * Medication extraction is an auxiliary capability: any failure (including
     * an AI service hiccup) must never fail the whole document. It is logged and
     * skipped.
     */
    private function extractAndStoreMedications(MedicalDocument $document, ExtractionDto $extraction): void
    {
        try {
            $medications = $this->client->extractMedications($extraction);

            if ($medications === []) {
                return;
            }

            $this->storeMedications($document, $medications);
        } catch (\Throwable $e) {
            Log::warning('document.medication_extraction_skipped', [
                'document_id' => $document->id,
                'exception' => get_class($e),
            ]);
        }
    }

    /**
     * @param  list<MedicationDto>  $medications
     */
    private function storeMedications(MedicalDocument $document, array $medications): void
    {
        $document->medications()->delete();

        foreach (array_values($medications) as $index => $medication) {
            $document->medications()->create([
                'user_id' => $document->user_id,
                'name' => $medication->name,
                'strength' => $medication->strength,
                'dosage_form' => $medication->dosageForm,
                'dose' => $medication->dose,
                'frequency' => $medication->frequency,
                'route' => $medication->route,
                'prescriber' => $medication->prescriber,
                'indications' => $medication->indications,
                'start_date' => $medication->startDate,
                'end_date' => $medication->endDate,
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

        $this->notifications->notify(
            $document->user,
            'Processing failed',
            $this->safeMessage($e),
            'analysis',
            ['document_id' => $document->id],
        );
    }

    private function safeMessage(\Throwable $e): string
    {
        if ($e instanceof FastApiResponseException) {
            return Str::limit($e->getMessage(), 450, '…');
        }

        return 'Document processing failed. Please try again later.';
    }
}