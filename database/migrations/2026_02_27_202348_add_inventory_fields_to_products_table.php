<?php
// database/migrations/2026_02_27_XXXXXX_add_inventory_fields_to_products_table.php

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
        Schema::table('products', function (Blueprint $table) {
            // إضافة حقول المخزون
            $table->integer('quantity')->default(0)->after('price');
            
            $table->integer('low_stock_threshold')->default(5)->after('quantity');
            
            // إضافة حقول الوحدات والقياس
            $table->enum('unit_type', ['وحدة', 'كرتون', 'صندوق', 'طبق', 'حبة', 'كيلو', 'لتر'])
                  ->default('وحدة')
                  ->after('low_stock_threshold');
            
            $table->integer('pieces_per_unit')->default(1)->after('unit_type');
            
            $table->boolean('allow_partial_unit')->default(true)->after('pieces_per_unit');
            
            $table->decimal('min_order_quantity', 10, 2)->default(1.00)->after('allow_partial_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'quantity',
                'low_stock_threshold',
                'unit_type',
                'pieces_per_unit',
                'allow_partial_unit',
                'min_order_quantity'
            ]);
        });
    }
};