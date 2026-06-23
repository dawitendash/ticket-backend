<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class UserDeviceSeeder extends Seeder
{
    public function run(): void
    {
        $users = DB::table('users')->get();
        $devices = [];

        $deviceNames = ['iPhone 15 Pro', 'Samsung Galaxy S24', 'Google Pixel 8', 'MacBook Pro', 'iPad Air'];
        $platforms = ['ios', 'android', 'windows', 'macos'];
        $deviceTypes = ['mobile', 'web', 'tablet'];

        foreach ($users as $user) {
            // Each user has 1-2 devices
            $numDevices = rand(1, 2);

            for ($i = 0; $i < $numDevices; $i++) {
                $deviceToken = 'dev_' . Str::random(50);
                
                $devices[] = [
                    'device_id' => Str::uuid(),
                    'user_id' => $user->user_id,
                    'device_name' => $deviceNames[array_rand($deviceNames)] . " " . ($i + 1),
                    'device_type' => $deviceTypes[array_rand($deviceTypes)],
                    'platform' => $platforms[array_rand($platforms)],
                    'device_token' => $deviceToken,
                    'fcm_token' => 'fcm_' . Str::random(100),
                    'ip_address' => '192.168.' . rand(1, 255) . '.' . rand(1, 255),
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'is_trusted' => (bool) rand(0, 1),
                    'last_active_at' => now()->subMinutes(rand(1, 60)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Also add some devices without user_id (for testing null constraint)
        for ($i = 0; $i < 3; $i++) {
            $deviceToken = 'dev_guest_' . Str::random(30);
            
            $devices[] = [
                'device_id' => Str::uuid(),
                'user_id' => null, 
                'device_name' => 'Guest Device ' . ($i + 1),
                'device_type' => $deviceTypes[array_rand($deviceTypes)],
                'platform' => $platforms[array_rand($platforms)],
                'device_token' => $deviceToken,
                'fcm_token' => 'fcm_guest_' . Str::random(80),
                'ip_address' => '192.168.100.' . rand(1, 255),
                'user_agent' => 'Mozilla/5.0 (Guest Browser)',
                'is_trusted' => false,
                'last_active_at' => now()->subHours(rand(1, 24)),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('user_devices')->insert($devices);
    }
}