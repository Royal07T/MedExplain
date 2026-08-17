<?php

namespace App\DTOs;

final class AiAnalysisDto
{
    /**
     * @param  list<string>  $concerns
     * @param  list<AnalysisItemDto>  $items
     */
    public function __construct(
        public readonly string $summary,
        public readonly string $disclaimer,
        public readonly array $concerns,
        public readonly array $items,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['summary'] ?? '',
            $data['disclaimer'] ?? '',
            $data['concerns'] ?? [],
            array_map(
                static fn (array $item): AnalysisItemDto => AnalysisItemDto::fromArray($item),
                $data['items'] ?? [],
            ),
        );
    }
}