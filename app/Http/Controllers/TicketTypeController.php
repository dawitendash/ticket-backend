<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketTypeRequest;
use App\Http\Requests\UpdateTicketTypeRequest;
use App\Http\Requests\BulkStoreTicketTypeRequest;
use App\Http\Resources\TicketTypeResource;
use App\Services\TicketTypeService;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;

class TicketTypeController extends Controller
{
    use AuthorizesRequests;
    
    protected TicketTypeService $ticketTypeService;

    // Cache TTL in seconds
    protected int $publicCacheTTL = 600; // 10 minutes
    protected int $adminCacheTTL = 300; // 5 minutes
    protected int $statsCacheTTL = 180; // 3 minutes

    public function __construct(TicketTypeService $ticketTypeService)
    {
        $this->ticketTypeService = $ticketTypeService;
    }

    // =============================================
    // PUBLIC ROUTES - No authentication required
    // =============================================

    /**
     * Display a listing of ticket types.
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'concert_id', 'is_active', 'min_price', 'max_price', 'available', 'search'
        ]);

        $perPage = $request->get('per_page', 15);
        
        $cacheKey = $this->getCacheKey('ticket_types_list', $filters, $perPage);
        
        $ticketTypes = Cache::remember($cacheKey, $this->publicCacheTTL, function () use ($filters, $perPage) {
            return $this->ticketTypeService->listTicketTypes($filters, $perPage);
        });

        return response()->json([
            'success' => true,
            'data' => TicketTypeResource::collection($ticketTypes),
            'cached' => Cache::has($cacheKey),
        ]);
    }

    /**
     * Get ticket types for a specific concert.
     */
    public function getByConcert(string $concertId)
    {
        $cacheKey = $this->getCacheKey('ticket_types_by_concert', $concertId);
        
        $ticketTypes = Cache::remember($cacheKey, $this->publicCacheTTL, function () use ($concertId) {
            return $this->ticketTypeService->getTicketTypesByConcert($concertId);
        });

        return response()->json([
            'success' => true,
            'data' => TicketTypeResource::collection($ticketTypes),
            'cached' => Cache::has($cacheKey),
        ]);
    }

    /**
     * Get available ticket types for a concert.
     */
    public function getAvailableByConcert(string $concertId)
    {
        $cacheKey = $this->getCacheKey('ticket_types_available', $concertId);
        
        $ticketTypes = Cache::remember($cacheKey, $this->publicCacheTTL, function () use ($concertId) {
            return $this->ticketTypeService->getAvailableTicketTypes($concertId);
        });

        return response()->json([
            'success' => true,
            'data' => TicketTypeResource::collection($ticketTypes),
            'cached' => Cache::has($cacheKey),
        ]);
    }

    /**
     * Display the specified ticket type.
     */
    public function show(string $id)
    {
        try {
            $cacheKey = $this->getCacheKey('ticket_type_detail', $id);
            
            $ticketType = Cache::remember($cacheKey, $this->publicCacheTTL, function () use ($id) {
                return $this->ticketTypeService->findTicketTypeOrFail($id);
            });

            return response()->json([
                'success' => true,
                'data' => new TicketTypeResource($ticketType),
                'cached' => Cache::has($cacheKey),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket type not found',
            ], 404);
        }
    }

    /**
     * Check ticket type availability.
     */
    public function checkAvailability(Request $request, string $id)
    {
        $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $quantity = $request->get('quantity', 1);
        
        $cacheKey = $this->getCacheKey('ticket_type_availability', $id, $quantity);
        
        $result = Cache::remember($cacheKey, 60, function () use ($id, $quantity) {
            $available = $this->ticketTypeService->checkAvailability($id, $quantity);
            
            try {
                $ticketType = $this->ticketTypeService->findTicketTypeOrFail($id);
                return [
                    'available' => $available,
                    'available_count' => $ticketType->capacity - $ticketType->sold_count,
                    'requested_quantity' => $quantity,
                    'ticket_type_id' => $id,
                ];
            } catch (\Exception $e) {
                return null;
            }
        });

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket type not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'cached' => Cache::has($cacheKey),
        ]);
    }

    // =============================================
    // ADMIN ROUTES - Admin only
    // =============================================

    /**
     * Display a listing of all ticket types (Admin).
     */
    public function adminIndex(Request $request)
    {
        $this->authorize('viewAny', TicketType::class);

        $filters = $request->only([
            'concert_id', 'is_active', 'min_price', 'max_price', 'available', 'search', 'with_trashed'
        ]);

        $perPage = $request->get('per_page', 15);
        
        $cacheKey = $this->getCacheKey('ticket_types_admin', $filters, $perPage);
        
        $ticketTypes = Cache::remember($cacheKey, $this->adminCacheTTL, function () use ($filters, $perPage) {
            return $this->ticketTypeService->listTicketTypes($filters, $perPage);
        });

        return response()->json([
            'success' => true,
            'data' => TicketTypeResource::collection($ticketTypes),
            'cached' => Cache::has($cacheKey),
        ]);
    }

    /**
     * Store a newly created ticket type.
     */
    public function store(StoreTicketTypeRequest $request)
    {
        $this->authorize('create', TicketType::class);

        try {
            $data = $request->validated();
            $ticketType = $this->ticketTypeService->createTicketType($data);
            
            // Clear relevant caches
            $this->clearTicketTypeCaches($ticketType);

            return response()->json([
                'success' => true,
                'message' => 'Ticket type created successfully',
                'data' => new TicketTypeResource($ticketType),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk create ticket types for a concert.
     */
    public function bulkStore(BulkStoreTicketTypeRequest $request)
    {
        $this->authorize('create', TicketType::class);

        try {
            $data = $request->validated();
            $ticketTypes = $this->ticketTypeService->bulkCreateTicketTypes(
                $data['concert_id'],
                $data['ticket_types']
            );
            
            // Clear all ticket type caches
            $this->clearAllTicketTypeCaches();

            return response()->json([
                'success' => true,
                'message' => count($ticketTypes) . ' ticket types created successfully',
                'data' => TicketTypeResource::collection($ticketTypes),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket types: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified ticket type.
     */
    public function update(UpdateTicketTypeRequest $request, string $id)
    {
        try {
            $ticketType = $this->ticketTypeService->findTicketTypeOrFail($id);
            $this->authorize('update', $ticketType);

            $data = $request->validated();
            $ticketType = $this->ticketTypeService->updateTicketType($ticketType, $data);
            
            // Clear relevant caches
            $this->clearTicketTypeCaches($ticketType);

            return response()->json([
                'success' => true,
                'message' => 'Ticket type updated successfully',
                'data' => new TicketTypeResource($ticketType),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified ticket type.
     */
    public function destroy(Request $request, string $id)
    {
        try {
            $ticketType = $this->ticketTypeService->findTicketTypeOrFail($id);
            $this->authorize('delete', $ticketType);

            $force = $request->get('force', false);
            $result = $this->ticketTypeService->deleteTicketType($ticketType, $force);
            
            // Clear relevant caches
            $this->clearTicketTypeCaches($ticketType);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'ticket_count' => $result['ticket_count'] ?? 0,
                    'suggestion' => 'Use force=true to delete with all associated tickets, or soft_delete=true to only delete if no tickets exist',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'ticket_count' => $result['ticket_count'] ?? 0,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a ticket type and all its tickets.
     */
    public function destroyWithTickets(Request $request, string $id)
    {
        try {
            $ticketType = $this->ticketTypeService->findTicketTypeOrFail($id);
            $this->authorize('delete', $ticketType);

            $force = $request->get('force', false);
            $result = $this->ticketTypeService->deleteTicketTypeWithTickets($ticketType, $force);
            
            // Clear relevant caches
            $this->clearTicketTypeCaches($ticketType);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'ticket_count' => $result['ticket_count'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Soft delete a ticket type (only if no tickets exist).
     */
    public function softDelete(string $id)
    {
        try {
            $ticketType = $this->ticketTypeService->findTicketTypeOrFail($id);
            $this->authorize('delete', $ticketType);

            $result = $this->ticketTypeService->softDeleteTicketType($ticketType);
            
            // Clear relevant caches
            $this->clearTicketTypeCaches($ticketType);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'ticket_count' => $result['ticket_count'] ?? 0,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted ticket type.
     */
    public function restore(string $id)
    {
        try {
            $ticketType = $this->ticketTypeService->restoreTicketType($id);
            $this->authorize('restore', $ticketType);
            
            // Clear relevant caches
            $this->clearTicketTypeCaches($ticketType);

            return response()->json([
                'success' => true,
                'message' => 'Ticket type restored successfully',
                'data' => new TicketTypeResource($ticketType),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore ticket type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get ticket type statistics.
     */
    public function statistics(string $id)
    {
        try {
            $this->authorize('viewStatistics', TicketType::class);
            
            $cacheKey = $this->getCacheKey('ticket_type_statistics', $id);
            
            $stats = Cache::remember($cacheKey, $this->statsCacheTTL, function () use ($id) {
                return $this->ticketTypeService->getTicketTypeStatistics($id);
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
                'cached' => Cache::has($cacheKey),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get ticket type statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sales analytics for a concert.
     */
    public function salesAnalytics(string $concertId)
    {
        try {
            $this->authorize('viewStatistics', TicketType::class);
            
            $cacheKey = $this->getCacheKey('ticket_type_analytics', $concertId);
            
            $analytics = Cache::remember($cacheKey, $this->statsCacheTTL, function () use ($concertId) {
                return $this->ticketTypeService->getSalesAnalytics($concertId);
            });

            return response()->json([
                'success' => true,
                'data' => $analytics,
                'cached' => Cache::has($cacheKey),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get sales analytics: ' . $e->getMessage(),
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
     * Clear ticket type related caches
     */
    private function clearTicketTypeCaches($ticketType): void
    {
        try {
            // Clear specific ticket type caches
            Cache::forget($this->getCacheKey('ticket_type_detail', $ticketType->ticket_type_id ?? $ticketType->id));
            Cache::forget($this->getCacheKey('ticket_type_statistics', $ticketType->ticket_type_id ?? $ticketType->id));
            Cache::forget($this->getCacheKey('ticket_type_availability', $ticketType->ticket_type_id ?? $ticketType->id));
            
            // Clear concert-specific caches
            if (isset($ticketType->concert_id)) {
                Cache::forget($this->getCacheKey('ticket_types_by_concert', $ticketType->concert_id));
                Cache::forget($this->getCacheKey('ticket_types_available', $ticketType->concert_id));
                Cache::forget($this->getCacheKey('ticket_type_analytics', $ticketType->concert_id));
            }
            
            // Clear list caches
            Cache::forget('ticket_types_list');
            Cache::forget('ticket_types_admin');
            
            // Clear pattern-based caches
            $this->clearCacheByPattern('ticket_types_list_');
            $this->clearCacheByPattern('ticket_types_admin_');
            $this->clearCacheByPattern('ticket_types_by_concert_');
            $this->clearCacheByPattern('ticket_types_available_');
            
        } catch (\Exception $e) {
            \Log::warning('Failed to clear ticket type caches: ' . $e->getMessage());
        }
    }

    /**
     * Clear all ticket type caches
     */
    private function clearAllTicketTypeCaches(): void
    {
        try {
            // Clear specific keys
            Cache::forget('ticket_types_list');
            Cache::forget('ticket_types_admin');
            
            // Clear pattern-based caches
            $this->clearCacheByPattern('ticket_types_list_');
            $this->clearCacheByPattern('ticket_types_admin_');
            $this->clearCacheByPattern('ticket_types_by_concert_');
            $this->clearCacheByPattern('ticket_types_available_');
            $this->clearCacheByPattern('ticket_type_detail_');
            $this->clearCacheByPattern('ticket_type_statistics_');
            $this->clearCacheByPattern('ticket_type_analytics_');
            $this->clearCacheByPattern('ticket_type_availability_');
            
            \Log::info('All ticket type caches cleared');
        } catch (\Exception $e) {
            \Log::error('Failed to clear all ticket type caches: ' . $e->getMessage());
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