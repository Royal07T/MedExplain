<?php

namespace App\Services\HealthQuery;

use App\Models\Medication;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Aggregates the most recent changes in a user's health record, newest first.
 *
 * Combines report/lab timeline events with medication changes and orders them
 * chronologically in PHP — the LLM never determines chronology. All events are
 * derived from the authenticated user's own data.
 */
final class RecentHealthChangesService
{
    public function __construct(private readonly HealthContextService $context) {}

    /**
     * @return list<array{type: string, occurred_at: string, title: string, description: string|null, document_id: int|null}>
     */
    public function changes(User $user, int $limit = 30): array
    {
        $events = $this->context->recentHealthEvents($user, $limit * 2)->values();

        $medicationEvents = $this->context
            ->medications($user)
            ->flatMap(function (Medication $medication): array {
                $events = [[
                    'type' => 'medication_added',
                    'occurred_at' => $medication->created_at,
                    'title' => 'Medication recorded',
                    'description' => $medication->name,
                    'document_id' => $medication->medical_document_id,
                ]];

                if ($medication->end_date !== null && $medication->end_date->lte(now()->startOfDay())) {
                    $events[] = [
                        'type' => 'medication_ended',
                        'occurred_at' => $medication->end_date->startOfDay(),
                        'title' => 'Medication ended',
                        'description' => $medication->name,
                        'document_id' => $medication->medical_document_id,
                    ];
                }

                return $events;
            });

        return $events
            ->merge($medicationEvents)
            ->sortByDesc('occurred_at')
            ->take($limit)
            ->map(fn (array $event): array => [
                'type' => $event['type'],
                'occurred_at' => Carbon::parse($event['occurred_at'])->toISOString(),
                'title' => $event['title'],
                'description' => $event['description'],
                'document_id' => $event['document_id'],
            ])
            ->values()
            ->all();
    }
}