
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->uuid('ticket_type_id')->primary();
            $table->foreignUuid('concert_id')->constrained('concerts', 'concert_id')->onDelete('cascade');
            $table->json('ticket_type_name');
            $table->json('ticket_type_description')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->integer('capacity');
            $table->integer('sold_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('concert_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};