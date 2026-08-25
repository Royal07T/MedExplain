<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class AdministrationController extends Controller
{
    /**
     * List all organizations.
     */
    public function organizations(Request $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        // Only super admins can list all organizations
        if (!$request->user()->hasAnyRole(['super_admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'No permission',
            ], 403);
        }

        $organizations = Organization::latest('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => $organizations->map(function ($org) {
                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'is_active' => $org->is_active,
                    'created_at' => $org->created_at?->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * List departments for an organization.
     */
    public function departments(Request $request, $organizationId): JsonResponse
    {
        $orgId = $request->user()?->organization_id;

        if (!$orgId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        // Check access - user must belong to the organization or be super_admin
        if (!$request->user()->hasAnyRole(['super_admin', 'admin']) &&
            !$request->user()->organization_id == $organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No permission',
            ], 403);
        }

        $departments = Department::where('organization_id', $organizationId)
            ->latest('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $departments->map(function ($dept) {
                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'code' => $dept->code,
                    'description' => $dept->description,
                    'capacity' => $dept->capacity,
                    'clinicians_count' => $dept->clinicians()->count(),
                    'nurses_count' => $dept->nurses()->count(),
                ];
            }),
        ]);
    }

    /**
     * Create a department.
     */
    public function storeDepartment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => ['required', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['sometimes', 'string', 'max:500'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Only super admins or admins of the organization can create departments
        if (!$request->user()->hasAnyRole(['super_admin', 'admin']) &&
            $request->user()->organization_id != $request->organization_id) {
            return response()->json([
                'success' => false,
                'message' => 'No permission',
            ], 403);
        }

        $department = Department::create([
            'organization_id' => $request->organization_id,
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'capacity' => $request->capacity,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'description' => $department->description,
                'capacity' => $department->capacity,
                'message' => 'Department created successfully',
            ],
        ], 201);
    }

    /**
     * Assign user to department.
     */
    public function assignUser(Request $request, $userId, $departmentId): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $department = Department::where('id', $departmentId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $user = User::findOrFail($userId);

        // Check permissions
        $hasPermission = $request->user()->hasAnyRole(['super_admin', 'admin']) ||
            ($request->user()->id == $userId);

        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'message' => 'No permission',
            ], 403);
        }

        // Remove user from existing departments first
        $user->departments()->detach();

        // Assign to new department
        $user->departments()->attach($departmentId);

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'department_id' => $department->id,
                'department_name' => $department->name,
                'message' => 'User assigned to department successfully',
            ],
        ]);
    }

    /**
     * Get a single organization.
     */
    public function showOrganization($id): JsonResponse
    {
        $organizationId = request()->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'No organization context',
            ], 403);
        }

        $organization = Organization::where('id', $id)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'address' => $organization->address,
                'phone' => $organization->phone,
                'email' => $organization->email,
                'website' => $organization->website,
                'is_active' => $organization->is_active,
                'created_at' => $organization->created_at?->toISOString(),
            ],
        ]);
    }
}