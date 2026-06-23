<?php

namespace App\Policies;

use App\Models\PaymentAccount;
use App\Models\User;

class PaymentAccountPolicy
{
    /**
     * Determine if the user can view any payment accounts.
     * Only admins can view all accounts.
     */
    public function viewAny(?User $user): bool
    {
       return true;
    }

    /**
     * Determine if the user can view a specific payment account.
     */
    public function view(User $user, PaymentAccount $paymentAccount): bool
    {
        return $user->isAdmin() || $user->user_id === $paymentAccount->user_id;
    }

    /**
     * Determine if the user can create payment accounts.
     * Only admins can create payment accounts.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can update a payment account.
     * Only admins can update payment accounts.
     */
    public function update(User $user, PaymentAccount $paymentAccount): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can delete a payment account.
     * Only admins can delete payment accounts.
     */
    public function delete(User $user, PaymentAccount $paymentAccount): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can restore a soft-deleted payment account.
     * Only admins can restore payment accounts.
     */
    public function restore(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can force delete a payment account.
     * Only admins can force delete payment accounts.
     */
    public function forceDelete(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can make a payment account default.
     * Only admins can make accounts default.
     */
    public function makeDefault(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can toggle account status.
     * Only admins can change account status.
     */
    public function toggleStatus(User $user): bool
    {
        return $user->isAdmin();
    }
}