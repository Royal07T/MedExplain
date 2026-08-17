<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles the physical storage of medical documents.
 *
 * The dedicated "documents" disk keeps uploaded files private and isolates
 * them from the rest of the application. Switching to S3-compatible object
 * storage later only requires changing the disk configuration.
 */
final class DocumentStorage
{
    /**
     * Store an uploaded file privately and return the generated storage path.
     */
    public function store(UploadedFile $file, int $userId): string
    {
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();

        return $file->storeAs('u'.$userId, $filename, 'documents');
    }

    /**
     * Delete a stored document by its storage path.
     */
    public function delete(string $storagePath): void
    {
        Storage::disk('documents')->delete($storagePath);
    }
}