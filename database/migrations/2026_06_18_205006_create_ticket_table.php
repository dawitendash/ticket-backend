
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('ticket_id')->primary();
            $table->foreignUuid('device_id')->nullable()->constrained('user_devices', 'device_id')->onDelete('set null');
            $table->foreignUuid('user_id')->nullable()->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignUuid('ticket_type_id')->constrained('ticket_types', 'ticket_type_id');
            $table->foreignUuid('concert_id')->constrained('concerts', 'concert_id');
            $table->string('order_reference', 50)->unique();
            $table->string('receipt_url')->nullable();
            $table->enum('payment_status',['pending','success','failed']);
            $table->string('qr_code', 500)->unique();
            $table->string('ticket_number', 50)->unique();
            $table->decimal('price_paid', 15, 2);
            $table->enum('status', ['active', 'used', 'cancelled', 'refunded'])->default('active');
            $table->timestamp('purchase_date')->useCurrent();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('ticket_type_id');
            $table->index('concert_id');
            $table->index('qr_code');
            $table->index('status');
            $table->index('ticket_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};