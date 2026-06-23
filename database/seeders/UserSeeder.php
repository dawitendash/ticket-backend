<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = DB::table('roles')->where('slug', 'admin')->first();
        $scannerRole = DB::table('roles')->where('slug', 'scanner')->first();
        $userRole = DB::table('roles')->where('slug', 'user')->first();

        $users = [];

        // 1. Admin Users
        $users[] = [
            'user_id' => Str::uuid(),
            'user_name' => 'admin',
            'profile_name' => json_encode([
                'en' => 'System Admin', 
                'am' => 'የስርዓት አስተዳዳሪ'
            ]),
            'email' => 'admin@ticketing.com',
            'password' => Hash::make('password'),
            'phone_number' => '+251967990551',
            'role_id' => $adminRole->role_id,
            'gate_number' => 0,
            'status' => 1,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        // 2. Scanner Users (Gate Staff)
        for ($i = 1; $i <= 15; $i++) {
            $users[] = [
                'user_id' => Str::uuid(),
                'user_name' => "scanner{$i}",
                'profile_name' => json_encode([
                    'en' => "Scanner {$i}", 
                    'am' => "ስካነር {$i}"
                ]),
                'email' => "scanner{$i}@ticketing.com",
                'password' => Hash::make('password'),
                'phone_number' => "+25191111111{$i}",
                'role_id' => $scannerRole->role_id,
                'gate_number' => $i,
                'status' => 1,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // 3. Regular Users (20 users)
        $amharicNames = [
            'አብይ', 'ሳራ', 'ዳዊት', 'ሩት', 'ሳሙኤል',
            'ማርያም', 'ዮሴፍ', 'ሄለን', 'ሚካኤል', 'ኤልሳቤት',
            'ዳንኤል', 'ሪቃኤል', 'ናኦሚ', 'ብርሃን', 'ገላውዴዎስ',
            'ሰሎሞን', 'እስቴር', 'ተክለ', 'አርሳማ', 'ሀና'
        ];

        for ($i = 1; $i <= 5; $i++) {
            $users[] = [
                'user_id' => Str::uuid(),
                'user_name' => "user{$i}",
                'profile_name' => json_encode([
                    'en' => "User {$i}", 
                    'am' => $amharicNames[$i-1] ?? "ተጠቃሚ {$i}"
                ]),
                'email' => "user{$i}@example.com",
                'password' => Hash::make('password'),
                'phone_number' => "+25191111112{$i}",
                'role_id' => $userRole->role_id,
                'gate_number' => 0,
                'status' => 1,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('users')->insert($users);
    }
}