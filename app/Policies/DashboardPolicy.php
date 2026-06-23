<?php

namespace App\Policies;

use App\Models\User;

class DashboardPolicy
{
    /**
     * Determine if the user can view the admin dashboard.
     */
    public function viewAdmin(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can view the scanner dashboard.
     */
    public function viewScanner(User $user): bool
    {
        return $user->isScanner() || $user->isAdmin();
    }

    /**
     * Determine if the user can view concert-specific dashboard.
     */
    public function viewConcert(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can view chart data.
     */
    public function viewCharts(User $user): bool
    {
        return $user->isAdmin();
    }
}