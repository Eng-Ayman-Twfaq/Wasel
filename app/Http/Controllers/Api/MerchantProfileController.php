<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateMerchantProfileRequest;
use App\Http\Resources\MerchantProfileResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

class MerchantProfileController extends Controller
{
    // ─────────────────────────────────────────
    // Helper — التحقق أن المستخدم تاجر نشط
    // ─────────────────────────────────────────
    private function getMerchantUser()
    {
        $user = Auth::user();

        if (!$user || !$user->isStoreOwner()) {
            return null;
        }

        return $user;
    }

    // =========================================================
    // GET /api/auth/merchant/profile
    // جلب بيانات التاجر والمتجر
    // =========================================================
    public function show()
    {
        try {
            $user = $this->getMerchantUser();

            if (!$user) {
                return $this->forbiddenResponse('غير مصرح لك');
            }

            // تحميل المتجر مع المنطقة
            $user->load(['store.area']);

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب بيانات الملف الشخصي بنجاح',
                'data'    => new MerchantProfileResource($user),
            ]);

        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    // =========================================================
    // PUT /api/auth/merchant/profile
    // تعديل بيانات التاجر والمتجر
    // =========================================================
    public function update(UpdateMerchantProfileRequest $request)
    {
        try {
            $user = $this->getMerchantUser();

            if (!$user) {
                return $this->forbiddenResponse('غير مصرح لك');
            }

            DB::transaction(function () use ($request, $user) {

                // ── تحديث بيانات المستخدم ──
                $userFields = [];

                if ($request->filled('first_name')) {
                    $userFields['first_name'] = $request->first_name;
                }
                if ($request->filled('father_name')) {
                    $userFields['father_name'] = $request->father_name;
                }
                if ($request->filled('grandfather_name')) {
                    $userFields['grandfather_name'] = $request->grandfather_name;
                }
                if ($request->filled('last_name')) {
                    $userFields['last_name'] = $request->last_name;
                }
                // if ($request->filled('phone')) {
                //     $userFields['phone'] = $request->phone;
                // }

                if (!empty($userFields)) {
                    $user->update($userFields);
                }

                // ── تحديث بيانات المتجر ──
                if ($user->store) {
                    $storeFields = [];

                    if ($request->filled('store_name')) {
                        $storeFields['store_name'] = $request->store_name;
                    }
                    if ($request->filled('address')) {
                        $storeFields['address'] = $request->address;
                    }
                    if ($request->filled('latitude')) {
                        $storeFields['latitude'] = $request->latitude;
                    }
                    if ($request->filled('longitude')) {
                        $storeFields['longitude'] = $request->longitude;
                    }

                    if (!empty($storeFields)) {
                        $user->store->update($storeFields);
                    }
                }
            });

            // إعادة البيانات المحدثة
            $user->fresh()->load(['store.area']);

            return response()->json([
                'status'  => true,
                'message' => 'تم تحديث الملف الشخصي بنجاح',
                'data'    => new MerchantProfileResource($user->fresh()->load(['store.area'])),
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

    private function serverError()
    {
        return response()->json(['status' => false, 'message' => 'حدث خطأ في الخادم'], 500);
    }


    // =========================================================
    // PUT /api/auth/merchant/profile/password
    // تغيير كلمة المرور
    // =========================================================
    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->forbiddenResponse('غير مصرح لك');
            }

            // التحقق من كلمة المرور الحالية
            if (!\Illuminate\Support\Facades\Hash::check(
                $request->current_password,
                $user->password
            )) {
                return response()->json([
                    'status'  => false,
                    'message' => 'كلمة المرور الحالية غير صحيحة',
                    'errors'  => ['current_password' => ['كلمة المرور الحالية غير صحيحة']],
                ], 422);
            }

            // تحديث كلمة المرور
            $user->update([
                'password' => \Illuminate\Support\Facades\Hash::make($request->new_password),
            ]);

            // إلغاء جميع التوكنات الأخرى (تسجيل خروج من باقي الأجهزة)
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

            return response()->json([
                'status'  => true,
                'message' => 'تم تغيير كلمة المرور بنجاح',
            ]);

        } catch (\Exception $e) {
            return $this->serverError();
        }
    }
}