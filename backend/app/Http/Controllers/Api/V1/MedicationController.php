<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MedicationResource;
use App\Services\MedicationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

final class MedicationController extends Controller
{
    public function __construct(private readonly MedicationService $medicationService) {}

    /**
     * List the authenticated user's medications.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $medications = $this->medicationService->forUser($request->user());

        return MedicationResource::collection($medications);
    }
}