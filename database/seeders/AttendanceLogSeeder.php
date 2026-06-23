<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AttendanceLogSeeder extends Seeder
{
    public function run(): void
    {
        // Get tickets that are marked as 'used'
        $usedTickets = DB::table('tickets')->where('status', 'used')->get();
        
        // Get scanners (users with scanner or admin role)
        $scanners = DB::table('users')
            ->whereIn('role_id', function ($query) {
                $query->select('role_id')
                    ->from('roles')
                    ->whereIn('slug', ['scanner', 'admin']);
            })
            ->get();

        // Get all devices for reference
        $devices = DB::table('user_devices')->get();

        if ($usedTickets->isEmpty() || $scanners->isEmpty()) {
            return;
        }

        $attendanceLogs = [];

        // Success entries for used tickets
        foreach ($usedTickets as $ticket) {
            $concert = DB::table('concerts')
                ->where('concert_id', $ticket->concert_id)
                ->first();

            if (!$concert) continue;

            $scanner = $scanners->random();
            
            // Get device for this userticket
            $device = $devices->where('user_id', $ticket->user_id)->first();
            $deviceId = $device ? $device->device_id : null;

            $attendanceLogs[] = [
                'attendance_log_id' => Str::uuid(),
                'ticket_id' => $ticket->ticket_id,
                'concert_id' => $ticket->concert_id,
                'device_id' => $deviceId,
                'user_id' => $ticket->user_id,
                'scanned_by' => $scanner->user_id,
                'gate_number' => 'Gate ' . rand(1, 5),
                'scan_time' => now()->subHours(rand(1, 5)),
                'status' => 'success',
                'failure_reason' => null,
            ];
        }

        // Add some failed attempts for active tickets
        $activeTickets = DB::table('tickets')->where('status', 'active')->limit(5)->get();
        
        foreach ($activeTickets as $ticket) {
            $scanner = $scanners->random();
            $statuses = ['already_used', 'invalid', 'expired'];
            $status = $statuses[array_rand($statuses)];
            
            // Get device for this user/ticket
            $device = $devices->where('user_id', $ticket->user_id)->first();
            $deviceId = $device ? $device->device_id : null;
            
            $failureReasons = [
                'already_used' => json_encode([
                    'en' => 'Ticket already scanned',
                    'am' => 'ቲኬቱ ቀድሞ ተቃኝቷል'
                ]),
                'invalid' => json_encode([
                    'en' => 'Invalid QR code',
                    'am' => 'የማይሰራ QR ኮድ'
                ]),
                'expired' => json_encode([
                    'en' => 'Ticket has expired',
                    'am' => 'ቲኬቱ ጊዜው አልፏል'
                ]),
            ];

            $attendanceLogs[] = [
                'attendance_log_id' => Str::uuid(),
                'ticket_id' => $ticket->ticket_id,
                'concert_id' => $ticket->concert_id,
                'device_id' => $deviceId,
                'user_id' => $ticket->user_id,
                'scanned_by' => $scanner->user_id,
                'gate_number' => 'Gate ' . rand(1, 5),
                'scan_time' => now()->subHours(rand(1, 3)),
                'status' => $status,
                'failure_reason' => $failureReasons[$status],
            ];
        }

        // Add some entries with no device (testing nullable)
        if ($usedTickets->count() > 2) {
            $randomTicket = $usedTickets->random();
            $scanner = $scanners->random();
            
            $attendanceLogs[] = [
                'attendance_log_id' => Str::uuid(),
                'ticket_id' => $randomTicket->ticket_id,
                'concert_id' => $randomTicket->concert_id,
                'device_id' => null, // Test nullable
                'user_id' => $randomTicket->user_id,
                'scanned_by' => $scanner->user_id,
                'gate_number' => 'Gate ' . rand(1, 5),
                'scan_time' => now()->subHours(rand(1, 2)),
                'status' => 'success',
                'failure_reason' => null,
            ];
        }

        DB::table('attendance_logs')->insert($attendanceLogs);
    }
}