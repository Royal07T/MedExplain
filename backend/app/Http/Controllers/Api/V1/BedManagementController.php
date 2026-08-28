<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class BedManagementController extends Controller
{
    /**
     * List wards for the organization.
     */
    public function wardsIndex(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $wards = Ward::withCount('beds')
            ->withCount(['beds as occupied_beds_count' => fn ($q) => $q->where('is_occupied', true)])
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $wards->map(fn ($ward) => [
                'id' => $ward->id,
                'name' => $ward->name,
                'code' => $ward->code,
                'floor' => $ward->floor,
                'location' => $ward->location,
                'capacity' => $ward->capacity,
                'beds_count' => $ward->beds_count,
                'occupied_beds_count' => $ward->occupied_beds_count,
                'is_active' => $ward->is_active,
            ]),
        ]);
    }

    /**
     * Store a new ward.
     */
    public function wardStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'floor' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'department_id' => ['nullable', 'exists:departments,id'],
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

        $ward = Ward::create([
            'organization_id' => $organizationId,
            'department_id' => $request->department_id,
            'name' => $request->name,
            'code' => $request->code,
            'floor' => $request->floor,
            'location' => $request->location,
            'capacity' => $request->capacity,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $ward->id,
                'name' => $ward->name,
                'code' => $ward->code,
                'floor' => $ward->floor,
                'location' => $ward->location,
                'capacity' => $ward->capacity,
                'is_active' => $ward->is_active,
            ],
            'message' => 'Ward created successfully',
        ], 201);
    }

    /**
     * List beds in a ward.
     */
    public function bedsIndex(Request $request, $wardId): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $ward = Ward::where('id', $wardId)->where('organization_id', $organizationId)->firstOrFail();

        $beds = Bed::with(['assignments' => fn ($q) => $q->whereNull('discharged_at')])
            ->where('ward_id', $ward->id)
            ->where('organization_id', $organizationId)
            ->orderBy('bed_number')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $beds->map(fn ($bed) => $this->bedPayload($bed)),
        ]);
    }

    /**
     * Add beds to a ward.
     */
    public function bedStore(Request $request, $wardId, ?int $count = null): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $ward = Ward::where('id', $wardId)->where('organization_id', $organizationId)->firstOrFail();

        $numToCreate = $count ?? (int) $request->input('count', 1);
        $bedType = $request->input('bed_type', 'standard');

        if ($numToCreate < 1 || $numToCreate > 100) {
            return response()->json([
                'success' => false,
                'message' => 'count must be between 1 and 100',
            ], 422);
        }

        $nextNumber = $this->nextBedNumber($ward);

        $created = [];
        for ($i = 0; $i < $numToCreate; $i++) {
            $created[] = Bed::create([
                'organization_id' => $organizationId,
                'ward_id' => $ward->id,
                'bed_number' => $nextNumber + $i,
                'bed_type' => $bedType,
            ]);
        }

        $bed = $created[0];

        return response()->json([
            'success' => true,
            'data' => [
                'ward_id' => $ward->id,
                'created_count' => count($created),
                'first_bed_number' => $bed->bed_number,
            ],
            'message' => 'Beds created successfully',
        ], 201);
    }

    /**
     * Assign a patient to a bed.
     */
    public function assignBed(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => ['required', 'exists:users,id'],
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

        $bed = Bed::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        if ($bed->is_occupied) {
            return response()->json([
                'success' => false,
                'message' => 'Bed is already occupied',
            ], 409);
        }

        $patient = User::where('id', $request->patient_id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        BedAssignment::create([
            'organization_id' => $organizationId,
            'bed_id' => $bed->id,
            'patient_id' => $patient->id,
            'assigned_by' => $request->user()->id,
        ]);

        $bed->is_occupied = true;
        $bed->cleaning_status = 'occupied';
        $bed->save();

        return response()->json([
            'success' => true,
            'data' => $this->bedPayload($bed->refresh()),
            'message' => 'Patient assigned to bed successfully',
        ]);
    }

    /**
     * Discharge a patient from a bed.
     */
    public function dischargeBed(Request $request, $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $bed = Bed::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $active = $bed->assignments()->whereNull('discharged_at')->first();

        if ($active) {
            $active->discharged_at = now();
            $active->save();
        }

        $bed->is_occupied = false;
        $bed->cleaning_status = 'needs_cleaning';
        $bed->save();

        return response()->json([
            'success' => true,
            'data' => $this->bedPayload($bed->refresh()),
            'message' => 'Patient discharged from bed',
        ]);
    }

    /**
     * Update bed cleaning status.
     */
    public function updateCleaning(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cleaning_status' => ['required', 'in:clean,needs_cleaning,being_cleaned,occupied'],
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

        $bed = Bed::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $bed->cleaning_status = $request->cleaning_status;
        $bed->save();

        return response()->json([
            'success' => true,
            'data' => $this->bedPayload($bed->refresh()),
            'message' => 'Cleaning status updated',
        ]);
    }

    /**
     * Bed utilization analytics.
     */
    public function utilization(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json(['success' => false, 'message' => 'No organization context'], 403);
        }

        $wards = Ward::withCount('beds')
            ->withCount(['beds as occupied_beds_count' => fn ($q) => $q->where('is_occupied', true)])
            ->where('organization_id', $organizationId)
            ->get();

        $totalBeds = $wards->sum('beds_count');
        $occupiedBeds = $wards->sum(fn ($w) => $w->occupied_beds_count);
        $available = $totalBeds - $occupiedBeds;

        return response()->json([
            'success' => true,
            'data' => [
                'total_beds' => $totalBeds,
                'occupied_beds' => $occupiedBeds,
                'available_beds' => $available,
                'utilization_rate' => $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0,
                'wards' => $wards->map(fn ($w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'beds_count' => $w->beds_count,
                    'occupied_beds_count' => $w->occupied_beds_count,
                    'utilization_rate' => $w->beds_count > 0 ? round(($w->occupied_beds_count / $w->beds_count) * 100, 1) : 0,
                ]),
            ],
        ]);
    }

    /**
     * Build a bed payload including active assignment info.
     */
    private function bedPayload(Bed $bed): array
    {
        $active = $bed->relationLoaded('assignments')
            ? $bed->assignments->firstWhere('discharged_at', null)
            : $bed->assignments()->whereNull('discharged_at')->first();

        return [
            'id' => $bed->id,
            'ward_id' => $bed->ward_id,
            'bed_number' => $bed->bed_number,
            'bed_type' => $bed->bed_type,
            'is_occupied' => $bed->is_occupied,
            'cleaning_status' => $bed->cleaning_status,
            'notes' => $bed->notes,
            'current_patient' => $active ? [
                'id' => $active->patient_id,
                'name' => $active->patient?->name,
                'assigned_at' => $active->assigned_at?->toISOString(),
            ] : null,
        ];
    }

    /**
     * Determine the next bed number for a ward.
     */
    private function nextBedNumber(Ward $ward): int
    {
        return (int) $ward->beds()->max('bed_number') + 1;
    }
}
