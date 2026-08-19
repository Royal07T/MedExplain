<?php

namespace App\DTOs;

final class MedicationDto
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $strength,
        public readonly ?string $dosageForm,
        public readonly ?string $dose,
        public readonly ?string $frequency,
        public readonly ?string $route,
        public readonly ?string $prescriber,
        public readonly ?string $indications,
        public readonly ?string $startDate,
        public readonly ?string $endDate,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'] ?? '',
            $data['strength'] ?? null,
            $data['dosage_form'] ?? null,
            $data['dose'] ?? null,
            $data['frequency'] ?? null,
            $data['route'] ?? null,
            $data['prescriber'] ?? null,
            $data['indications'] ?? null,
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'strength' => $this->strength,
            'dosage_form' => $this->dosageForm,
            'dose' => $this->dose,
            'frequency' => $this->frequency,
            'route' => $this->route,
            'prescriber' => $this->prescriber,
            'indications' => $this->indications,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ];
    }
}