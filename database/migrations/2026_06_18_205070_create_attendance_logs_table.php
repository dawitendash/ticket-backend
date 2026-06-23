<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->uuid('attendance_log_id')->primary();
            $table->foreignUuid('ticket_id')->constrained('tickets', 'ticket_id')->onDelete('cascade');
            $table->foreignUuid('concert_id')->constrained('concerts', 'concert_id');
            $table->foreignUuid('device_id')->nullable()->constrained('user_devices', 'device_id')->onDelete('set null');
            $table->foreignUuid('user_id')->nullable()->constrained('users', 'user_id');
            $table->foreignUuid('scanned_by')->constrained('users', 'user_id');
            $table->string('gate_number', 50)->nullable();
            $table->timestamp('scan_time')->useCurrent();
            $table->enum('status', ['success', 'already_used', 'invalid', 'expired'])->default('success');
            $table->text('failure_reason')->nullable();
            
            $table->index('ticket_id');
            $table->index('concert_id');
            $table->index('scanned_by');
            $table->index('scan_time');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};