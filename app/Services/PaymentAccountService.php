<?php

namespace App\Services;

use App\Models\PaymentAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentAccountService
{
    /**
     * List payment accounts with filters.
     */
    public function listPaymentAccounts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return PaymentAccount::query() 
            ->when($filters['account_type'] ?? null, fn($q, $type) => $q->where('account_type', $type))
            ->when($filters['provider'] ?? null, fn($q, $provider) => $q->where('provider', $provider))
            ->when(isset($filters['is_active']), fn($q) => $q->where('is_active', (bool) $filters['is_active']))
            ->when(isset($filters['is_default']), fn($q) => $q->where('is_default', (bool) $filters['is_default']))
            ->when($filters['search'] ?? null, function($q, $search) {
                $q->where(function($query) use ($search) {
                    $query->where('owner_name', 'LIKE', "%{$search}%")
                        ->orWhere('account_identifier', 'LIKE', "%{$search}%")
                        ->orWhere('provider', 'LIKE', "%{$search}%");
                });
            }) 
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get payment accounts for a specific user.
     */
    public function getUserPaymentAccounts(string $userId): \Illuminate\Database\Eloquent\Collection
    {
        return PaymentAccount::where('user_id', $userId)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get active payment accounts for a user.
     */
    public function getUserActiveAccounts(string $userId): \Illuminate\Database\Eloquent\Collection
    {
        return PaymentAccount::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->get();
    }

    /**
     * Get default payment account for a user.
     */
    public function getUserDefaultAccount(string $userId): ?PaymentAccount
    {
        return PaymentAccount::where('user_id', $userId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Find a payment account by ID.
     */
    public function findPaymentAccount(string $id): ?PaymentAccount
    {
        return PaymentAccount::with(['user'])->find($id);
    }

    /**
     * Find a payment account by ID or fail.
     */
    public function findPaymentAccountOrFail(string $id): PaymentAccount
    {
        return PaymentAccount::with(['user'])->findOrFail($id);
    }

    /**
     * Create a payment account.
     */
    public function createPaymentAccount(array $data): PaymentAccount
    {
        return DB::transaction(function () use ($data) {
            $data['payment_account_id'] = Str::uuid();
            $data['is_active'] = $data['is_active'] ?? true;
            $data['is_default'] = $data['is_default'] ?? false;
            
            $account = PaymentAccount::create($data);
            
            // If this is marked as default, update other accounts
            if ($data['is_default']) {
                $account->makeDefault();
            }
            
            return $account->fresh();
        });
    }

    /**
     * Update a payment account.
     */
    public function updatePaymentAccount(PaymentAccount $account, array $data): PaymentAccount
    {
        return DB::transaction(function () use ($account, $data) {
            $account->update($data);
            
            // If this is marked as default, update other accounts
            if (isset($data['is_default']) && $data['is_default']) {
                $account->makeDefault();
            }
            
            return $account->fresh();
        });
    }

    /**
     * Delete a payment account.
     */
    public function deletePaymentAccount(PaymentAccount $account, bool $force = false): bool
    {
        return DB::transaction(function () use ($account, $force) {
            // If this is the default account, assign default to another account
            if ($account->is_default) {
                $newDefault = PaymentAccount::where('user_id', $account->user_id)
                    ->where('payment_account_id', '!=', $account->payment_account_id)
                    ->where('is_active', true)
                    ->first();
                
                if ($newDefault) {
                    $newDefault->makeDefault();
                }
            }
            
            if ($force) {
                return $account->forceDelete();
            }
            
            return $account->delete();
        });
    }

    /**
     * Restore a soft-deleted payment account.
     */
    public function restorePaymentAccount(string $id): PaymentAccount
    {
        $account = PaymentAccount::onlyTrashed()->findOrFail($id);
        $account->restore();
        return $account->fresh();
    }

    /**
     * Make a payment account the default for its user.
     */
    public function makeDefault(string $id): PaymentAccount
    {
        $account = $this->findPaymentAccountOrFail($id);
        $account->makeDefault();
        return $account->fresh();
    }

    /**
     * Toggle payment account status.
     */
    public function toggleStatus(string $id): PaymentAccount
    {
        $account = $this->findPaymentAccountOrFail($id);
        
        if ($account->is_active) {
            $account->deactivate();
        } else {
            $account->activate();
        }
        
        return $account->fresh();
    }
}