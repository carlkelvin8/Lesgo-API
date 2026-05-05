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
        // Load the partner relationship if not already loaded
        if (!$menuItem->relationLoaded('category')) {
            $menuItem->load('category.partner');
        }

        $partner = $menuItem->category->partner;

        // Only the partner owner or admin can update
        return $user->id === $partner->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can delete the menu item.
     */
    public function delete(User $user, MenuItem $menuItem): bool
    {
        // Load the partner relationship if not already loaded
        if (!$menuItem->relationLoaded('category')) {
            $menuItem->load('category.partner');
        }

        $partner = $menuItem->category->partner;

        // Only the partner owner or admin can delete
        return $user->id === $partner->user_id || $user->isAdmin();
    }
}

