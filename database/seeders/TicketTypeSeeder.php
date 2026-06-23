<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TicketTypeSeeder extends Seeder
{
    public function run(): void
    {
        $concerts = DB::table('concerts')->get();

        $ticketTypes = [];

        foreach ($concerts as $concert) {
            // Normal tickets
            $ticketTypes[] = [
                'ticket_type_id' => Str::uuid(),
                'concert_id' => $concert->concert_id,
                'ticket_type_name' => json_encode([
                    'en' => 'Normal', 
                    'am' => 'መደበኛ'
                ]),
                'ticket_type_description' => json_encode([
                    'en' => 'Standard entry ticket',
                    'am' => 'መደበኛ የመግቢያ ቲኬት'
                ]),
                'price' => 50.00,
                'capacity' => floor($concert->max_capacity * 0.6),
                'sold_count' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // VIP tickets
            $ticketTypes[] = [
                'ticket_type_id' => Str::uuid(),
                'concert_id' => $concert->concert_id,
                'ticket_type_name' => json_encode([
                    'en' => 'VIP', 
                    'am' => 'ቪአይፒ'
                ]),
                'ticket_type_description' => json_encode([
                    'en' => 'VIP entry with early access and exclusive lounge',
                    'am' => 'ቀዳሚ መግቢያ እና ልዩ ላውንጅ ያለው የቪአይፒ መግቢያ'
                ]),
                'price' => 150.00,
                'capacity' => floor($concert->max_capacity * 0.25),
                'sold_count' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // VVIP tickets
            $ticketTypes[] = [
                'ticket_type_id' => Str::uuid(),
                'concert_id' => $concert->concert_id,
                'ticket_type_name' => json_encode([
                    'en' => 'VVIP', 
                    'am' => 'ቪቪአይፒ'
                ]),
                'ticket_type_description' => json_encode([
                    'en' => 'VVIP experience with backstage access and meet & greet',
                    'am' => 'የጀርባ መድረክ መዳረሻ እና መገናኘት ያለው የቪቪአይፒ ልምድ'
                ]),
                'price' => 300.00,
                'capacity' => floor($concert->max_capacity * 0.15),
                'sold_count' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('ticket_types')->insert($ticketTypes);
    }
}