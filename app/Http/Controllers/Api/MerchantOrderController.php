<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MerchantOrderApprovalRequest;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderMerchantApproval;
use App\Models\Transaction;
use App\Models\User;
use App\Http\Resources\OrderResource;
use App\Traits\PasswordVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class MerchantOrderController extends Controller
{
    use PasswordVerification;

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
    // Helper — التحقق أن المستخدم تاجر نشط
    // ─────────────────────────────────────────
    private function getMerchantStore()
    {
        $user = Auth::user();
        if (!$user) throw new \Exception('المستخدم غير مسجل الدخول', 401);
        if (!$user->store) throw new \Exception('ليس لديك متجر مسجل', 403);
        if (!$user->store->isMerchant()) throw new \Exception('المتجر ليس من نوع تاجر', 403);
        if (!$user->store->isActive()) throw new \Exception('المتجر غير نشط. يرجى التواصل مع الدعم', 403);
        return $user->store;
    }

    private function getMerchantOrder($orderId, $storeId)
    {
        return Order::whereHas('orderDetails', fn($q) => $q->where('store_id', $storeId))
            ->find($orderId);
    }

    private function getMerchantApproval($orderId, $storeId)
    {
        return OrderMerchantApproval::where('order_id', $orderId)
            ->where('merchant_store_id', $storeId)
            ->first();
    }

    // =========================================================
    // GET /api/auth/merchant/orders/stats
    // =========================================================
    public function stats()
    {
        try {
            $store    = $this->getMerchantStore();
            $base     = fn() => Order::whereHas('orderDetails', fn($q) => $q->where('store_id', $store->id));
            $daynBase = fn() => $base()->where('approval_flow', 'merchant');

            $approvalCount = fn($status) => $daynBase()->whereHas('merchantApprovals',
                fn($q) => $q->where('merchant_store_id', $store->id)->where('status', $status)
            )->count();

            return response()->json([
                'status' => true,
                'data'   => [
                    'waiting'       => $base()->where('status', 'قيد_الانتظار')->count(),
                    'processing'    => $base()->where('status', 'قيد_المعالجة')->count(),
                    'completed'     => $base()->where('status', 'مكتمل')->count(),
                    'rejected'      => $base()->where('status', 'مرفوض')->count(),
                    'cancelled'     => $base()->where('status', 'ملغي')->count(),
                    'total'         => $base()->count(),
                    'dayn_pending'  => $approvalCount('بانتظار'),
                    'dayn_approved' => $approvalCount('موافق'),
                    'dayn_rejected' => $approvalCount('مرفوض'),
                ],
            ]);
        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    // =========================================================
    // GET /api/auth/merchant/orders
    // =========================================================
    public function index(Request $request)
    {
        try {
            $store = $this->getMerchantStore();

            $query = Order::whereHas('orderDetails', fn($q) => $q->where('store_id', $store->id))
                ->with([
                    'grocery:id,store_name,address',
                    'paymentMethod:id,name',
                    'orderDetails' => fn($q) => $q->where('store_id', $store->id)
                        ->with('product:id,name,price,unit_type'),
                    'merchantApprovals' => fn($q) => $q->where('merchant_store_id', $store->id),
                ])
                ->latest();

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $orders = $query->paginate(10);

            if ($orders->isEmpty()) {
                return response()->json([
                    'status'     => false,
                    'message'    => 'لا يوجد طلبات حالياً',
                    'data'       => [],
                    'pagination' => [
                        'current_page' => $orders->currentPage(),
                        'last_page'    => $orders->lastPage(),
                        'per_page'     => $orders->perPage(),
                        'total'        => $orders->total(),
                    ],
                ]);
            }

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
    // GET /api/auth/merchant/orders/{id}
    // =========================================================
    public function show($id)
    {
        try {
            $store = $this->getMerchantStore();

            $order = Order::whereHas('orderDetails', fn($q) => $q->where('store_id', $store->id))
                ->with([
                    'grocery:id,store_name,address',
                    'paymentMethod:id,name',
                    'orderDetails' => fn($q) => $q->where('store_id', $store->id)
                        ->with('product:id,name,price,unit_type'),
                    'merchantApprovals' => fn($q) => $q->where('merchant_store_id', $store->id),
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
    // POST /api/auth/merchant/orders/{id}/approve
    // =========================================================
    public function approve(MerchantOrderApprovalRequest $request, $id)
    {
        try {
            $store = $this->getMerchantStore();

            $check = $this->verifyPassword($request->password);
            if ($check !== true) return $check;

            $order = $this->getMerchantOrder($id, $store->id);
            if (!$order) return $this->notFoundResponse('الطلب غير موجود');

            if ($order->approval_flow !== 'merchant') {
                return response()->json([
                    'status'  => false,
                    'message' => 'هذا الطلب لا يتطلب موافقة التاجر',
                ], 422);
            }

            $approval = $this->getMerchantApproval($order->id, $store->id);
            if (!$approval) return $this->notFoundResponse('لا يوجد سجل موافقة لهذا الطلب');

            if (!$approval->isPending()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'تم البت في هذا الطلب مسبقاً',
                ], 422);
            }

            DB::transaction(function () use ($order, $approval, $store) {

                // ── 1. تحديث موافقة هذا التاجر ──
                $approval->update([
                    'status'      => 'موافق',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);

                $order->update([
                    'merchant_approval_status' => 'موافق',
                    'merchant_approved_by'     => $store->id,
                    'merchant_approved_at'     => now(),
                ]);

                // ── 2. مبلغ هذا التاجر تحديداً ──
                $merchantTotal = $order->orderDetails()
                    ->where('store_id', $store->id)
                    ->sum('subtotal');

                // ── 3. فاتورة merchant لهذا التاجر ──
                $merchantInvoice = Invoice::create([
                    'order_id'          => $order->id,
                    'store_id'          => $store->id,
                    'customer_store_id' => $order->store_id,
                    'invoice_type'      => 'merchant',
                    'total_amount'      => $merchantTotal,
                    'invoice_status'    => 'مرسلة',
                    'sent_at'           => now(),
                ]);

                // ── 4. معاملة مالية لهذا التاجر مرتبطة بفاتورته ──
                Transaction::create([
                    'invoice_id'       => $merchantInvoice->id,
                    'amount'           => $merchantTotal,
                    'payment_method_id'=> $order->payment_method_id,
                    'transaction_date' => now(),
                    'reference'        => 'ORD-' . $order->id . '-STORE-' . $store->id,
                    'status'           => 'قيد_الانتظار', // تصبح ناجحة عند موافقة الكل
                ]);

                // ── 5. إشعار للبقالة: تاجر وافق ──
                $groceryUser = User::whereHas('store', fn($q) => $q->where('id', $order->store_id))->first();

                if ($groceryUser) {
                    $this->sendNotification(
                        $groceryUser->id,
                        'تاجر وافق على طلبك ✅',
                        "وافق {$store->store_name} على طلبك رقم #{$order->id}",
                        ['type' => 'merchant_approved', 'order_id' => (string) $order->id]
                    );
                }

                // ── 6. هل جميع التجار وافقوا؟ ──
                $allApproved = OrderMerchantApproval::where('order_id', $order->id)
                    ->where('status', '!=', 'موافق')
                    ->doesntExist();

                if ($allApproved) {

                    // 6a. تحديث حالة الطلب + حالة الدفع → مدفوع
                    $order->update([
                        'status'           => 'قيد_المعالجة',
                        'payment_status'   => 'مدفوع',
                        'customer_visible' => true,
                    ]);

                    // 6b. تحديث جميع المعاملات المالية لهذا الطلب → ناجحة
                    Transaction::whereHas('invoice',
                        fn($q) => $q->where('order_id', $order->id)
                    )->update(['status' => 'ناجحة']);

                    // 6c. تحديث جميع الفواتير merchant → مدفوعة
                    Invoice::where('order_id', $order->id)
                        ->where('invoice_type', 'merchant')
                        ->update(['invoice_status' => 'مدفوعة']);

                    // 6d. فاتورة master = مجموع كل فواتير merchant
                    $masterTotal = Invoice::where('order_id', $order->id)
                        ->where('invoice_type', 'merchant')
                        ->sum('total_amount');

                    $masterInvoice = Invoice::create([
                        'order_id'          => $order->id,
                        'store_id'          => $order->store_id,
                        'customer_store_id' => $order->store_id,
                        'invoice_type'      => 'master',
                        'total_amount'      => $masterTotal,
                        'invoice_status'    => 'مدفوعة',
                        'sent_at'           => now(),
                    ]);

                    // 6e. معاملة مالية للفاتورة master
                    Transaction::create([
                        'invoice_id'        => $masterInvoice->id,
                        'amount'            => $masterTotal,
                        'payment_method_id' => $order->payment_method_id,
                        'transaction_date'  => now(),
                        'reference'         => 'ORD-' . $order->id . '-MASTER',
                        'status'            => 'ناجحة',
                    ]);

                    // 6f. إشعار للبقالة: الطلب كاملاً معتمد
                    if ($groceryUser) {
                        $this->sendNotification(
                            $groceryUser->id,
                            'طلبك معتمد بالكامل 🎉',
                            "تمت موافقة جميع التجار على طلبك رقم #{$order->id} بإجمالي {$masterTotal} ريال",
                            [
                                'type'     => 'order_fully_approved',
                                'order_id' => (string) $order->id,
                                'total'    => (string) $masterTotal,
                            ]
                        );
                    }
                }
            });

            return response()->json([
                'status'  => true,
                'message' => 'تمت الموافقة على الطلب بنجاح',
            ]);
        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    // =========================================================
    // POST /api/auth/merchant/orders/{id}/reject
    // =========================================================
    public function reject(MerchantOrderApprovalRequest $request, $id)
    {
        try {
            $store = $this->getMerchantStore();

            $check = $this->verifyPassword($request->password);
            if ($check !== true) return $check;

            $order = $this->getMerchantOrder($id, $store->id);
            if (!$order) return $this->notFoundResponse('الطلب غير موجود');

            if ($order->approval_flow !== 'merchant') {
                return response()->json([
                    'status'  => false,
                    'message' => 'هذا الطلب لا يتطلب موافقة التاجر',
                ], 422);
            }

            $approval = $this->getMerchantApproval($order->id, $store->id);
            if (!$approval) return $this->notFoundResponse('لا يوجد سجل موافقة لهذا الطلب');

            if (!$approval->isPending()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'تم البت في هذا الطلب مسبقاً',
                ], 422);
            }

            DB::transaction(function () use ($order, $approval, $store) {

                // ── 1. تحديث الرفض ──
                $approval->update([
                    'status'      => 'مرفوض',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);

                $order->update([
                    'merchant_approval_status' => 'مرفوض',
                    'status'                   => 'مرفوض',
                    'payment_status'           => 'فشل_الدفع',
                    'customer_visible'         => false,
                ]);

                // ── 2. إلغاء أي معاملات مالية أُنشئت مسبقاً لهذا الطلب ──
                Transaction::whereHas('invoice',
                    fn($q) => $q->where('order_id', $order->id)
                )->update(['status' => 'فشلت']);

                // ── 3. إشعار للبقالة ──
                $groceryUser = User::whereHas('store', fn($q) => $q->where('id', $order->store_id))->first();

                if ($groceryUser) {
                    $this->sendNotification(
                        $groceryUser->id,
                        'تم رفض طلبك ❌',
                        "رفض {$store->store_name} طلبك رقم #{$order->id}",
                        ['type' => 'order_rejected', 'order_id' => (string) $order->id]
                    );
                }
            });

            return response()->json([
                'status'  => true,
                'message' => 'تم رفض الطلب',
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