<?php

namespace App\Services\HealthQuery;

use App\Enums\HealthQueryIntent;

/**
 * A single intent's routing definition: the deterministic patterns used to
 * detect it from a question, and whether trusted medical knowledge (RAG)
 * should be retrieved before the LLM explains.
 */
final class IntentDefinition
{
    /**
     * @param  list<string>  $patterns  Case-insensitive regex patterns
     */
    public function __construct(
        public readonly HealthQueryIntent $intent,
        public readonly array $patterns,
        public readonly bool $requiresRag,
    ) {}

    public function matches(string $question): bool
    {
        $normalized = mb_strtolower(trim($question));

        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }
}