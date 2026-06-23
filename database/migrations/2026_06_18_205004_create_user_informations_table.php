
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_informations', function (Blueprint $table) {
            $table->uuid('user_information_id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignUuid("device_id")->nullable()->constrained('user_devices','device_id')->onDelete('cascade');
            $table->json('full_name');
            $table->string('phone_number')->unique();
            $table->string('national_id_front_image')->nullable();
            $table->string('national_id_back_image')->nullable();
            $table->string('national_id_number')->nullable(); 
            $table->text('address')->nullable();
            $table->timestamps();
            $table->unique('user_id');
            $table->index('phone_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_informations');
    }
};