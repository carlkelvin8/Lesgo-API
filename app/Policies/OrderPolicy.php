<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine if the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can list orders (filtered by ownership in controller)
    }

    /**
     * Determine if the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        // Admin can view all, others can view their own orders
        return $user->role === 'admin' 
            || $order->customer_id === $user->id 
            || $order->driver_id === $user->id;
    }

    /**
     * Determine if the user can create orders.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['customer', 'admin']);
    }

    /**
     * Determine if the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        // Admin can update all, customer can update their own pending orders
        if ($user->role === 'admin') {
            return true;
        }

        return $order->customer_id === $user->id && $order->status === 'pending';
    }

    /**
     * Determine if the user can delete the order.
     */
    public function delete(User $user, Order $order): bool
    {
        // Only admin can delete, and only pending orders
        return $user->role === 'admin' && $order->status === 'pending';
    }

    /**
     * Determine if the user can update order status.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        // Admin can always update status
        if ($user->role === 'admin') {
            return true;
        }

        // Driver can update status of their assigned orders
        if ($user->role === 'driver' && $order->driver_id === $user->id) {
            return true;
        }

        return false;
    }
}
