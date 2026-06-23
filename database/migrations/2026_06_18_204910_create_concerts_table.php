
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concerts', function (Blueprint $table) {
            $table->uuid('concert_id')->primary();
            $table->json('name');
            $table->json('artist');
            $table->json('venue');
            $table->json('description')->nullable();
            $table->dateTime('concert_date');
            $table->dateTime('door_open_time');
            $table->enum('status', ['upcoming', 'ongoing', 'completed', 'cancelled'])->default('upcoming');
            $table->integer('max_capacity')->unsigned();
            $table->string('image_url')->nullable();
            $table->timestamps();
            
            $table->index('concert_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concerts');
    }
};