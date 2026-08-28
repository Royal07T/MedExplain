<?php

namespace App\Services\Terminology;

/**
 * Offline, deterministic vocabulary used by the terminology service.
 *
 * This is a small reference codeset (common ICD-10-CM codes and SNOMED CT
 * concepts) so the terminology endpoints work without a live network
 * connection. It is not a full terminology server — expand the arrays to
 * cover more of a real vocabulary.
 *
 * @internal
 */
final class TerminologyCodes
{
    /**
     * @return array<int, array{code: string, display: string}>
     */
    public static function icd10(): array
    {
        return [
            ['code' => 'A09', 'display' => 'Infectious gastroenteritis and colitis, unspecified'],
            ['code' => 'E11.9', 'display' => 'Type 2 diabetes mellitus without complications'],
            ['code' => 'E78.5', 'display' => 'Hyperlipidemia, unspecified'],
            ['code' => 'F32.9', 'display' => 'Major depressive disorder, single episode, unspecified'],
            ['code' => 'G43.9', 'display' => 'Migraine, unspecified, not intractable'],
            ['code' => 'I10', 'display' => 'Essential (primary) hypertension'],
            ['code' => 'I25.10', 'display' => 'Atherosclerotic heart disease of native coronary artery without angina pectoris'],
            ['code' => 'I48.91', 'display' => 'Unspecified atrial fibrillation'],
            ['code' => 'J06.9', 'display' => 'Acute upper respiratory infection, unspecified'],
            ['code' => 'J45.909', 'display' => 'Unspecified asthma, uncomplicated'],
            ['code' => 'J18.9', 'display' => 'Pneumonia, unspecified organism'],
            ['code' => 'K21.0', 'display' => 'Gastro-esophageal reflux disease with esophagitis'],
            ['code' => 'K29.50', 'display' => 'Unspecified chronic gastritis without bleeding'],
            ['code' => 'M54.5', 'display' => 'Low back pain'],
            ['code' => 'M17.9', 'display' => 'Osteoarthritis of knee, unspecified'],
            ['code' => 'N39.0', 'display' => 'Urinary tract infection, site not specified'],
            ['code' => 'R05', 'display' => 'Cough'],
            ['code' => 'R10.9', 'display' => 'Unspecified abdominal pain'],
            ['code' => 'R42', 'display' => 'Dizziness and giddiness'],
            ['code' => 'R51', 'display' => 'Headache'],
            ['code' => 'Z00.00', 'display' => 'Encounter for general adult medical examination without abnormal findings'],
            ['code' => 'Z23', 'display' => 'Encounter for immunization'],
        ];
    }

    /**
     * @return array<int, array{code: string, display: string}>
     */
    public static function snomed(): array
    {
        return [
            ['code' => '73211009', 'display' => 'Diabetes mellitus (disorder)'],
            ['code' => '38341003', 'display' => 'Hypertensive disorder, systemic arterial (disorder)'],
            ['code' => '195967001', 'display' => 'Asthma (disorder)'],
            ['code' => '386661006', 'display' => 'Fever (finding)'],
            ['code' => '49727002', 'display' => 'Cough (finding)'],
            ['code' => '25064002', 'display' => 'Headache (finding)'],
            ['code' => '29857009', 'display' => 'Chest pain (finding)'],
            ['code' => '271807003', 'display' => 'Eruption of skin (disorder)'],
            ['code' => '87522002', 'display' => 'Obesity (disorder)'],
            ['code' => '427172004', 'display' => 'Dyspnea (finding)'],
        ];
    }

    /**
     * @return array<int, array{code: string, display: string}>
     *
     * @throws \InvalidArgumentException
     */
    public static function for(string $system): array
    {
        return match (strtolower($system)) {
            'icd10', 'icd-10', 'icd10cm' => self::icd10(),
            'snomed', 'snomedct', 'snomed-ct' => self::snomed(),
            default => throw new \InvalidArgumentException("Unsupported terminology system: {$system}"),
        };
    }
}
