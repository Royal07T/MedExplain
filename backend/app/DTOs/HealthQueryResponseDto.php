<?php

namespace App\DTOs;

/**
 * Structured AI response produced by the FastAPI health-intelligence layer.
 *
 * Every field mirrors the schema returned by `POST /api/v1/health/query` so the
 * frontend can render the answer sections independently. Text fields are short
 * patient-friendly sentences; nothing here is a diagnosis.
 */
final class HealthQueryResponseDto
{
    /**
     * @param  list<string>  $facts
     * @param  list<string>  $changes
     * @param  list<array{text: string, category: string}>  $context
     * @param  list<string>  $educationalExplanation
     * @param  list<string>  $questionsForProfessional
     * @param  list<string>  $sources
     * @param  list<array{type: string, label: string, reference: string|null}>  $dataUsed
     */
    public function __construct(
        public readonly string $summary,
        public readonly array $facts,
        public readonly array $changes,
        public readonly array $context,
        public readonly array $educationalExplanation,
        public readonly array $questionsForProfessional,
        public readonly array $sources,
        public readonly string $disclaimer,
        public readonly array $dataUsed,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['summary'] ?? '',
            $data['facts'] ?? [],
            $data['changes'] ?? [],
            $data['context'] ?? [],
            $data['educational_explanation'] ?? [],
            $data['questions_for_professional'] ?? [],
            $data['sources'] ?? [],
            $data['disclaimer'] ?? '',
            $data['data_used'] ?? [],
        );
    }
}