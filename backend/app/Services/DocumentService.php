<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Jobs\ProcessMedicalDocumentJob;
use App\Models\AiAnalysis;
use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

final class DocumentService
{
    public function __construct(
        private readonly DocumentStorage $storage,
        private readonly AuditService $auditService,
    ) {}

    /**
     * Store an uploaded file and create the medical_documents record.
     */
    public function create(User $user, UploadedFile $file): MedicalDocument
    {
        $path = $this->storage->store($file, $user->id);

        $document = MedicalDocument::create([
            'user_id' => $user->id,
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'document_type' => DocumentType::Unknown,
            'status' => DocumentStatus::Uploaded,
        ]);

        $this->auditService->record(AuditEvent::DocumentUploaded, $user, $document);

        ProcessMedicalDocumentJob::dispatch($document->id);

        return $document;
    }

    /**
     * Paginate the documents owned by the given user.
     */
    public function paginateForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return MedicalDocument::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->paginate($perPage);
    }

    /**
     * Record a document view audit event.
     */
    public function recordView(User $user, MedicalDocument $document): void
    {
        $this->auditService->record(AuditEvent::DocumentViewed, $user, $document);
    }

    /**
     * Remove the stored file, the record, and audit the deletion.
     */
    public function delete(User $user, MedicalDocument $document): void
    {
        $this->storage->delete($document->storage_path);

        $document->delete();

        $this->auditService->record(AuditEvent::DocumentDeleted, $user, $document);
    }

    /**
     * Load the analysis (with items and lab results) for a document, if any.
     */
    public function getAnalysis(MedicalDocument $document): ?AiAnalysis
    {
        return $document->analysis()
            ->with([
                'items',
                'medicalDocument.extraction.labResults',
            ])
            ->first();
    }
}