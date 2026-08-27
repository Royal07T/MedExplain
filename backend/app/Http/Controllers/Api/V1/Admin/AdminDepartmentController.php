<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $departments = Department::where('organization_id', $orgId)
            ->withCount('clinicians')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $departments]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:departments,code',
            'description' => 'nullable|string',
            'capacity' => 'nullable|integer|min:0',
        ]);

        $department = Department::create([
            ...$validated,
            'organization_id' => $request->user()->organization_id,
        ]);

        return response()->json(['data' => $department], 201);
    }

    public function show(Request $request, Department $department): JsonResponse
    {
        if ($department->organization_id !== $request->user()->organization_id) {
            abort(403, 'Forbidden.');
        }

        $department->loadCount('clinicians');

        return response()->json(['data' => $department]);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        if ($department->organization_id !== $request->user()->organization_id) {
            abort(403, 'Forbidden.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'nullable|integer|min:0',
        ]);

        $department->update($validated);

        return response()->json(['data' => $department]);
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        if ($department->organization_id !== $request->user()->organization_id) {
            abort(403, 'Forbidden.');
        }

        $department->delete();

        return response()->json(['message' => 'Department deleted.']);
    }
}
