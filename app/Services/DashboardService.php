<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Concert;
use App\Models\User;
use App\Models\TicketType;
use App\Models\AttendanceLog;
use App\Models\UserInformation;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get admin dashboard statistics.
     */
    public function getAdminDashboard(): array
    {
        // Total counts
        $totalUsers = User::count();
        $totalConcerts = Concert::count();
        $totalTickets = Ticket::count();
        $totalTicketTypes = TicketType::count();
        
        // Ticket statistics
        $activeTickets = Ticket::where('status', 'active')->count();
        $usedTickets = Ticket::where('status', 'used')->count();
        $cancelledTickets = Ticket::where('status', 'cancelled')->count();
        $refundedTickets = Ticket::where('status', 'refunded')->count();
        
        // Revenue statistics
        $totalRevenue = Ticket::sum('price_paid');
        $todayRevenue = Ticket::whereDate('purchase_date', today())->sum('price_paid');
        $weekRevenue = Ticket::whereBetween('purchase_date', [now()->startOfWeek(), now()->endOfWeek()])->sum('price_paid');
        $monthRevenue = Ticket::whereMonth('purchase_date', now()->month)->sum('price_paid');
        
        // Concert statistics
        $upcomingConcerts = Concert::where('status', 'upcoming')->count();
        $ongoingConcerts = Concert::where('status', 'ongoing')->count();
        $completedConcerts = Concert::where('status', 'completed')->count();
        $cancelledConcerts = Concert::where('status', 'cancelled')->count();
        
        // User statistics
        $usersWithInfo = UserInformation::count();
        $usersWithNationalId = UserInformation::whereNotNull('national_id_number')->count();
        $usersWithCompleteProfile = UserInformation::whereNotNull('full_name')
            ->whereNotNull('phone_number')
            ->whereNotNull('national_id_number')
            ->count();
        
        // Recent activity
        $recentTickets = Ticket::with(['user', 'concert'])
            ->latest('purchase_date')
            ->limit(10)
            ->get();
            
        $recentAttendance = AttendanceLog::with(['user', 'concert', 'ticket'])
            ->latest('scan_time')
            ->limit(10)
            ->get();
        
        // Top concerts by ticket sales
        $topConcerts = Concert::withCount('tickets')
            ->having('tickets_count', '>', 0)
            ->orderBy('tickets_count', 'desc')
            ->limit(5)
            ->get();
        
        // Daily ticket sales (last 7 days)
        $dailySales = Ticket::select(
                DB::raw('DATE(purchase_date) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(price_paid) as revenue')
            )
            ->where('purchase_date', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();
        
        return [
            'overview' => [
                'total_users' => $totalUsers,
                'total_concerts' => $totalConcerts,
                'total_tickets' => $totalTickets,
                'total_ticket_types' => $totalTicketTypes,
                'total_revenue' => (float) $totalRevenue,
            ],
            'ticket_stats' => [
                'active' => $activeTickets,
                'used' => $usedTickets,
                'cancelled' => $cancelledTickets,
                'refunded' => $refundedTickets,
                'conversion_rate' => $totalTickets > 0 ? round(($usedTickets / $totalTickets) * 100, 2) : 0,
            ],
            'revenue' => [
                'today' => (float) $todayRevenue,
                'this_week' => (float) $weekRevenue,
                'this_month' => (float) $monthRevenue,
                'total' => (float) $totalRevenue,
            ],
            'concerts' => [
                'upcoming' => $upcomingConcerts,
                'ongoing' => $ongoingConcerts,
                'completed' => $completedConcerts,
                'cancelled' => $cancelledConcerts,
            ],
            'users' => [
                'total' => $totalUsers,
                'with_information' => $usersWithInfo,
                'with_national_id' => $usersWithNationalId,
                'complete_profile' => $usersWithCompleteProfile,
                'completion_rate' => $totalUsers > 0 ? round(($usersWithCompleteProfile / $totalUsers) * 100, 2) : 0,
            ],
            'recent_activity' => [
                'recent_tickets' => $recentTickets,
                'recent_attendance' => $recentAttendance,
            ],
            'top_concerts' => $topConcerts,
            'daily_sales' => $dailySales,
        ];
    }

    /**
     * Get scanner dashboard statistics.
     */
    public function getScannerDashboard(?string $concertId = null): array
    {
        // Get scanner's current concert if not specified
        if (!$concertId) {
            $concert = Concert::where('status', 'ongoing')->first();
            $concertId = $concert?->concert_id;
        }
        
        // Total tickets for the concert
        $totalTickets = Ticket::where('concert_id', $concertId)->count();
        $activeTickets = Ticket::where('concert_id', $concertId)->where('status', 'active')->count();
        $usedTickets = Ticket::where('concert_id', $concertId)->where('status', 'used')->count();
        
        // Today's scans
        $todayScans = AttendanceLog::where('concert_id', $concertId)
            ->whereDate('scan_time', today())
            ->count();
            
        // Total scans
        $totalScans = AttendanceLog::where('concert_id', $concertId)->count();
        
        // Recent scans
        $recentScans = AttendanceLog::where('concert_id', $concertId)
            ->with(['user', 'ticket'])
            ->latest('scan_time')
            ->limit(10)
            ->get();
        
        // Scan by gate
        $gateScans = AttendanceLog::where('concert_id', $concertId)
            ->select('gate_number', DB::raw('COUNT(*) as count'))
            ->groupBy('gate_number')
            ->get();
        
        // Scan by hour (last 24 hours)
        $hourlyScans = AttendanceLog::where('concert_id', $concertId)
            ->where('scan_time', '>=', now()->subHours(24))
            ->select(
                DB::raw('HOUR(scan_time) as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('hour')
            ->orderBy('hour', 'asc')
            ->get();
        
        // Get concert details
        $concert = Concert::find($concertId);
        
        return [
            'concert' => $concert ? [
                'id' => $concert->concert_id,
                'name' => $concert->name,
                'venue' => $concert->venue,
                'status' => $concert->status,
                'start_date' => $concert->concert_date?->toDateTimeString(),
            ] : null,
            'ticket_stats' => [
                'total' => $totalTickets,
                'active' => $activeTickets,
                'used' => $usedTickets,
                'remaining' => $activeTickets,
                'scan_rate' => $totalTickets > 0 ? round(($usedTickets / $totalTickets) * 100, 2) : 0,
            ],
            'scan_stats' => [
                'today' => $todayScans,
                'total' => $totalScans,
                'recent' => $recentScans,
            ],
            'gate_stats' => $gateScans,
            'hourly_scans' => $hourlyScans,
        ];
    }

    /**
     * Get concert-specific dashboard for admin.
     */
    public function getConcertDashboard(string $concertId): array
    {
        $concert = Concert::with(['ticketTypes'])->findOrFail($concertId);
        
        // Ticket statistics
        $totalTickets = Ticket::where('concert_id', $concertId)->count();
        $activeTickets = Ticket::where('concert_id', $concertId)->where('status', 'active')->count();
        $usedTickets = Ticket::where('concert_id', $concertId)->where('status', 'used')->count();
        $cancelledTickets = Ticket::where('concert_id', $concertId)->where('status', 'cancelled')->count();
        $refundedTickets = Ticket::where('concert_id', $concertId)->where('status', 'refunded')->count();
        
        // Revenue
        $totalRevenue = Ticket::where('concert_id', $concertId)->sum('price_paid');
        
        // Ticket type breakdown
        $ticketTypeBreakdown = TicketType::where('concert_id', $concertId)
            ->withCount('tickets')
            ->get()
            ->map(function ($type) {
                return [
                    'name' => $type->ticket_type_name,
                    'capacity' => $type->capacity,
                    'sold' => $type->tickets_count,
                    'available' => $type->capacity - $type->tickets_count,
                    'revenue' => $type->tickets->sum('price_paid'),
                ];
            });
        
        // Attendance
        $attendanceLogs = AttendanceLog::where('concert_id', $concertId)
            ->count();
        
        return [
            'concert' => [
                'id' => $concert->concert_id,
                'name' => $concert->name,
                'venue' => $concert->venue,
                'status' => $concert->status,
                'date' => $concert->concert_date?->toDateTimeString(),
            ],
            'ticket_stats' => [
                'total' => $totalTickets,
                'active' => $activeTickets,
                'used' => $usedTickets,
                'cancelled' => $cancelledTickets,
                'refunded' => $refundedTickets,
                'attendance_rate' => $totalTickets > 0 ? round(($usedTickets / $totalTickets) * 100, 2) : 0,
            ],
            'revenue' => (float) $totalRevenue,
            'ticket_types' => $ticketTypeBreakdown,
            'attendance' => [
                'total_scans' => $attendanceLogs,
            ],
        ];
    }

    /**
     * Get chart data for admin dashboard.
     */
    public function getChartData(string $period = 'week'): array
    {
        $startDate = match($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->subDays(7),
        };
        
        // Daily sales
        $dailySales = Ticket::select(
                DB::raw('DATE(purchase_date) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(price_paid) as revenue')
            )
            ->where('purchase_date', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();
        
        // Daily scans
        $dailyScans = AttendanceLog::select(
                DB::raw('DATE(scan_time) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('scan_time', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();
        
        return [
            'labels' => $dailySales->pluck('date'),
            'sales' => [
                'count' => $dailySales->pluck('count'),
                'revenue' => $dailySales->pluck('revenue'),
            ],
            'scans' => $dailyScans->pluck('count'),
        ];
    }
}