<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserDevice;

class UserDevicePolicy
{
    /**
     * Determine if the user can view any devices.
     */
    public function viewAny(?User $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine if the user can view a specific device.
     */
    public function view(?User $user, UserDevice $device): bool
    {
        if (!$user) {
            return false;
        }
        
        return $user->user_id === $device->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can create devices.
     * ✅ Allow EVERYONE (including unauthenticated) to register devices.
     */
    public function create(?User $user): bool
    {
        return true; // ✅ This is the key - allows unauthenticated users
    }

    /**
     * Determine if the user can update a device.
     */
    public function update(?User $user, UserDevice $device): bool
    {
        if (!$user) {
            return false;
        }
        
        return $user->user_id === $device->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can delete a device.
     */
    public function delete(?User $user, UserDevice $device): bool
    {
        if (!$user) {
            return false;
        }
        
        return $user->user_id === $device->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can restore a soft-deleted device.
     */
    public function restore(?User $user, UserDevice $device): bool
    {
        if (!$user) {
            return false;
        }
        
        return $user->user_id === $device->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can force delete a device.
     */
    public function forceDelete(User $user, UserDevice $device): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can trust a device.
     */
    public function trust(?User $user, UserDevice $device): bool
    {
        if (!$user) {
            return false;
        }
        
        return $user->user_id === $device->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can untrust a device.
     */
    public function untrust(?User $user, UserDevice $device): bool
    {
        if (!$user) {
            return false;
        }
        
        return $user->user_id === $device->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can update FCM token for a device.
     */
    public function updateFcmToken(?User $user, UserDevice $device): bool
    {
        if (!$user) {
            return false;
        }
        
        return $user->user_id === $device->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can ping a device (update last_active_at).
     */
    public function ping(?User $user, UserDevice $device): bool
    {
        if (!$user) {
            return false;
        }
        
        return $user->user_id === $device->user_id || $user->isAdmin();
    }

    /**
     * Determine if the user can view device statistics.
     */
    public function viewStatistics(?User $user, ?string $userId = null): bool
    {
        if ($user && $user->isAdmin()) {
            return true;
        }
        
        if ($userId && $user && $user->user_id === $userId) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine if the user can view all device statistics (admin only).
     */
    public function viewAdminStatistics(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can manage devices for other users.
     */
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can delete all devices for a user.
     */
    public function deleteAll(?User $user, ?string $userId = null): bool
    {
        if (!$user) {
            return false;
        }
        
        if ($user->isAdmin()) {
            return true;
        }
        
        if ($userId && $user->user_id === $userId) {
            return true;
        }
        
        return false;
    }
}