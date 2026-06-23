<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class UserInformationSeeder extends Seeder
{
    public function run(): void
    {
        $userRole = DB::table('roles')->where('slug', 'user')->first();
        $users = DB::table('users')->where('role_id', $userRole->role_id)->get();
        $devices = DB::table('user_devices')->get();

        $amharicNames = [
            'አብይ አህመድ', 'ሳራ ሙሉጌታ', 'ዳዊት ኃይሉ', 'ሩት ተስፋዬ', 'ሳሙኤል አለሙ',
            'ማርያም ደሳለኝ', 'ዮሴፍ ገብረእግዚአብሔር', 'ሄለን መኮንን', 'ሚካኤል አስፋው', 'ኤልሳቤት ታደሰ',
            'ዳንኤል እንዳሻው', 'ሪቃኤል በላይ', 'ናኦሚ አብርሃም', 'ብርሃን አያሌው', 'ገላውዴዎስ ጥላሁን',
            'ሰሎሞን ሐዲስ', 'እስቴር ጌታቸው', 'ተክለ ወልደሚካኤል', 'አርሳማ ከበደ', 'ሀና አክሊሉ'
        ];

        $userInformations = [];

        foreach ($users as $index => $user) {
            // Assign a random device to each user (or null)
            $device = $devices->random();
            
            $userInformations[] = [
                'user_information_id' => Str::uuid(),
                'user_id' => $user->user_id,
                'device_id' => $device->device_id, 
                'full_name' => json_encode([
                    'en' => "User " . ($index + 1) . " Full Name",
                    'am' => $amharicNames[$index] ?? "ተጠቃሚ " . ($index + 1)
                ]),
                'phone_number' => $user->phone_number,
                'national_id_front_image' => "uploads/national_ids/front_{$user->user_name}.jpg",
                'national_id_back_image' => "uploads/national_ids/back_{$user->user_name}.jpg",
                'national_id_number' => "ETNID" . str_pad($index + 1, 8, '0', STR_PAD_LEFT),
                'address' => json_encode([
                    'en' => "Address for User {$index}, Dessie, Ethiopia",
                    'am' => "ለተጠቃሚ {$index} አድራሻ፣ ደሴ፣ ኢትዮጵያ"
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('user_informations')->insert($userInformations);
    }
}