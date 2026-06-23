<?php

namespace App\Policies;

use App\Models\TicketType;
use App\Models\User;

class TicketTypePolicy
{
    /**
     * Determine if the user can view any ticket types.
     * Public access - anyone can view ticket types.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view a specific ticket type.
     * Public access - anyone can view ticket types.
     */
    public function view(?User $user, TicketType $ticketType): bool
    {
        return true;
    }

    /**
     * Determine if the user can create ticket types.
     * Only admins can create ticket types.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can update a ticket type.
     * Only admins can update ticket types.
     */
    public function update(User $user, TicketType $ticketType): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can delete a ticket type.
     * Only admins can delete ticket types.
     */
    public function delete(User $user, TicketType $ticketType): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore a ticket type.
     * Only admins can restore ticket types.
     */
    public function restore(User $user, TicketType $ticketType): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can force delete a ticket type.
     * Only admins can force delete ticket types.
     */
    public function forceDelete(User $user, TicketType $ticketType): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can view ticket type statistics.
     * Only admins and scanners can view statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->isAdmin() || $user->isScanner();
    }

    /**
     * Determine if the user can bulk create ticket types.
     * Only admins can bulk create ticket types.
     */
    public function bulkCreate(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can delete ticket types with their tickets.
     * Only admins can perform this action.
     */
    public function deleteWithTickets(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can soft delete a ticket type.
     * Only admins can soft delete ticket types.
     */
    public function softDelete(User $user, TicketType $ticketType): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can check availability of a ticket type.
     * Public access - anyone can check availability.
     */
    public function checkAvailability(?User $user, TicketType $ticketType): bool
    {
        return true;
    }

    /**
     * Determine if the user can view sales analytics.
     * Only admins can view sales analytics.
     */
    public function viewSalesAnalytics(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can manage ticket types.
     * Only admins can manage ticket types.
     */
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}