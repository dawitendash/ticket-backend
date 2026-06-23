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
            $data = $this->dashboardService->getChartData($period);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch chart data: ' . $e->getMessage(),
            ], 500);
        }
    }
}