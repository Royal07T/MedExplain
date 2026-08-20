<?php

namespace App\Services\HealthQuery;

use App\Enums\HealthQueryIntent;

/**
 * Registry of supported health-query intents and their detection patterns.
 *
 * Intent detection is deterministic and testable — the LLM never performs
 * routing. Adding a new intent is a matter of registering a new definition here
 * (plus a handler in the orchestrator), not editing a growing conditional.
 */
final class IntentRegistry
{
    /** @var array<string, IntentDefinition> */
    private array $definitions;

    /**
     * @param  array<string, IntentDefinition>  $definitions
     */
    public function __construct(array $definitions = [])
    {
        $this->definitions = $definitions === [] ? $this->defaults() : $definitions;
    }

    public function detect(string $question): HealthQueryIntent
    {
        foreach ($this->definitions as $definition) {
            if ($definition->matches($question)) {
                return $definition->intent;
            }
        }

        return HealthQueryIntent::GeneralHealthQuestion;
    }

    public function definition(HealthQueryIntent $intent): IntentDefinition
    {
        return $this->definitions[$intent->value]
            ?? $this->definitions[HealthQueryIntent::GeneralHealthQuestion->value];
    }

    /**
     * @return array<string, IntentDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return array<string, IntentDefinition>
     */
    private function defaults(): array
    {
        return [
            HealthQueryIntent::ReportComparison->value => new IntentDefinition(
                intent: HealthQueryIntent::ReportComparison,
                patterns: [
                    '/what changed between/',
                    '/what changed in my last/',
                    '/changed between my last two reports/',
                    '/compare my last two reports/',
                    '/difference between my last two reports/',
                    '/compare my/i',
                    '/compare reports/',
                ],
                requiresRag: true,
            ),
            HealthQueryIntent::CurrentVsPrevious->value => new IntentDefinition(
                intent: HealthQueryIntent::CurrentVsPrevious,
                patterns: [
                    '/current results in the context/',
                    '/in context of my previous/',
                    '/current vs(\.)? previous/',
                    '/current versus previous/',
                    '/compared to my previous/',
                    '/my previous results/',
                    '/how do my current/',
                ],
                requiresRag: true,
            ),
            HealthQueryIntent::LabTrend->value => new IntentDefinition(
                intent: HealthQueryIntent::LabTrend,
                patterns: [
                    '/show me how/',
                    '/how (has|is|did|does) my .* changed/',
                    '/how my .* changed/',
                    '/changed over time/',
                    '/over time/',
                    '/trend/',
                    '/trajectory/',
                ],
                requiresRag: false,
            ),
            HealthQueryIntent::MedicationContext->value => new IntentDefinition(
                intent: HealthQueryIntent::MedicationContext,
                patterns: [
                    '/which medications were active/',
                    '/medications were active/',
                    '/medication.*active when/',
                    '/active during/',
                    '/active at the time/',
                    '/what was i taking/',
                    '/medications when this result/',
                    '/medications at the time/',
                ],
                requiresRag: false,
            ),
            HealthQueryIntent::RecentHealthChanges->value => new IntentDefinition(
                intent: HealthQueryIntent::RecentHealthChanges,
                patterns: [
                    '/most recent changes/',
                    '/recent changes/',
                    '/latest changes/',
                    '/changed recently/',
                    '/recently changed/',
                    '/what.*changed in my health record/',
                    '/what.*new in my health record/',
                    '/what is new.*recently/',
                    '/whats new/',
                ],
                requiresRag: false,
            ),
            HealthQueryIntent::HealthTimeline->value => new IntentDefinition(
                intent: HealthQueryIntent::HealthTimeline,
                patterns: [
                    '/health timeline/',
                    '/health events/',
                    '/what events/',
                    '/my timeline/',
                ],
                requiresRag: false,
            ),
            HealthQueryIntent::LabHistory->value => new IntentDefinition(
                intent: HealthQueryIntent::LabHistory,
                patterns: [
                    '/history of my labs/',
                    '/my lab results/',
                    '/all my lab/',
                    '/history of.*lab/',
                    '/list.*lab/',
                    '/my glucose results/',
                ],
                requiresRag: false,
            ),
            HealthQueryIntent::MedicationHistory->value => new IntentDefinition(
                intent: HealthQueryIntent::MedicationHistory,
                patterns: [
                    '/medication history/',
                    '/history of my medications/',
                    '/what medications/',
                    '/my medications/',
                    '/current medications/',
                ],
                requiresRag: false,
            ),
            HealthQueryIntent::GeneralHealthQuestion->value => new IntentDefinition(
                intent: HealthQueryIntent::GeneralHealthQuestion,
                patterns: [],
                requiresRag: true,
            ),
        ];
    }
}