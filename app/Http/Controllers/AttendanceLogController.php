<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Concert;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

class AttendanceLogController extends Controller
{  
    use AuthorizesRequests;
    protected AttendanceService $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    // =============================================
    // PUBLIC ROUTES - No authentication required
    // =============================================

    /**
     * Get attendance stats for a concert (Public).
     */
    public function stats(string $concertId)
    {
        try {
            $concert = Concert::findOrFail($concertId);
            
            // Only show stats for active concerts to public
            if (!auth()->check() && !in_array($concert->status, ['upcoming', 'ongoing'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Concert not available',
                ], 404);
            }

            $stats = $this->attendanceService->getConcertAttendanceStats($concertId);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get attendance stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =============================================
    // SCANNER ROUTES - Scanner and Admin only
    // =============================================

    /**
     * Scan a ticket (Scanner/Admin only).
     */
    public function scan(Request $request)
    {
        $this->authorize('scan', AttendanceLog::class);

        $request->validate([
            'qr_code' => 'required|string',
            'gate_number' => 'nullable|string|max:50',
            'device_id' => 'nullable|uuid|exists:user_devices,device_id',
        ]);

        try {
            $scanner = auth()->user();
            $result = $this->attendanceService->scanTicket(
                $request->qr_code,
                $scanner,
                $request->gate_number
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result['data'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'status' => $result['status'],
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to scan ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get scanner's history (Scanner/Admin only).
     */
    public function scannerHistory(Request $request)
    {
        $this->authorize('viewAny', AttendanceLog::class);

        try {
            $scanner = auth()->user();
            $limit = $request->get('limit', 20);
            
            $history = $this->attendanceService->getScannerHistory($scanner, $limit);

            return response()->json([
                'success' => true,
                'data' => $history,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get scanner history: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get scanner's today scans (Scanner/Admin only).
     */
    public function todayScans(Request $request)
    {
        $this->authorize('viewAny', AttendanceLog::class);

        try {
            $scanner = auth()->user();
            $today = now()->toDateString();
            
            $scans = AttendanceLog::where('scanned_by', $scanner->user_id)
                ->whereDate('scan_time', $today)
                ->with(['ticket', 'concert', 'user'])
                ->latest('scan_time')
                ->get();

            $successful = $scans->where('status', 'success')->count();
            $failed = $scans->where('status', '!=', 'success')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $scans->count(),
                    'successful' => $successful,
                    'failed' => $failed,
                    'scans' => $scans,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get today scans: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get scanner's statistics (Scanner/Admin only).
     */
    public function scannerStats(Request $request)
    {
        $this->authorize('viewAny', AttendanceLog::class);

        try {
            $scanner = auth()->user();
            
            $totalScans = AttendanceLog::where('scanned_by', $scanner->user_id)->count();
            $successfulScans = AttendanceLog::where('scanned_by', $scanner->user_id)
                ->where('status', 'success')
                ->count();
            $failedScans = $totalScans - $successfulScans;
            
            $todayScans = AttendanceLog::where('scanned_by', $scanner->user_id)
                ->whereDate('scan_time', now()->toDateString())
                ->count();

            $lastScan = AttendanceLog::where('scanned_by', $scanner->user_id)
                ->latest('scan_time')
                ->first();

            $stats = [
                'total_scans' => $totalScans,
                'successful_scans' => $successfulScans,
                'failed_scans' => $failedScans,
                'success_rate' => $totalScans > 0 ? round(($successfulScans / $totalScans) * 100, 2) : 0,
                'today_scans' => $todayScans,
                'last_scan' => $lastScan,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get scanner statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =============================================
    // ADMIN ROUTES - Admin only
    // =============================================

    /**
     * Display a listing of attendance logs (Admin only).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', AttendanceLog::class);

        $filters = $request->only([
            'concert_id', 'ticket_id', 'user_id', 'scanned_by', 
            'status', 'gate_number', 'date_from', 'date_to'
        ]);

        $perPage = $request->get('per_page', 15);
        
        $logs = $this->attendanceService->listAttendanceLogs($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Display the specified attendance log (Admin only).
     */
    public function show(string $id)
    {
        $this->authorize('view', AttendanceLog::class);

        try {
            $log = AttendanceLog::with(['ticket', 'concert', 'user', 'scanner', 'device'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $log,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance log not found',
            ], 404);
        }
    }

    /**
     * Get attendance logs for a concert (Admin only).
     */
    public function concertLogs(string $concertId, Request $request)
    {
        $this->authorize('viewAny', AttendanceLog::class);

        try {
            $concert = Concert::findOrFail($concertId);
            
            $filters = $request->only(['status', 'gate_number', 'date_from', 'date_to']);
            $filters['concert_id'] = $concertId;

            $perPage = $request->get('per_page', 15);
            
            $logs = $this->attendanceService->listAttendanceLogs($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'concert' => $concert,
                    'logs' => $logs,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get concert logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get attendance statistics (Admin only).
     */
    public function statistics(Request $request)
    {
        $this->authorize('viewStatistics', AttendanceLog::class);

        try {
            $concertId = $request->get('concert_id');
            
            if ($concertId) {
                $stats = $this->attendanceService->getConcertAttendanceStats($concertId);
            } else {
                // Overall statistics
                $total = AttendanceLog::count();
                $successful = AttendanceLog::where('status', 'success')->count();
                $failed = $total - $successful;
                
                $stats = [
                    'total' => $total,
                    'successful' => $successful,
                    'failed' => $failed,
                    'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
                    'today' => AttendanceLog::whereDate('scan_time', now()->toDateString())->count(),
                    'this_week' => AttendanceLog::whereBetween('scan_time', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                    'this_month' => AttendanceLog::whereMonth('scan_time', now()->month)->count(),
                    'by_status' => AttendanceLog::select('status', DB::raw('count(*) as total'))
                        ->groupBy('status')
                        ->get(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new attendance log manually (Admin only).
     */
    public function store(Request $request)
    {
        $this->authorize('create', AttendanceLog::class);

        $request->validate([
            'ticket_id' => 'required|uuid|exists:tickets,ticket_id',
            'concert_id' => 'required|uuid|exists:concerts,concert_id',
            'user_id' => 'nullable|uuid|exists:users,user_id',
            'device_id' => 'nullable|uuid|exists:user_devices,device_id',
            'gate_number' => 'nullable|string|max:50',
            'status' => 'required|in:success,already_used,invalid,expired',
            'failure_reason' => 'nullable|array',
        ]);

        try {
            $data = $request->all();
            $data['scanned_by'] = auth()->id();
            
            $log = $this->attendanceService->createAttendanceLog($data);

            return response()->json([
                'success' => true,
                'message' => 'Attendance log created successfully',
                'data' => $log,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create attendance log: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified attendance log (Admin only).
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('update', AttendanceLog::class);

        $log = AttendanceLog::findOrFail($id);

        $request->validate([
            'ticket_id' => 'sometimes|uuid|exists:tickets,ticket_id',
            'concert_id' => 'sometimes|uuid|exists:concerts,concert_id',
            'user_id' => 'nullable|uuid|exists:users,user_id',
            'device_id' => 'nullable|uuid|exists:user_devices,device_id',
            'gate_number' => 'nullable|string|max:50',
            'status' => 'sometimes|in:success,already_used,invalid,expired',
            'failure_reason' => 'nullable|array',
        ]);

        try {
            $log = $this->attendanceService->updateAttendanceLog($log, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Attendance log updated successfully',
                'data' => $log,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update attendance log: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified attendance log (Admin only).
     */
    public function destroy(Request $request, string $id)
    {
        $this->authorize('delete', AttendanceLog::class);

        try {
            $log = AttendanceLog::findOrFail($id);
            
            $force = $request->get('force', false);
            $this->attendanceService->deleteAttendanceLog($log, $force);

            return response()->json([
                'success' => true,
                'message' => $force ? 'Attendance log permanently deleted' : 'Attendance log moved to trash',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attendance log: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get attendance logs for a specific user (Admin only).
     */
    public function userLogs(string $userId, Request $request)
    {
        $this->authorize('viewAny', AttendanceLog::class);

        try {
            $filters = $request->only(['status', 'date_from', 'date_to']);
            $filters['user_id'] = $userId;

            $perPage = $request->get('per_page', 15);
            
            $logs = $this->attendanceService->listAttendanceLogs($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get attendance summary for dashboard (Admin only).
     */
    public function dashboardSummary()
    {
        $this->authorize('viewStatistics', AttendanceLog::class);

        try {
            $today = now()->toDateString();
            
            $summary = [
                'today' => [
                    'total' => AttendanceLog::whereDate('scan_time', $today)->count(),
                    'successful' => AttendanceLog::whereDate('scan_time', $today)
                        ->where('status', 'success')
                        ->count(),
                    'failed' => AttendanceLog::whereDate('scan_time', $today)
                        ->where('status', '!=', 'success')
                        ->count(),
                ],
                'this_week' => [
                    'total' => AttendanceLog::whereBetween('scan_time', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                    'successful' => AttendanceLog::whereBetween('scan_time', [now()->startOfWeek(), now()->endOfWeek()])
                        ->where('status', 'success')
                        ->count(),
                ],
                'total' => [
                    'all' => AttendanceLog::count(),
                    'successful' => AttendanceLog::where('status', 'success')->count(),
                    'failed' => AttendanceLog::where('status', '!=', 'success')->count(),
                ],
                'recent' => AttendanceLog::with(['user', 'concert', 'scanner'])
                    ->latest('scan_time')
                    ->limit(10)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get dashboard summary: ' . $e->getMessage(),
            ], 500);
        }
    }
}