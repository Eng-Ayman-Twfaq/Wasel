<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderMerchantApproval;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class DashboardController extends Controller
{
    // =========================================================
    // GET /api/auth/merchant/dashboard
    // =========================================================
    public function index()
    {
        try {
            $user  = Auth::user();
            if (!$user) return $this->forbidden();

            $store = $user->store;
            if (!$store) {
                return response()->json([
                    'status'  => false,
                    'message' => 'لا يوجد متجر مرتبط بحسابك',
                ], 404);
            }

            $storeId = $store->id;
            $today   = Carbon::today();
            $month   = Carbon::now()->startOfMonth();

            // ══════════════════════════════════════════
            // 1. إحصائيات المنتجات
            // ══════════════════════════════════════════
            $products        = Product::where('store_id', $storeId)->withTrashed(false);
            $totalProducts   = $products->count();
            $availableProducts = Product::where('store_id', $storeId)
                ->where('is_available', true)
                ->where('quantity', '>', 0)
                ->count();
            $lowStockProducts = Product::where('store_id', $storeId)
                ->whereColumn('quantity', '<=', 'low_stock_threshold')
                ->where('quantity', '>', 0)
                ->count();
            $outOfStockProducts = Product::where('store_id', $storeId)
                ->where('quantity', '<=', 0)
                ->count();

            // ══════════════════════════════════════════
            // 2. إحصائيات الطلبات
            // ══════════════════════════════════════════
            // الطلبات التي تحتوي منتجات من متجري
            $myOrderIds = OrderDetail::where('store_id', $storeId)
                ->distinct()
                ->pluck('order_id');

            $totalOrders   = Order::whereIn('id', $myOrderIds)->count();
            $todayOrders   = Order::whereIn('id', $myOrderIds)
                ->whereDate('created_at', $today)->count();
            $pendingOrders = OrderMerchantApproval::where('merchant_store_id', $storeId)
                ->where('status', 'بانتظار')
                ->count();

            $ordersByStatus = Order::whereIn('id', $myOrderIds)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(); // ← يضمن إرجاع {} وليس [] عند الفراغ

            // ══════════════════════════════════════════
            // 3. الإيرادات
            // ══════════════════════════════════════════
            $totalRevenue = OrderDetail::where('store_id', $storeId)
                ->whereHas('order', fn($q) => $q->where('status', 'مكتمل'))
                ->sum('subtotal');

            $monthRevenue = OrderDetail::where('store_id', $storeId)
                ->whereHas('order', fn($q) => $q
                    ->where('status', 'مكتمل')
                    ->where('created_at', '>=', $month))
                ->sum('subtotal');

            $todayRevenue = OrderDetail::where('store_id', $storeId)
                ->whereHas('order', fn($q) => $q
                    ->where('status', 'مكتمل')
                    ->whereDate('created_at', $today))
                ->sum('subtotal');

            // ══════════════════════════════════════════
            // 4. مخطط الإيرادات — آخر 7 أيام
            // ══════════════════════════════════════════
            $revenueChart = collect(range(6, 0))->map(function ($daysAgo) use ($storeId) {
                $date  = Carbon::today()->subDays($daysAgo);
                $amount = OrderDetail::where('store_id', $storeId)
                    ->whereHas('order', fn($q) => $q
                        ->where('status', 'مكتمل')
                        ->whereDate('created_at', $date))
                    ->sum('subtotal');
                return [
                    'date'   => $date->format('m/d'),
                    'day'    => $date->locale('ar')->dayName,
                    'amount' => (float) $amount,
                ];
            })->values();

            // ══════════════════════════════════════════
            // 5. آخر 5 طلبات
            // ══════════════════════════════════════════
            $recentOrders = Order::whereIn('id', $myOrderIds)
                ->with(['grocery:id,store_name', 'paymentMethod:id,name'])
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn($o) => [
                    'id'             => $o->id,
                    'grocery_name'   => $o->grocery?->store_name ?? 'غير معروف',
                    'total_amount'   => (float) $o->total_amount,
                    'status'         => $o->status,
                    'payment_method' => $o->paymentMethod?->name ?? '',
                    'created_at'     => $o->created_at->diffForHumans(),
                    'created_at_raw' => $o->created_at->format('Y-m-d H:i'),
                ]);

            // ══════════════════════════════════════════
            // 6. منتجات منخفضة المخزون (أول 5)
            // ══════════════════════════════════════════
            $lowStockItems = Product::where('store_id', $storeId)
                ->whereColumn('quantity', '<=', 'low_stock_threshold')
                ->where('quantity', '>', 0)
                ->select('id', 'name', 'quantity', 'low_stock_threshold', 'unit_type')
                ->limit(5)
                ->get()
                ->map(fn($p) => [
                    'id'                  => $p->id,
                    'name'                => $p->name,
                    'quantity'            => $p->quantity,
                    'low_stock_threshold' => $p->low_stock_threshold,
                    'unit_type'           => $p->unit_type,
                ]);

            // ══════════════════════════════════════════
            // 7. بيانات المتجر + الموقع
            // ══════════════════════════════════════════
            $storeData = [
                'id'         => $store->id,
                'store_name' => $store->store_name,
                'address'    => $store->address,
                'latitude'   => (float) $store->latitude,
                'longitude'  => (float) $store->longitude,
                'is_approved'=> $store->is_approved,
                'area'       => $store->area?->name,
            ];

            // ══════════════════════════════════════════
            // الاستجابة الكاملة
            // ══════════════════════════════════════════
            return response()->json([
                'status'  => true,
                'message' => 'تم جلب بيانات لوحة التحكم بنجاح',
                'data'    => [

                    // معلومات التاجر
                    'merchant' => [
                        'name'  => $user->full_name,
                        'store' => $storeData,
                    ],

                    // إحصائيات المنتجات
                    'products' => [
                        'total'      => $totalProducts,
                        'available'  => $availableProducts,
                        'low_stock'  => $lowStockProducts,
                        'out_of_stock' => $outOfStockProducts,
                    ],

                    // إحصائيات الطلبات
                    'orders' => [
                        'total'          => $totalOrders,
                        'today'          => $todayOrders,
                        'pending'        => $pendingOrders,
                        'by_status'      => $ordersByStatus,
                    ],

                    // الإيرادات
                    'revenue' => [
                        'total'   => (float) $totalRevenue,
                        'month'   => (float) $monthRevenue,
                        'today'   => (float) $todayRevenue,
                    ],

                    // مخطط الإيرادات 7 أيام
                    'revenue_chart' => $revenueChart,

                    // آخر الطلبات
                    'recent_orders' => $recentOrders,

                    // منتجات منخفضة المخزون
                    'low_stock_items' => $lowStockItems,
                ],
            ]);

        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    private function forbidden()
    {
        return response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403);
    }

    private function serverError($msg = '')
    {
        return response()->json(['status' => false, 'message' => 'حدث خطأ في الخادم'], 500);
    }
}