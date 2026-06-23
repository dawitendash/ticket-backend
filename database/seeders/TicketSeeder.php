<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        // Get all necessary data
        $userRole = DB::table('roles')->where('slug', 'user')->first();
        $users = DB::table('users')->where('role_id', $userRole->role_id)->get();
        $ticketTypes = DB::table('ticket_types')->get();
        $concerts = DB::table('concerts')->get();
        $devices = DB::table('user_devices')->get();

        $tickets = [];
        $ticketNumber = 1000;

        // Select random users to buy tickets (not all users buy tickets)
        $usersWithTickets = $users->random(min(count($users), rand(10, 15)));

        foreach ($usersWithTickets as $user) {
            // Each user buys 1-3 tickets
            $numTickets = rand(1, 3);

            for ($i = 0; $i < $numTickets; $i++) {
                // Random ticket type
                $ticketType = $ticketTypes->random();
                
                // Get the concert for this ticket type
                $concert = $concerts->firstWhere('concert_id', $ticketType->concert_id);
                
                if (!$concert) continue;

                // Get user's device (or null)
                $userDevices = $devices->where('user_id', $user->user_id);
                $deviceId = $userDevices->isNotEmpty() ? $userDevices->random()->device_id : null;

                $ticketNumber++;

        
                $qrData = [
                    'ticket_id' => Str::uuid(),
                    'user_id' => $user->user_id,
                    'concert_id' => $concert->concert_id,
                    'ticket_number' => 'TICK-' . $ticketNumber,
                    'timestamp' => now()->timestamp
                ];

                $tickets[] = [
                    'ticket_id' => Str::uuid(),
                    'device_id' => $deviceId,
                    'user_id' => $user->user_id,
                    'ticket_type_id' => $ticketType->ticket_type_id,
                    'concert_id' => $concert->concert_id,
                    'order_reference' => 'ORD-' . Str::random(12),
                    'qr_code' => base64_encode(json_encode($qrData)),
                    'ticket_number' => 'TICK-' . $ticketNumber,
                    'price_paid' => $ticketType->price,
                    'status' => ['active', 'active', 'active', 'used', 'active'][rand(0, 4)],
                    'purchase_date' => now()->subDays(rand(1, 30)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Update sold_count in ticket_types
                DB::table('ticket_types')
                    ->where('ticket_type_id', $ticketType->ticket_type_id)
                    ->increment('sold_count');
            }
        }
 
        $vipTicketType = $ticketTypes->where('ticket_type_name->en', 'VIP')->first();
        $vvipTicketType = $ticketTypes->where('ticket_type_name->en', 'VVIP')->first();
        
        if ($vipTicketType && $users->count() >= 3) {
            for ($i = 0; $i < 3; $i++) {
                $user = $users[$i];
                $userDevices = $devices->where('user_id', $user->user_id);
                $deviceId = $userDevices->isNotEmpty() ? $userDevices->random()->device_id : null;
                
                $ticketNumber++;
                $qrData = [
                    'ticket_id' => Str::uuid(),
                    'user_id' => $user->user_id,
                    'concert_id' => $vipTicketType->concert_id,
                    'ticket_number' => 'TICK-' . $ticketNumber,
                    'timestamp' => now()->timestamp
                ];

                $tickets[] = [
                    'ticket_id' => Str::uuid(),
                    'device_id' => $deviceId,
                    'user_id' => $user->user_id,
                    'ticket_type_id' => $vipTicketType->ticket_type_id,
                    'concert_id' => $vipTicketType->concert_id,
                    'order_reference' => 'ORD-VIP-' . Str::random(8),
                    'qr_code' => base64_encode(json_encode($qrData)),
                    'ticket_number' => 'TICK-' . $ticketNumber,
                    'price_paid' => $vipTicketType->price,
                    'status' => 'active',
                    'purchase_date' => now()->subDays(rand(1, 10)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                DB::table('ticket_types')
                    ->where('ticket_type_id', $vipTicketType->ticket_type_id)
                    ->increment('sold_count');
            }
        }

 
        if ($vvipTicketType && $users->count() >= 2) {
            for ($i = 0; $i < 2; $i++) {
                $user = $users[$i];
                $userDevices = $devices->where('user_id', $user->user_id);
                $deviceId = $userDevices->isNotEmpty() ? $userDevices->random()->device_id : null;
                
                $ticketNumber++;
                $qrData = [
                    'ticket_id' => Str::uuid(),
                    'user_id' => $user->user_id,
                    'concert_id' => $vvipTicketType->concert_id,
                    'ticket_number' => 'TICK-' . $ticketNumber,
                    'timestamp' => now()->timestamp
                ];

                $tickets[] = [
                    'ticket_id' => Str::uuid(),
                    'device_id' => $deviceId,
                    'user_id' => $user->user_id,
                    'ticket_type_id' => $vvipTicketType->ticket_type_id,
                    'concert_id' => $vvipTicketType->concert_id,
                    'order_reference' => 'ORD-VVIP-' . Str::random(8),
                    'qr_code' => base64_encode(json_encode($qrData)),
                    'ticket_number' => 'TICK-' . $ticketNumber,
                    'price_paid' => $vvipTicketType->price,
                    'status' => 'active',
                    'purchase_date' => now()->subDays(rand(1, 5)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                DB::table('ticket_types')
                    ->where('ticket_type_id', $vvipTicketType->ticket_type_id)
                    ->increment('sold_count');
            }
        }

        DB::table('tickets')->insert($tickets);
    }
}