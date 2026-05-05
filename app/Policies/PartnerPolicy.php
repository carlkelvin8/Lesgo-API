<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;

class PartnerPolicy
{
    /**
     * Determine if the user can view the partner.
     */
    public function view(User $user, Partner $partner): bool
    {
        // Anyone can view active partners
        if ($partner->status === 'active') {
            return true;
        }

        // Only owner or admin can view inactive partners
        return $user->id === $partner->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can update the partner.
     */
    public function update(User $user, Partner $partner): bool
    {
        // Only the owner or admin can update
        return $user->id === $partner->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can delete the partner.
     */
    public function delete(User $user, Partner $partner): bool
    {
        // Only admin can delete
        return $user->isAdmin();
    }

    /**
     * Determine if the user can manage the partner's menu.
     */
    public function manageMenu(User $user, Partner $partner): bool
    {
        // Only the owner or admin can manage menu
        return $user->id === $partner->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can view the partner's orders.
     */
    public function viewOrders(User $user, Partner $partner): bool
    {
        // Only the owner or admin can view orders
        return $user->id === $partner->user_id || $user->isAdmin();
    }
}

