<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_merchant_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                  ->constrained('orders')
                  ->cascadeOnDelete();
            $table->foreignId('merchant_store_id')
                  ->constrained('stores')
                  ->cascadeOnDelete();
            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->enum('status', ['بانتظار', 'موافق', 'مرفوض'])
                  ->default('بانتظار');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // تاجر واحد لكل طلب
            $table->unique(['order_id', 'merchant_store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_merchant_approvals');
    }
};