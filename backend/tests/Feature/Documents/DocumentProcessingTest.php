<?php

namespace Tests\Feature\Documents;

use App\Models\MedicalDocument;
use App\Models\User;
use App\Services\DocumentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentProcessingTest extends TestCase
{
    use RefreshDatabase;

    private function fakeFastApi(): void
    {
        Http::fake([
            '*/api/v1/documents/extract' => Http::response([
                'document_type' => 'lab_report',
                'extraction_method' => 'pdf_text',
                'raw_text' => "Test Result Unit Reference Range Status\nGlucose 95 mg/dL 70-99 Normal\n",
                'lab_tests' => [],
                'warnings' => [],
            ], 200),
            '*/api/v1/documents/parse-lab-report' => Http::response([
                'document_type' => 'lab_report',
                'extraction_method' => 'pdf_text',
                'raw_text' => "Test Result Unit Reference Range Status\nGlucose 95 mg/dL 70-99 Normal\n",
                'lab_tests' => [
                    ['name' => 'Glucose', 'value' => '95', 'unit' => 'mg/dL', 'reference_range' => '70-99', 'status' => 'within_range'],
                    ['name' => 'Cholesterol', 'value' => '240', 'unit' => 'mg/dL', 'reference_range' => '< 200', 'status' => 'above_range'],
                ],
                'warnings' => [],
            ], 200),
            '*/api/v1/analysis/explain' => Http::response([
                'summary' => 'Educational summary for 2 test(s).',
                'disclaimer' => 'Not a diagnosis.',
                'concerns' => ['Cholesterol (240)'],
                'items' => [
                    ['test_name' => 'Glucose', 'explanation' => 'Within range.', 'category' => 'reference_comparison'],
                    ['test_name' => 'Cholesterol', 'explanation' => 'Above range; discuss.', 'category' => 'possible_context'],
                ],
            ], 200),
        ]);
    }

    public function test_processes_document_end_to_end(): void
    {
        Storage::fake('documents');
        $this->fakeFastApi();

        $user = User::factory()->create();
        $document = MedicalDocument::factory()->for($user)->create([
            'status' => 'uploaded',
            'document_type' => 'unknown',
        ]);
        Storage::disk('documents')->put($document->storage_path, 'pdf-bytes');

        $this->app->make(DocumentProcessor::class)->process($document);

        $document->refresh();

        $this->assertSame('processed', $document->status->value);
        $this->assertSame('lab_report', $document->document_type->value);
        $this->assertNotNull($document->processed_at);

        $this->assertDatabaseHas('document_extractions', [
            'medical_document_id' => $document->id,
            'extraction_method' => 'pdf_text',
        ]);

        $this->assertDatabaseHas('lab_results', [
            'name' => 'Glucose',
            'value' => '95',
            'unit' => 'mg/dL',
            'reference_range' => '70-99',
            'status' => 'within_range',
        ]);
        $this->assertDatabaseHas('lab_results', [
            'name' => 'Cholesterol',
            'value' => '240',
            'status' => 'above_range',
        ]);

        $this->assertDatabaseHas('ai_analyses', [
            'medical_document_id' => $document->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('analysis_items', [
            'test_name' => 'Glucose',
            'category' => 'reference_comparison',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'analysis_created']);
    }

    public function test_keeps_existing_type_when_extraction_is_unknown(): void
    {
        Storage::fake('documents');

        Http::fake([
            '*/api/v1/documents/extract' => Http::response([
                'document_type' => 'unknown',
                'extraction_method' => 'none',
                'raw_text' => '',
                'lab_tests' => [],
                'warnings' => ['No extractable text'],
            ], 200),
            '*/api/v1/documents/parse-lab-report' => Http::response([
                'document_type' => 'unknown',
                'extraction_method' => 'none',
                'raw_text' => '',
                'lab_tests' => [],
                'warnings' => [],
            ], 200),
            '*/api/v1/analysis/explain' => Http::response([
                'summary' => 'Overview.',
                'disclaimer' => 'Not a diagnosis.',
                'concerns' => [],
                'items' => [],
            ], 200),
        ]);

        $user = User::factory()->create();
        $document = MedicalDocument::factory()->for($user)->create([
            'status' => 'uploaded',
            'document_type' => 'unknown',
        ]);
        Storage::disk('documents')->put($document->storage_path, 'pdf-bytes');

        $this->app->make(DocumentProcessor::class)->process($document);

        $document->refresh();

        $this->assertSame('processed', $document->status->value);
        $this->assertSame('unknown', $document->document_type->value);
    }

    public function test_stores_medications_when_extracted(): void
    {
        Storage::fake('documents');
        $this->fakeFastApi();

        Http::fake([
            '*/api/v1/medications/extract' => Http::response([
                'medications' => [
                    [
                        'name' => 'Metformin',
                        'strength' => '500 mg',
                        'dosage_form' => 'tablet',
                        'dose' => '500',
                        'frequency' => 'twice daily',
                        'route' => 'oral',
                        'prescriber' => null,
                        'indications' => null,
                        'start_date' => null,
                        'end_date' => null,
                    ],
                ],
                'warnings' => [],
            ], 200),
        ]);

        $user = User::factory()->create();
        $document = MedicalDocument::factory()->for($user)->create([
            'status' => 'uploaded',
            'document_type' => 'unknown',
        ]);
        Storage::disk('documents')->put($document->storage_path, 'pdf-bytes');

        $this->app->make(DocumentProcessor::class)->process($document);

        $document->refresh();

        $this->assertSame('processed', $document->status->value);
        $this->assertDatabaseHas('medications', [
            'user_id' => $user->id,
            'medical_document_id' => $document->id,
            'name' => 'Metformin',
            'strength' => '500 mg',
            'frequency' => 'twice daily',
        ]);
    }

    public function test_medication_failure_does_not_fail_document(): void
    {
        Storage::fake('documents');
        $this->fakeFastApi();

        Http::fake([
            '*/api/v1/medications/extract' => Http::response(['detail' => 'boom'], 500),
        ]);

        $user = User::factory()->create();
        $document = MedicalDocument::factory()->for($user)->create([
            'status' => 'uploaded',
            'document_type' => 'unknown',
        ]);
        Storage::disk('documents')->put($document->storage_path, 'pdf-bytes');

        $this->app->make(DocumentProcessor::class)->process($document);

        $document->refresh();

        $this->assertSame('processed', $document->status->value);
        $this->assertDatabaseMissing('medications', [
            'medical_document_id' => $document->id,
        ]);
    }
}