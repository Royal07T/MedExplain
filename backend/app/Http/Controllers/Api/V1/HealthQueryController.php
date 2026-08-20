<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HealthQuery\HealthQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HealthQueryController extends Controller
{
    public function __construct(private readonly HealthQueryService $service) {}

    /**
     * Ask the health-intelligence layer a natural-language question.
     *
     * The answer is a structured, strictly validated response; it is never a
     * diagnosis and never contains more than the user's own data supports.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:4000'],
        ]);

        $result = $this->service->answer($request->user(), $validated['question']);
        $answer = $result->answer;

        return response()->json([
            'query_id' => $result->queryId,
            'intent' => $result->intent,
            'answer' => [
                'summary' => $answer->summary,
                'facts' => $answer->facts,
                'changes' => $answer->changes,
                'context' => $answer->context,
                'educational_explanation' => $answer->educationalExplanation,
                'questions_for_professional' => $answer->questionsForProfessional,
                'sources' => $answer->sources,
                'disclaimer' => $answer->disclaimer,
                'data_used' => $answer->dataUsed,
            ],
        ]);
    }
}