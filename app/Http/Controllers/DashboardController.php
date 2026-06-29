<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class DashboardController extends Controller
{
    use AuthorizesRequests;
    
    protected DashboardService $dashboardService;

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
            $data = $this->dashboardService->getAdminDashboard();

            return response()->json([
                'success' => true,
                'data' => $data,
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
            
            $data = $this->dashboardService->getScannerDashboard($concertId);

            return response()->json([
                'success' => true,
                'data' => $data,
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
            $data = $this->dashboardService->getConcertDashboard($concertId);

            return response()->json([
                'success' => true,
                'data' => $data,
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
            
            $data = $this->dashboardService->getChartData($period);

            return response()->json([
                'success' => true,
                'data' => $data,
                'period' => $period,
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
     */
    public function realTimeDashboard(Request $request)
    {
        $this->authorize('viewAdmin', DashboardService::class);

        try {
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
                        $result['admin'] = $this->dashboardService->getAdminDashboard();
                        break;
                    case 'charts':
                        $result['charts'] = $this->dashboardService->getChartData($period);
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
}