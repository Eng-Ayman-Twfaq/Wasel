<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\DeviceVerificationAttempt;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * تسجيل الدخول
     * 
     * @bodyParam phone string required رقم الهاتف
     * @bodyParam password string required كلمة المرور
     * @bodyParam device_id string required معرف الجهاز الفريد
     * @bodyParam device_name string اسم الجهاز (اختياري)
     */
    public function login(Request $request)
    {
        // 1. التحقق من صحة البيانات
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string',
            'device_id' => 'required|string',
            'device_name' => 'nullable|string|max:255',
            'fcm_token' => 'nullable|string' // للإشعارات
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المدخلة',
                'errors' => $validator->errors()
            ], 422);
        }

        // 2. البحث عن المستخدم
        $user = User::where('phone', $request->phone)->first();

        // 3. التحقق من كلمة المرور
        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->handleFailedLogin($request);
        }
        // $user->load('store');
        // 4. التحقق من حالة المستخدم
        // if (!$user->is_active) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'الحساب غير نشط، يرجى التواصل مع الدعم'
        //     ], 403);
        // }

        // 5. البحث عن الجهاز
        $device = UserDevice::where('user_id', $user->id)
                            ->where('device_id', $request->device_id)
                            ->first();

        // 6. تسجيل الدخول (بدون إنشاء توكن حتى نكمل التحقق)
        Auth::login($user);
            $user->load('store');
        // 7. معالجة حالة الجهاز
        return $this->handleDeviceLogin($user, $device, $request);
    }

    /**
     * معالجة تسجيل الدخول حسب حالة الجهاز
     */
    /**
 * معالجة تسجيل الدخول حسب حالة الجهاز
 */
/**
 * معالجة تسجيل الدخول حسب حالة الجهاز
 */
/**
 * معالجة تسجيل الدخول حسب حالة الجهاز
 */
private function handleDeviceLogin($user, $device, $request)
{
    // ✅ التحقق من عدد محاولات إعادة الإرسال للجهاز الحالي
    $resendCount = DeviceVerificationAttempt::where('user_id', $user->id)
        ->where('device_id', $request->device_id)
        ->count();

    // إذا وصل إلى 3 محاولات إعادة إرسال، يتم حظر الوصول فوراً
    if ($resendCount >= 3) {
        return response()->json([
            'success' => false,
            'message' => 'تم حظر هاتفك يرجى التواصل مع فريق الدعم',
            'data' => [
                'is_blocked' => true,
                'reason' => 'تجاوز عدد محاولات إعادة إرسال رمز التحقق'
            ]
        ], 403);
    }

    // حالة 1: جهاز موثوق ومعتمد
    if ($device && $device->is_approved) {
        return $this->handleTrustedDevice($user, $device, $request);
    }

    // حالة 2: جهاز مسجل ولكن غير معتمد
    if ($device && !$device->is_approved) {
        // ✅ التحقق من وجود محاولة تحقق ناجحة لهذا الجهاز
        $verifiedAttempt = DeviceVerificationAttempt::where('user_id', $user->id)
            ->where('device_id', $request->device_id)
            ->where('status', 'verified')
            ->first();

        // إذا كان هناك تحقق ناجح سابق، نعرض رسالة "غير مفعل"
        if ($verifiedAttempt) {
            return response()->json([
                'success' => false,
                'message' => 'جهازك غير مفعل للوصول إلى التطبيق، يرجى التواصل مع فريق الدعم',
                'data' => [
                    'device_status' => 'pending_approval',
                    'device_id' => $device->device_id,
                    'support_contact' => 'support@wasel.com'
                ]
            ], 403);
        }

        // إذا لم يكن هناك تحقق ناجح (أي لا يزال في مرحلة التحقق)، نرسل رمز تحقق جديد
        return $this->handleNewDevice($user, $request);
    }

    // حالة 3: جهاز جديد تماماً
    return $this->handleNewDevice($user, $request);
}

    /**
     * معالجة جهاز موثوق
     */
    private function handleTrustedDevice($user, $device, $request)
    {
         // 🔥 حذف التوكن القديم الخاص بهذا الجهاز فقط
        $user->tokens()
            ->where('name', 'auth_token_' . $device->device_id)
            ->delete();

        // إنشاء توكن جديد مربوط بالجهاز
        $token = $user->createToken(
            'auth_token_' . $device->device_id
        )->plainTextToken;
        
        // تحديث آخر دخول
        $device->update([
            'last_login_at' => now(),
            'device_name' => $request->device_name ?? $device->device_name
        ]);

        // تحديث FCM Token إذا وجد
        if ($request->fcm_token) {
            $device->update(['fcm_token' => $request->fcm_token]);
        }

        // إنشاء توكن دخول
        // $token = $user->createToken('auth_token_' . $device->device_id)->plainTextToken;

        // تسجيل حدث الدخول
        $this->logSecurityEvent($user->id, 'successful_login', [
            'device_id' => $device->device_id,
            'device_name' => $device->device_name,
            'ip' => $request->ip()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => [
                'user' => $this->formatUserData($user),
                'token' => $token,
                'device' => [
                    'id' => $device->id,
                    'name' =>$device->device_name,
                    'is_approved' => true,
                    'last_login' => $device->last_login_at
                ]
            ]
        ]);
    }

    /**
     * معالجة جهاز غير معتمد
     */
    private function handleUnapprovedDevice($user, $device, $request)
    {
        // تحديث آخر محاولة دخول
        $device->update([
            'last_login_at' => now(),
            'device_name' => $request->device_name ?? $device->device_name
        ]);

        // تسجيل محاولة دخول لجهاز غير معتمد
        $this->logSecurityEvent($user->id, 'unapproved_device_login_attempt', [
            'device_id' => $device->device_id,
            'device_name' => $device->device_name,
            'ip' => $request->ip()
        ]);

        // رسالة للمستخدم بأن الجهاز غير مفعل
        return response()->json([
            'success' => false,
            'message' => 'جهازك غير مفعل للوصول إلى التطبيق، يرجى التواصل مع فريق الدعم',
            'data' => [
                'device_status' => $device->is_approved,//'pending_approval',
                'device_id' => $device->device_id,
                'support_contact' => 'support@wasel.com' // يمكنك وضع رقم الدعم هنا
            ]
        ], 403); // 403 Forbidden
    }

  /**
 * معالجة جهاز جديد
 */
/**
 * معالجة جهاز جديد
 */
private function handleNewDevice($user, $request)
{
    // ✅ استخدام updateOrCreate بدلاً من create لتجنب duplicate entry
    $device = UserDevice::updateOrCreate(
        [
            'user_id' => $user->id,
            'device_id' => $request->device_id
        ],
        [
            'device_name' => $request->device_name ?? 'جهاز غير معروف',
            'is_approved' => false,
            'last_login_at' => now()
        ]
    );

    // إلغاء المحاولات السابقة المعلقة
    DeviceVerificationAttempt::where('user_id', $user->id)
        ->where('device_id', $request->device_id)
        ->where('status', 'pending')
        ->update(['status' => 'expired']);

    // إنشاء رمز تحقق جديد
    $verification = DeviceVerificationAttempt::create([
        'user_id' => $user->id,
        'device_id' => $request->device_id,
        'device_name' => $request->device_name ?? 'جهاز غير معروف',
        'verification_code' => $this->generateVerificationCode(),
        'ip_address' => $request->ip(),
        'attempts' => 0,
        'max_attempts' => 3,
        'expires_at' => now()->addMinutes(2),
        'status' => 'pending'
    ]);

    // إرسال رمز التحقق عبر SMS
    $this->sendVerificationSMS($user->phone, $verification->verification_code);

    // إرسال إشعار للأجهزة الموثوقة
    $this->notifyTrustedDevices($user, $request);

    return response()->json([
        'success' => true,
        'requires_verification' => true,
        'message' => 'تم إرسال رمز التحقق إلى هاتفك',
        'data' => [
            'device_id' => $request->device_id,
            'device_name' => $device->device_name,
            'phone' => $this->maskPhoneNumber($user->phone),
            'expires_in' => 120,
            'attempts_remaining' => 3
        ]
    ]);
}
    /**
     * التحقق من رمز الجهاز
     * 
     * @bodyParam phone string required رقم الهاتف
     * @bodyParam device_id string required معرف الجهاز
     * @bodyParam verification_code string required رمز التحقق
     */
    /**
 * التحقق من رمز الجهاز
 * 
 * @bodyParam phone string required رقم الهاتف
 * @bodyParam device_id string required معرف الجهاز
 * @bodyParam verification_code string required رمز التحقق
 */
/**
 * التحقق من رمز الجهاز
 */
public function verifyDevice(Request $request)
{
    $validator = Validator::make($request->all(), [
        'phone' => 'required|string',
        'device_id' => 'required|string',
        'verification_code' => 'required|string|size:6'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'خطأ في البيانات المدخلة',
            'errors' => $validator->errors()
        ], 422);
    }

    $user = User::where('phone', $request->phone)->first();
    
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'المستخدم غير موجود'
        ], 404);
    }

    // البحث عن محاولة التحقق النشطة
    $verification = DeviceVerificationAttempt::where('user_id', $user->id)
        ->where('device_id', $request->device_id)
        ->where('status', 'pending')
        ->latest()
        ->first();

    if (!$verification) {
        return response()->json([
            'success' => false,
            'message' => 'لم يتم طلب رمز تحقق لهذا الجهاز'
        ], 400);
    }

    // التحقق من صلاحية الكود (دقيقتان)
    if (Carbon::parse($verification->expires_at)->isPast()) {
        $verification->update(['status' => 'expired']);
        return response()->json([
            'success' => false,
            'message' => 'انتهت صلاحية رمز التحقق (دقيقتان). يرجى طلب رمز جديد'
        ], 400);
    }

    // التحقق من صحة الكود
    if ($verification->verification_code !== $request->verification_code) {
        // زيادة عدد المحاولات الفاشلة
        $verification->incrementAttempts();
        
        // إذا وصل إلى 3 محاولات خاطئة لهذا الكود، يتم حظر هذه المحاولة فقط
        if ($verification->attempts >= 3) {
            // تحديث حالة المحاولة إلى blocked
            $verification->update(['status' => 'blocked']);

            return response()->json([
                'success' => false,
                'message' => 'لقد تجاوزت عدد المحاولات المسموحة (3 مرات). يرجى طلب رمز تحقق جديد'
            ], 429);
        }

        // المحاولات المتبقية
        $attemptsLeft = 3 - $verification->attempts;

        return response()->json([
            'success' => false,
            'message' => 'رمز التحقق غير صحيح',
            'data' => [
                'attempts_remaining' => $attemptsLeft,
                'expires_in' => now()->diffInSeconds($verification->expires_at)
            ]
        ], 400);
    }

    // كود صحيح - أكمل العملية
    // تحديث حالة التحقق
    $verification->update([
        'status' => 'verified',
        'verified_at' => now()
    ]);

    // تحديث الجهاز (يبقى غير معتمد حتى موافقة المشرف)
    $device = UserDevice::updateOrCreate(
        [
            'user_id' => $user->id,
            'device_id' => $request->device_id
        ],
        [
            'device_name' => $verification->device_name ?? 'جهاز غير معروف',
            'is_approved' => false,
            'last_login_at' => now()
        ]
    );

    // إرسال إشعار للمشرفين للموافقة على الجهاز
    $this->notifyAdminsForApproval($user, $device);

    // إرسال إشعار للمستخدم بانتظار الموافقة
    $this->notifyUserDevicePending($user, $device);

    return response()->json([
        'success' => true,
        'message' => 'تم التحقق من الجهاز بنجاح. سيتم تفعيل جهازك بعد موافقة فريق الدعم. يرجى تسجيل الدخول مرة أخرى لاحقاً.',
        'data' => [
            'device_id' => $device->device_id,
            'device_status' => 'pending_approval',
            'requires_relogin' => true
        ]
    ]);
}

 /**
 * إعادة إرسال رمز التحقق
 */
public function resendVerificationCode(Request $request)
{
    $validator = Validator::make($request->all(), [
        'phone' => 'required|string',
        'device_id' => 'required|string',
        'device_name' => 'nullable|string|max:255'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'خطأ في البيانات المدخلة',
            'errors' => $validator->errors()
        ], 422);
    }

    $user = User::where('phone', $request->phone)->first();
    
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'المستخدم غير موجود'
        ], 404);
    }

    // حساب عدد محاولات إعادة الإرسال (كل المحاولات السابقة)
    $resendCount = DeviceVerificationAttempt::where('user_id', $user->id)
        ->where('device_id', $request->device_id)
        ->count();

    // إذا وصل إلى 3 محاولات سابقة، نمنع إعادة الإرسال
    if ($resendCount >= 3) {
        return response()->json([
            'success' => false,
            'message' => 'لقد استنفذت عدد محاولات إعادة الإرسال (3 مرات). يرجى التواصل مع فريق الدعم'
        ], 429);
    }

    // إنشاء رمز جديد
    $verification = $this->createVerificationCode($user, $request);

    // إرسال SMS
    $this->sendVerificationSMS($user->phone, $verification->verification_code);

    return response()->json([
        'success' => true,
        'message' => 'تم إرسال رمز تحقق جديد',
        'data' => [
            'expires_in' => 120,
            'attempts_remaining' => 3,
            'resend_attempts_left' => 3 - ($resendCount + 1)
        ]
    ]);
}

    /**
     * تسجيل الخروج
     */
    public function logout(Request $request)
    {
        $user = Auth::user();
        
        // حذف التوكن الحالي
        $request->user()->currentAccessToken()->delete;

        // تحديث آخر نشاط للجهاز
        if ($request->device_id) {
            UserDevice::where('user_id', $user->id)
                ->where('device_id', $request->device_id)
                ->update(['last_logout_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }

  /**
 * إنشاء رمز تحقق جديد
 */
private function createVerificationCode($user, $request)
{
    // إلغاء المحاولات السابقة
    DeviceVerificationAttempt::where('user_id', $user->id)
        ->where('device_id', $request->device_id)
        ->where('status', 'pending')
        ->update(['status' => 'expired']);

    // إنشاء رمز جديد (صلاحية دقيقتين)
    return DeviceVerificationAttempt::create([
        'user_id' => $user->id,
        'device_id' => $request->device_id,
        'device_name' => $request->device_name,
        'verification_code' => $this->generateVerificationCode(),
        'ip_address' => $request->ip(),
        'attempts' => 0,
        'max_attempts' => 3, // 3 محاولات كحد أقصى
        'expires_at' => now()->addMinutes(2), // دقيقتين صلاحية
        'status' => 'pending'
    ]);
}

    /**
     * توليد رمز تحقق عشوائي
     */
    private function generateVerificationCode()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * إرسال رمز التحقق عبر SMS
     */
    private function sendVerificationSMS($phone, $code)
    {
        // استخدام خدمة SMS
        // \App\Services\SmsService::send($phone, "رمز التحقق الخاص بك هو: $code");
        
        // للتجربة فقط - تسجيل في ملف اللوق
        Log::info("SMS sent to $phone: Verification code $code");
    }

    /**
     * إرسال إشعار للأجهزة الموثوقة
     */
    private function notifyTrustedDevices($user, $request)
    {
        $trustedDevices = UserDevice::where('user_id', $user->id)
            ->where('is_approved', true)
            ->where('device_id', '!=', $request->device_id)
            ->get();

        $location = $this->getLocationFromIP($request->ip());

        $notificationData = [
            'title' => '⚠️ تنبيه أمني',
            'body' => "محاولة دخول من جهاز جديد\n" .
                     "الجهاز: {$request->device_name}\n" .
                     "الموقع: {$location}\n" .
                     "الوقت: " . now()->format('Y-m-d H:i:s'),
            'data' => [
                'type' => 'new_device_login',
                'device_id' => $request->device_id,
                'ip' => $request->ip(),
                'requires_action' => true
            ]
        ];

        foreach ($trustedDevices as $device) {
            // حفظ الإشعار في قاعدة البيانات
            Notification::create([
                'user_id' => $user->id,
                'title' => $notificationData['title'],
                'body' => $notificationData['body'],
                'data' => $notificationData['data']
            ]);

            // إرسال push notification
            if ($device->fcm_token) {
                // \App\Services\FcmService::send($device->fcm_token, $notificationData);
            }
        }
    }

    /**
     * إشعار المشرفين للموافقة
     */
    private function notifyAdminsForApproval($user, $device)
    {
        // البحث عن المشرفين
        $admins = User::where('role', 'مدير')->orWhere('role', 'دعم')->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => 'طلب موافقة جهاز جديد',
                'body' => "المستخدم: {$user->full_name}\nالجهاز: {$device->device_name}\nيرجى الموافقة على الجهاز",
                'data' => [
                    'type' => 'device_approval_request',
                    'user_id' => $user->id,
                    'device_id' => $device->id,
                    'action_url' => "/admin/devices/approve/{$device->id}"
                ]
            ]);
        }
    }

    /**
     * إشعار المستخدم بانتظار الموافقة
     */
    private function notifyUserDevicePending($user, $device)
    {
        Notification::create([
            'user_id' => $user->id,
            'title' => 'الجهاز في انتظار الموافقة',
            'body' => "جهازك {$device->device_name} في انتظار موافقة المشرف. سيتم إشعارك عند التفعيل.",
            'data' => [
                'type' => 'device_pending',
                'device_id' => $device->id
            ]
        ]);
    }

    /**
     * معالجة تسجيل الدخول الفاشل
     */
    private function handleFailedLogin($request)
    {
        // تسجيل محاولة فاشلة
        Log::warning('Failed login attempt', [
            'phone' => $request->phone,
            'device_id' => $request->device_id,
            'ip' => $request->ip()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'رقم الهاتف أو كلمة المرور غير صحيحة'
        ], 401);
    }

    /**
     * تسجيل حدث أمني
     */
    private function logSecurityEvent($userId, $eventType, $data = [])
    {
        // يمكنك إنشاء جدول security_events لاحقاً
        Log::info("Security Event: $eventType", [
            'user_id' => $userId,
            'data' => $data
        ]);
    }

    /**
     * إخفاء رقم الهاتف
     */
    private function maskPhoneNumber($phone)
    {
        $length = strlen($phone);
        if ($length <= 4) return $phone;
        
        $visibleDigits = 4;
        $maskedLength = $length - $visibleDigits;
        
        return substr($phone, 0, 2) . str_repeat('*', $maskedLength) . substr($phone, -2);
    }

    /**
     * الحصول على الموقع من IP
     */
    private function getLocationFromIP($ip)
    {
        // يمكن استخدام خدمة مثل ipapi.co أو ipinfo.io
        return 'موقع غير معروف';
    }

    /**
     * تنسيق بيانات المستخدم
     */
    private function formatUserData($user)
    {
        return [
            'id' => $user->id,
            'name' => $user->full_name,
            'first_name' => $user->first_name,
            'father_name' => $user->father_name,
            'grandfather_name' => $user->grandfather_name,
            'last_name' => $user->last_name,
            'phone' => $user->phone,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            // 👇 الإضافة الجديدة
        'store_type' => optional($user->store)->store_type,
        'store_is_approved' => optional($user->store)->is_approved,
        'store_id' => optional($user->store)->id,
        // 'device_approved'=>optional($user->de)
//         'account_status' => [
//     'user_active' => $user->is_active,
//     'store_approved' => optional($user->store)->is_approved,
//     'device_approved' => true,
// ],
        ];
    }

    /**
 * حساب الوقت المتبقي بالثواني
 */
private function getRemainingSeconds($expiresAt)
{
    $now = now();
    $expires = Carbon::parse($expiresAt);
    
    if ($expires->gt($now)) {
        return $expires->diffInSeconds($now);
    }
    
    return 0;
}
}