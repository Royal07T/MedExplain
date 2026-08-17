<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\MedicalDocument;
use App\Services\DocumentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Processes a document against the FastAPI service.
 *
 * Retries only transient transport failures; permanent failures mark the
 * document failed and are not retried.
 */
class ProcessMedicalDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $documentId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(DocumentProcessor $processor): void
    {
        $document = MedicalDocument::find($this->documentId);

        if ($document === null) {
            return;
        }

        $processor->process($document);
    }

    /**
     * Reached only after retries are exhausted.
     */
    public function failed(\Throwable $e): void
    {
        $document = MedicalDocument::find($this->documentId);

        if ($document === null) {
            return;
        }

        $document->update([
            'status' => DocumentStatus::Failed,
            'error_message' => 'Processing could not be completed after multiple attempts.',
            'processed_at' => now(),
        ]);
    }
}