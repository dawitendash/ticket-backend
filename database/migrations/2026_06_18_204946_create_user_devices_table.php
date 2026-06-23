
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->uuid('device_id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users', 'user_id')->onDelete('cascade');
            $table->string('device_name')->nullable();
            $table->string('device_type')->nullable(); // mobile, web, tablet
            $table->string('platform')->nullable(); // android, ios, windows
            $table->string('device_token')->unique()->nullable();
            $table->text('fcm_token')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('is_trusted')->default(true);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('device_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};