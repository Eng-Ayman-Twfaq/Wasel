<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->decimal('total_amount', 12, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->enum('status', ['قيد_الانتظار', 'قيد_المعالجة', 'مكتمل', 'ملغي', 'مرفوض'])->default('قيد_الانتظار');
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->enum('payment_status', ['بانتظار_الدفع', 'مدفوع', 'فشل_الدفع'])->default('بانتظار_الدفع');
            $table->enum('approval_flow', ['support', 'merchant'])->default('support');
            $table->enum('merchant_approval_status', ['بانتظار', 'موافق', 'مرفوض'])->nullable();
            $table->timestamp('merchant_approved_at')->nullable();
            $table->unsignedBigInteger('merchant_approved_by')->nullable();
            $table->unsignedBigInteger('support_team_id')->nullable();
            $table->unsignedBigInteger('support_approved_by')->nullable();
            $table->timestamp('support_approved_at')->nullable();
            $table->boolean('customer_visible')->default(false);
            $table->text('delivery_address');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
};