<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderMerchantApproval;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class GroceryOrderController extends Controller
{
    // ─────────────────────────────────────────
    // Helper — إرسال إشعار داخلي
    // ─────────────────────────────────────────
    private function sendNotification(int $userId, string $title, string $body, array $data = []): void
    {
        Notification::create([
            'user_id' => $userId,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
            'is_read' => false,
        ]);
    }

    // ─────────────────────────────────────────
    // Helper — التحقق أن المستخدم بقالة نشطة
    // ─────────────────────────────────────────
    private function getGroceryStore()
    {
        $user = Auth::user();
        if (!$user || !$user->store || !$user->store->isGrocery() || !$user->store->isActive()) {
            return null;
        }
        return $user->store;
    }

    // =========================================================
    // GET /api/auth/grocery/orders
    // =========================================================
    public function index(Request $request)
    {
        try {
            $store = $this->getGroceryStore();
            if (!$store) return $this->forbiddenResponse('غير مصرح لك بعرض الطلبات');

            $query = Order::where('store_id', $store->id)
                ->with([
                    'paymentMethod:id,name',
                    'orderDetails.product:id,name,unit_type,image_url',
                    'merchantApprovals.merchantStore:id,store_name',
                ])
                ->latest();

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $orders = $query->paginate(10);

            return response()->json([
                'status'     => true,
                'message'    => 'تم جلب الطلبات بنجاح',
                'data'       => OrderResource::collection($orders),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page'    => $orders->lastPage(),
                    'per_page'     => $orders->perPage(),
                    'total'        => $orders->total(),
                ],
            ]);
        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    // =========================================================
    // GET /api/auth/grocery/orders/stats
    // =========================================================
    public function stats()
    {
        try {
            $store = $this->getGroceryStore();
            if (!$store) return $this->forbiddenResponse('غير مصرح لك');

            $base = fn() => Order::where('store_id', $store->id);

            return response()->json([
                'status' => true,
                'data'   => [
                    'pending'    => $base()->where('status', 'قيد_الانتظار')->count(),
                    'processing' => $base()->where('status', 'قيد_المعالجة')->count(),
                    'completed'  => $base()->where('status', 'مكتمل')->count(),
                    'rejected'   => $base()->where('status', 'مرفوض')->count(),
                    'cancelled'  => $base()->where('status', 'ملغي')->count(),
                    'total'      => $base()->count(),
                ],
            ]);
        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    // =========================================================
    // GET /api/auth/grocery/orders/{id}
    // =========================================================
    public function show($id)
    {
        try {
            $store = $this->getGroceryStore();
            if (!$store) return $this->forbiddenResponse('غير مصرح لك');

            $order = Order::where('store_id', $store->id)
                ->with([
                    'paymentMethod:id,name',
                    'orderDetails.product:id,name,price,unit_type,image_url',
                    'merchantApprovals.merchantStore:id,store_name',
                ])
                ->find($id);

            if (!$order) return $this->notFoundResponse('الطلب غير موجود');

            return response()->json([
                'status' => true,
                'data'   => new OrderResource($order),
            ]);
        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    // =========================================================
    // POST /api/auth/grocery/orders
    // إنشاء طلب جديد
    // =========================================================
    public function store(StoreOrderRequest $request)
    {
        try {
            $groceryStore = $this->getGroceryStore();
            if (!$groceryStore) return $this->forbiddenResponse('غير مصرح لك بإنشاء طلب');

            // ── التحقق من المنتجات ──
            $productIds = collect($request->items)->pluck('product_id');
            $products   = Product::whereIn('id', $productIds)
                ->with('offers')
                ->get()
                ->keyBy('id');

            foreach ($request->items as $item) {
                $product = $products->get($item['product_id']);

                if (!$product) {
                    return response()->json([
                        'status'  => false,
                        'message' => "المنتج رقم {$item['product_id']} غير موجود",
                    ], 422);
                }

                if (!$product->is_available || $product->quantity <= 0) {
                    return response()->json([
                        'status'  => false,
                        'message' => "المنتج «{$product->name}» غير متوفر حالياً",
                    ], 422);
                }

                if (!$product->isQuantityAvailable($item['quantity'])) {
                    return response()->json([
                        'status'  => false,
                        'message' => "الكمية المطلوبة من «{$product->name}» غير متوفرة. المتاح: {$product->quantity}",
                    ], 422);
                }
            }

            // ── طريقة الدفع ──
            $paymentMethod = PaymentMethod::find($request->payment_method_id);
            $isDayn        = $paymentMethod->name === 'دين';

            // ── حساب الإجماليات ──
            $totalAmount = 0;
            $orderItems  = [];

            foreach ($request->items as $item) {
                $product      = $products->get($item['product_id']);
                $unitPrice    = $product->discounted_price;
                $subtotal     = $item['quantity'] * $unitPrice;
                $totalAmount += $subtotal;

                $orderItems[] = [
                    'product_id'    => $product->id,
                    'store_id'      => $product->store_id,
                    'quantity'      => $item['quantity'],
                    'price_at_time' => $unitPrice,
                    'subtotal'      => $subtotal,
                ];
            }

            // ── إنشاء الطلب ──
            $order = DB::transaction(function () use (
                $request, $groceryStore, $isDayn,
                $totalAmount, $orderItems, $products
            ) {
                // 1. إنشاء الطلب
                $order = Order::create([
                    'store_id'                 => $groceryStore->id,
                    'total_amount'             => $totalAmount,
                    'delivery_fee'             => 0,
                    'status'                   => 'قيد_الانتظار',
                    'payment_method_id'        => $request->payment_method_id,
                    'payment_status'           => 'بانتظار_الدفع',
                    'approval_flow'            => $isDayn ? 'merchant' : 'support',
                    'merchant_approval_status' => $isDayn ? 'بانتظار' : null,
                    'customer_visible'         => !$isDayn,
                    'delivery_address'         => $request->delivery_address,
                    'notes'                    => $request->notes,
                ]);

                // 2. تفاصيل الطلب + خصم المخزون
                foreach ($orderItems as $item) {
                    OrderDetail::create(array_merge(['order_id' => $order->id], $item));
                    $products->get($item['product_id'])->deductStock($item['quantity']);
                }

                // 3. سجلات الموافقة لكل تاجر (دين فقط)
                if ($isDayn) {
                    $merchantStoreIds = collect($orderItems)->pluck('store_id')->unique();
                    foreach ($merchantStoreIds as $merchantStoreId) {
                        OrderMerchantApproval::create([
                            'order_id'          => $order->id,
                            'merchant_store_id' => $merchantStoreId,
                            'status'            => 'بانتظار',
                        ]);
                    }
                }

                return $order;
            });

            // ── إشعارات بعد إنشاء الطلب ──
            if ($isDayn) {
                // إشعار لكل تاجر معني بالطلب
                $merchantStoreIds = collect($orderItems)->pluck('store_id')->unique();

                foreach ($merchantStoreIds as $merchantStoreId) {
                    $merchantUser = User::whereHas('store',
                        fn($q) => $q->where('id', $merchantStoreId)
                    )->first();

                    if (!$merchantUser) continue;

                    // مبلغ هذا التاجر تحديداً
                    $merchantTotal = collect($orderItems)
                        ->where('store_id', $merchantStoreId)
                        ->sum('subtotal');

                    $this->sendNotification(
                        $merchantUser->id,
                        'طلب دين جديد يحتاج موافقتك 🔔',
                        "لديك طلب رقم #{$order->id} من {$groceryStore->store_name} بمبلغ {$merchantTotal} ريال",
                        [
                            'type'     => 'dayn_order_pending',
                            'order_id' => (string) $order->id,
                            'amount'   => (string) $merchantTotal,
                        ]
                    );
                }
            } else {
                // إشعار لفريق الدعم (الدفع عند الاستلام)
                // يمكن إضافته لاحقاً عند بناء نظام الدعم
            }

            // ── إعادة الطلب كاملاً ──
            $order->load([
                'paymentMethod:id,name',
                'orderDetails.product:id,name,unit_type',
                'merchantApprovals.merchantStore:id,store_name',
            ]);

            return response()->json([
                'status'  => true,
                'message' => $isDayn
                    ? 'تم إرسال الطلب وبانتظار موافقة التاجر'
                    : 'تم إنشاء الطلب بنجاح',
                'data'    => new OrderResource($order),
            ], 201);

        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    // =========================================================
    // DELETE /api/auth/grocery/orders/{id}/cancel
    // =========================================================
    public function cancel($id)
    {
        try {
            $store = $this->getGroceryStore();
            if (!$store) return $this->forbiddenResponse('غير مصرح لك');

            $order = Order::where('store_id', $store->id)->find($id);
            if (!$order) return $this->notFoundResponse('الطلب غير موجود');

            if ($order->status !== 'قيد_الانتظار') {
                return response()->json([
                    'status'  => false,
                    'message' => 'لا يمكن إلغاء الطلب بعد بدء المعالجة',
                ], 422);
            }

            DB::transaction(function () use ($order) {
                // إعادة المخزون
                foreach ($order->orderDetails as $detail) {
                    $detail->product->increment(
                        'quantity',
                        $detail->quantity * $detail->product->pieces_per_unit
                    );
                }

                $order->update(['status' => 'ملغي']);
            });

            return response()->json([
                'status'  => true,
                'message' => 'تم إلغاء الطلب بنجاح',
            ]);
        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────
    private function forbiddenResponse($message)
    {
        return response()->json(['status' => false, 'message' => $message], 403);
    }

    private function notFoundResponse($message)
    {
        return response()->json(['status' => false, 'message' => $message], 404);
    }

    private function serverError()
    {
        return response()->json(['status' => false, 'message' => 'حدث خطأ في الخادم'], 500);
    }
}