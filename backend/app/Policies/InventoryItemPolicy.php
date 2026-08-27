<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function view(User $user, InventoryItem $item): bool
    {
        return ($user->isAdmin() || $user->isSuperAdmin())
            && $user->organization_id === $item->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('inventory.manage');
    }

    public function update(User $user, InventoryItem $item): bool
    {
        if (!$user->hasPermissionTo('inventory.manage')) {
            return false;
        }

        return $user->organization_id === $item->organization_id;
    }
}
