<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class InventoryController extends Controller
{
    /**
     * List inventory items for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $items = InventoryItem::where('organization_id', $organizationId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'item_type' => $item->item_type,
                    'status' => $item->status,
                    'quantity_on_hand' => $item->quantity_on_hand,
                    'minimum_stock_level' => $item->minimum_stock_level,
                    'maximum_stock_level' => $item->maximum_stock_level,
                    'batch_number' => $item->batch_number,
                    'expiration_date' => $item->expiration_date,
                    'supplier' => $item->supplier,
                    'is_low_stock' => $item->isLowStock(),
                    'is_over_stock' => $item->isOverStock(),
                    'needs_reorder' => $item->needsReorder(),
                ];
            }),
        ]);
    }

    /**
     * Update inventory quantity (admin only).
     */
    public function updateQuantity(Request $request, $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $item = InventoryItem::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'quantity_on_hand' => ['required', 'integer', 'min:0'],
            'reason' => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $item->quantity_on_hand = $request->quantity_on_hand;
        $item->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $item->id,
                'name' => $item->name,
                'quantity_on_hand' => $item->quantity_on_hand,
                'status' => $item->status,
                'is_low_stock' => $item->isLowStock(),
                'is_over_stock' => $item->isOverStock(),
                'message' => 'Inventory quantity updated successfully',
            ],
        ]);
    }

    /**
     * Record inventory adjustment (used when dispensing medications, etc.).
     */
    public function adjust(Request $request, $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $item = InventoryItem::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'adjustment' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $item->quantity_on_hand += $request->adjustment;
        // Ensure quantity doesn't go below 0
        if ($item->quantity_on_hand < 0) {
            $item->quantity_on_hand = 0;
        }
        $item->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $item->id,
                'name' => $item->name,
                'quantity_on_hand' => $item->quantity_on_hand,
                'status' => $item->status,
                'message' => 'Inventory adjusted successfully',
            ],
        ]);
    }

    /**
     * Get a single inventory item.
     */
    public function show($id): JsonResponse
    {
        $organizationId = request()->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $item = InventoryItem::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'item_type' => $item->item_type,
                'status' => $item->status,
                'quantity_on_hand' => $item->quantity_on_hand,
                'minimum_stock_level' => $item->minimum_stock_level,
                'maximum_stock_level' => $item->maximum_stock_level,
                'batch_number' => $item->batch_number,
                'expiration_date' => $item->expiration_date,
                'supplier' => $item->supplier,
                'is_low_stock' => $item->isLowStock(),
                'is_over_stock' => $item->isOverStock(),
                'needs_reorder' => $item->needsReorder(),
            ],
        ]);
    }
}