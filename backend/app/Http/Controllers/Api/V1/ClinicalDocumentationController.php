<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProblemList;
use App\Models\Allergy;
use App\Models\VitalSign;
use App\Models\ClinicalNote;
use App\Models\ClinicalNoteTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ClinicalDocumentationController extends Controller
{
    // Problem List Endpoints

    public function getProblemList(Request $request, $patientId): JsonResponse
    {
        $problems = ProblemList::where('patient_id', $patientId)
            ->where('organization_id', $request->user()->organization_id)
            ->with(['createdBy', 'updatedBy'])
            ->get();

        return response()->json($problems);
    }

    public function storeProblem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:users,id',
            'icd10_code' => 'required|string|max:10',
            'icd10_description' => 'required|string',
            'clinical_notes' => 'nullable|string',
            'status' => 'required|in:active,resolved,chronic,recurrent',
            'onset_date' => 'nullable|date',
            'resolved_date' => 'nullable|date|after_or_equal:onset_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $problem = ProblemList::create([
            'patient_id' => $request->patient_id,
            'organization_id' => $request->user()->organization_id,
            'icd10_code' => $request->icd10_code,
            'icd10_description' => $request->icd10_description,
            'clinical_notes' => $request->clinical_notes,
            'status' => $request->status,
            'onset_date' => $request->onset_date,
            'resolved_date' => $request->resolved_date,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($problem->load(['createdBy', 'updatedBy']), 201);
    }

    public function updateProblem(Request $request, $id): JsonResponse
    {
        $problem = ProblemList::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'icd10_code' => 'sometimes|string|max:10',
            'icd10_description' => 'sometimes|string',
            'clinical_notes' => 'nullable|string',
            'status' => 'sometimes|in:active,resolved,chronic,recurrent',
            'onset_date' => 'nullable|date',
            'resolved_date' => 'nullable|date|after_or_equal:onset_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $problem->update($request->only([
            'icd10_code', 'icd10_description', 'clinical_notes', 'status', 'onset_date', 'resolved_date'
        ]));
        $problem->updated_by = $request->user()->id;
        $problem->save();

        return response()->json($problem->load(['createdBy', 'updatedBy']));
    }

    public function deleteProblem($id): JsonResponse
    {
        $problem = ProblemList::findOrFail($id);
        $problem->delete();
        return response()->json(['message' => 'Problem deleted successfully']);
    }

    // Allergy Endpoints

    public function getAllergies(Request $request, $patientId): JsonResponse
    {
        $allergies = Allergy::where('patient_id', $patientId)
            ->where('organization_id', $request->user()->organization_id)
            ->with(['createdBy', 'updatedBy'])
            ->get();

        return response()->json($allergies);
    }

    public function storeAllergy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:users,id',
            'allergen_type' => 'required|in:drug,food,environmental,other',
            'allergen_name' => 'required|string',
            'reaction_description' => 'nullable|string',
            'severity' => 'required|in:mild,moderate,severe,life_threatening',
            'status' => 'required|in:active,resolved,unconfirmed',
            'onset_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $allergy = Allergy::create([
            'patient_id' => $request->patient_id,
            'organization_id' => $request->user()->organization_id,
            'allergen_type' => $request->allergen_type,
            'allergen_name' => $request->allergen_name,
            'reaction_description' => $request->reaction_description,
            'severity' => $request->severity,
            'status' => $request->status,
            'onset_date' => $request->onset_date,
            'notes' => $request->notes,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($allergy->load(['createdBy', 'updatedBy']), 201);
    }

    public function updateAllergy(Request $request, $id): JsonResponse
    {
        $allergy = Allergy::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'allergen_type' => 'sometimes|in:drug,food,environmental,other',
            'allergen_name' => 'sometimes|string',
            'reaction_description' => 'nullable|string',
            'severity' => 'sometimes|in:mild,moderate,severe,life_threatening',
            'status' => 'sometimes|in:active,resolved,unconfirmed',
            'onset_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $allergy->update($request->only([
            'allergen_type', 'allergen_name', 'reaction_description', 'severity', 'status', 'onset_date', 'notes'
        ]));
        $allergy->updated_by = $request->user()->id;
        $allergy->save();

        return response()->json($allergy->load(['createdBy', 'updatedBy']));
    }

    public function deleteAllergy($id): JsonResponse
    {
        $allergy = Allergy::findOrFail($id);
        $allergy->delete();
        return response()->json(['message' => 'Allergy deleted successfully']);
    }

    // Vital Signs Endpoints

    public function getVitalSigns(Request $request, $patientId): JsonResponse
    {
        $vitalSigns = VitalSign::where('patient_id', $patientId)
            ->where('organization_id', $request->user()->organization_id)
            ->with(['recordedBy'])
            ->latestFirst()
            ->get();

        return response()->json($vitalSigns);
    }

    public function storeVitalSign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:users,id',
            'encounter_id' => 'nullable|exists:encounters,id',
            'temperature' => 'nullable|numeric',
            'temperature_unit' => 'sometimes|string|max:10',
            'heart_rate' => 'nullable|integer',
            'blood_pressure_systolic' => 'nullable|integer',
            'blood_pressure_diastolic' => 'nullable|integer',
            'respiratory_rate' => 'nullable|integer',
            'oxygen_saturation' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
            'weight_unit' => 'sometimes|string|max:10',
            'height' => 'nullable|numeric',
            'height_unit' => 'sometimes|string|max:10',
            'pain_score' => 'nullable|integer|min:0|max:10',
            'notes' => 'nullable|string',
            'recorded_at' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $vitalSign = VitalSign::create([
            'patient_id' => $request->patient_id,
            'encounter_id' => $request->encounter_id,
            'organization_id' => $request->user()->organization_id,
            'temperature' => $request->temperature,
            'temperature_unit' => $request->temperature_unit ?? 'C',
            'heart_rate' => $request->heart_rate,
            'blood_pressure_systolic' => $request->blood_pressure_systolic,
            'blood_pressure_diastolic' => $request->blood_pressure_diastolic,
            'respiratory_rate' => $request->respiratory_rate,
            'oxygen_saturation' => $request->oxygen_saturation,
            'weight' => $request->weight,
            'weight_unit' => $request->weight_unit ?? 'kg',
            'height' => $request->height,
            'height_unit' => $request->height_unit ?? 'cm',
            'pain_score' => $request->pain_score,
            'notes' => $request->notes,
            'recorded_by' => $request->user()->id,
            'recorded_at' => $request->recorded_at,
        ]);

        if ($vitalSign->weight && $vitalSign->height) {
            $vitalSign->calculateBMI();
        }

        return response()->json($vitalSign->load('recordedBy'), 201);
    }

    public function getVitalSignTrends(Request $request, $patientId): JsonResponse
    {
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);

        $vitalSigns = VitalSign::where('patient_id', $patientId)
            ->where('organization_id', $request->user()->organization_id)
            ->where('recorded_at', '>=', $startDate)
            ->orderBy('recorded_at')
            ->get();

        return response()->json([
            'vital_signs' => $vitalSigns,
            'trends' => $this->calculateTrends($vitalSigns)
        ]);
    }

    private function calculateTrends($vitalSigns)
    {
        return [
            'temperature' => $vitalSigns->pluck('temperature')->filter()->values(),
            'heart_rate' => $vitalSigns->pluck('heart_rate')->filter()->values(),
            'blood_pressure_systolic' => $vitalSigns->pluck('blood_pressure_systolic')->filter()->values(),
            'blood_pressure_diastolic' => $vitalSigns->pluck('blood_pressure_diastolic')->filter()->values(),
            'weight' => $vitalSigns->pluck('weight')->filter()->values(),
            'recorded_at' => $vitalSigns->pluck('recorded_at'),
        ];
    }

    // Clinical Notes Endpoints

    public function getClinicalNotes(Request $request, $patientId): JsonResponse
    {
        $notes = ClinicalNote::where('patient_id', $patientId)
            ->where('organization_id', $request->user()->organization_id)
            ->with(['author', 'cosigner', 'template'])
            ->latestFirst()
            ->get();

        return response()->json($notes);
    }

    public function storeClinicalNote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:users,id',
            'encounter_id' => 'nullable|exists:encounters,id',
            'template_id' => 'nullable|exists:clinical_note_templates,id',
            'note_type' => 'required|in:admission,progress,discharge,consultation,procedure,other',
            'subjective' => 'nullable|string',
            'objective' => 'nullable|string',
            'assessment' => 'nullable|string',
            'plan' => 'nullable|string',
            'full_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $note = ClinicalNote::create([
            'patient_id' => $request->patient_id,
            'encounter_id' => $request->encounter_id,
            'organization_id' => $request->user()->organization_id,
            'template_id' => $request->template_id,
            'note_type' => $request->note_type,
            'subjective' => $request->subjective,
            'objective' => $request->objective,
            'assessment' => $request->assessment,
            'plan' => $request->plan,
            'full_note' => $request->full_note,
            'author_id' => $request->user()->id,
            'status' => 'draft',
        ]);

        return response()->json($note->load(['author', 'cosigner', 'template']), 201);
    }

    public function updateClinicalNote(Request $request, $id): JsonResponse
    {
        $note = ClinicalNote::findOrFail($id);

        if ($note->status === 'final') {
            return response()->json(['message' => 'Cannot update finalized notes'], 403);
        }

        $validator = Validator::make($request->all(), [
            'subjective' => 'nullable|string',
            'objective' => 'nullable|string',
            'assessment' => 'nullable|string',
            'plan' => 'nullable|string',
            'full_note' => 'nullable|string',
            'status' => 'sometimes|in:draft,final,amended',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $note->update($request->only(['subjective', 'objective', 'assessment', 'plan', 'full_note', 'status']));

        if ($request->status === 'final') {
            $note->finalize();
        }

        return response()->json($note->load(['author', 'cosigner', 'template']));
    }

    public function cosignClinicalNote(Request $request, $id): JsonResponse
    {
        $note = ClinicalNote::findOrFail($id);
        $note->cosign($request->user());
        return response()->json($note->load(['author', 'cosigner', 'template']));
    }

    public function deleteClinicalNote($id): JsonResponse
    {
        $note = ClinicalNote::findOrFail($id);
        if ($note->status === 'final') {
            return response()->json(['message' => 'Cannot delete finalized notes'], 403);
        }
        $note->delete();
        return response()->json(['message' => 'Clinical note deleted successfully']);
    }

    // Clinical Note Templates Endpoints

    public function getTemplates(Request $request): JsonResponse
    {
        $templates = ClinicalNoteTemplate::where('organization_id', $request->user()->organization_id)
            ->active()
            ->with(['createdBy', 'updatedBy'])
            ->get();

        return response()->json($templates);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'specialty' => 'nullable|string',
            'note_type' => 'required|in:admission,progress,discharge,consultation,procedure,other',
            'structure' => 'nullable|array',
            'default_subjective' => 'nullable|string',
            'default_objective' => 'nullable|string',
            'default_assessment' => 'nullable|string',
            'default_plan' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $template = ClinicalNoteTemplate::create([
            'organization_id' => $request->user()->organization_id,
            'name' => $request->name,
            'specialty' => $request->specialty,
            'note_type' => $request->note_type,
            'structure' => $request->structure,
            'default_subjective' => $request->default_subjective,
            'default_objective' => $request->default_objective,
            'default_assessment' => $request->default_assessment,
            'default_plan' => $request->default_plan,
            'is_active' => $request->is_active ?? true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($template->load(['createdBy', 'updatedBy']), 201);
    }

    public function updateTemplate(Request $request, $id): JsonResponse
    {
        $template = ClinicalNoteTemplate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string',
            'specialty' => 'nullable|string',
            'note_type' => 'sometimes|in:admission,progress,discharge,consultation,procedure,other',
            'structure' => 'nullable|array',
            'default_subjective' => 'nullable|string',
            'default_objective' => 'nullable|string',
            'default_assessment' => 'nullable|string',
            'default_plan' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $template->update($request->only([
            'name', 'specialty', 'note_type', 'structure',
            'default_subjective', 'default_objective', 'default_assessment', 'default_plan', 'is_active'
        ]));
        $template->updated_by = $request->user()->id;
        $template->save();

        return response()->json($template->load(['createdBy', 'updatedBy']));
    }

    public function deleteTemplate($id): JsonResponse
    {
        $template = ClinicalNoteTemplate::findOrFail($id);
        $template->delete();
        return response()->json(['message' => 'Template deleted successfully']);
    }
}
