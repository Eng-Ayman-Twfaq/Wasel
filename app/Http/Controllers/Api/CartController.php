<?php
// app/Http/Controllers/Api/CartController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    // سعر التوصيل لكل كيلومتر (ريال)
    const DELIVERY_RATE_PER_KM = 500;

    // المسافة التي تُعتبر "بعيدة" (كيلومتر)
    const FAR_DISTANCE_THRESHOLD_KM = 15;

    // ══════════════════════════════════════════
    // GET /api/auth/grocery/cart
    // عرض السلة الكاملة مع تفاصيل التوصيل
    // ══════════════════════════════════════════
    public function index(Request $request): JsonResponse
    {
        $user  = Auth::user();
        $store = $user->store; // متجر البقالة (العميل)

        $items = CartItem::with(['product.store'])
            ->where('user_id', $user->id)
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'status'   => true,
                'data'     => [],
                'summary'  => $this->emptySummary(),
                'warnings' => [],
            ]);
        }

        // ── حساب ملخص السلة ──
        $summary  = $this->buildSummary($items, $store, $request);
        $warnings = $this->buildWarnings($items, $store);

        return response()->json([
            'status'   => true,
            'data'     => CartItemResource::collection($items),
            'summary'  => $summary,
            'warnings' => $warnings,
        ]);
    }

    // ══════════════════════════════════════════
    // POST /api/auth/grocery/cart
    // إضافة منتج للسلة
    // ══════════════════════════════════════════
    public function store(AddToCartRequest $request): JsonResponse
    {
        $user    = Auth::user();
        $product = Product::with('store')->findOrFail($request->product_id);

        // ── تحقق أن المنتج متاح ──
        if (!$product->is_available || $product->quantity <= 0) {
            return response()->json([
                'status'  => false,
                'message' => 'هذا المنتج غير متاح حالياً أو نفد من المخزون',
            ], 422);
        }

        // ── تحقق من الحد الأدنى للطلب ──
        if ($request->quantity < $product->min_order_quantity) {
            return response()->json([
                'status'  => false,
                'message' => "الحد الأدنى للطلب هو {$product->min_order_quantity} {$product->unit_type}",
            ], 422);
        }

        // ── تحقق من الكمية المتاحة ──
        if ($request->quantity > $product->quantity) {
            return response()->json([
                'status'  => false,
                'message' => "الكمية المطلوبة تتجاوز المخزون المتاح ({$product->quantity} {$product->unit_type})",
            ], 422);
        }

        // ── أضف أو حدّث في السلة ──
        $cartItem = CartItem::updateOrCreate(
            [
                'user_id'    => $user->id,
                'product_id' => $product->id,
            ],
            [
                'quantity' => $request->quantity,
            ]
        );

        $cartItem->load(['product.store']);

        // ── تحضير التحذيرات بعد الإضافة ──
        $allItems = CartItem::with(['product.store'])
            ->where('user_id', $user->id)
            ->get();

        $store    = $user->store;
        $warnings = $this->buildWarnings($allItems, $store);
        $summary  = $this->buildSummary($allItems, $store, $request);

        return response()->json([
            'status'   => true,
            'message'  => 'تمت الإضافة إلى السلة بنجاح',
            'data'     => new CartItemResource($cartItem),
            'summary'  => $summary,
            'warnings' => $warnings,
        ], 201);
    }

    // ══════════════════════════════════════════
    // PATCH /api/auth/grocery/cart/{id}
    // تعديل كمية عنصر
    // ══════════════════════════════════════════
    public function update(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        $user     = Auth::user();
        $cartItem = CartItem::with(['product.store'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $product = $cartItem->product;

        // تحقق من الكمية
        if ($request->quantity > $product->quantity) {
            return response()->json([
                'status'  => false,
                'message' => "الكمية المطلوبة تتجاوز المخزون ({$product->quantity} {$product->unit_type})",
            ], 422);
        }

        if ($request->quantity < $product->min_order_quantity) {
            return response()->json([
                'status'  => false,
                'message' => "الحد الأدنى للطلب هو {$product->min_order_quantity} {$product->unit_type}",
            ], 422);
        }

        $cartItem->update(['quantity' => $request->quantity]);
        $cartItem->load(['product.store']);

        $allItems = CartItem::with(['product.store'])
            ->where('user_id', $user->id)
            ->get();

        $store    = $user->store;
        $warnings = $this->buildWarnings($allItems, $store);
        $summary  = $this->buildSummary($allItems, $store, $request);

        return response()->json([
            'status'   => true,
            'message'  => 'تم تحديث الكمية',
            'data'     => new CartItemResource($cartItem),
            'summary'  => $summary,
            'warnings' => $warnings,
        ]);
    }

    // ══════════════════════════════════════════
    // DELETE /api/auth/grocery/cart/{id}
    // حذف عنصر من السلة
    // ══════════════════════════════════════════
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        CartItem::where('user_id', $user->id)
            ->findOrFail($id)
            ->delete();

        return response()->json([
            'status'  => true,
            'message' => 'تم حذف المنتج من السلة',
        ]);
    }

    // ══════════════════════════════════════════
    // DELETE /api/auth/grocery/cart
    // تفريغ السلة كاملاً
    // ══════════════════════════════════════════
    public function clear(): JsonResponse
    {
        $user = Auth::user();
        CartItem::where('user_id', $user->id)->delete();

        return response()->json([
            'status'  => true,
            'message' => 'تم تفريغ السلة',
        ]);
    }

    // ══════════════════════════════════════════
    // GET /api/auth/grocery/cart/count
    // عدد العناصر في السلة (للـ badge)
    // ══════════════════════════════════════════
    public function count(): JsonResponse
    {
        $count = CartItem::where('user_id', Auth::id())->count();
        return response()->json(['status' => true, 'count' => $count]);
    }

    // ════════════════════════════════════════════════════
    // PRIVATE: بناء ملخص السلة مع حساب التوصيل
    // ════════════════════════════════════════════════════
    private function buildSummary($items, $groceryStore, $request): array
    {
        // ── إجمالي الأسعار ──
        $subtotal = $items->sum(function ($item) {
            $unitPrice = $item->product->discounted_price ?? $item->product->price;
            return $unitPrice * $item->quantity;
        });

        // ── التجار المختلفون ──
        $storeIds = $items->pluck('product.store.id')->unique()->filter()->values();

        // ── حساب رسوم التوصيل ──
        $deliveryFee       = 0;
        $deliveryBreakdown = [];

        $groceryLat = $groceryStore?->latitude  ?? $request->header('X-Lat');
        $groceryLng = $groceryStore?->longitude ?? $request->header('X-Lng');

        foreach ($storeIds as $storeId) {
            $traderStore = Store::find($storeId);
            if (!$traderStore || !$groceryLat || !$groceryLng) continue;

            $distanceKm = $this->haversineKm(
                (float) $groceryLat, (float) $groceryLng,
                (float) $traderStore->latitude, (float) $traderStore->longitude
            );

            $fee = round($distanceKm * self::DELIVERY_RATE_PER_KM, 0);
            $deliveryFee += $fee;

            $deliveryBreakdown[] = [
                'store_id'    => $storeId,
                'store_name'  => $traderStore->store_name,
                'distance_km' => round($distanceKm, 2),
                'fee'         => $fee,
            ];
        }

        // إذا أكثر من تاجر — رسوم توصيل إضافية 500 ريال لكل تاجر إضافي
        $extraTradersCount = max(0, $storeIds->count() - 1);
        $extraFee          = $extraTradersCount * 500;
        $deliveryFee      += $extraFee;

        $total = round($subtotal + $deliveryFee, 2);

        return [
            'items_count'         => $items->count(),
            'traders_count'       => $storeIds->count(),
            'subtotal'            => round($subtotal, 2),
            'delivery_fee'        => $deliveryFee,
            'extra_traders_fee'   => $extraFee,
            'total'               => $total,
            'delivery_breakdown'  => $deliveryBreakdown,
            'delivery_rate_per_km'=> self::DELIVERY_RATE_PER_KM,
        ];
    }

    // ════════════════════════════════════════════════════
    // PRIVATE: بناء التحذيرات
    // ════════════════════════════════════════════════════
    private function buildWarnings($items, $groceryStore): array
    {
        $warnings = [];

        $groceryLat = $groceryStore?->latitude;
        $groceryLng = $groceryStore?->longitude;

        // ── التجار المختلفون ──
        $storeIds = $items->pluck('product.store.id')->unique()->filter()->values();

        if ($storeIds->count() > 1) {
            $storeNames = $items
                ->pluck('product.store.store_name')
                ->unique()
                ->filter()
                ->values()
                ->join('، ');

            $warnings[] = [
                'type'    => 'multiple_traders',
                'level'   => 'info',
                'title'   => 'منتجات من تجار متعددين',
                'message' => "سلتك تحتوي على منتجات من {$storeIds->count()} تجار مختلفين ($storeNames). "
                           . "سيتم احتساب رسوم توصيل منفصلة لكل تاجر مما قد يزيد التكلفة الإجمالية.",
                'icon'    => 'delivery_multiple',
            ];
        }

        // ── تجار بعيدون ──
        if ($groceryLat && $groceryLng) {
            foreach ($storeIds as $storeId) {
                $traderStore = Store::find($storeId);
                if (!$traderStore) continue;

                $distanceKm = $this->haversineKm(
                    (float) $groceryLat, (float) $groceryLng,
                    (float) $traderStore->latitude, (float) $traderStore->longitude
                );

                if ($distanceKm >= self::FAR_DISTANCE_THRESHOLD_KM) {
                    $fee = round($distanceKm * self::DELIVERY_RATE_PER_KM);
                    $warnings[] = [
                        'type'    => 'far_trader',
                        'level'   => 'warning',
                        'title'   => 'تاجر بعيد',
                        'message' => "متجر \"{$traderStore->store_name}\" يبعد عنك "
                                   . round($distanceKm, 1) . " كم. "
                                   . "رسوم التوصيل من هذا المتجر ستكون حوالي "
                                   . number_format($fee) . " ريال.",
                        'icon'    => 'location_far',
                        'store_id'    => $storeId,
                        'distance_km' => round($distanceKm, 1),
                    ];
                }
            }
        }

        // ── منتجات ينفد مخزونها ──
        foreach ($items as $item) {
            $product = $item->product;
            if ($item->quantity > $product->quantity) {
                $warnings[] = [
                    'type'    => 'stock_exceeded',
                    'level'   => 'error',
                    'title'   => 'كمية غير كافية',
                    'message' => "المنتج \"{$product->name}\" لديه {$product->quantity} "
                               . "{$product->unit_type} فقط في المخزون، "
                               . "لكنك طلبت {$item->quantity}.",
                    'icon'    => 'inventory_issue',
                    'product_id' => $product->id,
                ];
            }
        }

        return $warnings;
    }

    // ════════════════════════════════════════════════════
    // PRIVATE: حساب المسافة بين نقطتين (Haversine)
    // ════════════════════════════════════════════════════
    private function haversineKm(
        float $lat1, float $lng1,
        float $lat2, float $lng2
    ): float {
        $R = 6371; // نصف قطر الأرض بالكيلومتر
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // ── ملخص فارغ ──
    private function emptySummary(): array
    {
        return [
            'items_count'          => 0,
            'traders_count'        => 0,
            'subtotal'             => 0,
            'delivery_fee'         => 0,
            'extra_traders_fee'    => 0,
            'total'                => 0,
            'delivery_breakdown'   => [],
            'delivery_rate_per_km' => self::DELIVERY_RATE_PER_KM,
        ];
    }
}