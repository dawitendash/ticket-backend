<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    use AuthorizesRequests;
    
    protected DashboardService $dashboardService;

    // Cache TTL in seconds
    protected int $adminCacheTTL = 120; // 2 minutes
    protected int $scannerCacheTTL = 30; // 30 seconds
    protected int $chartCacheTTL = 300; // 5 minutes

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get admin dashboard statistics.
     */
    public function adminDashboard()
    {
        $this->authorize('viewAdmin', DashboardService::class);

        try {
            $cacheKey = $this->getCacheKey('dashboard_admin');
            
            $data = Cache::remember($cacheKey, $this->adminCacheTTL, function () {
                return $this->dashboardService->getAdminDashboard();
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'cached' => Cache::has($cacheKey),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch admin dashboard: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get scanner dashboard statistics.
     */
    public function scannerDashboard(Request $request)
    {
        $this->authorize('viewScanner', DashboardService::class);

        try {
            $concertId = $request->get('concert_id');
            $scannerId = auth()->id();
            
            // Generate cache key with scanner ID and concert ID
            $cacheKey = $this->getCacheKey('dashboard_scanner', $scannerId, $concertId ?? 'current');
            
            $data = Cache::remember($cacheKey, $this->scannerCacheTTL, function () use ($concertId) {
                return $this->dashboardService->getScannerDashboard($concertId);
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'cached' => Cache::has($cacheKey),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch scanner dashboard: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get concert-specific dashboard (Admin only).
     */
    public function concertDashboard(string $concertId)
    {
        $this->authorize('viewConcert', DashboardService::class);

        try {
            $cacheKey = $this->getCacheKey('dashboard_concert', $concertId);
            
            $data = Cache::remember($cacheKey, $this->adminCacheTTL, function () use ($concertId) {
                return $this->dashboardService->getConcertDashboard($concertId);
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'cached' => Cache::has($cacheKey),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch concert dashboard: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get chart data (Admin only).
     */
    public function chartData(Request $request)
    {
        $this->authorize('viewCharts', DashboardService::class);

        try {
            $period = $request->get('period', 'week');
            
            // Validate period
            if (!in_array($period, ['week', 'month', 'year'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid period. Allowed: week, month, year',
                ], 422);
            }
            
            $cacheKey = $this->getCacheKey('dashboard_charts', $period);
            
            $data = Cache::remember($cacheKey, $this->chartCacheTTL, function () use ($period) {
                return $this->dashboardService->getChartData($period);
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'period' => $period,
                'cached' => Cache::has($cacheKey),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch chart data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get real-time dashboard data (for WebSocket/SSE).
     * This bypasses cache for real-time updates.
     */
    public function realTimeDashboard(Request $request)
    {
        $this->authorize('viewAdmin', DashboardService::class);

        try {
            // Bypass cache for real-time data
            $data = $this->dashboardService->getAdminDashboard();

            return response()->json([
                'success' => true,
                'data' => $data,
                'real_time' => true,
                'timestamp' => now()->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch real-time dashboard: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get cached dashboard status.
     */
    public function cacheStatus()
    {
        $this->authorize('viewAdmin', DashboardService::class);

        try {
            $cacheKeys = [
                'dashboard_admin',
                'dashboard_scanner',
                'dashboard_concert',
                'dashboard_charts'
            ];
            
            $status = [];
            foreach ($cacheKeys as $key) {
                $status[$key] = [
                    'exists' => Cache::has($key),
                    'ttl' => Cache::ttl($key),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get cache status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear dashboard cache (Admin only).
     */
    public function clearCache(Request $request)
    {
        $this->authorize('viewAdmin', DashboardService::class);

        try {
            $type = $request->get('type', 'all');
            
            switch ($type) {
                case 'admin':
                    Cache::forget('dashboard_admin');
                    break;
                case 'scanner':
                    $this->clearCacheByPattern('dashboard_scanner_');
                    break;
                case 'concert':
                    $this->clearCacheByPattern('dashboard_concert_');
                    break;
                case 'charts':
                    $this->clearCacheByPattern('dashboard_charts_');
                    break;
                case 'all':
                default:
                    $this->clearAllDashboardCache();
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => "Dashboard cache cleared successfully for type: {$type}",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear dashboard cache: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get multiple dashboard data in one request (performance optimization).
     */
    public function bulkDashboard(Request $request)
    {
        $this->authorize('viewAdmin', DashboardService::class);

        try {
            $sections = $request->get('sections', ['admin', 'charts']);
            $period = $request->get('period', 'week');
            
            $result = [];
            
            foreach ($sections as $section) {
                switch ($section) {
                    case 'admin':
                        $cacheKey = $this->getCacheKey('dashboard_admin');
                        $result['admin'] = Cache::remember($cacheKey, $this->adminCacheTTL, function () {
                            return $this->dashboardService->getAdminDashboard();
                        });
                        break;
                    case 'charts':
                        $cacheKey = $this->getCacheKey('dashboard_charts', $period);
                        $result['charts'] = Cache::remember($cacheKey, $this->chartCacheTTL, function () use ($period) {
                            return $this->dashboardService->getChartData($period);
                        });
                        break;
                    default:
                        $result[$section] = null;
                        break;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'timestamp' => now()->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bulk dashboard data: ' . $e->getMessage(),
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
        } else {
            // For other drivers, we need to clear known keys
            $this->clearKnownCacheKeys($pattern);
        }
    }

    /**
     * Clear known cache keys for non-Redis drivers
     */
    private function clearKnownCacheKeys(string $pattern): void
    {
        try {
            // Get all known dashboard cache keys
            $knownKeys = [
                'dashboard_admin',
                'dashboard_scanner',
                'dashboard_concert',
                'dashboard_charts'
            ];
            
            foreach ($knownKeys as $key) {
                if (strpos($key, $pattern) === 0) {
                    Cache::forget($key);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Could not clear known cache keys: ' . $e->getMessage());
        }
    }

    /**
     * Clear all dashboard caches
     */
    private function clearAllDashboardCache(): void
    {
        try {
            // Clear specific keys
            Cache::forget('dashboard_admin');
            Cache::forget('dashboard_charts_week');
            Cache::forget('dashboard_charts_month');
            Cache::forget('dashboard_charts_year');
            
            // Clear pattern-based keys
            $this->clearCacheByPattern('dashboard_scanner_');
            $this->clearCacheByPattern('dashboard_concert_');
            $this->clearCacheByPattern('dashboard_charts_');
            
            \Log::info('All dashboard caches cleared');
        } catch (\Exception $e) {
            \Log::error('Failed to clear all dashboard caches: ' . $e->getMessage());
        }
    }

    /**
     * Warm up dashboard cache (for performance optimization)
     */
    public function warmUpCache()
    {
        $this->authorize('viewAdmin', DashboardService::class);

        try {
            // Warm up admin dashboard
            $cacheKey = $this->getCacheKey('dashboard_admin');
            Cache::remember($cacheKey, $this->adminCacheTTL, function () {
                return $this->dashboardService->getAdminDashboard();
            });

            // Warm up charts for different periods
            foreach (['week', 'month', 'year'] as $period) {
                $cacheKey = $this->getCacheKey('dashboard_charts', $period);
                Cache::remember($cacheKey, $this->chartCacheTTL, function () use ($period) {
                    return $this->dashboardService->getChartData($period);
                });
            }

            return response()->json([
                'success' => true,
                'message' => 'Dashboard cache warmed up successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to warm up dashboard cache: ' . $e->getMessage(),
            ], 500);
        }
    }
}