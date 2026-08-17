<?php

namespace App\DTOs;

final class ExtractionDto
{
    public function __construct(
        public readonly string $documentType,
        public readonly string $extractionMethod,
        public readonly string $rawText,
        public readonly array $warnings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['document_type'] ?? 'unknown',
            $data['extraction_method'] ?? 'none',
            $data['raw_text'] ?? '',
            $data['warnings'] ?? [],
        );
    }
}