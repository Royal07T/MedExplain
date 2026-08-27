<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $items = InventoryItem::where('organization_id', $orgId)
            ->orderBy('name')
            ->get();

        $summary = [
            'total_items' => $items->count(),
            'low_stock' => $items->filter->needsReorder()->count(),
            'out_of_stock' => $items->where('quantity_on_hand', 0)->count(),
        ];

        return response()->json([
            'data' => [
                'items' => $items,
                'summary' => $summary,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:255|unique:inventory_items,sku',
            'item_type' => 'nullable|string|max:255',
            'quantity_on_hand' => 'required|integer|min:0',
            'minimum_stock_level' => 'nullable|integer|min:0',
            'maximum_stock_level' => 'nullable|integer|min:0',
            'batch_number' => 'nullable|string|max:255',
            'expiration_date' => 'nullable|date',
            'supplier' => 'nullable|string|max:255',
        ]);

        $item = InventoryItem::create([
            ...$validated,
            'organization_id' => $request->user()->organization_id,
            'status' => 'in_stock',
        ]);

        return response()->json(['data' => $item], 201);
    }

    public function show(Request $request, InventoryItem $item): JsonResponse
    {
        if ($item->organization_id !== $request->user()->organization_id) {
            abort(403, 'Forbidden.');
        }

        return response()->json(['data' => $item]);
    }

    public function update(Request $request, InventoryItem $item): JsonResponse
    {
        if ($item->organization_id !== $request->user()->organization_id) {
            abort(403, 'Forbidden.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'item_type' => 'nullable|string|max:255',
            'quantity_on_hand' => 'sometimes|integer|min:0',
            'minimum_stock_level' => 'nullable|integer|min:0',
            'maximum_stock_level' => 'nullable|integer|min:0',
            'batch_number' => 'nullable|string|max:255',
            'expiration_date' => 'nullable|date',
            'supplier' => 'nullable|string|max:255',
        ]);

        $item->update($validated);

        return response()->json(['data' => $item]);
    }

    public function destroy(Request $request, InventoryItem $item): JsonResponse
    {
        if ($item->organization_id !== $request->user()->organization_id) {
            abort(403, 'Forbidden.');
        }

        $item->delete();

        return response()->json(['message' => 'Inventory item deleted.']);
    }
}
