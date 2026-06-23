<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Order matters for foreign key constraints
        $this->call([
            ConcertSeeder::class,
            TicketTypeSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            UserDeviceSeeder::class,
            UserInformationSeeder::class,
            PaymentAccountSeeder::class,
            TicketSeeder::class,
            AttendanceLogSeeder::class,
        ]);
    }
}