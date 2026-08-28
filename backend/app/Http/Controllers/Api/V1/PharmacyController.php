<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DrugInventory;
use App\Models\Formulary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class PharmacyController extends Controller
{
    /**
     * List drug inventory for the organization.
     */
    public function inventoryIndex(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $query = DrugInventory::where('organization_id', $organizationId)
            ->with('medication');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter low stock
        if ($request->boolean('low_stock', false)) {
            $query->lowStock();
        }

        // Filter expiring soon
        if ($request->boolean('expiring_soon', false)) {
            $query->expiringSoon();
        }

        // Filter expired
        if ($request->boolean('expired', false)) {
            $query->expired();
        }

        $inventory = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $inventory->map(function ($item) {
                return [
                    'id' => $item->id,
                    'medication_id' => $item->medication_id,
                    'medication_name' => $item->medication?->name,
                    'batch_number' => $item->batch_number,
                    'expiry_date' => $item->expiry_date?->toISOString(),
                    'quantity_on_hand' => $item->quantity_on_hand,
                    'minimum_stock_level' => $item->minimum_stock_level,
                    'maximum_stock_level' => $item->maximum_stock_level,
                    'location' => $item->location,
                    'supplier' => $item->supplier,
                    'unit_cost' => $item->unit_cost,
                    'status' => $item->status,
                    'is_low_stock' => $item->isLowStock(),
                    'is_expired' => $item->isExpired(),
                    'is_expiring_soon' => $item->isExpiringSoon(),
                    'notes' => $item->notes,
                ];
            }),
        ]);
    }

    /**
     * Create a new inventory item.
     */
    public function inventoryStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'medication_id' => ['required', 'exists:medications,id'],
            'batch_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'expiry_date' => ['required', 'date'],
            'quantity_on_hand' => ['required', 'integer', 'min:0'],
            'minimum_stock_level' => ['sometimes', 'integer', 'min:0'],
            'maximum_stock_level' => ['sometimes', 'integer', 'min:0'],
            'location' => ['sometimes', 'nullable', 'string', 'max:100'],
            'supplier' => ['sometimes', 'nullable', 'string', 'max:255'],
            'unit_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:available,reserved,expired,recalled'],
            'notes' => ['sometimes', 'nullable', 'string'],
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
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $inventory = DrugInventory::create([
            'organization_id' => $organizationId,
            'medication_id' => $request->medication_id,
            'batch_number' => $request->batch_number,
            'expiry_date' => $request->expiry_date,
            'quantity_on_hand' => $request->quantity_on_hand,
            'minimum_stock_level' => $request->minimum_stock_level ?? 10,
            'maximum_stock_level' => $request->maximum_stock_level ?? 1000,
            'location' => $request->location,
            'supplier' => $request->supplier,
            'unit_cost' => $request->unit_cost,
            'status' => $request->status ?? 'available',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $inventory->id,
                'message' => 'Inventory item created successfully',
            ],
        ], 201);
    }

    /**
     * Update inventory item.
     */
    public function inventoryUpdate(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quantity_on_hand' => ['sometimes', 'integer', 'min:0'],
            'minimum_stock_level' => ['sometimes', 'integer', 'min:0'],
            'maximum_stock_level' => ['sometimes', 'integer', 'min:0'],
            'location' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'in:available,reserved,expired,recalled'],
            'notes' => ['sometimes', 'nullable', 'string'],
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
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $inventory = DrugInventory::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        if ($request->has('quantity_on_hand')) {
            $inventory->quantity_on_hand = $request->quantity_on_hand;
        }
        if ($request->has('minimum_stock_level')) {
            $inventory->minimum_stock_level = $request->minimum_stock_level;
        }
        if ($request->has('maximum_stock_level')) {
            $inventory->maximum_stock_level = $request->maximum_stock_level;
        }
        if ($request->has('location')) {
            $inventory->location = $request->location;
        }
        if ($request->has('status')) {
            $inventory->status = $request->status;
        }
        if ($request->has('notes')) {
            $inventory->notes = $request->notes;
        }
        $inventory->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $inventory->id,
                'message' => 'Inventory item updated successfully',
            ],
        ]);
    }

    /**
     * List formulary for the organization.
     */
    public function formularyIndex(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $query = Formulary::where('organization_id', $organizationId)
            ->with('medication');

        // Filter active only
        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        // Filter by tier
        if ($request->has('tier')) {
            $query->byTier($request->tier);
        }

        // Filter requiring authorization
        if ($request->boolean('requires_auth', false)) {
            $query->requiresAuth();
        }

        $formulary = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $formulary->map(function ($item) {
                return [
                    'id' => $item->id,
                    'medication_id' => $item->medication_id,
                    'medication_name' => $item->medication?->name,
                    'formulary_code' => $item->formulary_code,
                    'tier' => $item->tier,
                    'requires_prior_authorization' => $item->requires_prior_authorization,
                    'quantity_limit' => $item->quantity_limit,
                    'days_supply_limit' => $item->days_supply_limit,
                    'restrictions' => $item->restrictions,
                    'alternatives' => $item->alternatives,
                    'is_active' => $item->is_active,
                    'is_currently_active' => $item->isCurrentlyActive(),
                    'effective_date' => $item->effective_date?->toISOString(),
                    'discontinued_date' => $item->discontinued_date?->toISOString(),
                    'notes' => $item->notes,
                ];
            }),
        ]);
    }

    /**
     * Create a new formulary entry.
     */
    public function formularyStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'medication_id' => ['required', 'exists:medications,id'],
            'formulary_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'tier' => ['required', 'in:generic,preferred_brand,non_preferred,specialty'],
            'requires_prior_authorization' => ['sometimes', 'boolean'],
            'quantity_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'days_supply_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'restrictions' => ['sometimes', 'nullable', 'string'],
            'alternatives' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'effective_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
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
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $formulary = Formulary::create([
            'organization_id' => $organizationId,
            'medication_id' => $request->medication_id,
            'formulary_code' => $request->formulary_code,
            'tier' => $request->tier,
            'requires_prior_authorization' => $request->boolean('requires_prior_authorization', false),
            'quantity_limit' => $request->quantity_limit,
            'days_supply_limit' => $request->days_supply_limit,
            'restrictions' => $request->restrictions,
            'alternatives' => $request->alternatives,
            'is_active' => $request->boolean('is_active', true),
            'effective_date' => $request->effective_date,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $formulary->id,
                'message' => 'Formulary entry created successfully',
            ],
        ], 201);
    }

    /**
     * Update formulary entry.
     */
    public function formularyUpdate(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tier' => ['sometimes', 'in:generic,preferred_brand,non_preferred,specialty'],
            'requires_prior_authorization' => ['sometimes', 'boolean'],
            'quantity_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'days_supply_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'restrictions' => ['sometimes', 'nullable', 'string'],
            'alternatives' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'discontinued_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
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
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $formulary = Formulary::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        if ($request->has('tier')) {
            $formulary->tier = $request->tier;
        }
        if ($request->has('requires_prior_authorization')) {
            $formulary->requires_prior_authorization = $request->requires_prior_authorization;
        }
        if ($request->has('quantity_limit')) {
            $formulary->quantity_limit = $request->quantity_limit;
        }
        if ($request->has('days_supply_limit')) {
            $formulary->days_supply_limit = $request->days_supply_limit;
        }
        if ($request->has('restrictions')) {
            $formulary->restrictions = $request->restrictions;
        }
        if ($request->has('alternatives')) {
            $formulary->alternatives = $request->alternatives;
        }
        if ($request->has('is_active')) {
            $formulary->is_active = $request->is_active;
        }
        if ($request->has('discontinued_date')) {
            $formulary->discontinued_date = $request->discontinued_date;
        }
        if ($request->has('notes')) {
            $formulary->notes = $request->notes;
        }
        $formulary->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $formulary->id,
                'message' => 'Formulary entry updated successfully',
            ],
        ]);
    }
}
