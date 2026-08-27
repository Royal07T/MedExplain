<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return ($user->isAdmin() || $user->isSuperAdmin())
            && $user->organization_id === $invoice->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('billing.manage');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if (!$user->hasPermissionTo('billing.manage')) {
            return false;
        }

        return $user->organization_id === $invoice->organization_id;
    }
}
