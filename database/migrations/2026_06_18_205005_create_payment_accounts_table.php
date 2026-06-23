
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->uuid('payment_account_id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users', 'user_id')->onDelete('cascade');
            $table->string('account_type', 25);  
            $table->string('owner_name');
            $table->string('account_identifier')->unique();  
            $table->string('provider')->nullable();  
            $table->string('last_four', 4)->nullable();
            $table->integer('expiry_month')->nullable();
            $table->integer('expiry_year')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};