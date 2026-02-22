<?php
// app/Http/Controllers/Api/Auth/DeviceController.php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\UserDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeviceController extends Controller
{
    /**
     * تسجيل جهاز جديد أو تحديث جهاز موجود
     */
    public function registerDevice(RegisterDeviceRequest $request, $userId)
    {
        try {
            // التحقق من وجود المستخدم
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'المستخدم غير موجود'
                ], 404);
            }

            // البحث عن الجهاز
            $device = UserDevice::where('user_id', $userId)
                ->where('device_id', $request->device_id)
                ->first();

            $isNewDevice = false;

            DB::beginTransaction();

            if ($device) {
                // تحديث جهاز موجود
                $device->update([
                    'device_name' => $request->device_name,
                    'last_login_at' => now(),
                    // لا نغير حالة is_approved
                ]);
            } else {
                // إنشاء جهاز جديد
                $device = UserDevice::create([
                    'user_id' => $userId,
                    'device_id' => $request->device_id,
                    'device_name' => $request->device_name,
                    'is_approved' => false, // يحتاج موافقة
                    'last_login_at' => now(),
                ]);
                $isNewDevice = true;
            }

            DB::commit();

            // تسجيل في اللوج للتصحيح
            Log::info('تم تسجيل جهاز جديد', [
                'user_id' => $userId,
                'device_id' => $request->device_id,
                'is_new' => $isNewDevice
            ]);

            return response()->json([
                'status' => true,
                'message' => $isNewDevice 
                    ? 'تم تسجيل الجهاز الجديد، في انتظار الموافقة'
                    : 'تم تحديث معلومات الجهاز',
                'device' => new DeviceResource($device),
                'is_new_device' => $isNewDevice
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في تسجيل الجهاز: ' . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء تسجيل الجهاز',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * التحقق من صلاحية الجهاز (للتسجيل الدخول)
     */
    public function verifyDevice(Request $request, $userId)
    {
        $request->validate([
            'device_id' => 'required|string'
        ]);

        try {
            $device = UserDevice::where('user_id', $userId)
                ->where('device_id', $request->device_id)
                ->first();

            if (!$device) {
                return response()->json([
                    'status' => false,
                    'message' => 'الجهاز غير مسجل',
                    'is_new_device' => true
                ], 404);
            }

            if (!$device->is_approved) {
                return response()->json([
                    'status' => false,
                    'message' => 'الجهاز غير معتمد، يرجى التواصل مع الإدارة',
                    'device' => new DeviceResource($device),
                    'is_approved' => false
                ], 403);
            }

            // تحديث آخر دخول
            $device->update(['last_login_at' => now()]);

            return response()->json([
                'status' => true,
                'message' => 'الجهاز معتمد',
                'device' => new DeviceResource($device),
                'is_approved' => true
            ], 200);

        } catch (\Exception $e) {
            Log::error('خطأ في التحقق من الجهاز: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء التحقق من الجهاز'
            ], 500);
        }
    }

    /**
     * موافقة المدير على جهاز (للوحة التحكم)
     */
    public function approveDevice(Request $request, $deviceId)
    {
        try {
            $device = UserDevice::find($deviceId);
            if (!$device) {
                return response()->json([
                    'status' => false,
                    'message' => 'الجهاز غير موجود'
                ], 404);
            }

            // التحقق من أن المستخدم الحالي هو مدير
            // $this->authorize('approve', $device);

            $device->approve(Auth::id());

            // هنا يمكن إرسال إشعار للمستخدم بأن جهازه معتمد
            // Notification::send($device->user, new DeviceApprovedNotification($device));

            return response()->json([
                'status' => true,
                'message' => 'تم اعتماد الجهاز بنجاح',
                'device' => new DeviceResource($device)
            ], 200);

        } catch (\Exception $e) {
            Log::error('خطأ في اعتماد الجهاز: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء اعتماد الجهاز'
            ], 500);
        }
    }

    /**
     * جلب أجهزة مستخدم معين
     */
    public function getUserDevices($userId)
    {
        try {
            $devices = UserDevice::where('user_id', $userId)
                ->orderBy('last_login_at', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'data' => DeviceResource::collection($devices)
            ], 200);

        } catch (\Exception $e) {
            Log::error('خطأ في جلب أجهزة المستخدم: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء جلب الأجهزة'
            ], 500);
        }
    }

    /**
     * حذف جهاز (تسجيل خروج من جهاز)
     */
    public function revokeDevice($deviceId)
    {
        try {
            $device = UserDevice::find($deviceId);
            if (!$device) {
                return response()->json([
                    'status' => false,
                    'message' => 'الجهاز غير موجود'
                ], 404);
            }

            // التحقق من أن المستخدم الحالي هو صاحب الجهاز أو مدير
            // if ($device->user_id != auth()->id() && !auth()->user()->isAdmin()) {
            //     return response()->json(['status' => false, 'message' => 'غير مصرح'], 403);
            // }

            $device->delete();

            return response()->json([
                'status' => true,
                'message' => 'تم إلغاء ربط الجهاز بنجاح'
            ], 200);

        } catch (\Exception $e) {
            Log::error('خطأ في إلغاء ربط الجهاز: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء إلغاء ربط الجهاز'
            ], 500);
        }
    }
}