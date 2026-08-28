<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssessmentType;
use App\Enums\CarePlanStatus;
use App\Enums\FallRiskLevel;
use App\Enums\MedicationAdminStatus;
use App\Http\Controllers\Controller;
use App\Models\CarePlan;
use App\Models\MedicationAdministration;
use App\Models\NursingAssessment;
use App\Models\ShiftHandoff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class NursingDocumentationController extends Controller
{
    /**
     * Care plans for a patient (optionally filtered by status).
     */
    public function carePlansIndex(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;
        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $query = CarePlan::with('patient', 'assignee', 'creator')
            ->where('organization_id', $organizationId);

        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $plans = $query->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => $plans->map(fn ($plan) => $this->carePlanPayload($plan)),
        ]);
    }

    /**
     * Create a care plan.
     */
    public function carePlanStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'goals' => ['nullable', 'array'],
            'interventions' => ['nullable', 'array'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $organizationId = $request->user()?->organization_id;
        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        User::where('id', $request->patient_id)->where('organization_id', $organizationId)->firstOrFail();

        $plan = CarePlan::create([
            'organization_id' => $organizationId,
            'patient_id' => $request->patient_id,
            'title' => $request->title,
            'description' => $request->description,
            'goals' => $request->goals,
            'interventions' => $request->interventions,
            'assigned_to' => $request->assigned_to,
            'created_by' => $request->user()->id,
            'status' => CarePlanStatus::Active->value,
            'started_at' => now()->toDateString(),
            'due_date' => $request->due_date,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->carePlanPayload($plan),
            'message' => 'Care plan created',
        ], 201);
    }

    /**
     * Update a care plan.
     */
    public function carePlanUpdate(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'goals' => ['nullable', 'array'],
            'interventions' => ['nullable', 'array'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $plan = $this->findCarePlan($request, $id);
        $plan->fill($validator->validated());
        $plan->save();

        return response()->json([
            'success' => true,
            'data' => $this->carePlanPayload($plan),
            'message' => 'Care plan updated',
        ]);
    }

    /**
     * Update care plan status (active/on_hold/completed/cancelled).
     */
    public function carePlanStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:active,on_hold,completed,cancelled'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $plan = $this->findCarePlan($request, $id);
        $plan->status = $request->status;
        if ($request->status === 'completed') {
            $plan->completed_at = now()->toDateString();
        }
        $plan->save();

        return response()->json([
            'success' => true,
            'data' => $this->carePlanPayload($plan),
            'message' => 'Care plan status updated',
        ]);
    }

    /**
     * Medication Administration Record (MAR) for a patient.
     */
    public function medicationAdminIndex(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;
        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $query = MedicationAdministration::with('patient', 'administeredBy')
            ->where('organization_id', $organizationId)
            ->orderByDesc('scheduled_time');

        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()->map(fn ($ma) => $this->marPayload($ma)),
        ]);
    }

    /**
     * Schedule / record a medication administration (MAR entry).
     */
    public function medicationAdminStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'exists:users,id'],
            'prescription_id' => ['nullable', 'exists:prescriptions,id'],
            'medication_name' => ['required', 'string', 'max:255'],
            'dose' => ['nullable', 'string', 'max:100'],
            'dose_unit' => ['nullable', 'string', 'max:50'],
            'route' => ['nullable', 'string', 'max:50'],
            'scheduled_time' => ['nullable', 'date'],
            'status' => ['nullable', 'in:given,refused,held,not_given'],
            'notes' => ['nullable', 'string'],
            'vitals_before' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $organizationId = $request->user()?->organization_id;
        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        User::where('id', $request->patient_id)->where('organization_id', $organizationId)->firstOrFail();

        $status = $request->status ?? MedicationAdminStatus::NotGiven->value;
        $record = MedicationAdministration::create([
            'organization_id' => $organizationId,
            'patient_id' => $request->patient_id,
            'prescription_id' => $request->prescription_id,
            'medication_name' => $request->medication_name,
            'dose' => $request->dose,
            'dose_unit' => $request->dose_unit,
            'route' => $request->route,
            'scheduled_time' => $request->scheduled_time,
            'administered_time' => $status === MedicationAdminStatus::Given->value ? now() : null,
            'status' => $status,
            'administered_by' => $status === MedicationAdminStatus::Given->value ? $request->user()->id : null,
            'notes' => $request->notes,
            'vitals_before' => $request->vitals_before,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->marPayload($record),
            'message' => 'Medication administration recorded',
        ], 201);
    }

    /**
     * Update MAR status (e.g. mark as given/refused/held).
     */
    public function medicationAdminStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:given,refused,held,not_given'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $organizationId = $request->user()?->organization_id;
        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $record = MedicationAdministration::where('id', $id)->where('organization_id', $organizationId)->firstOrFail();
        $record->status = $request->status;
        if ($request->status === MedicationAdminStatus::Given->value) {
            $record->administered_time = now();
            $record->administered_by = $request->user()->id;
        }
        if ($request->filled('notes')) {
            $record->notes = $request->notes;
        }
        $record->save();

        return response()->json([
            'success' => true,
            'data' => $this->marPayload($record),
            'message' => 'MAR status updated',
        ]);
    }

    /**
     * Nursing assessment templates available for documentation.
     */
    public function assessmentTemplates(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['value' => AssessmentType::Admission->value, 'label' => 'Admission Assessment'],
                ['value' => AssessmentType::Shift->value, 'label' => 'Shift Assessment'],
                ['value' => AssessmentType::Falls->value, 'label' => 'Fall Risk Assessment'],
                ['value' => AssessmentType::PressureUlcer->value, 'label' => 'Pressure Ulcer Assessment'],
                ['value' => AssessmentType::General->value, 'label' => 'General Assessment'],
            ],
        ]);
    }

    /**
     * Nursing assessments for a patient (optionally by type).
     */
    public function assessmentsIndex(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;
        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $query = NursingAssessment::with('patient', 'performedBy')
            ->where('organization_id', $organizationId)
            ->orderByDesc('assessment_time');

        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }
        if ($request->has('type')) {
            $query->where('assessment_type', $request->type);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()->map(fn ($a) => $this->assessmentPayload($a)),
        ]);
    }

    /**
     * Create a nursing assessment (handles falls + pressure ulcer types).
     */
    public function assessmentStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'exists:users,id'],
            'assessment_type' => ['required', 'in:admission,shift,falls,pressure_ulcer,general'],
            'template_name' => ['nullable', 'string', 'max:255'],
            'assessment_data' => ['nullable', 'array'],
            'findings' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'fall_risk_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'fall_risk_level' => ['nullable', 'in:low,moderate,high'],
            'pressure_ulcer_stage' => ['nullable', 'string', 'max:30'],
            'assessment_time' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $organizationId = $request->user()?->organization_id;
        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        User::where('id', $request->patient_id)->where('organization_id', $organizationId)->firstOrFail();

        $fallRiskLevel = $request->fall_risk_level;
        if ($request->assessment_type === AssessmentType::Falls->value && $request->filled('fall_risk_score') && !$fallRiskLevel) {
            $fallRiskLevel = $this->deriveFallRisk($request->fall_risk_score);
        }

        $assessment = NursingAssessment::create([
            'organization_id' => $organizationId,
            'patient_id' => $request->patient_id,
            'assessment_type' => $request->assessment_type,
            'template_name' => $request->template_name,
            'assessment_data' => $request->assessment_data,
            'findings' => $request->findings,
            'notes' => $request->notes,
            'fall_risk_score' => $request->fall_risk_score,
            'fall_risk_level' => $fallRiskLevel,
            'pressure_ulcer_stage' => $request->pressure_ulcer_stage,
            'assessment_time' => $request->assessment_time ?? now(),
            'performed_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->assessmentPayload($assessment),
            'message' => 'Assessment recorded',
        ], 201);
    }

    /**
     * Latest fall risk assessment per patient (for a board).
     */
    public function fallRiskSummary(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;
        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $latest = NursingAssessment::with('patient')
            ->where('organization_id', $organizationId)
            ->where('assessment_type', AssessmentType::Falls->value)
            ->whereNotNull('fall_risk_level')
            ->get()
            ->sortByDesc('assessment_time')
            ->unique('patient_id')
            ->values()
            ->take(50);

        return response()->json([
            'success' => true,
            'data' => $latest->map(fn ($a) => $this->assessmentPayload($a)),
        ]);
    }

    /**
     * Shift handoffs for a patient or unit.
     */
    public function handoffsIndex(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;
        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $query = ShiftHandoff::with('patient', 'fromNurse', 'toNurse')
            ->where('organization_id', $organizationId)
            ->orderByDesc('handoff_time');

        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()->map(fn ($h) => $this->handoffPayload($h)),
        ]);
    }

    /**
     * Create a shift handoff.
     */
    public function handoffStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'exists:users,id'],
            'to_nurse_id' => ['nullable', 'exists:users,id'],
            'unit' => ['nullable', 'string', 'max:50'],
            'clinical_summary' => ['nullable', 'string'],
            'tasks_to_complete' => ['nullable', 'string'],
            'medication_review' => ['nullable', 'string'],
            'safety_concerns' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $organizationId = $request->user()?->organization_id;
        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        User::where('id', $request->patient_id)->where('organization_id', $organizationId)->firstOrFail();

        $handoff = ShiftHandoff::create([
            'organization_id' => $organizationId,
            'patient_id' => $request->patient_id,
            'from_nurse_id' => $request->user()->id,
            'to_nurse_id' => $request->to_nurse_id,
            'unit' => $request->unit,
            'shift_start' => $request->shift_start,
            'shift_end' => $request->shift_end,
            'clinical_summary' => $request->clinical_summary,
            'tasks_to_complete' => $request->tasks_to_complete,
            'medication_review' => $request->medication_review,
            'safety_concerns' => $request->safety_concerns,
            'is_complete' => true,
            'handoff_time' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->handoffPayload($handoff),
            'message' => 'Shift handoff recorded',
        ], 201);
    }

    private function deriveFallRisk(int $score): string
    {
        if ($score >= 45) {
            return FallRiskLevel::High->value;
        }
        if ($score >= 20) {
            return FallRiskLevel::Moderate->value;
        }
        return FallRiskLevel::Low->value;
    }

    private function findCarePlan(Request $request, $id): CarePlan
    {
        return CarePlan::where('id', $id)
            ->where('organization_id', $request->user()?->organization_id)
            ->firstOrFail();
    }

    private function carePlanPayload(CarePlan $plan): array
    {
        return [
            'id' => $plan->id,
            'patient_id' => $plan->patient_id,
            'patient_name' => $plan->patient?->name,
            'title' => $plan->title,
            'description' => $plan->description,
            'goals' => $plan->goals,
            'interventions' => $plan->interventions,
            'status' => $plan->status?->value,
            'assignee_name' => $plan->assignee?->name,
            'creator_name' => $plan->creator?->name,
            'started_at' => $plan->started_at?->toDateString(),
            'due_date' => $plan->due_date?->toDateString(),
            'completed_at' => $plan->completed_at?->toDateString(),
        ];
    }

    private function marPayload(MedicationAdministration $ma): array
    {
        return [
            'id' => $ma->id,
            'patient_id' => $ma->patient_id,
            'patient_name' => $ma->patient?->name,
            'prescription_id' => $ma->prescription_id,
            'medication_name' => $ma->medication_name,
            'dose' => $ma->dose,
            'dose_unit' => $ma->dose_unit,
            'route' => $ma->route,
            'scheduled_time' => $ma->scheduled_time?->toISOString(),
            'administered_time' => $ma->administered_time?->toISOString(),
            'status' => $ma->status?->value,
            'administered_by_name' => $ma->administeredBy?->name,
            'notes' => $ma->notes,
            'vitals_before' => $ma->vitals_before,
        ];
    }

    private function assessmentPayload(NursingAssessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'patient_id' => $assessment->patient_id,
            'patient_name' => $assessment->patient?->name,
            'assessment_type' => $assessment->assessment_type?->value,
            'template_name' => $assessment->template_name,
            'assessment_data' => $assessment->assessment_data,
            'findings' => $assessment->findings,
            'notes' => $assessment->notes,
            'assessment_time' => $assessment->assessment_time?->toISOString(),
            'performed_by_name' => $assessment->performedBy?->name,
            'fall_risk_score' => $assessment->fall_risk_score,
            'fall_risk_level' => $assessment->fall_risk_level?->value,
            'pressure_ulcer_stage' => $assessment->pressure_ulcer_stage,
        ];
    }

    private function handoffPayload(ShiftHandoff $handoff): array
    {
        return [
            'id' => $handoff->id,
            'patient_id' => $handoff->patient_id,
            'patient_name' => $handoff->patient?->name,
            'from_nurse_name' => $handoff->fromNurse?->name,
            'to_nurse_name' => $handoff->toNurse?->name,
            'unit' => $handoff->unit,
            'clinical_summary' => $handoff->clinical_summary,
            'tasks_to_complete' => $handoff->tasks_to_complete,
            'medication_review' => $handoff->medication_review,
            'safety_concerns' => $handoff->safety_concerns,
            'is_complete' => $handoff->is_complete,
            'handoff_time' => $handoff->handoff_time?->toISOString(),
        ];
    }
}
