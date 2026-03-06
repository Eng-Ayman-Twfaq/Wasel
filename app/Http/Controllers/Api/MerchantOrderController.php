<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MerchantOrderApprovalRequest;
use App\Models\Order;
use App\Models\OrderMerchantApproval;
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
    // Helper — التحقق أن المستخدم تاجر نشط
    // ─────────────────────────────────────────
    private function getMerchantStore()
    {
        $user = Auth::user();
         if (!$user) {
        throw new \Exception('المستخدم غير مسجل الدخول', 401);
    }
    
    if (!$user->store) {
        throw new \Exception('ليس لديك متجر مسجل', 403);
    }
    
    if (!$user->store->isMerchant()) {
        throw new \Exception('المتجر ليس من نوع تاجر', 403);
    }
    
    if (!$user->store->isActive()) {
        throw new \Exception('المتجر غير نشط. يرجى التواصل مع الدعم', 403);
    }
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
            $store = $this->getMerchantStore();
            if (!$store) return $this->forbiddenResponse('غير مصرح لك');

            // جميع طلبات هذا التاجر بغض النظر عن approval_flow
            $base = fn() => Order::whereHas('orderDetails',
                fn($q) => $q->where('store_id', $store->id)
            );

            // إحصائيات طلبات الدين (تحتاج موافقة التاجر)
            $daynBase = fn() => $base()->where('approval_flow', 'merchant');

            $approvalCount = fn($status) => $daynBase()->whereHas('merchantApprovals',
                fn($q) => $q->where('merchant_store_id', $store->id)->where('status', $status)
            )->count();

            $pending  = $approvalCount('بانتظار');
            $approved = $approvalCount('موافق');
            $rejected = $approvalCount('مرفوض');

            return response()->json([
                'status' => true,
                'data'   => [
                    // ── إجمالي حسب حالة الطلب ──
                    'waiting'    => $base()->where('status', 'قيد_الانتظار')->count(),
                    'processing' => $base()->where('status', 'قيد_المعالجة')->count(),
                    'completed'  => $base()->where('status', 'مكتمل')->count(),
                    'rejected'   => $base()->where('status', 'مرفوض')->count(),
                    'cancelled'  => $base()->where('status', 'ملغي')->count(),
                    'total'      => $base()->count(),

                    // ── طلبات الدين التي تحتاج موافقة التاجر ──
                    'dayn_pending'  => $pending,
                    'dayn_approved' => $approved,
                    'dayn_rejected' => $rejected,
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
            if (!$store) return $this->forbiddenResponse('غير مصرح لك بعرض الطلبات');

            $query = Order::whereHas('orderDetails', fn($q) => $q->where('store_id', $store->id))
                ->with([
                    'grocery:id,store_name,address',
                    'paymentMethod:id,name',
                    'orderDetails' => fn($q) => $q->where('store_id', $store->id)
                        ->with('product:id,name,price,unit_type,image_url'),
                    'merchantApprovals' => fn($q) => $q->where('merchant_store_id', $store->id),
                ])
                ->latest();
            
            // ✅ فلترة حسب حالة الطلب
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $orders = $query->paginate(10);
// ✅ التحقق من وجود طلبات
        if ($orders->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'لايوجد طلبات حاليا',
                'data'    => [],
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page'    => $orders->lastPage(),
                    'per_page'     => $orders->perPage(),
                    'total'        => $orders->total(),
                ],
            ]);
        }
            return response()->json([
                'status'  => true,
                'message' => 'تم جلب الطلبات بنجاح',
                'data'    => OrderResource::collection($orders),
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
            if (!$store) return $this->forbiddenResponse('غير مصرح لك');

            $order = Order::whereHas('orderDetails', fn($q) => $q->where('store_id', $store->id))
                ->with([
                    'grocery:id,store_name,address',
                    'paymentMethod:id,name',
                    'orderDetails' => fn($q) => $q->where('store_id', $store->id)
                        ->with('product:id,name,price,unit_type,image_url'),
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
    // ✅ تتطلب كلمة المرور
    // =========================================================
    public function approve(MerchantOrderApprovalRequest $request, $id)
    {
        try {
            $store = $this->getMerchantStore();
            if (!$store) return $this->forbiddenResponse('غير مصرح لك');

            // ✅ التحقق من كلمة المرور عبر الـ Trait
            $check = $this->verifyPassword($request->password);
            if ($check !== true) return $check;

            $order = $this->getMerchantOrder($id, $store->id);
            if (!$order) return $this->notFoundResponse('الطلب غير موجود');

            // ✅ التحقق أن الطلب يسمح بموافقة التاجر (دفع دين فقط)
            if ($order->approval_flow !== 'merchant') {
                return response()->json([
                    'status'  => false,
                    'message' => 'هذا الطلب لا يتطلب موافقة التاجر، يتم معالجته عبر فريق الدعم',
                ], 422);
            }

            $approval = $this->getMerchantApproval($order->id, $store->id);
            if (!$approval) return $this->notFoundResponse('لا يوجد سجل موافقة مرتبط بهذا الطلب');

            if (!$approval->isPending()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'تم البت في هذا الطلب مسبقاً',
                ], 422);
            }

            DB::transaction(function () use ($order, $approval, $store) {
                // تحديث موافقة هذا التاجر
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

                // هل جميع التجار وافقوا؟
                $allApproved = OrderMerchantApproval::where('order_id', $order->id)
                    ->where('status', '!=', 'موافق')
                    ->doesntExist();

                if ($allApproved) {
                    $order->update([
                        'status'           => 'قيد_المعالجة',
                        'customer_visible' => true,
                    ]);
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
    // ✅ تتطلب كلمة المرور
    // =========================================================
    public function reject(MerchantOrderApprovalRequest $request, $id)
    {
        try {
            $store = $this->getMerchantStore();
            if (!$store) return $this->forbiddenResponse('غير مصرح لك');

            // ✅ التحقق من كلمة المرور عبر الـ Trait
            $check = $this->verifyPassword($request->password);
            if ($check !== true) return $check;

            $order = $this->getMerchantOrder($id, $store->id);
            if (!$order) return $this->notFoundResponse('الطلب غير موجود');

            // ✅ التحقق أن الطلب يسمح بموافقة التاجر (دفع دين فقط)
            if ($order->approval_flow !== 'merchant') {
                return response()->json([
                    'status'  => false,
                    'message' => 'هذا الطلب لا يتطلب موافقة التاجر، يتم معالجته عبر فريق الدعم',
                ], 422);
            }

            $approval = $this->getMerchantApproval($order->id, $store->id);
            if (!$approval) return $this->notFoundResponse('لا يوجد سجل موافقة مرتبط بهذا الطلب');

            if (!$approval->isPending()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'تم البت في هذا الطلب مسبقاً',
                ], 422);
            }

            DB::transaction(function () use ($order, $approval) {
                $approval->update([
                    'status'      => 'مرفوض',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);

                // رفض الطلب كاملاً فور رفض أي تاجر
                $order->update([
                    'merchant_approval_status' => 'مرفوض',
                    'status'                   => 'مرفوض',
                    'customer_visible'         => false,
                ]);
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