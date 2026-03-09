<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\DeviceResourceShow;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class MerchantDeviceController extends Controller
{
    private function user()
    {
        return Auth::user();
    }

    // =========================================================
    // GET /api/auth/merchant/devices
    // جلب جميع الأجهزة المسجلة للتاجر
    // =========================================================
    public function index()
    {
        try {
            $user = $this->user();
            if (!$user) return $this->forbidden();

            $devices = UserDevice::where('user_id', $user->id)
                ->orderByDesc('last_login_at')
                ->get();

            // تحديد الجهاز الحالي (آخر جهاز مُفعَّل ومتصل)
            $currentDeviceId = $user->device_id;

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب الأجهزة بنجاح',
                'data'    => DeviceResourceShow::collection($devices)->map(function ($device) use ($currentDeviceId) {
                    return array_merge($device->resolve(), [
                        'is_current' => $device->resource->device_id === $currentDeviceId,
                    ]);
                }),
                'summary' => [
                    'total'    => $devices->count(),
                    'approved' => $devices->where('is_approved', true)->count(),
                    'pending'  => $devices->where('is_approved', false)->count(),
                ],
            ]);

        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    // =========================================================
    // DELETE /api/auth/merchant/devices/{id}
    // حذف جهاز (لا يمكن حذف الجهاز الحالي)
    // =========================================================
    public function destroy(int $id)
    {
        try {
            $user = $this->user();
            if (!$user) return $this->forbidden();

            $device = UserDevice::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$device) {
                return response()->json([
                    'status'  => false,
                    'message' => 'الجهاز غير موجود',
                ], 404);
            }

            // منع حذف الجهاز الحالي
            if ($device->device_id === $user->device_id) {
                return response()->json([
                    'status'  => false,
                    'message' => 'لا يمكن حذف الجهاز الحالي',
                ], 422);
            }

            $device->delete();

            return response()->json([
                'status'  => true,
                'message' => 'تم حذف الجهاز بنجاح',
            ]);

        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    // =========================================================
    // Helpers
    // =========================================================
    private function forbidden()
    {
        return response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403);
    }

    private function serverError()
    {
        return response()->json(['status' => false, 'message' => 'حدث خطأ في الخادم'], 500);
    }
}