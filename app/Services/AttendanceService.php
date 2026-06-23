<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttendanceService
{
    public function listAttendanceLogs(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return AttendanceLog::query()
            ->when($filters['concert_id'] ?? null, fn($q, $id) => $q->where('concert_id', $id))
            ->when($filters['ticket_id'] ?? null, fn($q, $id) => $q->where('ticket_id', $id))
            ->when($filters['user_id'] ?? null, fn($q, $id) => $q->where('user_id', $id))
            ->when($filters['scanned_by'] ?? null, fn($q, $id) => $q->where('scanned_by', $id))
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['gate_number'] ?? null, fn($q, $gate) => $q->where('gate_number', $gate))
            ->when($filters['date_from'] ?? null, fn($q, $date) => $q->whereDate('scan_time', '>=', $date))
            ->when($filters['date_to'] ?? null, fn($q, $date) => $q->whereDate('scan_time', '<=', $date))
            ->with(['ticket', 'concert', 'user', 'scanner'])
            ->latest('scan_time')
            ->paginate($perPage);
    }

    public function createAttendanceLog(array $data): AttendanceLog
    {
        return DB::transaction(function () use ($data) {
            $data['attendance_log_id'] = Str::uuid();
            $data['scan_time'] = $data['scan_time'] ?? now();
            return AttendanceLog::create($data);
        });
    }

    public function scanTicket(string $qrCode, User $scanner, ?string $gateNumber = null): array
    {
        return DB::transaction(function () use ($qrCode, $scanner, $gateNumber) {
            $ticket = Ticket::where('qr_code', $qrCode)->first();

            if (!$ticket) {
                return $this->logFailedScan(null, $scanner, 'invalid', 'Invalid QR code');
            }

            if ($ticket->status === 'used') {
                return $this->logFailedScan($ticket, $scanner, 'already_used', 'Ticket already used');
            }

            if ($ticket->status !== 'active') {
                return $this->logFailedScan($ticket, $scanner, 'invalid', 'Ticket is not active');
            }

            if ($ticket->concert->status !== 'ongoing') {
                return $this->logFailedScan($ticket, $scanner, 'expired', 'Concert is not open');
            }

            // Mark ticket as used
            $ticket->update(['status' => 'used']);

            // Create attendance log
            $this->createAttendanceLog([
                'ticket_id' => $ticket->ticket_id,
                'concert_id' => $ticket->concert_id,
                'user_id' => $ticket->user_id,
                'scanned_by' => $scanner->user_id,
                'gate_number' => $gateNumber,
                'status' => 'success',
            ]);

            return [
                'success' => true,
                'message' => 'Entry granted',
                'data' => [
                    'user_name' => $ticket->user->profile_name['en'] ?? $ticket->user->user_name,
                    'ticket_number' => $ticket->ticket_number,
                    'ticket_type' => $ticket->ticketType->ticket_type_name['en'] ?? null,
                    'concert' => $ticket->concert->name['en'] ?? null,
                ]
            ];
        });
    }

    public function logFailedScan(?Ticket $ticket, User $scanner, string $status, string $reason): array
    {
        if ($ticket) {
            $this->createAttendanceLog([
                'ticket_id' => $ticket->ticket_id,
                'concert_id' => $ticket->concert_id,
                'user_id' => $ticket->user_id,
                'scanned_by' => $scanner->user_id,
                'status' => $status,
                'failure_reason' => $reason,
            ]);
        }

        return [
            'success' => false,
            'message' => $reason,
            'status' => $status,
        ];
    }

    public function updateAttendanceLog(AttendanceLog $log, array $data): AttendanceLog
    {
        return DB::transaction(function () use ($log, $data) {
            $log->update($data);
            return $log->fresh();
        });
    }

    public function deleteAttendanceLog(AttendanceLog $log, bool $force = false): void
    {
        DB::transaction(function () use ($log, $force) {
            // If successful scan, revert ticket status
            if ($log->status === 'success') {
                $ticket = $log->ticket;
                if ($ticket && $ticket->status === 'used') {
                    $ticket->update(['status' => 'active']);
                }
            }

            if ($force) {
                $log->forceDelete();
            } else {
                $log->delete();
            }
        });
    }

    public function getConcertAttendanceStats(string $concertId): array
    {
        $totalTickets = \App\Models\Concert::findOrFail($concertId)->tickets()->count();
        $checkedIn = AttendanceLog::where('concert_id', $concertId)
            ->where('status', 'success')
            ->distinct('ticket_id')
            ->count('ticket_id');

        return [
            'total_tickets' => $totalTickets,
            'checked_in' => $checkedIn,
            'remaining' => $totalTickets - $checkedIn,
            'attendance_rate' => $totalTickets > 0 ? round(($checkedIn / $totalTickets) * 100, 2) : 0,
        ];
    }

    public function getScannerHistory(User $scanner, int $limit = 20)
    {
        return AttendanceLog::where('scanned_by', $scanner->user_id)
            ->with(['ticket', 'concert', 'user'])
            ->latest('scan_time')
            ->limit($limit)
            ->get();
    }
}