<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class UserController extends Controller
{
    /**
     * Return the authenticated user with their profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update the authenticated user's display name and profile details.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
        ]);

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
            ],
        );

        return response()->json([
            'user' => new UserResource($user->load('profile')),
        ]);
    }

    /**
     * Upload (or replace) the authenticated user's profile picture.
     */
    public function updateAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);

        if ($profile->avatar_path !== null) {
            Storage::disk('public')->delete($profile->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $profile->update(['avatar_path' => $path]);

        return response()->json([
            'user' => new UserResource($user->load('profile')),
        ]);
    }
}