<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    /**
     * Determine if the user can update the menu item.
     */
    public function update(User $user, MenuItem $menuItem): bool
    {
        $menuItem->loadMissing('partner');

        return $user->id === $menuItem->partner?->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can delete the menu item.
     */
    public function delete(User $user, MenuItem $menuItem): bool
    {
        $menuItem->loadMissing('partner');

        return $user->id === $menuItem->partner?->user_id || $user->isAdmin();
    }
}

