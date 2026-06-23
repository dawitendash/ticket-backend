<?php

namespace App\Policies;

use App\Models\Concert;
use App\Models\User;

class ConcertPolicy
{
    /**
     * Determine if the user can view any concerts.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isScanner();
    }

    /**
     * Determine if the user can view the concert.
     */
    public function view(User $user, Concert $concert): bool
    {
        // Public can view active concerts
        if (!$user) {
            return in_array($concert->status, ['upcoming', 'ongoing']);
        }
        
        return $user->isAdmin() || $user->isScanner();
    }

    /**
     * Determine if the user can create concerts.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can update the concert.
     */
    public function update(User $user, Concert $concert): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can delete the concert.
     */
    public function delete(User $user, Concert $concert): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore the concert.
     */
    public function restore(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can force delete the concert.
     */
    public function forceDelete(User $user, Concert $concert): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can scan tickets for the concert.
     */
    public function scan(User $user): bool
    {
        return $user->isScanner() || $user->isAdmin();
    }

    /**
     * Determine if the user can view concert statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->isAdmin() || $user->isScanner();
    }
}