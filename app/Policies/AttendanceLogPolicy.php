<?php

namespace App\Policies;

use App\Models\AttendanceLog;
use App\Models\User;

class AttendanceLogPolicy
{
    /**
     * Determine if the user can view any attendance logs.
     */
    public function viewAny(User $user): bool
    {
        // Admin can view all logs
        if ($user->isAdmin()) {
            return true;
        }

        // Scanner can view logs they created
        if ($user->isScanner()) {
            return true;
        }

        // Regular users can view their own attendance logs
        return true;
    }

    /**
     * Determine if the user can view the attendance log.
     */
    public function view(User $user, AttendanceLog $log): bool
    {
        // Admin can view any log
        if ($user->isAdmin()) {
            return true;
        }

        // Scanner can view logs they created
        if ($user->isScanner()) {
            return $log->scanned_by === $user->user_id;
        }

        // Regular users can view their own logs
        return $user->user_id === $log->user_id;
    }

    /**
     * Determine if the user can create attendance logs.
     */
    public function create(User $user): bool
    {
        return $user->isScanner() || $user->isAdmin();
    }

    /**
     * Determine if the user can scan tickets.
     */
    public function scan(User $user): bool
    {
        return $user->isScanner() || $user->isAdmin();
    }

    /**
     * Determine if the user can update the attendance log.
     */
    public function update(User $user, AttendanceLog $log): bool
    {
        // Only admin can update logs
        return $user->isAdmin();
    }

    /**
     * Determine if the user can delete the attendance log.
     */
    public function delete(User $user, AttendanceLog $log): bool
    {
        // Only admin can delete logs
        return $user->isAdmin();
    }

    /**
     * Determine if the user can permanently delete the attendance log.
     */
    public function forceDelete(User $user, AttendanceLog $log): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can view attendance statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->isAdmin() || $user->isScanner();
    }
}