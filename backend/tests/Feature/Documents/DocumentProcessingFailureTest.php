<?php

namespace Tests\Feature\Documents;

use App\Exceptions\FastApiConnectionException;
use App\Jobs\ProcessMedicalDocumentJob;
use App\Models\MedicalDocument;
use App\Models\User;
use App\Services\DocumentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentProcessingFailureTest extends TestCase
{
    use RefreshDatabase;

    private function makeDocument(User $user): MedicalDocument
    {
        Storage::fake('documents');

        $document = MedicalDocument::factory()->for($user)->create(['status' => 'uploaded']);
        Storage::disk('documents')->put($document->storage_path, 'pdf-bytes');

        return $document;
    }

    public function test_transient_connection_failure_propagates_for_retry(): void
    {
        $user = User::factory()->create();
        $document = $this->makeDocument($user);

        Http::fake([
            '*' => static function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        try {
            $this->app->make(DocumentProcessor::class)->process($document);
            $this->fail('Expected FastApiConnectionException to be thrown.');
        } catch (FastApiConnectionException) {
            $document->refresh();
            $this->assertSame('processing', $document->status->value);
            $this->assertNull($document->error_message);
        }
    }

    public function test_permanent_failure_marks_document_failed_safely(): void
    {
        $user = User::factory()->create();
        $document = $this->makeDocument($user);

        Http::fake([
            '*/api/v1/documents/extract' => Http::response([
                'detail' => 'Unsupported file type',
            ], 415),
        ]);

        $this->app->make(DocumentProcessor::class)->process($document);

        $document->refresh();

        $this->assertSame('failed', $document->status->value);
        $this->assertStringContainsString('415', $document->error_message);
        $this->assertStringNotContainsString('Exception', $document->error_message);
        $this->assertDatabaseCount('document_extractions', 0);
        $this->assertDatabaseCount('ai_analyses', 0);
    }

    public function test_failed_job_hook_marks_document_failed_after_retries(): void
    {
        $user = User::factory()->create();
        $document = $this->makeDocument($user);

        (new ProcessMedicalDocumentJob($document->id))->failed(new \RuntimeException('boom'));

        $document->refresh();

        $this->assertSame('failed', $document->status->value);
        $this->assertStringContainsString('multiple attempts', $document->error_message);
    }
}