<?php

namespace App\DTOs;

final class LabResultDto
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
        public readonly ?string $unit,
        public readonly ?string $referenceRange,
        public readonly string $status,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'] ?? '',
            $data['value'] ?? '',
            $data['unit'] ?? null,
            $data['reference_range'] ?? null,
            $data['status'] ?? 'unknown',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'unit' => $this->unit,
            'reference_range' => $this->referenceRange,
            'status' => $this->status,
        ];
    }
}