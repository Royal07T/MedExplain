<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\MedicalDocument;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

final class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documentService) {}

    /**
     * List the authenticated user's documents.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $documents = $this->documentService->paginateForUser($request->user());

        return DocumentResource::collection($documents);
    }

    /**
     * Upload and store a new medical document.
     */
    public function store(UploadDocumentRequest $request): JsonResponse
    {
        $document = $this->documentService->create($request->user(), $request->file('file'));

        return response()->json(new DocumentResource($document), 201);
    }

    /**
     * Show a single document the user owns.
     */
    public function show(Request $request, MedicalDocument $document): JsonResponse
    {
        $this->authorize('view', $document);

        $this->documentService->recordView($request->user(), $document);

        return response()->json(new DocumentResource($document));
    }

    /**
     * Delete a document the user owns.
     */
    public function destroy(Request $request, MedicalDocument $document): JsonResponse
    {
        $this->authorize('delete', $document);

        $this->documentService->delete($request->user(), $document);

        return response()->json(null, 204);
    }
}