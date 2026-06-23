<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserInformation;

class UserInformationPolicy
{
    /**
     * Determine if the user can view any user information.
     * Users can view their own information, admins can view all.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin(); // Only admins can view all user information
    }

    /**
     * Determine if the user can view a specific user information.
     */
    public function view(?User $user, UserInformation $userInformation): bool
    {
        
        return $user->isAdmin() || $user->user_id === $userInformation->user_id;
    }

    /**
     * Determine if the user can create user information.
     * Authenticated users can create their own information.
     */
    public function create(?User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update user information.
     */
    public function update(?User $user, UserInformation $userInformation): bool
    {
        if (!$user) {
            return false;
        }
        
        return $user->isAdmin() || $user->user_id === $userInformation->user_id;
    }

    /**
     * Determine if the user can delete user information.
     */
    public function delete(?User $user, UserInformation $userInformation): bool
    {
        if (!$user) {
            return false;
        }
        
        return $user->isAdmin() || $user->user_id === $userInformation->user_id;
    }

    /**
     * Determine if the user can restore user information.
     */
    public function restore(User $user, UserInformation $userInformation): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can force delete user information.
     */
    public function forceDelete(User $user, UserInformation $userInformation): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can view statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->isAdmin();
    }
}