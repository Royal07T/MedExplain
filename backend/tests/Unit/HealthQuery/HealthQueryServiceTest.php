<?php

namespace Tests\Unit\HealthQuery;

use App\Enums\DocumentStatus;
use App\Models\DocumentExtraction;
use App\Models\LabResult;
use App\Models\MedicalDocument;
use App\Models\Medication;
use App\Models\User;
use App\Services\FastApiClient;
use App\Services\HealthQuery\HealthContextService;
use App\Services\HealthQuery\HealthQueryService;
use App\Services\HealthQuery\IntentRegistry;
use App\Services\HealthQuery\LabTrendEngine;
use App\Services\HealthQuery\MedicationAtDateResolver;
use App\Services\HealthQuery\RecentHealthChangesService;
use App\Services\HealthQuery\ReportComparisonService;
use App\Services\HealthService;
use App\Services\MedicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): HealthQueryService
    {
        $context = new HealthContextService(
            app(HealthService::class),
            app(MedicationService::class),
        );

        return new HealthQueryService(
            $context,
            new ReportComparisonService(),
            new LabTrendEngine(),
            new MedicationAtDateResolver(),
            new RecentHealthChangesService($context),
            new IntentRegistry(),
            app(FastApiClient::class),
        );
    }

    private function fakeHealthQuery(): void
    {
        Http::fake([
            '*/api/v1/health/query' => Http::response([
                'summary' => 'A stub summary.',
                'facts' => ['Stub fact'],
                'changes' => [],
                'context' => [],
                'educational_explanation' => [],
                'questions_for_professional' => [],
                'sources' => [],
                'disclaimer' => 'Educational only.',
                'data_used' => [],
            ]),
        ]);
    }

    private function processedReport(User $user, int $daysAgo): MedicalDocument
    {
        return MedicalDocument::factory()->for($user)->create([
            'status' => DocumentStatus::Processed,
            'processed_at' => now()->subDays($daysAgo),
            'created_at' => now()->subDays($daysAgo),
        ]);
    }

    private function lab(
        User $user,
        MedicalDocument $document,
        string $name,
        string $value,
        string $status,
        int $daysAgo,
    ): LabResult {
        $extraction = DocumentExtraction::firstOrCreate([
            'medical_document_id' => $document->id,
        ], [
            'extraction_method' => 'pdf_text',
            'raw_text' => '',
        ]);

        return LabResult::create([
            'document_extraction_id' => $extraction->id,
            'user_id' => $user->id,
            'name' => $name,
            'normalized_name' => mb_strtolower($name),
            'value' => $value,
            'unit' => 'mg/dL',
            'status' => $status,
            'collected_at' => now()->subDays($daysAgo),
            'sort_order' => 0,
        ]);
    }

    public function test_report_comparison_sends_intent_and_structured_comparison(): void
    {
        $this->fakeHealthQuery();

        $user = User::factory()->create();
        $older = $this->processedReport($user, 30);
        $newer = $this->processedReport($user, 1);
        $this->lab($user, $older, 'Glucose', '90', 'within_range', 30);
        $this->lab($user, $newer, 'Glucose', '110', 'above_range', 1);

        $result = $this->service()->answer($user, 'What changed between my last two reports?');

        $this->assertSame('REPORT_COMPARISON', $result->intent);
        $this->assertNotEmpty($result->queryId);
        $this->assertSame('A stub summary.', $result->answer->summary);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            $this->assertSame('REPORT_COMPARISON', $body['intent']);
            $this->assertSame('changed', $body['comparison']['changes'][0]['change_type']);
            $this->assertSame(20.0, $body['comparison']['changes'][0]['change']);
            $this->assertCount(2, $body['data_used']);
            $this->assertSame('report', $body['data_used'][0]['type']);

            return true;
        });
    }

    public function test_lab_trend_detects_test_and_sends_deterministic_trend(): void
    {
        $this->fakeHealthQuery();

        $user = User::factory()->create();
        $report = $this->processedReport($user, 30);
        $this->lab($user, $report, 'Glucose', '90', 'within_range', 30);
        $this->lab($user, $report, 'Glucose', '110', 'above_range', 1);
        $this->lab($user, $report, 'Hemoglobin', '14', 'within_range', 1);

        $this->service()->answer($user, 'Show me how my glucose has changed over time.');

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            $this->assertSame('LAB_TREND', $body['intent']);
            $this->assertSame('glucose', $body['detected_test']);
            $this->assertSame(2, $body['trend']['observation_count']);
            $this->assertSame(20.0, $body['trend']['summary']['net_change']);
            $this->assertSame('increased', $body['trend']['summary']['direction']);

            return true;
        });
    }

    public function test_medication_context_resolves_against_latest_result_date(): void
    {
        $this->fakeHealthQuery();

        $user = User::factory()->create();
        $report = $this->processedReport($user, 5);
        $this->lab($user, $report, 'Glucose', '95', 'within_range', 5);
        Medication::create([
            'user_id' => $user->id,
            'name' => 'Metformin',
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => null,
        ]);
        Medication::create([
            'user_id' => $user->id,
            'name' => 'OldDrug',
            'start_date' => now()->subDays(90)->toDateString(),
            'end_date' => now()->subDays(40)->toDateString(),
        ]);

        $this->service()->answer($user, 'Which medications were active when this result was recorded?');

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            $this->assertSame('MEDICATION_CONTEXT', $body['intent']);
            $this->assertNotNull($body['target_lab_result']);
            $active = collect($body['medications_at_date'])
                ->first(fn (array $m): bool => $m['medication'] === 'Metformin');
            $ended = collect($body['medications_at_date'])
                ->first(fn (array $m): bool => $m['medication'] === 'OldDrug');

            $this->assertTrue($active['active']);
            $this->assertFalse($ended['active']);
            $this->assertSame('active_at_result_date', $active['status']);
            $this->assertSame('ended_before_result_date', $ended['status']);

            return true;
        });
    }

    public function test_general_question_sends_no_structured_sections(): void
    {
        $this->fakeHealthQuery();

        $user = User::factory()->create();

        $this->service()->answer($user, 'What is HbA1c?');

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            $this->assertSame('GENERAL_HEALTH_QUESTION', $body['intent']);
            $this->assertArrayNotHasKey('comparison', $body);
            $this->assertArrayNotHasKey('trend', $body);
            $this->assertArrayNotHasKey('medications_at_date', $body);
            $this->assertArrayHasKey('patient_context', $body);
            $this->assertArrayHasKey('question', $body);

            return true;
        });
    }

    public function test_payload_only_contains_the_authenticated_users_data(): void
    {
        $this->fakeHealthQuery();

        $owner = User::factory()->create();
        $ownerReport = $this->processedReport($owner, 1);
        $this->lab($owner, $ownerReport, 'Glucose', '110', 'above_range', 1);

        $other = User::factory()->create();
        $this->service()->answer($other, 'What changed between my last two reports?');

        Http::assertSent(function (Request $request) use ($ownerReport): bool {
            $body = $request->data();

            $this->assertSame([], $body['data_used']);
            $this->assertFalse($body['previous_report_available']);
            $this->assertNull($body['comparison']);

            return true;
        });
    }

    public function test_wraps_response_into_query_id_intent_and_answer(): void
    {
        $this->fakeHealthQuery();

        $user = User::factory()->create();

        $result = $this->service()->answer($user, 'What is HbA1c?');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $result->queryId,
        );
        $this->assertSame('GENERAL_HEALTH_QUESTION', $result->intent);
        $this->assertSame(['Stub fact'], $result->answer->facts);
        $this->assertSame('Educational only.', $result->answer->disclaimer);
    }
}