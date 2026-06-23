<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'role_id' => Str::uuid(),
                'name' => json_encode([
                    'en' => 'Admin', 
                    'am' => 'አስተዳዳሪ'
                ]),
                'description' => json_encode([
                    'en' => 'Full system access and control', 
                    'am' => 'ሙሉ የስርዓት ተደራሽነት እና ቁጥጥር'
                ]),
                'slug' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'role_id' => Str::uuid(),
                'name' => json_encode([
                    'en' => 'Scanner', 
                    'am' => 'ስካነር'
                ]),
                'description' => json_encode([
                    'en' => 'Can scan tickets at gates', 
                    'am' => 'በበሮች ላይ ቲኬቶችን መቃኘት ይችላል'
                ]),
                'slug' => 'scanner',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'role_id' => Str::uuid(),
                'name' => json_encode([
                    'en' => 'User', 
                    'am' => 'ተጠቃሚ'
                ]),
                'description' => json_encode([
                    'en' => 'Regular user can buy tickets', 
                    'am' => 'መደበኛ ተጠቃሚ ቲኬቶችን መግዛት ይችላል'
                ]),
                'slug' => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('roles')->insert($roles);
    }
}