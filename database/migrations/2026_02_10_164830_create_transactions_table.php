<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('payment_method_id');
            $table->dateTime('transaction_date');
            $table->string('reference')->nullable();
            $table->enum('status', [
                'ناجحة',
                'فشلت',
                'قيد_الانتظار'
            ])->default('قيد_الانتظار');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};