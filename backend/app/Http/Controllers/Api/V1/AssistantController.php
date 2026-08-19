<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssistantController extends Controller
{
    public function __construct(private readonly AssistantService $assistantService) {}

    /**
     * Send a message to the guarded educational assistant.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $result = $this->assistantService->reply($request->user(), $validated['message']);

        return response()->json([
            'reply' => $result->reply,
            'disclaimer' => $result->disclaimer,
            'sources' => $result->sources,
        ]);
    }
}