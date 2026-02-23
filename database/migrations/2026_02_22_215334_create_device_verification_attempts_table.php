<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('device_verification_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('device_id');
            $table->string('device_name')->nullable();
            $table->string('verification_code', 6);
            $table->string('ip_address', 45)->nullable();
            $table->tinyInteger('attempts')->unsigned()->default(0);
            $table->tinyInteger('max_attempts')->unsigned()->default(3);
            $table->enum('status', ['pending', 'verified', 'expired', 'blocked'])
                  ->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            // العلاقة مع جدول users
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            // Indexes للبحث السريع
            $table->index('user_id');
            $table->index('device_id');
            $table->index('verification_code');
            
            // Unique constraint (اختياري - يمنع تكرار محاولة لنفس الجهاز)
            $table->unique(['user_id', 'device_id', 'status'], 'unique_pending_device');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_verification_attempts');
    }
};