<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Traits\PasswordVerification;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    use PasswordVerification;

    /**
     * عرض جميع منتجات التاجر
     */
    public function index()
    {
        try {

            $user =  Auth::user();

            if (!$this->checkMerchant($user)) {
                return $this->forbiddenResponse('غير مصرح لك بعرض المنتجات');
            }

            $products = $user->store->products()->latest()->paginate(10);

            return response()->json([
                'status' => true,
                'message' => 'تم جلب المنتجات بنجاح',
                'data' => ProductResource::collection($products),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ]);

        } catch (Exception $e) {
            return $this->serverError();
        }
    }
 // =========================================================
    // ✅ إحصائيات المنتجات والعروض — GET /api/auth/products/stats
    // =========================================================
    public function stats()
    {
        try {
            $user = Auth::user();

            if (!$this->checkMerchant($user)) {
                return $this->forbiddenResponse('غير مصرح لك بعرض الإحصائيات');
            }

            $store = $user->store;

            // ✅ إحصائيات المنتجات
            $totalProducts    = $store->products()->count();
            $availableProducts = $store->products()
                ->where('is_available', true)
                ->where('quantity', '>', 0)
                ->count();
            $outOfStockProducts = $store->products()
                ->where(function ($q) {
                    $q->where('is_available', false)
                      ->orWhere('quantity', '<=', 0);
                })
                ->count();
            $lowStockProducts = $store->products()
                ->where('is_available', true)
                ->where('quantity', '>', 0)
                ->whereColumn('quantity', '<=', 'low_stock_threshold')
                ->count();

            // ✅ إحصائيات العروض
            $totalPromotions  = $store->promotions()->count();
            $activePromotions = $store->promotions()
                ->where('is_active', true)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->count();

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب الإحصائيات بنجاح',
                'data'    => [
                    'products' => [
                        'total'        => $totalProducts,
                        'available'    => $availableProducts,
                        'out_of_stock' => $outOfStockProducts,
                        'low_stock'    => $lowStockProducts,
                    ],
                    'promotions' => [
                        'total'  => $totalPromotions,
                        'active' => $activePromotions,
                    ],
                ]
            ]);

        } catch (Exception $e) {
            return $this->serverError();
        }
    }
    /**
     * عرض منتج واحد
     */
    public function show($id)
    {
        try {

            $user =  Auth::user();

            if (!$this->checkMerchant($user)) {
                return $this->forbiddenResponse('غير مصرح لك بعرض المنتج');
            }

            $product = Product::where('store_id', $user->store->id)
                              ->find($id);

            if (!$product) {
                return $this->notFoundResponse('المنتج غير موجود');
            }

            return response()->json([
                'status' => true,
                'data' => new ProductResource($product)
            ]);

        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    /**
     * إنشاء منتج
     */
    public function store(StoreProductRequest $request)
    {
        try {

            $user =  Auth::user();

            if (!$this->checkMerchant($user)) {
                return $this->forbiddenResponse('غير مصرح لك بإضافة منتج');
            }

            $check = $this->verifyPassword($request->password);

            if ($check !== true) {
                return $check; // يرجع رسالة الخطأ تلقائياً
            }

            $product = $user->store->products()->create(
                $request->except('password')
            );

            return response()->json([
                'status' => true,
                'message' => 'تم إضافة المنتج بنجاح',
                'data' => new ProductResource($product)
            ], 201);

        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    /**
     * تعديل منتج
     */
    public function update(UpdateProductRequest $request, $id)
    {
        try {

            $user =  Auth::user();

            if (!$this->checkMerchant($user)) {
                return $this->forbiddenResponse('غير مصرح لك بالتعديل');
            }

            $product = Product::where('store_id', $user->store->id)
                              ->find($id);

            if (!$product) {
                return $this->notFoundResponse('المنتج غير موجود');
            }

            $check = $this->verifyPassword($request->password);

        if ($check !== true) {
            return $check; // يرجع رسالة الخطأ تلقائياً
        }

            $product->update(
                $request->except('password')
            );

            return response()->json([
                'status' => true,
                'message' => 'تم تعديل المنتج بنجاح',
                'data' => new ProductResource($product)
            ]);

        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    // /**
    //  * حذف منتج
    //  */
    // public function destroy(Request $request, $id)
    // {
    //     try {

    //         $request->validate([
    //             'password' => 'required|string'
    //         ], [
    //             'password.required' => 'كلمة المرور مطلوبة للتأكيد'
    //         ]);

    //         $user =  Auth::user();

    //         if (!$this->checkMerchant($user)) {
    //             return $this->forbiddenResponse('غير مصرح لك بالحذف');
    //         }

    //         $product = Product::where('store_id', $user->store->id)
    //                           ->find($id);

    //         if (!$product) {
    //             return $this->notFoundResponse('المنتج غير موجود');
    //         }

    //          $check = $this->verifyPassword($request->password);

    //     if ($check !== true) {
    //         return $check; // يرجع رسالة الخطأ تلقائياً
    //     }

    //         $product->delete();

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'تم حذف المنتج بنجاح'
    //         ]);

    //     } catch (Exception $e) {
    //         return $this->serverError();
    //     }
    // }
public function destroy(Request $request, $id)
{
    try {
        $request->validate([
            'password' => 'required|string'
        ], [
            'password.required' => 'كلمة المرور مطلوبة للتأكيد'
        ]);

        $user = Auth::user();

        if (!$this->checkMerchant($user)) {
            return $this->forbiddenResponse('غير مصرح لك بالحذف');
        }

        $product = Product::where('store_id', $user->store->id)
                          ->find($id);

        if (!$product) {
            return $this->notFoundResponse('المنتج غير موجود');
        }

        // --- تحقق من وجود الطلبات المرتبطة ---
        if ($product->orderDetails()->exists()) {
            return response()->json([
                'status'  => false,
                'message' => 'لا يمكن حذف المنتج لأنه مرتبط بطلبات'
            ], 422);
        }

        $check = $this->verifyPassword($request->password);
        if ($check !== true) {
            return $check; // يرجع رسالة الخطأ تلقائياً
        }

        $product->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف المنتج بنجاح'
        ]);

    } catch (Exception $e) {
        return $this->serverError();
    }
}
    // =========================================================
    // Helper Methods
    // =========================================================

    private function checkMerchant($user): bool
    {
        return $user
            && $user->isStoreOwner()
            && $user->store
            && $user->store->isMerchant();
    }

    private function forbiddenResponse($message)
    {
        return response()->json([
            'status' => false,
            'message' => $message
        ], 403);
    }

    private function notFoundResponse($message)
    {
        return response()->json([
            'status' => false,
            'message' => $message
        ], 404);
    }

    private function serverError()
    {
        return response()->json([
            'status' => false,
            'message' => 'حدث خطأ في الخادم'
        ], 500);
    }
}