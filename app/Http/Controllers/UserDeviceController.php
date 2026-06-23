<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserDeviceRequest;
use App\Http\Requests\UpdateUserDeviceRequest;
use App\Http\Resources\UserDeviceResource;
use App\Services\UserDeviceService;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserDeviceController extends Controller
{
    use AuthorizesRequests;
    
    protected UserDeviceService $deviceService;

    public function __construct(UserDeviceService $deviceService)
    {
        $this->deviceService = $deviceService;
    }

    // =============================================
    // PUBLIC ROUTES - No authentication required
    // =============================================

    
public function store(StoreUserDeviceRequest $request)
{
    // Allow unauthenticated users to create devices
    $this->authorize('create', UserDevice::class);

    try {
        $data = $request->validated();
        
        // Set user agent and IP
        $data['user_agent'] = $request->userAgent();
        $data['ip_address'] = $request->ip();
        
        // If user is authenticated, use their ID (overrides any provided user_id)
        // This prevents users from registering devices for other users
        if (auth()->check()) {
            $data['user_id'] = auth()->id();
        }
        // For unauthenticated users, user_id must be provided and validated by the request
        
        $device = $this->deviceService->createOrUpdateDevice($data);

        return response()->json([
            'success' => true,
            'message' => 'Device registered successfully',
            'data' => new UserDeviceResource($device),
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to register device: ' . $e->getMessage(),
        ], 500);
    }
}
    // =============================================
    // AUTHENTICATED ROUTES - Requires authentication
    // =============================================

    /**
     * Display a listing of the user's devices.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', UserDevice::class);

        $filters = $request->only([
            'user_id', 'device_type', 'platform', 'is_trusted', 'has_fcm', 'search'
        ]);

        // If no user_id specified, use the authenticated user
        if (!isset($filters['user_id']) || !auth()->user()->isAdmin()) {
            $filters['user_id'] = auth()->id();
        }

        $perPage = $request->get('per_page', 15);
        $devices = $this->deviceService->listDevices($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => UserDeviceResource::collection($devices),
        ]);
    }

    /**
     * Get devices for the authenticated user.
     */
    public function myDevices(Request $request)
    {
        $userId = auth()->id();
        $devices = $this->deviceService->getUserDevices($userId);

        return response()->json([
            'success' => true,
            'data' => UserDeviceResource::collection($devices),
        ]);
    }

    /**
     * Get trusted devices for the authenticated user.
     */
    public function myTrustedDevices()
    {
        $userId = auth()->id();
        $devices = $this->deviceService->getUserTrustedDevices($userId);

        return response()->json([
            'success' => true,
            'data' => UserDeviceResource::collection($devices),
        ]);
    }

    /**
     * Get online devices for the authenticated user.
     */
    public function myOnlineDevices()
    {
        $userId = auth()->id();
        $devices = $this->deviceService->getUserOnlineDevices($userId);

        return response()->json([
            'success' => true,
            'data' => UserDeviceResource::collection($devices),
        ]);
    }

    /**
     * Display the specified device.
     */
    public function show(string $id)
    {
        try {
            $device = $this->deviceService->findDeviceOrFail($id);
            $this->authorize('view', $device);

            return response()->json([
                'success' => true,
                'data' => new UserDeviceResource($device),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found',
            ], 404);
        }
    }

    /**
     * Update the specified device.
     */
    public function update(UpdateUserDeviceRequest $request, string $id)
    {
        try {
            $device = $this->deviceService->findDeviceOrFail($id);
            $this->authorize('update', $device);

            $data = $request->validated();
            
            // Update user agent and IP if provided
            if ($request->has('user_agent')) {
                $data['user_agent'] = $request->userAgent();
            }
            if ($request->has('ip_address')) {
                $data['ip_address'] = $request->ip();
            }
            
            $device = $this->deviceService->updateDevice($device, $data);

            return response()->json([
                'success' => true,
                'message' => 'Device updated successfully',
                'data' => new UserDeviceResource($device),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update device: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update FCM token for the specified device.
     */
    public function updateFcmToken(Request $request, string $id)
    {
        $request->validate([
            'fcm_token' => ['required', 'string'],
        ]);

        try {
            $device = $this->deviceService->findDeviceOrFail($id);
            $this->authorize('update', $device);

            $device = $this->deviceService->updateFcmToken($id, $request->fcm_token);

            return response()->json([
                'success' => true,
                'message' => 'FCM token updated successfully',
                'data' => new UserDeviceResource($device),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update FCM token: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified device.
     */
    public function destroy(Request $request, string $id)
    {
        try {
            $device = $this->deviceService->findDeviceOrFail($id);
            $this->authorize('delete', $device);

            $force = $request->get('force', false);
            $this->deviceService->deleteDevice($device, $force);

            return response()->json([
                'success' => true,
                'message' => $force ? 'Device permanently deleted' : 'Device removed successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete device: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove all devices for the authenticated user.
     */
    public function destroyAll(Request $request)
    {
        try {
            $userId = auth()->id();
            $force = $request->get('force', false);
            $count = $this->deviceService->deleteUserDevices($userId, $force);

            return response()->json([
                'success' => true,
                'message' => $count . ' devices removed successfully',
                'deleted_count' => $count,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete devices: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted device.
     */
    public function restore(string $id)
    {
        try {
            $device = $this->deviceService->restoreDevice($id);
            $this->authorize('restore', $device);

            return response()->json([
                'success' => true,
                'message' => 'Device restored successfully',
                'data' => new UserDeviceResource($device),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore device: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =============================================
    // DEVICE MANAGEMENT - Status updates
    // =============================================

    /**
     * Update last active timestamp.
     */
    public function ping(string $id)
    {
        try {
            $device = $this->deviceService->findDeviceOrFail($id);
            $this->authorize('update', $device);

            $device = $this->deviceService->updateLastActive($id);

            return response()->json([
                'success' => true,
                'message' => 'Device pinged successfully',
                'data' => new UserDeviceResource($device),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to ping device: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trust a device.
     */
    public function trust(string $id)
    {
        try {
            $device = $this->deviceService->findDeviceOrFail($id);
            $this->authorize('trust', $device);

            $device = $this->deviceService->trustDevice($id);

            return response()->json([
                'success' => true,
                'message' => 'Device trusted successfully',
                'data' => new UserDeviceResource($device),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to trust device: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Untrust a device.
     */
    public function untrust(string $id)
    {
        try {
            $device = $this->deviceService->findDeviceOrFail($id);
            $this->authorize('untrust', $device);

            $device = $this->deviceService->untrustDevice($id);

            return response()->json([
                'success' => true,
                'message' => 'Device untrusted successfully',
                'data' => new UserDeviceResource($device),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to untrust device: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =============================================
    // STATISTICS
    // =============================================

    /**
     * Get device statistics for the authenticated user.
     */
    public function statistics(Request $request)
    {
        $userId = auth()->id();
        $this->authorize('viewStatistics', [UserDevice::class, $userId]);

        try {
            $stats = $this->deviceService->getUserDeviceStatistics($userId);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get device statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get device statistics for a specific user.
     */
    public function userStatistics(Request $request, string $userId)
    {
        $this->authorize('viewStatistics', [UserDevice::class, $userId]);

        try {
            $stats = $this->deviceService->getUserDeviceStatistics($userId);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get device statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =============================================
    // ADMIN ROUTES
    // =============================================

    /**
     * Get all device statistics (Admin only).
     */
    public function adminStatistics()
    {
        $this->authorize('viewStatistics', UserDevice::class);

        try {
            $stats = $this->deviceService->getAllDeviceStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get device statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin restore a device.
     */
    public function adminRestore(string $id)
    {
        try {
            $this->authorize('restore', UserDevice::class);
            $device = $this->deviceService->restoreDevice($id);

            return response()->json([
                'success' => true,
                'message' => 'Device restored successfully',
                'data' => new UserDeviceResource($device),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore device: ' . $e->getMessage(),
            ], 500);
        }
    }
}