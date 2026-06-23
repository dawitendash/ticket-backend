<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Role;
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    /**
     * Determine whether the user can view any roles.
     * Only admins can view roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view a specific role.
     * Only admins can view roles.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create roles.
     * Only admins can create roles.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update a role.
     * Only admins can update roles.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete a role.
     * Only admins can delete roles.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore a soft-deleted role.
     * Only admins can restore roles.
     */
    public function restore(User $user, Role $role): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete a role.
     * Only admins can force delete roles.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can assign roles to users.
     * Only admins can assign roles.
     */
    public function assignRole(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can revoke roles from users.
     * Only admins can revoke roles.
     */
    public function revokeRole(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can manage role permissions.
     * Only admins can manage role permissions.
     */
    public function managePermissions(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view role statistics.
     * Only admins can view role statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->isAdmin();
    }
}