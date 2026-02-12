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
            $table->string('first_name');
            $table->string('father_name');
            $table->string('grandfather_name');
            $table->string('last_name');
            $table->string('full_name')->virtualAs("concat(first_name, ' ', father_name, ' ', grandfather_name, ' ', last_name)");
            $table->enum('gender', ['ذكر', 'أنثى']);
            $table->date('birth_date');
            $table->string('nationality');
            $table->string('phone')->unique();
            $table->string('email')->nullable();
            $table->string('password');
            $table->text('address');
            $table->enum('id_card_type', ['هوية_وطنية', 'جواز_سفر', 'بطاقة_عائلية']);
            $table->string('id_number')->unique();
            $table->date('issue_date');
            $table->date('expiry_date');
            $table->string('place_of_issue');
            $table->decimal('location_latitude', 10, 8)->nullable();
            $table->decimal('location_longitude', 11, 8)->nullable();
            $table->enum('role', ['مدير', 'دعم', 'مالك_محل']);
            $table->enum('owner_type', ['فرد', 'شركة'])->default('فرد');
            $table->unsignedBigInteger('area_id')->nullable();
            $table->string('device_id')->nullable();
            $table->enum('registration_status', ['بانتظار_الوثائق', 'قيد_المراجعة', 'موافق', 'مرفوض'])->default('بانتظار_الوثائق');
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