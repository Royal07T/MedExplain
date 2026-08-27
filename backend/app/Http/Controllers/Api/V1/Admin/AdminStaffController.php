<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $staff = User::where('organization_id', $orgId)
            ->whereIn('role', ['clinician', 'nursing_staff', 'admin'])
            ->with('profile')
            ->withCount('departments')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $staff]);
    }

    public function assign(Request $request, int $userId, int $departmentId): JsonResponse
    {
        $user = $request->user();

        $staff = User::where('id', $userId)
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        $department = Department::where('id', $departmentId)
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        if ($staff->departments()->where('department_id', $departmentId)->exists()) {
            return response()->json(['message' => 'Staff already assigned to this department.'], 409);
        }

        $staff->departments()->attach($departmentId);

        return response()->json(['message' => 'Staff assigned to department.']);
    }

    public function remove(Request $request, int $userId, int $departmentId): JsonResponse
    {
        $user = $request->user();

        $staff = User::where('id', $userId)
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        $staff->departments()->detach($departmentId);

        return response()->json(['message' => 'Staff removed from department.']);
    }
}
