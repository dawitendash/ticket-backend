<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine if the user can view any tickets.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view a specific ticket.
     */
    public function view(?User $user, Ticket $ticket): bool
    {
        if (!$user) {
            return false;
        }
        
        return $user->isAdmin() || $user->user_id === $ticket->user_id;
    }

    /**
     * Determine if the user can create tickets.
     * Only admins can create tickets.
     */
    public function create(?User $user): bool
    {
        return true; // Allow public to create tickets (e.g., through purchase) 
    }

    /**
     * Determine if the user can update a ticket.
     * Only admins can update tickets.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can delete a ticket.
     * Only admins can delete tickets.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore a ticket.
     * Only admins can restore tickets.
     */
    public function restore(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can force delete a ticket.
     * Only admins can force delete tickets.
     */
    public function forceDelete(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can scan tickets.
     * Scanners and admins can scan tickets.
     */
    public function scan(User $user): bool
    {
        return $user->isScanner() || $user->isAdmin();
    }

    /**
     * Determine if the user can validate tickets.
     * Scanners and admins can validate tickets.
     */
    public function validateTicket(User $user): bool
    {
        return $user->isScanner() || $user->isAdmin();
    }

    /**
     * Determine if the user can view ticket statistics.
     * Only admins can view statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->isAdmin();
    }
}