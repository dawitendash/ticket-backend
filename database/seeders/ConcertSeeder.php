<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ConcertSeeder extends Seeder
{
    public function run(): void
    {
        // Hamle 5 & 6 in Ethiopian Calendar (approx July 12-13 in Gregorian)
        // Using Ethiopian calendar months: Hamle = July/August
        $hamle5 = now()->setYear(2026)->setMonth(7)->setDay(12); // Hamle 5
        $hamle6 = now()->setYear(2026)->setMonth(7)->setDay(13); // Hamle 6

        $concerts = [
            [
                'concert_id' => Str::uuid(),
                'name' => json_encode([
                    'en' => 'Dessie Sheh Bahl Amba Music Festival 2026 - Day 1 and 2', 
                    'am' => 'ሸህ ባህል አምባ ሙዚቃ ፌስቲቫል 2026 - ቀን 1  '
                ]),
                'artist' => json_encode([
                    'en' => 'Various Ethiopian Artists', 
                    'am' => 'የተለያዩ የኢትዮጵያ አርቲስቶች'
                ]),
                'venue' => json_encode([
                    'en' => 'Bahl Amba Park, Dessie', 
                    'am' => 'አምባ ፓርክ፣ ደሴ'
                ]),
                'description' => json_encode([
                    'en' => 'The biggest cultural music festival in Dessie celebrating Sheh Bahl Amba tradition with top Ethiopian artists.',
                    'am' => 'በሸህ ባህል አምባ ወግ የሚከበረው በደሴ ትልቁ የባህል ሙዚቃ ፌስቲቫል ከላቁ የኢትዮጵያ አርቲስቶች ጋር።'
                ]),
                'concert_date' => $hamle5->setTime(16, 0, 0), // 4:00 PM
                'door_open_time' => $hamle5->setTime(14, 0, 0), // 2:00 PM
                'status' => 'upcoming',
                'max_capacity' => 3000,
                'image_url' => 'uploads/concerts/sheh_bahl_amba_day1.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ], 
        ];

        DB::table('concerts')->insert($concerts);
    }
}