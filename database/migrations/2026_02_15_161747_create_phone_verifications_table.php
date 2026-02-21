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
        Schema::create('phone_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('code', 6);
            $table->tinyInteger('attempts')->unsigned()->default(0);
            $table->tinyInteger('max_attempts')->unsigned()->default(5);
            $table->enum('status', ['pending', 'verified', 'expired', 'blocked'])->default('pending');
            $table->string('ip_address', 45)->nullable();
            $table->json('device_info')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            
            // إضافة فهرس مركب للحالات
            $table->index(['phone', 'status']);
        });
        
        // إضافة حقل phone_verified_at إلى جدول users
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // حذف الحقل من جدول users أولاً
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_verified_at');
        });
        
        // ثم حذف جدول phone_verifications
        Schema::dropIfExists('phone_verifications');
    }
};