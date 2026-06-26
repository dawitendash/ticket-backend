<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserInformationRequest;
use App\Http\Requests\UpdateUserInformationRequest;
use App\Http\Resources\UserInformationResource;
use App\Models\UserInformation;
use App\Services\UserInformationService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;

class UserInformationController extends Controller
{
    use AuthorizesRequests;
    
    protected UserInformationService $service;

    // Cache TTL in seconds
    protected int $publicCacheTTL = 600; // 10 minutes
    protected int $userCacheTTL = 300; // 5 minutes
    protected int $adminCacheTTL = 600; // 10 minutes
    protected int $statsCacheTTL = 180; // 3 minutes

    public function __construct(UserInformationService $service)
    {
        $this->service = $service;
    }

    // =============================================
    // PUBLIC ROUTES - Everyone can view
    // =============================================

    public function index(Request $request)
    {
        $this->authorize('viewAny', UserInformation::class);
        $filters = $request->only([
            'user_id', 'device_id', 'has_national_id', 'is_complete', 'search'
        ]);

        $perPage = $request->integer('per_page', 15);
        
        $cacheKey = $this->getCacheKey('user_information_list', $filters, $perPage);
        
        $informations = Cache::remember($cacheKey, $this->adminCacheTTL, function () use ($filters, $perPage) {
            return $this->service->listUserInformation($filters, $perPage);
        });

        return response()->json([
            'success' => true,
            'data' => UserInformationResource::collection($informations),
            'cached' => Cache::has($cacheKey),
        ]);
    }

    public function getByUserId(string $userId)
    {
        $cacheKey = $this->getCacheKey('user_information_by_user', $userId);
        
        $information = Cache::remember($cacheKey, $this->publicCacheTTL, function () use ($userId) {
            return $this->service->getByUserId($userId);
        });

        if (!$information) {
            return response()->json([
                'success' => false,
                'message' => 'User information not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserInformationResource($information),
            'cached' => Cache::has($cacheKey),
        ]);
    }

    public function getByDeviceId(string $deviceId)
    {
        $cacheKey = $this->getCacheKey('user_information_by_device', $deviceId);
        
        $information = Cache::remember($cacheKey, $this->publicCacheTTL, function () use ($deviceId) {
            return $this->service->getByDeviceId($deviceId);
        });

        if (!$information) {
            return response()->json([
                'success' => false,
                'message' => 'User information not found for this device',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserInformationResource($information),
            'cached' => Cache::has($cacheKey),
        ]);
    }

    public function getByPhoneNumber(Request $request)
    {
        $request->validate([
            'phone_number' => ['required', 'string'],
        ]);

        $cacheKey = $this->getCacheKey('user_information_by_phone', $request->phone_number);
        
        $information = Cache::remember($cacheKey, $this->publicCacheTTL, function () use ($request) {
            return $this->service->getByPhoneNumber($request->phone_number);
        });

        if (!$information) {
            return response()->json([
                'success' => false,
                'message' => 'User information not found for this phone number',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserInformationResource($information),
            'cached' => Cache::has($cacheKey),
        ]);
    }

    public function show(string $id)
    {
        $this->authorize('view', UserInformation::class);
        
        try {
            $cacheKey = $this->getCacheKey('user_information_detail', $id);
            
            $information = Cache::remember($cacheKey, $this->publicCacheTTL, function () use ($id) {
                return $this->service->findUserInformationOrFail($id);
            });

            return response()->json([
                'success' => true,
                'data' => new UserInformationResource($information),
                'cached' => Cache::has($cacheKey),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User information not found',
            ], 404);
        }
    }

    public function myInformation()
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $cacheKey = $this->getCacheKey('my_information', $user->user_id);
        
        $information = Cache::remember($cacheKey, $this->userCacheTTL, function () use ($user) {
            return $this->service->getByUserId($user->user_id);
        });

        if (!$information) {
            return response()->json([
                'success' => false,
                'message' => 'User information not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserInformationResource($information),
            'cached' => Cache::has($cacheKey),
        ]);
    }

    // =============================================
    // AUTHENTICATED ROUTES
    // =============================================

    public function store(StoreUserInformationRequest $request)
    {
        try {
            $data = $request->validated();
             
            if (!empty($data['user_id'])) {
                unset($data['device_id']);
            }
            
            if (empty($data['user_id']) && auth()->check()) {
                $data['user_id'] = auth()->id();
                unset($data['device_id']);  
            }
     
            if (empty($data['user_id']) && empty($data['device_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either user_id or device_id is required.',
                ], 422);
            }
            
            $information = $this->service->createUserInformation($data);
            
            // Clear relevant caches
            $this->clearUserInformationCaches($information);

            return response()->json([
                'success' => true,
                'message' => 'User information created successfully',
                'data' => new UserInformationResource($information),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user information: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateUserInformationRequest $request, string $id)
    {
        try {
            $information = $this->service->findUserInformationOrFail($id);
            $this->authorize('update', $information);

            $data = $request->validated();
            $information = $this->service->updateUserInformation($information, $data);
            
            // Clear relevant caches
            $this->clearUserInformationCaches($information);

            return response()->json([
                'success' => true,
                'message' => 'User information updated successfully',
                'data' => new UserInformationResource($information),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user information: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateMyInformation(UpdateUserInformationRequest $request)
    {
        try {
            $user = auth()->user();
            $information = $this->service->getByUserId($user->user_id);
            
            if (!$information) {
                return response()->json([
                    'success' => false,
                    'message' => 'User information not found',
                ], 404);
            }

            $data = $request->validated();
            $information = $this->service->updateUserInformation($information, $data);
            
            // Clear relevant caches
            $this->clearUserInformationCaches($information);

            return response()->json([
                'success' => true,
                'message' => 'Your information updated successfully',
                'data' => new UserInformationResource($information),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update your information: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $information = $this->service->findUserInformationOrFail($id);
            $this->authorize('delete', $information);

            $force = $request->get('force', false);
            $this->service->deleteUserInformation($information, $force);
            
            // Clear relevant caches
            $this->clearUserInformationCaches($information);

            return response()->json([
                'success' => true,
                'message' => $force ? 'User information permanently deleted' : 'User information deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user information: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteMyInformation(Request $request)
    {
        try {
            $user = auth()->user();
            $force = $request->get('force', false);
            $deleted = $this->service->deleteByUserId($user->user_id, $force);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'User information not found',
                ], 404);
            }
            
            // Clear user-specific caches
            $this->clearUserCaches($user->user_id);

            return response()->json([
                'success' => true,
                'message' => $force ? 'Your information permanently deleted' : 'Your information deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete your information: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function restore(string $id)
    {
        try {
            $this->authorize('restore', UserInformation::class);
            
            $information = $this->service->restoreUserInformation($id);
            
            // Clear relevant caches
            $this->clearUserInformationCaches($information);

            return response()->json([
                'success' => true,
                'message' => 'User information restored successfully',
                'data' => new UserInformationResource($information),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore user information: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =============================================
    // ADMIN ROUTES
    // =============================================

    public function statistics()
    {
        $this->authorize('viewStatistics', UserInformation::class);

        try {
            $cacheKey = 'user_information_statistics';
            
            $stats = Cache::remember($cacheKey, $this->statsCacheTTL, function () {
                return $this->service->getStatistics();
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
                'cached' => Cache::has($cacheKey),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =============================================
    // CACHE HELPER METHODS
    // =============================================

    /**
     * Generate cache key
     */
    private function getCacheKey(string $prefix, ...$params): string
    {
        $key = $prefix;
        foreach ($params as $param) {
            if (is_array($param)) {
                ksort($param);
                $key .= '_' . md5(json_encode($param));
            } elseif (is_object($param)) {
                $key .= '_' . md5(serialize($param));
            } else {
                $key .= '_' . (string) $param;
            }
        }
        return $key;
    }

    /**
     * Clear user information related caches
     */
    private function clearUserInformationCaches($information): void
    {
        try {
            $userId = $information->user_id ?? null;
            $deviceId = $information->device_id ?? null;
            $phoneNumber = $information->phone_number ?? null;
            $id = $information->id ?? null;
            
            // Clear specific caches
            if ($userId) {
                Cache::forget($this->getCacheKey('user_information_by_user', $userId));
                Cache::forget($this->getCacheKey('my_information', $userId));
            }
            
            if ($deviceId) {
                Cache::forget($this->getCacheKey('user_information_by_device', $deviceId));
            }
            
            if ($phoneNumber) {
                Cache::forget($this->getCacheKey('user_information_by_phone', $phoneNumber));
            }
            
            if ($id) {
                Cache::forget($this->getCacheKey('user_information_detail', $id));
            }
            
            // Clear list and statistics caches
            Cache::forget('user_information_list');
            Cache::forget('user_information_statistics');
            
            // Clear pattern-based caches
            $this->clearCacheByPattern('user_information_list_');
            $this->clearCacheByPattern('user_information_by_user_');
            $this->clearCacheByPattern('user_information_by_device_');
            $this->clearCacheByPattern('user_information_by_phone_');
            
        } catch (\Exception $e) {
            \Log::warning('Failed to clear user information caches: ' . $e->getMessage());
        }
    }

    /**
     * Clear user-specific caches
     */
    private function clearUserCaches(string $userId): void
    {
        try {
            Cache::forget($this->getCacheKey('user_information_by_user', $userId));
            Cache::forget($this->getCacheKey('my_information', $userId));
            Cache::forget('user_information_list');
            Cache::forget('user_information_statistics');
        } catch (\Exception $e) {
            \Log::warning('Failed to clear user caches: ' . $e->getMessage());
        }
    }

    /**
     * Clear all user information caches
     */
    private function clearAllUserInformationCaches(): void
    {
        try {
            // Clear specific keys
            Cache::forget('user_information_list');
            Cache::forget('user_information_statistics');
            
            // Clear pattern-based caches
            $this->clearCacheByPattern('user_information_list_');
            $this->clearCacheByPattern('user_information_by_user_');
            $this->clearCacheByPattern('user_information_by_device_');
            $this->clearCacheByPattern('user_information_by_phone_');
            $this->clearCacheByPattern('user_information_detail_');
            $this->clearCacheByPattern('my_information_');
            
            \Log::info('All user information caches cleared');
        } catch (\Exception $e) {
            \Log::error('Failed to clear all user information caches: ' . $e->getMessage());
        }
    }

    /**
     * Clear cache keys by pattern (Redis only)
     */
    private function clearCacheByPattern(string $pattern): void
    {
        if (Cache::getDefaultDriver() === 'redis') {
            try {
                $redis = Cache::store('redis')->getClient();
                $keys = $redis->keys($pattern . '*');
                if (!empty($keys)) {
                    $redis->del($keys);
                }
            } catch (\Exception $e) {
                \Log::warning('Could not clear cache by pattern: ' . $e->getMessage());
            }
        }
    }
}