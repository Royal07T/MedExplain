<?php

namespace App\DTOs;

final class AssistantDto
{
    /**
     * @param  list<string>  $sources
     */
    public function __construct(
        public readonly string $reply,
        public readonly string $disclaimer,
        public readonly array $sources,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['reply'] ?? '',
            $data['disclaimer'] ?? '',
            $data['sources'] ?? [],
        );
    }
}