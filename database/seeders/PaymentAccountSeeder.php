<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PaymentAccountSeeder extends Seeder
{
    public function run(): void
    {
        $role = DB::table('roles')->where('slug', 'admin')->first();
        $users = DB::table('users')->whereIn('role_id', [$role->role_id])->get();

        $paymentAccounts = [];

        foreach ($users as $index => $user) {
            $numAccounts = rand(1, 2);

            for ($i = 0; $i < $numAccounts; $i++) {
                $cardTypes = ['Visa', 'Mastercard', 'PayPal'];
                $accountTypes = ['credit_card', 'debit_card', 'paypal'];
                $typeIndex = rand(0, 2);

                $paymentAccounts[] = [
                    'payment_account_id' => Str::uuid(),
                    'user_id' => $user->user_id,
                    'account_type' => $accountTypes[$typeIndex],
                    'owner_name' => $user->user_name,
                    'account_identifier' => '****' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                    'provider' => $cardTypes[$typeIndex] ?? 'Visa',
                    'last_four' => str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                    'expiry_month' => rand(1, 12),
                    'expiry_year' => rand(2026, 2030),
                    'is_default' => $i === 0 ? true : false,
                    'is_active' => true,
                    'meta' => json_encode([
                        'en' => 'Last used: ' . now()->toDateTimeString(),
                        'am' => 'የመጨረሻ ጥቅም: ' . now()->toDateTimeString()
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('payment_accounts')->insert($paymentAccounts);
    }
}