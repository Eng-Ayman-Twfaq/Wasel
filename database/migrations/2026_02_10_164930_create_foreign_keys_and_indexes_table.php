<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. علاقات جدول users
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('SET NULL');
        });

        // 2. علاقات جدول stores
        Schema::table('stores', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('CASCADE');
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('SET NULL');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('SET NULL');
        });

        // 3. علاقات جدول user_uploaded_documents
        Schema::table('user_uploaded_documents', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('CASCADE');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('SET NULL');
        });

        // 4. علاقات جدول support_teams
        Schema::table('support_teams', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('CASCADE');
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('CASCADE');
        });

        // 5. علاقات جدول categories
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('categories')->onDelete('SET NULL');
        });

        // 6. علاقات جدول products
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('CASCADE');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('SET NULL');
        });

        // 7. علاقات جدول cart_items
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('CASCADE');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('CASCADE');
        });

        // 8. علاقات جدول orders
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('CASCADE');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('SET NULL');
            $table->foreign('support_team_id')->references('id')->on('support_teams')->onDelete('SET NULL');
        });

        // 9. علاقات جدول order_details
        Schema::table('order_details', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('CASCADE');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('CASCADE');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('CASCADE');
        });

        // 10. علاقات جدول invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('CASCADE');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('CASCADE');
            $table->foreign('customer_store_id')->references('id')->on('stores')->onDelete('CASCADE');
        });

        // 11. علاقات جدول transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('CASCADE');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('CASCADE');
        });

        // 12. علاقات جدول delivery_fee_rules
        Schema::table('delivery_fee_rules', function (Blueprint $table) {
            $table->foreign('from_area_id')->references('id')->on('areas')->onDelete('CASCADE');
            $table->foreign('to_area_id')->references('id')->on('areas')->onDelete('CASCADE');
        });

        // 13. علاقات جدول deliveries
        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('CASCADE');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('SET NULL');
        });

        // 14. علاقات جدول reviews
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('CASCADE');
            $table->foreign('reviewer_id')->references('id')->on('users')->onDelete('CASCADE');
            $table->foreign('reviewee_id')->references('id')->on('users')->onDelete('CASCADE');
        });

        // 15. علاقات جدول favorites
        Schema::table('favorites', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('CASCADE');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('CASCADE');
        });

        // 16. علاقات جدول offers
        Schema::table('offers', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->onDelete('CASCADE');
        });

        // 17. علاقات جدول search_histories
        Schema::table('search_histories', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('CASCADE');
        });

        // 18. علاقات جدول notifications
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('CASCADE');
        });

        // 19. إضافة Unique Constraints
        Schema::table('favorites', function (Blueprint $table) {
            $table->unique(['user_id', 'product_id']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['user_id', 'product_id']);
        });

        // 20. إضافة Indexes لتحسين الأداء
        Schema::table('products', function (Blueprint $table) {
            $table->index(['store_id', 'is_available']);
            $table->index(['category_id', 'is_available']);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->index(['store_type', 'is_approved']);
            $table->index(['latitude', 'longitude']);
            $table->index(['area_id', 'store_type']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'payment_status']);
            $table->index(['store_id', 'created_at']);
            $table->index(['support_team_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'registration_status']);
            $table->index('phone');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['parent_id', 'is_active']);
        });
    }

    public function down()
    {
        // حذف جميع العلاقات في حالة التراجع
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['area_id']);
            $table->dropForeign(['approved_by']);
        });

        Schema::table('user_uploaded_documents', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['verified_by']);
        });

        Schema::table('support_teams', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['area_id']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropForeign(['category_id']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropForeign(['payment_method_id']);
            $table->dropForeign(['support_team_id']);
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['store_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['store_id']);
            $table->dropForeign(['customer_store_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['payment_method_id']);
        });

        Schema::table('delivery_fee_rules', function (Blueprint $table) {
            $table->dropForeign(['from_area_id']);
            $table->dropForeign(['to_area_id']);
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['assigned_to']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['reviewer_id']);
            $table->dropForeign(['reviewee_id']);
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('search_histories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        // حذف Unique Constraints
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'product_id']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'product_id']);
        });

        // حذف Indexes
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'is_available']);
            $table->dropIndex(['category_id', 'is_available']);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['store_type', 'is_approved']);
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropIndex(['area_id', 'store_type']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'payment_status']);
            $table->dropIndex(['store_id', 'created_at']);
            $table->dropIndex(['support_team_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'registration_status']);
            $table->dropIndex(['phone']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'is_active']);
        });
    }
};