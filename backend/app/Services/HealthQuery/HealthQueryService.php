<?php

namespace App\Services\HealthQuery;

use App\DTOs\HealthQueryResponseDto;
use App\DTOs\HealthQueryResultDto;
use App\Enums\HealthQueryIntent;
use App\Models\LabResult;
use App\Models\MedicalDocument;
use App\Models\Medication;
use App\Models\User;
use App\Services\FastApiClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The Health Query Orchestrator.
 *
 * Turns a natural-language question into a structured execution plan:
 *
 *   detect intent (deterministic registry)
 *     → retrieve the ownership-scoped context the intent needs (lazy)
 *     → run the deterministic operations (comparison / trend / medication /
 *       recent changes)
 *     → send the structured context to the FastAPI health-intelligence layer,
 *       which adds trusted knowledge and calls the LLM gateway.
 *
 * The LLM never queries the database and never performs deterministic
 * calculations — both happen here, in backend code. Handlers are registered in
 * a map keyed by intent, so a new intent is one registry entry plus one handler
 * instead of a growing conditional.
 */
final class HealthQueryService
{
    /** @var array<string, callable(User, string): array> */
    private array $handlers;

    public function __construct(
        private readonly HealthContextService $context,
        private readonly ReportComparisonService $comparison,
        private readonly LabTrendEngine $trendEngine,
        private readonly MedicationAtDateResolver $medicationResolver,
        private readonly RecentHealthChangesService $recentChanges,
        private readonly IntentRegistry $registry,
        private readonly FastApiClient $client,
    ) {
        $this->handlers = [
            HealthQueryIntent::ReportComparison->value => fn (User $user): array => $this->reportComparisonContext($user),
            HealthQueryIntent::CurrentVsPrevious->value => fn (User $user): array => $this->reportComparisonContext($user),
            HealthQueryIntent::LabTrend->value => fn (User $user, string $question): array => $this->labTrendContext($user, $question),
            HealthQueryIntent::MedicationContext->value => fn (User $user): array => $this->medicationContext($user),
            HealthQueryIntent::RecentHealthChanges->value => fn (User $user): array => $this->recentChangesContext($user),
            HealthQueryIntent::HealthTimeline->value => fn (User $user): array => $this->timelineContext($user),
            HealthQueryIntent::LabHistory->value => fn (User $user): array => $this->labHistoryContext($user),
            HealthQueryIntent::MedicationHistory->value => fn (User $user): array => $this->medicationHistoryContext($user),
            HealthQueryIntent::GeneralHealthQuestion->value => fn (): array => [],
        ];
    }

    public function answer(User $user, string $question): HealthQueryResultDto
    {
        $intent = $this->registry->detect($question);
        $definition = $this->registry->definition($intent);
        $context = $this->buildContext($user, $intent, $question);

        $response = $this->client->healthQuery(
            $queryId = (string) Str::uuid(),
            $question,
            $intent->value,
            $context,
        );

        return new HealthQueryResultDto(
            $queryId,
            $intent->value,
            $response,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(User $user, HealthQueryIntent $intent, string $question): array
    {
        $handler = $this->handlers[$intent->value] ?? fn (): array => [];

        $section = $handler($user, $question);

        $section['patient_context'] = $this->context->patientContext($user);
        $section['question'] = $question;

        return $section;
    }

    /**
     * @return array<string, mixed>
     */
    private function reportComparisonContext(User $user): array
    {
        $latest = $this->context->latestReport($user);
        if ($latest === null) {
            return [
                'comparison' => null,
                'previous_report_available' => false,
                'data_used' => [],
            ];
        }

        $previous = $this->context->previousReport($user, (int) $latest->getKey());
        $currentObservations = $this->context->reportObservations($user, $latest);
        $previousObservations = $previous
            ? $this->context->reportObservations($user, $previous)
            : collect();

        $comparison = $this->comparison->compare(
            $previousObservations,
            $currentObservations,
            $previous?->processed_at ?? $previous?->created_at,
            $latest->processed_at ?? $latest->created_at,
        );

        $dataUsed = [$this->reportUsed($latest)];
        if ($previous !== null) {
            $dataUsed[] = $this->reportUsed($previous);
        }

        return [
            'comparison' => $comparison,
            'previous_report_available' => $previous !== null,
            'data_used' => $dataUsed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function labTrendContext(User $user, string $question): array
    {
        $name = $this->detectTestName($user, $question);
        $observations = $this->context->labHistory($user, $name, 200);

        $trend = $this->trendEngine->trend($observations, $name);

        return [
            'trend' => $trend,
            'detected_test' => $name,
            'data_used' => $this->labsUsed($observations),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function medicationContext(User $user): array
    {
        $latestLab = $this->context->labHistory($user, null, 1)->first();
        $latestReport = $this->context->latestReport($user);

        $targetDate = $latestLab?->collected_at
            ?? $latestReport?->processed_at
            ?? $latestReport?->created_at
            ?? now();

        $medications = $this->context->medications($user);
        $resolved = $this->medicationResolver->resolve($medications, $targetDate);

        $dataUsed = [];
        if ($latestLab !== null) {
            $dataUsed[] = $this->labUsed($latestLab);
        }
        foreach ($medications as $medication) {
            $dataUsed[] = $this->medicationUsed($medication);
        }

        return [
            'target_lab_result' => $latestLab ? [
                'name' => $latestLab->name,
                'value' => $latestLab->value,
                'unit' => $latestLab->unit,
                'collected_at' => $latestLab->collected_at?->toISOString(),
            ] : null,
            'medications_at_date' => $resolved,
            'data_used' => $dataUsed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recentChangesContext(User $user): array
    {
        return [
            'recent_changes' => $this->recentChanges->changes($user, 30),
            'data_used' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function timelineContext(User $user): array
    {
        return [
            'timeline' => $this->context->recentHealthEvents($user, 50)->values()->all(),
            'data_used' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function labHistoryContext(User $user): array
    {
        $history = $this->context->labHistory($user, null, 100);

        return [
            'lab_history' => $history->map(fn (LabResult $result): array => [
                'name' => $result->name,
                'value' => $result->value,
                'unit' => $result->unit,
                'status' => $result->status?->value,
                'collected_at' => $result->collected_at?->toISOString(),
                'document_id' => $result->documentExtraction?->medicalDocument?->id,
            ])->values()->all(),
            'data_used' => $this->labsUsed($history),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function medicationHistoryContext(User $user): array
    {
        $medications = $this->context->medications($user);

        return [
            'medication_history' => $medications->map(fn (Medication $medication): array => [
                'name' => $medication->name,
                'dose' => $medication->dose,
                'frequency' => $medication->frequency,
                'route' => $medication->route,
                'start_date' => $medication->start_date?->toDateString(),
                'end_date' => $medication->end_date?->toDateString(),
            ])->values()->all(),
            'data_used' => $medications->map(fn (Medication $medication): array => $this->medicationUsed($medication))->values()->all(),
        ];
    }

    private function detectTestName(User $user, string $question): ?string
    {
        $normalizedQuestion = mb_strtolower(preg_replace('/\s+/', ' ', trim($question)) ?? '');

        $names = $this->context
            ->labHistory($user, null, 500)
            ->pluck('normalized_name')
            ->filter()
            ->unique()
            ->values();

        $best = null;
        $bestLength = 0;

        foreach ($names as $name) {
            $length = mb_strlen((string) $name);
            if ($length > $bestLength && str_contains($normalizedQuestion, (string) $name)) {
                $best = (string) $name;
                $bestLength = $length;
            }
        }

        return $best;
    }

    /**
     * @return array{type: string, label: string, reference: string}
     */
    private function reportUsed(MedicalDocument $document): array
    {
        $date = $document->processed_at?->toDateString() ?? $document->created_at?->toDateString();

        return [
            'type' => 'report',
            'label' => $date ? "Report from {$date}" : $document->original_filename,
            'reference' => (string) $document->getKey(),
        ];
    }

    /**
     * @return array{type: string, label: string, reference: string}
     */
    private function labUsed(LabResult $result): array
    {
        $value = $result->value.($result->unit ? " {$result->unit}" : '');

        return [
            'type' => 'lab',
            'label' => "{$result->name} ({$value})",
            'reference' => (string) $result->getKey(),
        ];
    }

    /**
     * @return array{type: string, label: string, reference: string}
     */
    private function medicationUsed(Medication $medication): array
    {
        return [
            'type' => 'medication',
            'label' => $medication->name,
            'reference' => (string) $medication->getKey(),
        ];
    }

    /**
     * @param  Collection<int, LabResult>  $results
     * @return list<array{type: string, label: string, reference: string}>
     */
    private function labsUsed(Collection $results): array
    {
        return $results->map(fn (LabResult $result): array => $this->labUsed($result))->values()->all();
    }
}