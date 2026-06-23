<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentAccountRequest;
use App\Http\Requests\UpdatePaymentAccountRequest;
use App\Http\Resources\PaymentAccountResource;
use App\Models\PaymentAccount;
use App\Services\PaymentAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PaymentAccountController extends Controller
{
    use AuthorizesRequests;
    
    protected PaymentAccountService $service;

    public function __construct(PaymentAccountService $service)
    {
        $this->service = $service;
    }

    // =============================================
    // ADMIN ROUTES - Admin only
    // =============================================

    /**
     * Display a listing of payment accounts (Admin only).
     */
    public function index(Request $request)
    {
      //  $this->authorize('viewAny', PaymentAccount::class);

        $filters = $request->only([
             'account_type', 'provider', 'is_active', 'is_default', 'search'
        ]);

        $perPage = $request->integer('per_page', 15);
        $accounts = $this->service->listPaymentAccounts($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => PaymentAccountResource::collection($accounts),
        ]);
    }

    /**
     * Get payment accounts for a specific user (Admin only).
     */
    public function getUserAccounts(string $userId)
    {
        $this->authorize('viewAny', PaymentAccount::class);

        $accounts = $this->service->getUserPaymentAccounts($userId);

        return response()->json([
            'success' => true,
            'data' => PaymentAccountResource::collection($accounts),
        ]);
    }

    /**
     * Display the specified payment account.
     */
    public function show(string $id)
    {
        try {
            $account = $this->service->findPaymentAccountOrFail($id);
            $this->authorize('view', $account);

            return response()->json([
                'success' => true,
                'data' => new PaymentAccountResource($account),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment account not found',
            ], 404);
        }
    }

    /**
     * Store a newly created payment account (Admin only).
     */
    public function store(StorePaymentAccountRequest $request)
    {
        $this->authorize('create', PaymentAccount::class);

        try {
            $data = $request->validated();
            
            // If user_id is not provided, use the authenticated user
            if (!isset($data['user_id'])) {
                $data['user_id'] = auth()->id();
            }
            
            $account = $this->service->createPaymentAccount($data);

            return response()->json([
                'success' => true,
                'message' => 'Payment account created successfully',
                'data' => new PaymentAccountResource($account),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment account: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified payment account (Admin only).
     */
    public function update(UpdatePaymentAccountRequest $request, string $id)
    {
        try {
            $account = $this->service->findPaymentAccountOrFail($id);
            $this->authorize('update', $account);

            $data = $request->validated();
            $account = $this->service->updatePaymentAccount($account, $data);

            return response()->json([
                'success' => true,
                'message' => 'Payment account updated successfully',
                'data' => new PaymentAccountResource($account),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment account: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Make a payment account default (Admin only).
     */
    public function makeDefault(string $id)
    {
        try {
            $this->authorize('makeDefault', PaymentAccount::class);
            
            $account = $this->service->makeDefault($id);

            return response()->json([
                'success' => true,
                'message' => 'Default payment account updated successfully',
                'data' => new PaymentAccountResource($account),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update default account: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle payment account status (Admin only).
     */
    public function toggleStatus(string $id)
    {
        try {
            $account = $this->service->findPaymentAccountOrFail($id);
            $this->authorize('toggleStatus', $account);

            $account = $this->service->toggleStatus($id);

            return response()->json([
                'success' => true,
                'message' => $account->is_active ? 'Account activated successfully' : 'Account deactivated successfully',
                'data' => new PaymentAccountResource($account),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle account status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified payment account (Admin only).
     */
    public function destroy(Request $request, string $id)
    {
        try {
            $account = $this->service->findPaymentAccountOrFail($id);
            $this->authorize('delete', $account);

            $force = $request->get('force', false);
            $this->service->deletePaymentAccount($account, $force);

            return response()->json([
                'success' => true,
                'message' => $force ? 'Payment account permanently deleted' : 'Payment account deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment account: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted payment account (Admin only).
     */
    public function restore(string $id)
    {
        try {
            $this->authorize('restore', PaymentAccount::class);
            
            $account = $this->service->restorePaymentAccount($id);

            return response()->json([
                'success' => true,
                'message' => 'Payment account restored successfully',
                'data' => new PaymentAccountResource($account),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore payment account: ' . $e->getMessage(),
            ], 500);
        }
    }
}