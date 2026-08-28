<?php

namespace App\Services;

use App\Enums\CarePlanStatus;
use App\Enums\LabResultStatus;
use App\Models\CarePlan;
use App\Models\LabResult;
use App\Models\Patient;
use App\Models\ProblemList;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Support\Collection;

/**
 * Deterministic, organization-scoped population health analytics.
 *
 * Computes an org's disease registry, per-patient risk stratification, and
 * aggregate population dashboard stats entirely from data already in the
 * database (no external ML). All aggregation is PHP-side over scoped
 * collections so it is SQLite-safe and deterministic.
 *
 * Scope is expressed as a set of patient User ids; callers decide whether
 * that is the whole org or a clinician's granted patients.
 */
final class PopulationHealthService
{
    /**
     * Resolve the diagnostic (ICD-10) registry for a set of patients.
     *
     * Returns one entry per ICD-10 code with the count of distinct patients
     * holding an active/chronic diagnosis and a sample of patient user ids.
     *
     * @param  int[]  $patientIds
     * @return array{conditions: array<int, array{code: string, display: string, count: int, active_chronic: int}>, total: int}
     */
    public function diseaseRegistry(array $patientIds): array
    {
        if ($patientIds === []) {
            return ['conditions' => [], 'total' => 0];
        }

        $problems = ProblemList::whereIn('patient_id', $patientIds)
            ->whereIn('status', ['active', 'chronic'])
            ->get(['patient_id', 'icd10_code', 'icd10_description']);

        $conditions = $problems
            ->groupBy(fn ($p) => strtoupper(trim($p->icd10_code)))
            ->map(function (Collection $group): array {
                $display = $group->first()->icd10_description ?: 'Unspecified condition';

                return [
                    'code' => $group->first()->icd10_code,
                    'display' => $display,
                    'count' => $group->pluck('patient_id')->unique()->count(),
                    'active_chronic' => $group->count(),
                ];
            })
            ->values()
            ->sortByDesc('count')
            ->values()
            ->all();

        return ['conditions' => $conditions, 'total' => count($conditions)];
    }

    /**
     * Stratify a set of patients into low / moderate / high risk tiers.
     *
     * @param  int[]  $patientIds
     * @return array{patients: array<int, array{user_id: int, tier: string, score: int, factors: string[]}>, summary: array{low: int, moderate: int, high: int}}
     */
    public function riskStratification(array $patientIds): array
    {
        if ($patientIds === []) {
            return ['patients' => [], 'summary' => ['low' => 0, 'moderate' => 0, 'high' => 0]];
        }

        $problems = ProblemList::whereIn('patient_id', $patientIds)
            ->whereIn('status', ['active', 'chronic'])
            ->get(['patient_id', 'icd10_code'])
            ->groupBy('patient_id');

        $abnormal = LabResult::whereIn('user_id', $patientIds)
            ->whereIn('status', [LabResultStatus::AboveRange->value, LabResultStatus::BelowRange->value])
            ->get(['user_id'])
            ->pluck('user_id')
            ->unique();

        $vitals = VitalSign::whereIn('patient_id', $patientIds)
            ->whereNotNull('recorded_at')
            ->orderByDesc('recorded_at')
            ->get([
                'patient_id', 'heart_rate', 'blood_pressure_systolic', 'blood_pressure_diastolic',
                'oxygen_saturation', 'temperature',
            ])
            ->groupBy('patient_id')
            ->map->first();

        $ages = Patient::whereIn('user_id', $patientIds)
            ->get(['user_id', 'date_of_birth'])
            ->pluck('date_of_birth', 'user_id');

        $patients = collect($patientIds)->map(function (int $userId) use ($problems, $abnormal, $vitals, $ages): array {
            $score = 0;
            $factors = [];

            $conditions = $problems->get($userId, collect());
            if ($conditions->isNotEmpty()) {
                $score += min(3, $conditions->count()) + $conditions->count();
                $factors[] = $conditions->count().' active/chronic condition(s)';
            }

            if ($abnormal->contains($userId)) {
                $score += 1;
                $factors[] = 'abnormal lab result(s)';
            }

            $vital = $vitals->get($userId);
            $vitalFlag = $this->criticalVitalFactor($vital);
            if ($vitalFlag !== null) {
                $score += 1;
                $factors[] = $vitalFlag;
            }

            $dob = $ages->get($userId);
            if ($dob !== null && $this->age($dob) >= 65) {
                $score += 1;
                $factors[] = 'age 65+';
            }

            $tier = $score >= 5 ? 'high' : ($score >= 2 ? 'moderate' : 'low');

            return [
                'user_id' => $userId,
                'tier' => $tier,
                'score' => $score,
                'factors' => $factors,
            ];
        });

        $byTier = fn (string $tier): int => $patients->filter(fn ($p) => $p['tier'] === $tier)->count();

        return [
            'patients' => $patients->values()->all(),
            'summary' => ['low' => $byTier('low'), 'moderate' => $byTier('moderate'), 'high' => $byTier('high')],
        ];
    }

    /**
     * Aggregate population-level dashboard statistics.
     *
     * @param  int[]  $patientIds
     * @return array<string, mixed>
     */
    public function populationDashboard(array $patientIds): array
    {
        $demographics = Patient::whereIn('user_id', $patientIds)
            ->get(['user_id', 'gender', 'date_of_birth']);

        $total = count($patientIds);

        $gender = $demographics->groupBy(fn ($p) => $this->genderBucket($p->gender))->map->count()->all();

        $ageBands = $demographics->groupBy(function ($p): string {
            $age = $p->date_of_birth ? $this->age($p->date_of_birth) : null;

            return match (true) {
                $age === null => 'unknown',
                $age < 18 => '0-17',
                $age < 40 => '18-39',
                $age < 65 => '40-64',
                default => '65+',
            };
        })->map->count()->all();

        $registry = $this->diseaseRegistry($patientIds);

        $abnormalLabs = $patientIds === []
            ? 0
            : LabResult::whereIn('user_id', $patientIds)
                ->whereIn('status', [LabResultStatus::AboveRange->value, LabResultStatus::BelowRange->value])
                ->distinct('user_id')
                ->count('user_id');

        $risk = $this->riskStratification($patientIds);

        return [
            'total_patients' => $total,
            'gender_breakdown' => $gender,
            'age_band_breakdown' => $ageBands,
            'patients_with_abnormal_labs' => $abnormalLabs,
            'abnormal_lab_rate' => $total > 0 ? round(($abnormalLabs / $total) * 100, 1) : 0.0,
            'top_conditions' => array_slice($registry['conditions'], 0, 10),
            'risk_summary' => $risk['summary'],
        ];
    }

    /**
     * Identify deterministic care gaps for a set of patients.
     *
     * Gaps are derived from data already in the database, matched against
     * curated ICD-10 condition families. Only active/chronic diagnoses are
     * considered. Each gap is returned with a human-readable description.
     *
     * @param  int[]  $patientIds
     * @return array{patients: array<int, array{user_id: int, gaps: array<int, array{type: string, description: string}>}>, summary: array{total_patients: int, with_gaps: int, gaps_by_type: array<string, int>}}
     */
    public function careGaps(array $patientIds): array
    {
        if ($patientIds === []) {
            return ['patients' => [], 'summary' => ['total_patients' => 0, 'with_gaps' => 0, 'gaps_by_type' => []]];
        }

        $problems = ProblemList::whereIn('patient_id', $patientIds)
            ->whereIn('status', ['active', 'chronic'])
            ->get(['patient_id', 'icd10_code'])
            ->groupBy('patient_id');

        // Per-patient monitoring evidence.
        $a1cLabs = LabResult::whereIn('user_id', $patientIds)
            ->get(['user_id', 'normalized_name'])
            ->filter(fn ($r) => !$r->normalized_name
                || str_contains(strtolower($r->normalized_name), 'a1c')
                || str_contains(strtolower($r->normalized_name), 'hba1c')
                || str_contains(strtolower($r->normalized_name), 'glycated'))
            ->pluck('user_id')
            ->unique();

        $bpPatients = VitalSign::whereIn('patient_id', $patientIds)
            ->whereNotNull('blood_pressure_systolic')
            ->pluck('patient_id')
            ->unique();

        $spo2Patients = VitalSign::whereIn('patient_id', $patientIds)
            ->whereNotNull('oxygen_saturation')
            ->pluck('patient_id')
            ->unique();

        $withActiveCarePlan = CarePlan::whereIn('patient_id', $patientIds)
            ->where('status', CarePlanStatus::Active->value)
            ->pluck('patient_id')
            ->unique();

        $patients = collect($patientIds)->map(function (int $userId) use (
            $problems, $a1cLabs, $bpPatients, $spo2Patients, $withActiveCarePlan
        ): array {
            $gaps = [];

            $codes = $problems->get($userId, collect())
                ->pluck('icd10_code')
                ->map(fn ($c) => strtoupper((string) $c));

            $hasDiabetic = $codes->contains(fn ($c) => $this->icdInRange($c, 'E08', 'E14'));
            if ($hasDiabetic && !$a1cLabs->contains($userId)) {
                $gaps[] = [
                    'type' => 'diabetic_monitoring',
                    'description' => 'Patient has a diabetes diagnosis but no recorded HbA1c result.',
                ];
            }

            $hasHypertensive = $codes->contains(fn ($c) => $this->icdInRange($c, 'I10', 'I15'));
            if ($hasHypertensive && !$bpPatients->contains($userId)) {
                $gaps[] = [
                    'type' => 'hypertension_monitoring',
                    'description' => 'Patient has a hypertension diagnosis but no recorded blood pressure vital.',
                ];
            }

            $hasRespiratory = $codes->contains(fn ($c) => $this->icdInRange($c, 'J44', 'J45'));
            if ($hasRespiratory && !$spo2Patients->contains($userId)) {
                $gaps[] = [
                    'type' => 'respiratory_monitoring',
                    'description' => 'Patient has asthma/COPD but no recorded oxygen-saturation vital.',
                ];
            }

            if ($codes->isNotEmpty() && !$withActiveCarePlan->contains($userId)) {
                $gaps[] = [
                    'type' => 'care_coordination',
                    'description' => 'Patient has an active/chronic condition but no active care plan.',
                ];
            }

            return ['user_id' => $userId, 'gaps' => $gaps];
        })->filter(fn ($p) => $p['gaps'] !== []);

        $byType = $patients->flatMap(fn ($p) => collect($p['gaps'])->pluck('type'))->countBy()->all();

        return [
            'patients' => $patients->values()->all(),
            'summary' => [
                'total_patients' => count($patientIds),
                'with_gaps' => $patients->count(),
                'gaps_by_type' => $byType,
            ],
        ];
    }

    /**
     * Resolve the org's patient User ids (canonical population scope).
     *
     * @return int[]
     */
    public function organizationPatientIds(int $organizationId): array
    {
        return User::query()
            ->where('role', 'patient')
            ->where('organization_id', $organizationId)
            ->pluck('id')
            ->all();
    }

    private function criticalVitalFactor(?VitalSign $vital): ?string
    {
        if ($vital === null) {
            return null;
        }

        if ($vital->blood_pressure_systolic !== null && $vital->blood_pressure_systolic >= 180) {
            return 'critical blood pressure';
        }
        if ($vital->oxygen_saturation !== null && $vital->oxygen_saturation < 90) {
            return 'low oxygen saturation';
        }
        if ($vital->heart_rate !== null && $vital->heart_rate > 130) {
            return 'elevated heart rate';

        }
        if ($vital->temperature !== null && $vital->temperature >= 39.0) {
            return 'high temperature';
        }

        return null;
    }

    private function age(\DateTimeInterface $dob): int
    {
        return $dob->diff(now())->y;
    }

    private function genderBucket(?string $gender): string
    {
        return match (strtolower(trim((string) $gender))) {
            'male', 'm' => 'male',
            'female', 'f' => 'female',
            default => 'other',
        };
    }

    /**
     * Whether an ICD-10 code falls within a start/end alphabetic prefix range.
     *
     * The 3-char alphabetic chapter letters are compared with the code's
     * leading letters so e.g. 'E10' is in ['E08', 'E14'].
     */
    private function icdInRange(string $code, string $start, string $end): bool
    {
        $letter = substr($code, 0, 1);
        if (!ctype_alpha($letter) || strtoupper(substr($start, 0, 1)) !== $letter) {
            return false;
        }

        $num = (int) substr($code, 1, 2);
        return $num >= (int) substr($start, 1, 2) && $num <= (int) substr($end, 1, 2);
    }
}
