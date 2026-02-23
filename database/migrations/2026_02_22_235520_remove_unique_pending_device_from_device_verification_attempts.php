<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_verification_attempts', function (Blueprint $table) {
            // إسقاط الـ unique constraint
            $table->dropUnique('unique_pending_device');
            
            // إضافة index بدلاً من unique
            $table->index(['user_id', 'device_id', 'status'], 'idx_user_device_status');
        });
    }

    public function down(): void
    {
        Schema::table('device_verification_attempts', function (Blueprint $table) {
            $table->dropIndex('idx_user_device_status');
            $table->unique(['user_id', 'device_id', 'status'], 'unique_pending_device');
        });
    }
};