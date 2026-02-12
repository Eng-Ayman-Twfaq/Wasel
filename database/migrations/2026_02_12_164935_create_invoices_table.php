<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('customer_store_id');
            $table->enum('invoice_type', ['merchant', 'master'])->default('merchant');
            $table->decimal('total_amount', 12, 2);
            $table->enum('invoice_status', ['بانتظار', 'مرسلة', 'مدفوعة'])->default('بانتظار');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};