<?php

namespace App\Services\HealthQuery;

use App\Enums\DocumentStatus;
use App\Models\LabResult;
use App\Models\MedicalDocument;
use App\Models\User;
use App\Services\HealthService;
use App\Services\MedicationService;
use Illuminate\Support\Collection;

/**
 * Phased, ownership-scoped retrieval of a user's health data for the Health
 * Query Orchestrator.
 *
 * Every method enforces `user_id` at the query level — ownership is never
 * inferred from request input. Retrieval is lazy: only the pieces a specific
 * query needs are loaded; the user's full health history is never fetched by
 * default.
 */
final class HealthContextService
{
    public function __construct(
        private readonly HealthService $healthService,
        private readonly MedicationService $medicationService,
    ) {}

    /**
     * The user's most recently processed report, or null when none exists.
     */
    public function latestReport(User $user): ?MedicalDocument
    {
        return $this->processedReports($user)->first();
    }

    /**
     * The user's most recently processed report other than the given one.
     */
    public function previousReport(User $user, int $excludingId): ?MedicalDocument
    {
        return $this->processedReports($user)
            ->first(fn (MedicalDocument $document): bool => $document->getKey() !== $excludingId);
    }

    /**
     * The laboratory observations parsed from a specific report.
     *
     * Ownership is re-checked here so an arbitrary document cannot be passed in.
     *
     * @return Collection<int, LabResult>
     */
    public function reportObservations(User $user, MedicalDocument $document): Collection
    {
        if ($document->user_id !== $user->id) {
            return collect();
        }

        return LabResult::query()
            ->where('document_extraction_id', $document->extraction?->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * The user's lab results, newest sample first.
     *
     * When a test name is given, only results for that (normalized) test are
     * returned. This is the deterministic source for trend and lab-history
     * queries.
     *
     * @return Collection<int, LabResult>
     */
    public function labHistory(User $user, ?string $name = null, int $limit = 100): Collection
    {
        $query = LabResult::query()
            ->where('user_id', $user->id)
            ->with(['documentExtraction.medicalDocument'])
            ->orderByDesc('collected_at')
            ->orderByDesc('id');

        if ($name !== null) {
            $query->where('normalized_name', $this->normalizeName($name));
        }

        return $query->limit($limit)->get();
    }

    /**
     * All medications recorded for the user, newest first.
     *
     * @return Collection<int, \App\Models\Medication>
     */
    public function medications(User $user): Collection
    {
        return $this->medicationService->forUser($user);
    }

    /**
     * Recent health timeline events, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentHealthEvents(User $user, int $limit = 20): Collection
    {
        return $this->healthService->timeline($user)->take($limit)->values();
    }

    /**
     * Minimal, non-identifying patient context for the LLM prompt.
     *
     * Only age and sex are included; names, emails, and identifiers are never
     * sent to the AI service.
     *
     * @return array{age: int|null, sex: string|null}
     */
    public function patientContext(User $user): array
    {
        $profile = $user->profile;

        return [
            'age' => $profile?->date_of_birth?->age,
            'sex' => $profile?->gender?->value,
        ];
    }

    /**
     * The user's processed reports, newest first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, MedicalDocument>
     */
    private function processedReports(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return MedicalDocument::query()
            ->where('user_id', $user->id)
            ->where('status', DocumentStatus::Processed)
            ->orderByDesc('processed_at')
            ->orderByDesc('id')
            ->get();
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($name)) ?? '');
    }
}