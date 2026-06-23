<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
      
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->string('user_name')->unique()->index();
            $table->jsonb('profile_name');
            $table->string('email')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('phone_number', 20)->unique()->nullable();
            $table->string('profile_picture_url')->nullable();
            $table->string('lang', 5)->default('en');
            $table->smallInteger('login_attempts')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->dateTime('locked_until')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->tinyInteger('status')->default(1);
 
            $table->foreignUuid('role_id')->nullable()->constrained('roles', 'role_id')->onDelete('cascade');
            $table->integer('gate_number')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('role_id');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};