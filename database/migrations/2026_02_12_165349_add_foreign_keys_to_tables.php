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
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('set null');
        });

        // 2. علاقات جدول stores
        Schema::table('stores', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });

        // 3. علاقات جدول support_teams
        Schema::table('support_teams', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('cascade');
        });

        // 4. علاقات جدول products
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
        });

        // 5. علاقات جدول cart_items
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        // 6. علاقات جدول user_uploaded_documents
        Schema::table('user_uploaded_documents', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
        });

        // 7. علاقات جدول user_devices
        Schema::table('user_devices', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });

        // 8. علاقات جدول delivery_fee_rules
        Schema::table('delivery_fee_rules', function (Blueprint $table) {
            $table->foreign('from_area_id')->references('id')->on('areas')->onDelete('cascade');
            $table->foreign('to_area_id')->references('id')->on('areas')->onDelete('cascade');
        });

        // 9. علاقات جدول orders
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('set null');
            $table->foreign('merchant_approved_by')->references('id')->on('stores')->onDelete('set null');
            $table->foreign('support_team_id')->references('id')->on('support_teams')->onDelete('set null');
            $table->foreign('support_approved_by')->references('id')->on('users')->onDelete('set null');
        });

        // 10. علاقات جدول order_details
        Schema::table('order_details', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
        });

        // 11. علاقات جدول invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('set null');
            $table->foreign('customer_store_id')->references('id')->on('stores')->onDelete('cascade');
        });

        // 12. علاقات جدول transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('cascade');
        });

        // 13. علاقات جدول deliveries
        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
        });

        // 14. علاقات جدول reviews
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('reviewer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewee_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 15. علاقات جدول favorites
        Schema::table('favorites', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        // 16. علاقات جدول offers
        Schema::table('offers', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        // 17. علاقات جدول promotions
        Schema::table('promotions', function (Blueprint $table) {
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });

        // 18. علاقات جدول search_histories
        Schema::table('search_histories', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 19. علاقات جدول notifications
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ================ الفهارس (Indexes) لتحسين الأداء ================
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'registration_status']);
            $table->index('phone');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->index(['store_type', 'is_approved']);
            $table->index(['latitude', 'longitude']);
            $table->index(['area_id', 'store_type']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['store_id', 'is_available']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'payment_status']);
            $table->index(['store_id', 'created_at']);
            $table->index(['support_team_id', 'status']);
        });
    }

    public function down()
    {
        // حذف الفهارس أولاً
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'registration_status']);
            $table->dropIndex(['phone']);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['store_type', 'is_approved']);
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropIndex(['area_id', 'store_type']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'is_available']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'payment_status']);
            $table->dropIndex(['store_id', 'created_at']);
            $table->dropIndex(['support_team_id', 'status']);
        });

        // ثم حذف المفاتيح الأجنبية (بنفس الترتيب العكسي)
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('search_histories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['store_id']);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['reviewee_id']);
            $table->dropForeign(['reviewer_id']);
            $table->dropForeign(['order_id']);
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['order_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropForeign(['invoice_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['customer_store_id']);
            $table->dropForeign(['store_id']);
            $table->dropForeign(['order_id']);
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['order_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['support_approved_by']);
            $table->dropForeign(['support_team_id']);
            $table->dropForeign(['merchant_approved_by']);
            $table->dropForeign(['payment_method_id']);
            $table->dropForeign(['store_id']);
        });

        Schema::table('delivery_fee_rules', function (Blueprint $table) {
            $table->dropForeign(['to_area_id']);
            $table->dropForeign(['from_area_id']);
        });

        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('user_uploaded_documents', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
        });

        Schema::table('support_teams', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['area_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
        });
    }
};