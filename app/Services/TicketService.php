<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\AttendanceLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketService
{
    /**
     * Generate QR code.
     */
    public function generateQrCode(): string
    {
        return 'QR-' . Str::random(32);
    }

    /**
     * Generate ticket number.
     */
    public function generateTicketNumber(): string
    {
        return 'TKT-' . strtoupper(Str::random(8)) . '-' . date('Ymd');
    }

    /**
     * Generate order reference.
     */
    public function generateOrderReference(): string
    {
        return 'ORD-' . strtoupper(Str::random(10)) . '-' . date('Ymd');
    }

    /**
     * List tickets with filters.
     */
    public function listTickets(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Ticket::query()
            ->when($filters['user_id'] ?? null, fn($q, $id) => $q->where('user_id', $id))
            ->when($filters['concert_id'] ?? null, fn($q, $id) => $q->where('concert_id', $id))
            ->when($filters['ticket_type_id'] ?? null, fn($q, $id) => $q->where('ticket_type_id', $id))
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['device_id'] ?? null, fn($q, $id) => $q->where('device_id', $id))
            ->when($filters['search'] ?? null, function($q, $search) {
                $q->where(function($query) use ($search) {
                    $query->where('ticket_number', 'LIKE', "%{$search}%")
                        ->orWhere('order_reference', 'LIKE', "%{$search}%")
                        ->orWhere('qr_code', 'LIKE', "%{$search}%");
                });
            })
            ->when($filters['date_from'] ?? null, fn($q, $date) => $q->where('purchase_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn($q, $date) => $q->where('purchase_date', '<=', $date))
            ->with(['user', 'concert', 'ticketType', 'device', 'attendanceLogs'])
            ->latest('purchase_date')
            ->paginate($perPage);
    }

    /**
     * Get tickets for a specific user.
     */
    public function getUserTickets(string $userId): \Illuminate\Database\Eloquent\Collection
    {
        return Ticket::where('user_id', $userId)
            ->with(['concert', 'ticketType', 'attendanceLogs'])
            ->orderBy('purchase_date', 'desc')
            ->get();
    }

    /**
     * Get active tickets for a user.
     */
    public function getUserActiveTickets(string $userId): \Illuminate\Database\Eloquent\Collection
    {
        return Ticket::where('user_id', $userId)
            ->where('status', 'active')
            ->with(['concert', 'ticketType'])
            ->orderBy('purchase_date', 'desc')
            ->get();
    }

    /**
     * Get tickets for a concert.
     */
    public function getConcertTickets(string $concertId): \Illuminate\Database\Eloquent\Collection
    {
        return Ticket::where('concert_id', $concertId)
            ->with(['user', 'ticketType', 'attendanceLogs'])
            ->orderBy('purchase_date', 'desc')
            ->get();
    }

    /**
     * Find ticket by ID.
     */
    public function findTicket(string $id): ?Ticket
    {
        return Ticket::with(['user', 'concert', 'ticketType', 'device', 'attendanceLogs'])
            ->find($id);
    }

    /**
     * Find ticket by ID or fail.
     */
    public function findTicketOrFail(string $id): Ticket
    {
        return Ticket::with(['user', 'concert', 'ticketType', 'device', 'attendanceLogs'])
            ->findOrFail($id);
    }

    /**
     * Find ticket by QR code.
     */
    public function findByQrCode(string $qrCode): ?Ticket
    {
        return Ticket::with(['user', 'concert', 'ticketType', 'attendanceLogs'])
            ->where('qr_code', $qrCode)
            ->first();
    }

    /**
     * Find ticket by ticket number.
     */
    public function findByTicketNumber(string $ticketNumber): ?Ticket
    {
        return Ticket::with(['user', 'concert', 'ticketType', 'attendanceLogs'])
            ->where('ticket_number', $ticketNumber)
            ->first();
    }

    /**
     * Create a ticket.
     * ✅ Generates QR code, ticket number, and order reference in the service.
     */
    public function createTicket(array $data): Ticket
    {
        return DB::transaction(function () use ($data) { 
            $data['ticket_id'] = Str::uuid();
            $data['ticket_number'] = $this->generateTicketNumber();
            $data['qr_code'] = $this->generateQrCode(); 
            $data['purchase_date'] = $data['purchase_date'] ?? now();
            $data['status'] = $data['status'] ?? 'active';

            return Ticket::create($data);
        });
    }

    /**
     * Bulk create tickets.
     * ✅ Generates unique identifiers for each ticket in the service.
     */
    public function bulkCreateTickets(array $tickets): array
    {
        return DB::transaction(function () use ($tickets) {
            $created = [];
            foreach ($tickets as $data) { 
                $data['ticket_id'] = Str::uuid();
                $data['ticket_number'] = $this->generateTicketNumber();
                $data['qr_code'] = $this->generateQrCode(); 
                $data['purchase_date'] = $data['purchase_date'] ?? now();
                $data['status'] = $data['status'] ?? 'active';

                $created[] = Ticket::create($data);
            }
            return $created;
        });
    }

    /**
     * Update a ticket.
     */
    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        return DB::transaction(function () use ($ticket, $data) {
            $ticket->update($data);
            return $ticket->fresh();
        });
    }

    /**
     * Delete a ticket.
     */
    public function deleteTicket(Ticket $ticket, bool $force = false): bool
    {
        return DB::transaction(function () use ($ticket, $force) {
            if ($force) {
                return $ticket->forceDelete();
            }
            return $ticket->delete();
        });
    }

    /**
     * Restore a soft-deleted ticket.
     */
    public function restoreTicket(string $id): Ticket
    {
        $ticket = Ticket::onlyTrashed()->findOrFail($id);
        $ticket->restore();
        return $ticket->fresh();
    }

    /**
     * Scan ticket (mark as used).
     */
    public function scanTicket(string $qrCode, string $scannerId, ?string $deviceId = null, ?string $gateNumber = null): array
    {
        return DB::transaction(function () use ($qrCode, $scannerId, $deviceId, $gateNumber) {
            // Find ticket by QR code
            $ticket = Ticket::where('qr_code', $qrCode)->first();
            
            if (!$ticket) {
                return [
                    'success' => false,
                    'message' => 'Ticket not found',
                    'status' => 'invalid',
                ];
            }

            // Check if ticket is active
            if ($ticket->status !== 'active') {
                return [
                    'success' => false,
                    'message' => 'Ticket is ' . $ticket->status_label,
                    'status' => $ticket->status,
                    'ticket' => $ticket,
                ];
            }

            // Check if ticket is for a valid concert
            $concert = $ticket->concert;
            if (!$concert || $concert->status !== 'ongoing') {
                return [
                    'success' => false,
                    'message' => 'Concert is not open for scanning',
                    'status' => 'concert_not_open',
                ];
            }

            // Mark ticket as used
            $ticket->markAsUsed();

            // Create attendance log
            $attendanceLog = AttendanceLog::create([
                'attendance_log_id' => Str::uuid(),
                'ticket_id' => $ticket->ticket_id,
                'concert_id' => $ticket->concert_id,
                'device_id' => $deviceId,
                'user_id' => $ticket->user_id,
                'scanned_by' => $scannerId,
                'gate_number' => $gateNumber ?? 'Gate ' . rand(1, 5),
                'scan_time' => now(),
                'status' => 'success',
                'failure_reason' => null,
            ]);

            return [
                'success' => true,
                'message' => 'Ticket scanned successfully',
                'status' => 'success',
                'ticket' => $ticket->fresh(['concert', 'ticketType', 'userInformation', 'user']),
                'attendance_log' => $attendanceLog,
            ];
        });
    }

    /**
     * Validate ticket without marking as used.
     */
  /**
 * Validate ticket without marking as used.
 * Returns ticket details with user information by device_id.
 */
public function validateTicket(string $qrCode): array
{
    $ticket = Ticket::where('qr_code', $qrCode)->with(['concert', 'ticketType', 'userInformation'])->first();
    
    if (!$ticket) {
        return [
            'success' => false,
            'message' => 'Ticket not found',
            'status' => 'invalid',
        ];
    }

    if ($ticket->status !== 'active') {
        return [
            'success' => false,
            'message' => 'Ticket is ' . $ticket->status_label,
            'status' => $ticket->status,
            'ticket' => $ticket,
        ];
    }

    $concert = $ticket->concert;
    if (!$concert || $concert->status !== 'ongoing') {
        return [
            'success' => false,
            'message' => 'Concert is not open for scanning',
            'status' => 'concert_not_open',
        ];
    }

    // ✅ Get user information by device_id
    $userInformation = null;
    if ($ticket->device_id) {
        $userInformation = \App\Models\UserInformation::where('device_id', $ticket->device_id)->first();
    }

    return [
        'success' => true,
        'message' => 'Ticket is valid',
        'status' => 'valid',
        'ticket' => $ticket,
        'user_information' => $userInformation,
    ];
}
    /**
     * Get ticket statistics.
     */
    public function getStatistics(?string $concertId = null): array
    {
        $query = Ticket::query();
        
        if ($concertId) {
            $query->where('concert_id', $concertId);
        }
        
        $total = $query->count();
        $active = (clone $query)->where('status', 'active')->count();
        $used = (clone $query)->where('status', 'used')->count();
        $cancelled = (clone $query)->where('status', 'cancelled')->count();
        $refunded = (clone $query)->where('status', 'refunded')->count();
        
        return [
            'total_tickets' => $total,
            'active_tickets' => $active,
            'used_tickets' => $used,
            'cancelled_tickets' => $cancelled,
            'refunded_tickets' => $refunded,
            'converted_rate' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
        ];
    }
}