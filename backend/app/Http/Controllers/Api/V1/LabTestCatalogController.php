<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LabTestCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class LabTestCatalogController extends Controller
{
    /**
     * List lab test catalog for the organization.
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

        $query = LabTestCatalog::where('organization_id', $organizationId);

        // Filter active only
        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        // Search by name or code
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('test_name', 'like', '%' . $request->search . '%')
                    ->orWhere('test_code', 'like', '%' . $request->search . '%');
            });
        }

        $catalog = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $catalog->map(function ($test) {
                return [
                    'id' => $test->id,
                    'test_code' => $test->test_code,
                    'test_name' => $test->test_name,
                    'description' => $test->description,
                    'category' => $test->category,
                    'specimen_type' => $test->specimen_type,
                    'container_type' => $test->container_type,
                    'turnaround_hours' => $test->turnaround_hours,
                    'cost' => $test->cost,
                    'reference_ranges' => $test->reference_ranges,
                    'critical_values' => $test->critical_values,
                    'is_active' => $test->is_active,
                    'notes' => $test->notes,
                ];
            }),
        ]);
    }

    /**
     * Create a new lab test catalog entry.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'test_code' => ['required', 'string', 'max:50', 'unique:lab_test_catalogs,test_code'],
            'test_name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'specimen_type' => ['sometimes', 'string', 'max:50'],
            'container_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'turnaround_hours' => ['sometimes', 'integer', 'min:1'],
            'cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'reference_ranges' => ['sometimes', 'nullable', 'array'],
            'critical_values' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
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

        $catalog = LabTestCatalog::create([
            'organization_id' => $organizationId,
            'test_code' => $request->test_code,
            'test_name' => $request->test_name,
            'description' => $request->description,
            'category' => $request->category,
            'specimen_type' => $request->specimen_type ?? 'blood',
            'container_type' => $request->container_type,
            'turnaround_hours' => $request->turnaround_hours ?? 24,
            'cost' => $request->cost,
            'reference_ranges' => $request->reference_ranges,
            'critical_values' => $request->critical_values,
            'is_active' => $request->boolean('is_active', true),
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $catalog->id,
                'message' => 'Lab test catalog entry created successfully',
            ],
        ], 201);
    }

    /**
     * Update lab test catalog entry.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'test_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'specimen_type' => ['sometimes', 'string', 'max:50'],
            'container_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'turnaround_hours' => ['sometimes', 'integer', 'min:1'],
            'cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'reference_ranges' => ['sometimes', 'nullable', 'array'],
            'critical_values' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
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

        $catalog = LabTestCatalog::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        if ($request->has('test_name')) {
            $catalog->test_name = $request->test_name;
        }
        if ($request->has('description')) {
            $catalog->description = $request->description;
        }
        if ($request->has('category')) {
            $catalog->category = $request->category;
        }
        if ($request->has('specimen_type')) {
            $catalog->specimen_type = $request->specimen_type;
        }
        if ($request->has('container_type')) {
            $catalog->container_type = $request->container_type;
        }
        if ($request->has('turnaround_hours')) {
            $catalog->turnaround_hours = $request->turnaround_hours;
        }
        if ($request->has('cost')) {
            $catalog->cost = $request->cost;
        }
        if ($request->has('reference_ranges')) {
            $catalog->reference_ranges = $request->reference_ranges;
        }
        if ($request->has('critical_values')) {
            $catalog->critical_values = $request->critical_values;
        }
        if ($request->has('is_active')) {
            $catalog->is_active = $request->is_active;
        }
        if ($request->has('notes')) {
            $catalog->notes = $request->notes;
        }
        $catalog->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $catalog->id,
                'message' => 'Lab test catalog entry updated successfully',
            ],
        ]);
    }

    /**
     * Delete lab test catalog entry.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $catalog = LabTestCatalog::where('id', $id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $catalog->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lab test catalog entry deleted successfully',
        ]);
    }
}
