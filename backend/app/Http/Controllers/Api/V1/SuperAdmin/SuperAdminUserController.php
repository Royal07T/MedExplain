<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('profile');

        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->input('organization_id'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->paginate(20);

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('profile');

        return response()->json(['data' => $user]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'role' => 'sometimes|in:patient,clinician,nursing_staff,admin,super_admin',
            'organization_id' => 'nullable|exists:organizations,id',
        ]);

        $user->update($validated);

        return response()->json(['data' => $user]);
    }
}
