<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AcuityLevel;
use App\Http\Controllers\Controller;
use App\Models\AmbulanceDispatch;
use App\Models\EmergencyVisit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class EmergencyDepartmentController extends Controller
{
    /**
     * Check a patient into the emergency department (creates an ED visit).
     */
    public function checkIn(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'exists:users,id'],
            'chief_complaint' => ['nullable', 'string', 'max:500'],
            'vitals_summary' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        User::where('id', $request->patient_id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $existing = EmergencyVisit::where('patient_id', $request->patient_id)
            ->where('organization_id', $organizationId)
            ->whereNull('departure_time')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Patient already has an active ED visit',
            ], 409);
        }

        $visit = EmergencyVisit::create([
            'organization_id' => $organizationId,
            'patient_id' => $request->patient_id,
            'chief_complaint' => $request->chief_complaint,
            'vitals_summary' => $request->vitals_summary,
            'notes' => $request->notes,
            'acuity_level' => AcuityLevel::Nonurgent->value,
            'queue_status' => 'waiting',
            'arrival_time' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->visitPayload($visit),
            'message' => 'Patient checked into ED',
        ], 201);
    }

    /**
     * Assign triage acuity to an ED visit.
     */
    public function triage(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'acuity_level' => ['required', 'in:resuscitation,emergent,urgent,non-urgent'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $visit = $this->findVisit($request, $id);

        $visit->acuity_level = $request->acuity_level;
        $visit->queue_status = 'in_triage';
        $visit->triage_nurse_id = $request->user()->id;
        $visit->save();

        return response()->json([
            'success' => true,
            'data' => $this->visitPayload($visit),
            'message' => 'Triage acuity assigned',
        ]);
    }

    /**
     * Assign a clinician to see an ED visit (rapid assessment).
     */
    public function assignClinician(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'clinician_id' => ['required', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $visit = $this->findVisit($request, $id);

        $visit->clinician_id = $request->clinician_id;
        $visit->queue_status = 'being_seen';
        $visit->seen_by_clinician_at = now();
        $visit->save();

        return response()->json([
            'success' => true,
            'data' => $this->visitPayload($visit),
            'message' => 'Clinician assigned',
        ]);
    }

    /**
     * Update queue status for an ED visit.
     */
    public function updateQueue(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'queue_status' => ['required', 'in:waiting,in_triage,being_seen,admitted,discharged'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $visit = $this->findVisit($request, $id);

        $visit->queue_status = $request->queue_status;
        if (in_array($request->queue_status, ['admitted', 'discharged'], true) && !$visit->departure_time) {
            $visit->departure_time = now();
        }
        $visit->save();

        return response()->json([
            'success' => true,
            'data' => $this->visitPayload($visit),
            'message' => 'Queue status updated',
        ]);
    }

    /**
     * Set the disposition for an ED visit (admitted or discharged).
     */
    public function disposition(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'disposition' => ['required', 'in:admitted,discharged,transferred,observation'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $visit = $this->findVisit($request, $id);

        $visit->disposition = $request->disposition;
        if (in_array($request->disposition, ['admitted', 'transferred', 'observation'], true)) {
            $visit->queue_status = 'admitted';
        } else {
            $visit->queue_status = 'discharged';
        }
        $visit->departure_time = now();
        $visit->save();

        return response()->json([
            'success' => true,
            'data' => $this->visitPayload($visit),
            'message' => 'Disposition recorded',
        ]);
    }

    /**
     * ED track board: active visits sorted by acuity then arrival time.
     */
    public function trackBoard(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $visits = EmergencyVisit::with('patient')
            ->where('organization_id', $organizationId)
            ->whereNull('departure_time')
            ->get()
            ->sortBy([['acuity_priority', 'asc'], ['arrival_time', 'asc']], SORT_REGULAR);

        return response()->json([
            'success' => true,
            'data' => $visits->map(fn ($visit) => $this->visitPayload($visit)),
        ]);
    }

    /**
     * Dispatch an ambulance.
     */
    public function dispatchAmbulance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'emergency_visit_id' => ['nullable', 'exists:emergency_visits,id'],
            'patient_id' => ['nullable', 'exists:users,id'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'destination_hospital' => ['nullable', 'string', 'max:255'],
            'vehicle_id' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        if ($request->emergency_visit_id) {
            $this->findVisit($request, $request->emergency_visit_id);
        }

        $dispatch = AmbulanceDispatch::create([
            'organization_id' => $organizationId,
            'emergency_visit_id' => $request->emergency_visit_id,
            'patient_id' => $request->patient_id,
            'status' => \App\Enums\AmbulanceDispatchStatus::Dispatched->value,
            'pickup_location' => $request->pickup_location,
            'destination_hospital' => $request->destination_hospital,
            'vehicle_id' => $request->vehicle_id,
            'dispatched_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->dispatchPayload($dispatch),
            'message' => 'Ambulance dispatched',
        ], 201);
    }

    /**
     * Advance ambulance dispatch status (timeline).
     */
    public function updateAmbulance(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:dispatched,en_route,on_scene,transporting,delivered'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $dispatch = AmbulanceDispatch::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $dispatch->status = $request->status;
        $timestamps = [
            'dispatched' => 'dispatched_at',
            'en_route' => 'en_route_at',
            'on_scene' => 'on_scene_at',
            'transporting' => 'transporting_at',
            'delivered' => 'delivered_at',
        ];
        $dispatch->{$timestamps[$request->status]} = now();
        $dispatch->save();

        return response()->json([
            'success' => true,
            'data' => $this->dispatchPayload($dispatch),
            'message' => 'Ambulance status updated',
        ]);
    }

    /**
     * ED dashboard analytics (crowding metrics, length of stay, arrivals).
     */
    public function dashboard(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $active = EmergencyVisit::where('organization_id', $organizationId)
            ->whereNull('departure_time')
            ->get();

        $arrivalsToday = EmergencyVisit::where('organization_id', $organizationId)
            ->whereDate('arrival_time', now()->toDateString())
            ->count();

        $acuityCounts = collect(['resuscitation', 'emergent', 'urgent', 'non-urgent'])
            ->mapWithKeys(fn ($level) => [$level => 0])
            ->merge($active->groupBy(fn ($v) => $v->acuity_level?->value)->map->count())
            ->toArray();

        // Average length of stay for active visits (minutes, capped until departure).
        $avgLos = $active->count() > 0
            ? round($active->avg(fn ($v) => $v->length_of_stay_minutes), 1)
            : 0;

        $activeAmbulances = AmbulanceDispatch::where('organization_id', $organizationId)
            ->where('status', '!=', 'delivered')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'active_visits' => $active->count(),
                'arrivals_today' => $arrivalsToday,
                'acuity_breakdown' => $acuityCounts,
                'average_los_minutes' => $avgLos,
                'crowding_ratio' => $active->count() > 0 ? round($active->count() / max(1, $active->count()), 2) : 0,
                'active_ambulances' => $activeAmbulances->map(fn ($d) => $this->dispatchPayload($d)),
            ],
        ]);
    }

    private function findVisit(Request $request, $id): EmergencyVisit
    {
        return EmergencyVisit::where('id', $id)
            ->where('organization_id', $request->user()?->organization_id)
            ->firstOrFail();
    }

    private function visitPayload(EmergencyVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'patient_name' => $visit->patient?->name,
            'chief_complaint' => $visit->chief_complaint,
            'acuity_level' => $visit->acuity_level?->value,
            'queue_status' => $visit->queue_status,
            'disposition' => $visit->disposition,
            'arrival_time' => $visit->arrival_time?->toISOString(),
            'seen_by_clinician_at' => $visit->seen_by_clinician_at?->toISOString(),
            'departure_time' => $visit->departure_time?->toISOString(),
            'length_of_stay_minutes' => $visit->length_of_stay_minutes,
            'clinician_name' => $visit->clinician?->name,
            'triage_nurse_name' => $visit->triageNurse?->name,
            'vitals_summary' => $visit->vitals_summary,
            'notes' => $visit->notes,
        ];
    }

    private function dispatchPayload(AmbulanceDispatch $dispatch): array
    {
        return [
            'id' => $dispatch->id,
            'emergency_visit_id' => $dispatch->emergency_visit_id,
            'patient_name' => $dispatch->patient?->name,
            'status' => $dispatch->status?->value,
            'pickup_location' => $dispatch->pickup_location,
            'destination_hospital' => $dispatch->destination_hospital,
            'vehicle_id' => $dispatch->vehicle_id,
            'dispatched_at' => $dispatch->dispatched_at?->toISOString(),
            'en_route_at' => $dispatch->en_route_at?->toISOString(),
            'on_scene_at' => $dispatch->on_scene_at?->toISOString(),
            'transporting_at' => $dispatch->transporting_at?->toISOString(),
            'delivered_at' => $dispatch->delivered_at?->toISOString(),
        ];
    }
}
