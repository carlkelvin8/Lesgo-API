<?php

namespace App\Policies;

use App\Models\MenuCategory;
use App\Models\User;

class MenuCategoryPolicy
{
    /**
     * Determine if the user can update the menu category.
     */
    public function update(User $user, MenuCategory $menuCategory): bool
    {
        // Load the partner relationship if not already loaded
        if (!$menuCategory->relationLoaded('partner')) {
            $menuCategory->load('partner');
        }

        $partner = $menuCategory->partner;

        // Only the partner owner or admin can update
        return $user->id === $partner->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can delete the menu category.
     */
    public function delete(User $user, MenuCategory $menuCategory): bool
    {
        // Load the partner relationship if not already loaded
        if (!$menuCategory->relationLoaded('partner')) {
            $menuCategory->load('partner');
        }

        $partner = $menuCategory->partner;

        // Only the partner owner or admin can delete
        return $user->id === $partner->user_id || $user->isAdmin();
    }
}

