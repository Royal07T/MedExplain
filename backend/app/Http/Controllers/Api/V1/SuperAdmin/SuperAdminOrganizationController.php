<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminOrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizations = Organization::withCount(['users', 'patients'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $organizations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:organizations,slug',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
        ]);

        $organization = Organization::create($validated);

        return response()->json(['data' => $organization], 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        $organization->loadCount(['users', 'patients']);

        return response()->json(['data' => $organization]);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $organization->update($validated);

        return response()->json(['data' => $organization]);
    }
}
