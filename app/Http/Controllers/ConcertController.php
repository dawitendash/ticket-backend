<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConcertRequest;
use App\Http\Requests\UpdateConcertRequest;
use App\Http\Resources\ConcertResource;
use App\Http\Resources\ConcertCollection;
use App\Services\ConcertService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Concert;
use Illuminate\Support\Facades\Cache;

class ConcertController extends Controller
{
    use AuthorizesRequests;
    protected ConcertService $concertService;

    // Cache TTL in minutes
    protected int $cacheTTL = 60; // 1 hour for public data
    protected int $adminCacheTTL = 15; // 15 minutes for admin data

    public function __construct(ConcertService $concertService)
    {
        $this->concertService = $concertService;
    }

    // =============================================
    // PUBLIC ROUTES - No authentication required
    // =============================================

    /**
     * Display a listing of active concerts (Public).
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'status', 'artist', 'venue', 'date_from', 'date_to', 'search'
        ]);

        // Only show active concerts to public (upcoming and ongoing)
        if (!auth()->check()) {
            $filters['status'] = ['upcoming', 'ongoing'];
        }

        $perPage = $request->get('per_page', 15);
        
        // Generate cache key based on filters and pagination
        $cacheKey = $this->getCacheKey('concerts_public', $filters, $perPage);
        
        $concerts = Cache::remember($cacheKey, $this->cacheTTL * 60, function () use ($filters, $perPage) {
            return $this->concertService->listConcerts($filters, $perPage);
        });

        return response()->json([
            'success' => true,
            'data' => ConcertResource::collection($concerts),
        ]);
    }

    /**
     * Display a listing of upcoming concerts (Public).
     */
    public function upcoming(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        
        $cacheKey = $this->getCacheKey('concerts_upcoming', ['per_page' => $perPage]);
        
        $concerts = Cache::remember($cacheKey, $this->cacheTTL * 60, function () use ($perPage) {
            return $this->concertService->listConcerts(['status' => 'upcoming'], $perPage);
        });

        return response()->json([
            'success' => true,
            'data' => ConcertResource::collection($concerts),
        ]);
    }

    /**
     * Display the specified concert (Public).
     */
    public function show(string $id)
    {
        $cacheKey = $this->getCacheKey('concert_details', $id);
        
        $concert = Cache::remember($cacheKey, $this->cacheTTL * 60, function () use ($id) {
            return $this->concertService->getConcertWithTicketTypes($id);
        });

        if (!$concert) {
            return response()->json([
                'success' => false,
                'message' => 'Concert not found',
            ], 404);
        }

        // Check if concert is active for public view
        if (!auth()->check() && !in_array($concert->status, ['upcoming', 'ongoing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Concert not available',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ConcertResource($concert),
        ]);
    }

    /**
     * Get concert with ticket types (Public).
     */
    public function withTicketTypes(string $id)
    {
        try {
            $cacheKey = $this->getCacheKey('concert_with_tickets', $id);
            
            $concert = Cache::remember($cacheKey, $this->cacheTTL * 60, function () use ($id) {
                return $this->concertService->getConcertWithTicketTypes($id);
            });

            if (!$concert) {
                return response()->json([
                    'success' => false,
                    'message' => 'Concert not found',
                ], 404);
            }

            // Only show active concerts to public
            if (!auth()->check() && !in_array($concert->status, ['upcoming', 'ongoing'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Concert not available',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new ConcertResource($concert),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get concert details: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get next upcoming concert (Public).
     */
    public function next()
    {
        $cacheKey = 'concert_next_upcoming';
        
        $concert = Cache::remember($cacheKey, $this->cacheTTL * 60, function () {
            return $this->concertService->getNextUpcoming();
        });

        if (!$concert) {
            return response()->json([
                'success' => false,
                'message' => 'No upcoming concerts found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ConcertResource($concert),
        ]);
    }

    // =============================================
    // AUTHENTICATED ROUTES - Requires authentication
    // =============================================

    /**
     * Display a listing of all concerts (Authenticated Users).
     */
    public function all(Request $request)
    {
        $this->authorize('viewAny', Concert::class);

        $filters = $request->only([
            'status', 'artist', 'venue', 'date_from', 'date_to', 'search'
        ]);

        $perPage = $request->get('per_page', 15);
        
        // Shorter cache for authenticated users
        $cacheKey = $this->getCacheKey('concerts_all', $filters, $perPage);
        
        $concerts = Cache::remember($cacheKey, $this->adminCacheTTL * 60, function () use ($filters, $perPage) {
            return $this->concertService->listConcerts($filters, $perPage);
        });

        return response()->json([
            'success' => true,
            'data' => ConcertResource::collection($concerts),
        ]);
    }

    // =============================================
    // SCANNER ROUTES - Scanner and Admin only
    // =============================================

    /**
     * Get concert for scanning (Scanner/Admin only).
     */
    public function getForScanning(string $id)
    {
        $this->authorize('scan', Concert::class);

        try {
            // Short cache for scanning data (5 minutes)
            $cacheKey = $this->getCacheKey('concert_scanning', $id);
            
            $concertData = Cache::remember($cacheKey, 5 * 60, function () use ($id) {
                $concert = $this->concertService->findOrFail($id);
                
                // Only allow scanning for ongoing concerts
                if ($concert->status !== 'ongoing') {
                    return null;
                }
                
                return [
                    'concert' => $concert,
                    'total_tickets_sold' => $concert->total_tickets_sold,
                    'checked_in_count' => $concert->checked_in_count,
                    'remaining' => $concert->total_tickets_sold - $concert->checked_in_count,
                ];
            });

            if (!$concertData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Concert is not open for scanning',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'concert' => new ConcertResource($concertData['concert']),
                    'total_tickets_sold' => $concertData['total_tickets_sold'],
                    'checked_in_count' => $concertData['checked_in_count'],
                    'remaining' => $concertData['remaining'],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get concert for scanning: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get concert statistics for scanner (Scanner/Admin only).
     */
    public function scannerStatistics(string $id)
    {
        $this->authorize('viewStatistics', Concert::class);

        try {
            // Short cache for statistics (10 minutes)
            $cacheKey = $this->getCacheKey('scanner_statistics', $id);
            
            $stats = Cache::remember($cacheKey, 10 * 60, function () use ($id) {
                $stats = $this->concertService->getConcertStatistics($id);
                $stats['attendance_logs'] = $this->concertService->getAttendanceLogs($id);
                return $stats;
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get concert statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =============================================
    // ADMIN ROUTES - Admin only
    // =============================================

    /**
     * Display a listing of all concerts with trashed (Admin only).
     */
    public function adminIndex(Request $request)
    {
        $this->authorize('viewAny', Concert::class);

        $filters = $request->only([
            'status', 'artist', 'venue', 'date_from', 'date_to', 'search', 'with_trashed'
        ]);

        $perPage = $request->get('per_page', 15);
        
        $cacheKey = $this->getCacheKey('concerts_admin', $filters, $perPage);
        
        $concerts = Cache::remember($cacheKey, $this->adminCacheTTL * 60, function () use ($filters, $perPage) {
            return $this->concertService->listConcerts($filters, $perPage, true);
        });

        return response()->json([
            'success' => true,
            'data' => ConcertResource::collection($concerts),
        ]);
    }

    /**
     * Store a newly created concert (Admin only).
     */
    public function store(StoreConcertRequest $request)
    {
        $this->authorize('create', Concert::class);

        try {
            $data = $request->validated();

            // Handle image upload if provided
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('concerts', 'public');
                $data['image_url'] = asset('storage/' . $path);
            }

            $concert = $this->concertService->createConcert($data);
            
            // Clear all concert-related caches
            $this->clearConcertCache();

            return response()->json([
                'success' => true,
                'message' => 'Concert created successfully',
                'data' => new ConcertResource($concert),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create concert: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified concert (Admin only).
     */
    public function update(UpdateConcertRequest $request, string $id)
    {
        $concert = $this->concertService->findOrFail($id);
        $this->authorize('update', $concert);

        try {
            $data = $request->validated();

            // Handle image upload if provided
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('concerts', 'public');
                $data['image_url'] = asset('storage/' . $path);
            }

            $concert = $this->concertService->updateConcert($concert, $data);
            
            // Clear all concert-related caches
            $this->clearConcertCache($id);

            return response()->json([
                'success' => true,
                'message' => 'Concert updated successfully',
                'data' => new ConcertResource($concert),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update concert: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update concert status (Admin only).
     */
    public function updateStatus(Request $request, string $id)
    {
        $concert = $this->concertService->findOrFail($id);
        $this->authorize('update', $concert);

        $request->validate([
            'status' => 'required|in:upcoming,ongoing,completed,cancelled',
        ]);

        try {
            $concert = $this->concertService->updateConcertStatus($concert, $request->status);
            
            // Clear all concert-related caches
            $this->clearConcertCache($id);

            return response()->json([
                'success' => true,
                'message' => 'Concert status updated successfully',
                'data' => new ConcertResource($concert),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update concert status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified concert (Admin only).
     */
    public function destroy(Request $request, string $id)
    {
        $concert = $this->concertService->findOrFail($id);
        $this->authorize('delete', $concert);

        try {
            $force = $request->get('force', false);
            $this->concertService->deleteConcert($concert, $force);
            
            // Clear all concert-related caches
            $this->clearConcertCache($id);

            return response()->json([
                'success' => true,
                'message' => $force ? 'Concert permanently deleted' : 'Concert moved to trash',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete concert: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a deleted concert (Admin only).
     */
    public function restore(string $id)
    {
        try {
            // First, try to find the concert in trash (soft-deleted)
            $concert = $this->concertService->findTrashedOrFail($id);
            $this->authorize('restore', $concert);
            
            $restoredConcert = $this->concertService->restoreConcert($id);
            
            // Clear all concert-related caches
            $this->clearConcertCache($id);

            return response()->json([
                'success' => true,
                'message' => 'Concert restored successfully',
                'data' => new ConcertResource($restoredConcert),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Concert not found in trash or does not exist',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore concert: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get concert statistics (Admin only).
     */
    public function statistics(string $id)
    {
        $this->authorize('viewStatistics', Concert::class);

        try {
            // Cache statistics for 15 minutes
            $cacheKey = $this->getCacheKey('concert_statistics', $id);
            
            $stats = Cache::remember($cacheKey, $this->adminCacheTTL * 60, function () use ($id) {
                return $this->concertService->getConcertStatistics($id);
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get concert statistics: ' . $e->getMessage(),
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
                ksort($param); // Sort for consistency
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
     * Clear all concert-related caches
     */
    private function clearConcertCache(?string $concertId = null): void
    {
        try {
            // Clear specific concert caches if ID provided
            if ($concertId) {
                Cache::forget('concert_details_' . $concertId);
                Cache::forget('concert_with_tickets_' . $concertId);
                Cache::forget('concert_scanning_' . $concertId);
                Cache::forget('scanner_statistics_' . $concertId);
                Cache::forget('concert_statistics_' . $concertId);
            }

            // Clear list caches (using pattern matching)
            $keys = [
                'concerts_public',
                'concerts_upcoming',
                'concerts_all',
                'concerts_admin',
                'concert_next_upcoming'
            ];

            foreach ($keys as $key) {
                Cache::forget($key);
                // Also clear any keys that start with this pattern
                $this->clearCacheByPattern($key . '_');
            }

            // Clear dashboard caches if needed
            Cache::forget('dashboard:admin');
            Cache::forget('dashboard:charts:week');
            Cache::forget('dashboard:charts:month');
            Cache::forget('dashboard:charts:year');

        } catch (\Exception $e) {
            // Log error but don't break the flow
            \Log::error('Failed to clear concert cache: ' . $e->getMessage());
        }
    }

    /**
     * Clear cache keys by pattern (Redis only)
     * For other cache drivers, we'll clear specific keys
     */
    private function clearCacheByPattern(string $pattern): void
    {
        // For Redis, we can use pattern matching
        if (Cache::getDefaultDriver() === 'redis') {
            try {
                $redis = Cache::store('redis')->getClient();
                $keys = $redis->keys($pattern . '*');
                if (!empty($keys)) {
                    $redis->del($keys);
                }
            } catch (\Exception $e) {
                // Fallback to forgetting specific keys
                $this->clearKnownCacheKeys($pattern);
            }
        } else {
            // For other drivers, clear known cache keys
            $this->clearKnownCacheKeys($pattern);
        }
    }

    /**
     * Clear known cache keys that match the pattern
     */
    private function clearKnownCacheKeys(string $pattern): void
    {
        // Get all keys from cache store (if supported)
        try {
            // This is a simplified approach - for file/database drivers
            // You might want to implement a more robust solution
            $knownKeys = [
                'concerts_public',
                'concerts_upcoming', 
                'concerts_all',
                'concerts_admin',
                'concert_next_upcoming'
            ];
            
            foreach ($knownKeys as $key) {
                if (strpos($key, $pattern) === 0) {
                    Cache::forget($key);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Could not clear cache by pattern: ' . $e->getMessage());
        }
    }
}