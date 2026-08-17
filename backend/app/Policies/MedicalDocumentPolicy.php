<?php

namespace App\Policies;

use App\Models\MedicalDocument;
use App\Models\User;

class MedicalDocumentPolicy
{
    /**
     * Determine whether the user can view the document.
     */
    public function view(User $user, MedicalDocument $document): bool
    {
        return $this->owns($user, $document);
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, MedicalDocument $document): bool
    {
        return $this->owns($user, $document);
    }

    /**
     * A user may only access documents they own.
     */
    private function owns(User $user, MedicalDocument $document): bool
    {
        return $document->user_id === $user->id;
    }
}