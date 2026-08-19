<?php

namespace App\Services;

use App\DTOs\AssistantDto;
use App\DTOs\LabResultDto;
use App\DTOs\MedicationDto;
use App\Models\LabResult;
use App\Models\Medication;
use App\Models\User;

/**
 * Guards and forwards assistant requests to the FastAPI assistant endpoint.
 *
 * Only the authenticated user's own labs and medications are ever sent as
 * context; ownership is enforced by the query scope, never by request input.
 */
final class AssistantService
{
    public function __construct(private readonly FastApiClient $client) {}

    public function reply(User $user, string $message): AssistantDto
    {
        return $this->client->assistantChat(
            $message,
            $this->labContext($user),
            $this->medicationContext($user),
        );
    }

    /**
     * @return list<LabResultDto>
     */
    private function labContext(User $user): array
    {
        return LabResult::query()
            ->where('user_id', $user->id)
            ->latest('collected_at')
            ->limit(50)
            ->get()
            ->map(function (LabResult $result): LabResultDto {
                return new LabResultDto(
                    $result->name,
                    $result->value,
                    $result->unit,
                    $result->reference_range,
                    $result->status->value,
                );
            })
            ->values()
            ->all();
    }

    /**
     * @return list<MedicationDto>
     */
    private function medicationContext(User $user): array
    {
        return app(MedicationService::class)
            ->recentForContext($user)
            ->map(function (Medication $medication): MedicationDto {
                return new MedicationDto(
                    $medication->name,
                    $medication->strength,
                    $medication->dosage_form,
                    $medication->dose,
                    $medication->frequency,
                    $medication->route,
                    $medication->prescriber,
                    $medication->indications,
                    $medication->start_date?->toDateString(),
                    $medication->end_date?->toDateString(),
                );
            })
            ->values()
            ->all();
    }
}