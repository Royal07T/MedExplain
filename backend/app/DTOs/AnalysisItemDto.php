<?php

namespace App\DTOs;

final class AnalysisItemDto
{
    public function __construct(
        public readonly string $testName,
        public readonly string $explanation,
        public readonly string $category,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['test_name'] ?? '',
            $data['explanation'] ?? '',
            $data['category'] ?? 'education',
        );
    }
}