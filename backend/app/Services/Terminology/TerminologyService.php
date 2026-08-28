<?php

namespace App\Services\Terminology;

/**
 * Deterministic, offline terminology lookup for ICD-10-CM and SNOMED CT.
 *
 * Provides search, exact-code lookup, and validation against the bundled
 * reference vocabulary. Useful for improving the quality of free-form
 * `icd10_code` / `icd10_description` fields across the app without
 * depending on an external terminology server.
 */
final class TerminologyService
{
    /**
     * @return array<string, array<int, array{code: string, display: string}>>
     */
    private function vocabulary(): array
    {
        return [
            'icd10' => TerminologyCodes::icd10(),
            'snomed' => TerminologyCodes::snomed(),
        ];
    }

    /**
     * @return string[]
     */
    public function supportedSystems(): array
    {
        return ['icd10', 'snomed'];
    }

    /**
     * Search a terminology by display text or code.
     *
     * @return array{system: string, results: array<int, array{code: string, display: string, system: string}>}
     */
    public function search(string $system, string $query): array
    {
        $system = strtolower($system);
        $query = trim($query);

        $results = [];
        foreach ($this->entries($system) as $entry) {
            $matches = $query === ''
                || stripos($entry['code'], $query) !== false
                || stripos($entry['display'], $query) !== false;
            if ($matches) {
                $results[] = ['code' => $entry['code'], 'display' => $entry['display'], 'system' => $system];
            }
        }

        return ['system' => $system, 'results' => $results];
    }

    /**
     * Look up a single code within a system.
     *
     * @return array{found: bool, code?: string, display?: string, system: string}
     */
    public function lookup(string $system, string $code): array
    {
        $system = strtolower($system);
        $code = trim($code);

        foreach ($this->entries($system) as $entry) {
            if (strcasecmp($entry['code'], $code) === 0) {
                return ['found' => true, 'code' => $entry['code'], 'display' => $entry['display'], 'system' => $system];
            }
        }

        return ['found' => false, 'system' => $system];
    }

    /**
     * Validate a code (optionally against an expected display) within a system.
     *
     * @return array{valid: bool, system: string, code: string, canonical_display?: string, code_found: bool, display_consistent: bool}
     */
    public function validate(string $system, string $code, ?string $display = null): array
    {
        $lookup = $this->lookup($system, $code);
        $valid = $lookup['found'];

        $displayConsistent = true;
        if ($valid && $display !== null && trim($display) !== '') {
            $displayConsistent = $this->displayMatches(trim($display), $lookup['display'] ?? '');
        }

        return [
            'valid' => $valid && $displayConsistent,
            'system' => $system,
            'code' => trim($code),
            'canonical_display' => $lookup['display'] ?? null,
            'code_found' => $valid,
            'display_consistent' => $displayConsistent,
        ];
    }

    /**
     * @return array<int, array{code: string, display: string}>
     */
    private function entries(string $system): array
    {
        return $this->vocabulary()[$system] ?? [];
    }

    private function displayMatches(string $provided, string $canonical): bool
    {
        $normalize = fn (string $s): string => preg_replace('/\s+/', ' ', strtolower(trim($s))) ?? strtolower(trim($s));

        $a = $normalize($provided);
        $b = $normalize($canonical);

        return $a === $b || str_contains($a, $b) || str_contains($b, $a);
    }
}
