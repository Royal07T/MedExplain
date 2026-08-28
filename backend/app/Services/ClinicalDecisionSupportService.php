<?php

namespace App\Services;

use App\Models\Allergy;
use App\Models\Medication;
use App\Models\VitalSign;
use App\Models\ProblemList;
use Illuminate\Support\Collection;

class ClinicalDecisionSupportService
{
    /**
     * Check for drug-allergy interactions
     */
    public function checkDrugAllergyInteractions(int $patientId, array $medicationNames): Collection
    {
        $allergies = Allergy::where('patient_id', $patientId)
            ->active()
            ->drugAllergies()
            ->get();

        $alerts = collect();

        foreach ($medicationNames as $medicationName) {
            foreach ($allergies as $allergy) {
                if ($this->isMedicationAllergic($medicationName, $allergy->allergen_name)) {
                    $alerts->push([
                        'type' => 'allergy',
                        'severity' => $allergy->severity,
                        'message' => "Patient has allergy to {$allergy->allergen_name}. Prescribing {$medicationName} may cause an allergic reaction.",
                        'allergy' => $allergy->allergen_name,
                        'medication' => $medicationName,
                        'reaction' => $allergy->reaction_description,
                    ]);
                }
            }
        }

        return $alerts;
    }

    /**
     * Check for drug-drug interactions
     */
    public function checkDrugDrugInteractions(array $medicationNames): Collection
    {
        $alerts = collect();
        $knownInteractions = $this->getKnownDrugInteractions();

        for ($i = 0; $i < count($medicationNames); $i++) {
            for ($j = $i + 1; $j < count($medicationNames); $j++) {
                $med1 = $medicationNames[$i];
                $med2 = $medicationNames[$j];

                if ($this->hasInteraction($med1, $med2, $knownInteractions)) {
                    $interaction = $knownInteractions[$this->getInteractionKey($med1, $med2)];
                    $alerts->push([
                        'type' => 'drug_interaction',
                        'severity' => $interaction['severity'],
                        'message' => "Potential interaction between {$med1} and {$med2}: {$interaction['description']}",
                        'medication_1' => $med1,
                        'medication_2' => $med2,
                        'description' => $interaction['description'],
                        'recommendation' => $interaction['recommendation'] ?? null,
                    ]);
                }
            }
        }

        return $alerts;
    }

    /**
     * Check for dose adjustments based on patient conditions
     */
    public function checkDoseAdjustments(int $patientId, array $medications): Collection
    {
        $alerts = collect();
        $patientConditions = $this->getPatientConditions($patientId);

        foreach ($medications as $medication) {
            if (isset($patientConditions['renal_impairment']) && $this->requiresRenalAdjustment($medication['name'])) {
                $alerts->push([
                    'type' => 'dose_adjustment',
                    'severity' => 'moderate',
                    'message' => "Consider dose adjustment for {$medication['name']} due to renal impairment",
                    'medication' => $medication['name'],
                    'condition' => 'renal_impairment',
                    'recommendation' => 'Reduce dose or increase dosing interval based on creatinine clearance',
                ]);
            }

            if (isset($patientConditions['hepatic_impairment']) && $this->requiresHepaticAdjustment($medication['name'])) {
                $alerts->push([
                    'type' => 'dose_adjustment',
                    'severity' => 'moderate',
                    'message' => "Consider dose adjustment for {$medication['name']} due to hepatic impairment",
                    'medication' => $medication['name'],
                    'condition' => 'hepatic_impairment',
                    'recommendation' => 'Reduce dose or avoid use in severe hepatic impairment',
                ]);
            }
        }

        return $alerts;
    }

    /**
     * Check vital signs for critical values
     */
    public function checkVitalSigns(int $patientId): Collection
    {
        $alerts = collect();
        $latestVitals = VitalSign::where('patient_id', $patientId)
            ->latestFirst()
            ->first();

        if (!$latestVitals) {
            return $alerts;
        }

        // Blood pressure checks
        if ($latestVitals->blood_pressure_systolic > 180 || $latestVitals->blood_pressure_diastolic > 120) {
            $alerts->push([
                'type' => 'vital_sign',
                'severity' => 'severe',
                'message' => 'Hypertensive crisis detected',
                'parameter' => 'blood_pressure',
                'value' => $latestVitals->blood_pressure_systolic . '/' . $latestVitals->blood_pressure_diastolic,
                'recommendation' => 'Immediate medical attention required',
            ]);
        } elseif ($latestVitals->blood_pressure_systolic < 90 || $latestVitals->blood_pressure_diastolic < 60) {
            $alerts->push([
                'type' => 'vital_sign',
                'severity' => 'moderate',
                'message' => 'Hypotension detected',
                'parameter' => 'blood_pressure',
                'value' => $latestVitals->blood_pressure_systolic . '/' . $latestVitals->blood_pressure_diastolic,
                'recommendation' => 'Monitor and consider intervention',
            ]);
        }

        // Heart rate checks
        if ($latestVitals->heart_rate > 120) {
            $alerts->push([
                'type' => 'vital_sign',
                'severity' => 'moderate',
                'message' => 'Tachycardia detected',
                'parameter' => 'heart_rate',
                'value' => $latestVitals->heart_rate,
                'recommendation' => 'Assess for underlying cause',
            ]);
        } elseif ($latestVitals->heart_rate < 50) {
            $alerts->push([
                'type' => 'vital_sign',
                'severity' => 'moderate',
                'message' => 'Bradycardia detected',
                'parameter' => 'heart_rate',
                'value' => $latestVitals->heart_rate,
                'recommendation' => 'Assess for underlying cause',
            ]);
        }

        // Oxygen saturation checks
        if ($latestVitals->oxygen_saturation < 90) {
            $alerts->push([
                'type' => 'vital_sign',
                'severity' => 'severe',
                'message' => 'Hypoxemia detected',
                'parameter' => 'oxygen_saturation',
                'value' => $latestVitals->oxygen_saturation,
                'recommendation' => 'Immediate intervention required',
            ]);
        } elseif ($latestVitals->oxygen_saturation < 95) {
            $alerts->push([
                'type' => 'vital_sign',
                'severity' => 'mild',
                'message' => 'Low oxygen saturation',
                'parameter' => 'oxygen_saturation',
                'value' => $latestVitals->oxygen_saturation,
                'recommendation' => 'Monitor and consider supplemental oxygen',
            ]);
        }

        // Temperature checks
        if ($latestVitals->temperature > 39) {
            $alerts->push([
                'type' => 'vital_sign',
                'severity' => 'moderate',
                'message' => 'High fever detected',
                'parameter' => 'temperature',
                'value' => $latestVitals->temperature,
                'recommendation' => 'Assess for infection and consider antipyretics',
            ]);
        } elseif ($latestVitals->temperature < 35) {
            $alerts->push([
                'type' => 'vital_sign',
                'severity' => 'severe',
                'message' => 'Hypothermia detected',
                'parameter' => 'temperature',
                'value' => $latestVitals->temperature,
                'recommendation' => 'Immediate warming intervention required',
            ]);
        }

        return $alerts;
    }

    /**
     * Get guideline reminders for patient conditions
     */
    public function getGuidelineReminders(int $patientId): Collection
    {
        $alerts = collect();
        $problems = ProblemList::where('patient_id', $patientId)->active()->get();

        foreach ($problems as $problem) {
            $reminders = $this->getGuidelinesForCondition($problem->icd10_code);
            foreach ($reminders as $reminder) {
                $alerts->push([
                    'type' => 'guideline',
                    'severity' => 'mild',
                    'message' => $reminder['message'],
                    'condition' => $problem->icd10_description,
                    'guideline' => $reminder['guideline'],
                    'recommendation' => $reminder['recommendation'],
                ]);
            }
        }

        return $alerts;
    }

    /**
     * Get preventive care reminders
     */
    public function getPreventiveCareReminders(int $patientId, int $age): Collection
    {
        $alerts = collect();

        // Age-based screenings
        if ($age >= 50 && $age < 75) {
            $alerts->push([
                'type' => 'preventive',
                'severity' => 'mild',
                'message' => 'Colorectal cancer screening recommended',
                'recommendation' => 'Consider colonoscopy or FIT test',
            ]);
        }

        if ($age >= 40) {
            $alerts->push([
                'type' => 'preventive',
                'severity' => 'mild',
                'message' => 'Breast cancer screening recommended for females',
                'recommendation' => 'Annual mammogram',
            ]);
        }

        if ($age >= 65) {
            $alerts->push([
                'type' => 'preventive',
                'severity' => 'mild',
                'message' => 'Pneumococcal vaccination recommended',
                'recommendation' => 'Administer PCV13 or PPSV23 if not previously vaccinated',
            ]);
        }

        // Annual reminders
        $alerts->push([
            'type' => 'preventive',
            'severity' => 'mild',
            'message' => 'Annual influenza vaccination recommended',
            'recommendation' => 'Administer flu vaccine annually',
        ]);

        return $alerts;
    }

    // Helper methods

    private function isMedicationAllergic(string $medication, string $allergen): bool
    {
        $medicationLower = strtolower($medication);
        $allergenLower = strtolower($allergen);

        // Check for exact match or partial match
        return strpos($medicationLower, $allergenLower) !== false ||
               strpos($allergenLower, $medicationLower) !== false ||
               $this->isDrugClassMatch($medicationLower, $allergenLower);
    }

    private function isDrugClassMatch(string $medication, string $allergen): bool
    {
        $drugClasses = [
            'penicillin' => ['amoxicillin', 'ampicillin', 'penicillin', 'oxacillin', 'nafcillin'],
            'sulfa' => ['sulfamethoxazole', 'sulfadiazine', 'sulfa'],
            'ace' => ['lisinopril', 'enalapril', 'captopril', 'ramipril'],
            'nsaid' => ['ibuprofen', 'naproxen', 'diclofenac', 'ketorolac'],
        ];

        foreach ($drugClasses as $class => $drugs) {
            if (in_array($allergen, $drugs) && in_array($medication, $drugs)) {
                return true;
            }
        }

        return false;
    }

    private function getKnownDrugInteractions(): array
    {
        return [
            'warfarin-aspirin' => [
                'severity' => 'severe',
                'description' => 'Increased risk of bleeding',
                'recommendation' => 'Monitor INR closely, consider alternative',
            ],
            'warfarin-ibuprofen' => [
                'severity' => 'severe',
                'description' => 'Increased risk of bleeding',
                'recommendation' => 'Avoid concurrent use if possible',
            ],
            'lisinopril-potassium' => [
                'severity' => 'moderate',
                'description' => 'Risk of hyperkalemia',
                'recommendation' => 'Monitor potassium levels',
            ],
            'digoxin-verapamil' => [
                'severity' => 'moderate',
                'description' => 'Increased digoxin levels',
                'recommendation' => 'Monitor digoxin levels, reduce dose if needed',
            ],
            'simvastatin-clarithromycin' => [
                'severity' => 'severe',
                'description' => 'Increased risk of myopathy/rhabdomyolysis',
                'recommendation' => 'Hold simvastatin during clarithromycin therapy',
            ],
        ];
    }

    private function hasInteraction(string $med1, string $med2, array $interactions): bool
    {
        $key1 = $this->getInteractionKey($med1, $med2);
        $key2 = $this->getInteractionKey($med2, $med1);
        return isset($interactions[$key1]) || isset($interactions[$key2]);
    }

    private function getInteractionKey(string $med1, string $med2): string
    {
        return strtolower($med1) . '-' . strtolower($med2);
    }

    private function getPatientConditions(int $patientId): array
    {
        // This would typically come from lab results, problem list, etc.
        // For now, return empty array - implement based on actual data
        return [];
    }

    private function requiresRenalAdjustment(string $medication): bool
    {
        $renalAdjustmentMeds = ['warfarin', 'digoxin', 'lithium', 'metformin', 'ace inhibitors', 'arb'];
        $medLower = strtolower($medication);
        foreach ($renalAdjustmentMeds as $med) {
            if (strpos($medLower, $med) !== false) {
                return true;
            }
        }
        return false;
    }

    private function requiresHepaticAdjustment(string $medication): bool
    {
        $hepaticAdjustmentMeds = ['warfarin', 'statin', 'paracetamol', 'ketoprofen'];
        $medLower = strtolower($medication);
        foreach ($hepaticAdjustmentMeds as $med) {
            if (strpos($medLower, $med) !== false) {
                return true;
            }
        }
        return false;
    }

    private function getGuidelinesForCondition(string $icd10Code): array
    {
        $guidelines = [
            'E11' => [ // Type 2 Diabetes
                'message' => 'Diabetes management guidelines apply',
                'guideline' => 'ADA Standards of Care',
                'recommendation' => 'Monitor HbA1c every 3 months, assess complications annually',
            ],
            'I10' => [ // Hypertension
                'message' => 'Hypertension management guidelines apply',
                'guideline' => 'ACC/AHA Guidelines',
                'recommendation' => 'Monitor BP regularly, assess cardiovascular risk',
            ],
            'J45' => [ // Asthma
                'message' => 'Asthma management guidelines apply',
                'guideline' => 'GINA Guidelines',
                'recommendation' => 'Assess control regularly, review inhaler technique',
            ],
        ];

        return $guidelines[$icd10Code] ?? [];
    }
}
