<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->uuid('subject_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->uuid('causer_id')->nullable();  // Changed to UUID
            $table->string('causer_type')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->string('event')->nullable();
            $table->timestamps();
            
            $table->index('log_name');
            $table->index(['subject_id', 'subject_type']);
            $table->index(['causer_id', 'causer_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('activity_log');
    }
};