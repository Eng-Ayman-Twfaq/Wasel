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
        // في ملف migration لإضافة حقل fcm_token
Schema::table('user_devices', function (Blueprint $table) {
    $table->string('fcm_token')->nullable()->after('device_name');
    $table->timestamp('last_logout_at')->nullable()->after('last_login_at');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            //
        });
    }
};
