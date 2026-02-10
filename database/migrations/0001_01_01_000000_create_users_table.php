<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('email')->nullable();
            $table->string('password');
            $table->enum('role', ['مدير', 'دعم', 'مالك_محل']);
            $table->enum('owner_type', ['فرد', 'شركة'])->default('فرد');
            $table->string('identification_number')->nullable();
            $table->unsignedBigInteger('area_id')->nullable();
            $table->string('device_id')->nullable();
            $table->enum('registration_status', [
                'بانتظار_الوثائق', 
                'قيد_المراجعة', 
                'موافق', 
                'مرفوض'
            ])->default('بانتظار_الوثائق');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};