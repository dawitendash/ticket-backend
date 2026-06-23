<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine if the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can view the user.
     */
    public function view(User $user, User $targetUser): bool
    {
        // Admin can view any user
        if ($user->isAdmin()) {
            return true;
        }

        // Users can view their own profile
        return $user->user_id === $targetUser->user_id;
    }

    /**
     * Determine if the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can update the user.
     */
    public function update(User $user, User $targetUser): bool
    {
        // Admin can update any user
        if ($user->isAdmin()) {
            return true;
        }

        // Users can update their own profile
        return $user->user_id === $targetUser->user_id;
    }

    /**
     * Determine if the user can delete the user.
     */
    public function delete(User $user, User $targetUser): bool
    {
        // Only admin can delete users
        return $user->isAdmin() && $user->user_id !== $targetUser->user_id;
    }

    /**
     * Determine if the user can restore the user.
     */
    public function restore(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the user.
     */
    public function forceDelete(User $user, User $targetUser): bool
    {
        return $user->isAdmin() && $user->user_id !== $targetUser->user_id;
    }

    /**
     * Determine if the user can update user roles.
     */
    public function updateRole(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can lock/unlock users.
     */
    public function lock(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can view user statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->isAdmin();
    }
}