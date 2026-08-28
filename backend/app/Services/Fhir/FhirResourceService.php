<?php

namespace App\Services\Fhir;

use App\Models\Organization;
use App\Models\Patient;

/**
 * Minimal FHIR R4 resource serialization for the interoperability endpoints.
 *
 * Produces spec-shaped FHIR resources (raw, not wrapped in the app's
 * `success`/`data` envelope). Only maps the subset of fields the app models.
 */
final class FhirResourceService
{
    /**
     * Build an R4 Patient resource from a Patient demographics record.
     *
     * @return array<string, mixed>
     */
    public function patient(Patient $patient): array
    {
        $gender = $this->genderCode($patient->gender);

        $resource = [
            'resourceType' => 'Patient',
            'id' => (string) $patient->id,
            'meta' => [
                'lastUpdated' => $patient->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                'profile' => ['http://hl7.org/fhir/StructureDefinition/Patient'],
            ],
            'identifier' => array_values(array_filter([
                $patient->mrn ? [
                    'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                    'type' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'MR', 'display' => 'Medical record number']]],
                    'value' => $patient->mrn,
                ] : null,
                $patient->user_id ? [
                    'system' => 'http://medexplain.test/fhir/patient',
                    'value' => (string) $patient->user_id,
                ] : null,
            ])),
            'active' => true,
            'name' => [[
                'use' => 'official',
                'family' => $patient->last_name ?? 'Unnamed',
                'given' => array_values(array_filter([$patient->first_name])),
            ]],
            'gender' => $gender,
        ];

        if ($patient->date_of_birth) {
            $resource['birthDate'] = $patient->date_of_birth->toDateString();
        }

        $telecom = $this->patientTelecom($patient->phone, $patient->email);
        if ($telecom !== []) {
            $resource['telecom'] = $telecom;
        }

        if ($patient->address) {
            $resource['address'] = [[
                'line' => array_values(array_filter([$patient->address])),
            ]];
        }

        if ($patient->organization_id) {
            $resource['managingOrganization'] = [
                'reference' => "Organization/{$patient->organization_id}",
            ];
        }

        return $resource;
    }

    /**
     * Build an R4 Organization resource.
     *
     * @return array<string, mixed>
     */
    public function organization(Organization $organization): array
    {
        $resource = [
            'resourceType' => 'Organization',
            'id' => (string) $organization->id,
            'meta' => [
                'lastUpdated' => $organization->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                'profile' => ['http://hl7.org/fhir/StructureDefinition/Organization'],
            ],
            'active' => (bool) $organization->is_active,
            'name' => $organization->name ?? 'Unnamed Organization',
        ];

        if ($organization->email || $organization->phone) {
            $resource['telecom'] = array_values(array_filter([
                $organization->phone ? ['system' => 'phone', 'value' => $organization->phone] : null,
                $organization->email ? ['system' => 'email', 'value' => $organization->email] : null,
            ]));
        }

        if ($organization->address) {
            $resource['address'] = [[
                'line' => array_values(array_filter([$organization->address])),
            ]];
        }

        return $resource;
    }

    /**
     * Build a minimal R4 CapabilityStatement.
     *
     * @return array<string, mixed>
     */
    public function capabilityStatement(string $baseUrl): array
    {
        return [
            'resourceType' => 'CapabilityStatement',
            'status' => 'active',
            'date' => now()->toDateString(),
            'kind' => 'instance',
            'fhirVersion' => '4.0.1',
            'format' => ['application/fhir+json'],
            'rest' => [[
                'mode' => 'server',
                'resource' => [
                    ['type' => 'Patient', 'interaction' => [['code' => 'read']]],
                    ['type' => 'Organization', 'interaction' => [['code' => 'read']]],
                ],
            ]],
            'implementation' => [
                'description' => 'MedExplain interoperability read endpoint',
                'url' => $baseUrl,
            ],
        ];
    }

    /**
     * @return array<int, array{system: string, value: string}>
     */
    private function patientTelecom(?string $phone, ?string $email): array
    {
        return array_values(array_filter([
            $phone ? ['system' => 'phone', 'value' => $phone] : null,
            $email ? ['system' => 'email', 'value' => $email] : null,
        ]));
    }

    private function genderCode(?string $gender): string
    {
        return match (strtolower(trim((string) $gender))) {
            'male', 'm' => 'male',
            'female', 'f' => 'female',
            'other', 'o' => 'other',
            default => 'unknown',
        };
    }
}
