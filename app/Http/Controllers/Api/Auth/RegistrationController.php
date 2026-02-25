<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Store;
use App\Models\PhoneVerification;
use App\Models\UserUploadedDocument;
use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class RegistrationController extends Controller
{
    /**
     * المرحلة 1: البيانات الشخصية الأساسية + رقم الهاتف
     */
    public function registerStep1(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'grandfather_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|in:ذكر,أنثى',
            'birth_date' => 'required|date|before:today',
            'nationality' => 'required|string|max:255',
            'phone' => 'required|string|unique:users,phone|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
    //         'phone' => [
    // 'required',
    // 'string',
    // 'regex:/^([0-9\s\-\+\(\)]*)$/',
    // 'min:10',
    // 'max:15',
    // Rule::unique('users', 'phone')->where(function ($query) {
    //     return $query->where('is_active', 1);
    // }),
// ],
            // للارقام اليمينة
//             'phone' => [
//     'required',
//     'unique:users,phone',
//     'regex:/^(77|78|71|73|70)[0-9]{7}$/'
// ],

        ], [
            'first_name.required' => 'الاسم الأول مطلوب',
            'first_name.string' => 'الاسم الأول يجب أن يكون نصاً',
            'father_name.required' => 'اسم الأب مطلوب',
            'grandfather_name.required' => 'اسم الجد مطلوب',
            'last_name.required' => 'اللقب مطلوب',
            'gender.required' => 'الجنس مطلوب',
            'gender.in' => 'الجنس يجب أن يكون ذكر أو أنثى',
            'birth_date.required' => 'تاريخ الميلاد مطلوب',
            'birth_date.date' => 'تاريخ الميلاد يجب أن يكون تاريخاً صحيحاً',
            'birth_date.before' => 'تاريخ الميلاد يجب أن يكون قبل اليوم',
            'nationality.required' => 'الجنسية مطلوبة',
            'phone.required' => 'رقم الجوال مطلوب',
            'phone.unique' => 'رقم الجوال مستخدم بالفعل',
            'phone.regex' => 'صيغة رقم الجوال غير صحيحة',
            'phone.min' => 'رقم الجوال يجب أن يكون 10 أرقام على الأقل',
            'phone.max' => 'رقم الجوال يجب أن يكون 15 رقم على الأكثر',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // تخزين البيانات مؤقتاً في الجلسة أو إنشاء سجل مؤقت
            $tempData = [
                'first_name' => $request->first_name,
                'father_name' => $request->father_name,
                'grandfather_name' => $request->grandfather_name,
                'last_name' => $request->last_name,
                'gender' => $request->gender,
                'birth_date' => $request->birth_date,
                'nationality' => $request->nationality,
                'phone' => $request->phone,
            ];

            // يمكن تخزين في الكاش أو جلسة مؤقتة
            // هنا سنستخدم cache لمدة 30 دقيقة
            $tempToken = md5(uniqid() . $request->phone . time());
            cache()->put('registration_' . $tempToken, $tempData, now()->addMinutes(30));

            // إنشاء وإرسال رمز التحقق
            $verification = PhoneVerification::createVerification(
                $request->phone,
                $request->ip(),
                ['user_agent' => $request->userAgent()]
            );

            // هنا سيتم إرسال الرسالة (SMS)
            // sendSMS($request->phone, $verification->code);

            // للاختبار فقط - أرجع الرمز في الرد
            return response()->json([
                'status' => true,
                'message' => 'تم إرسال رمز التحقق إلى رقم الجوال',
                'data' => [
                    'temp_token' => $tempToken,
                    'phone' => $request->phone,
                    'code' => $verification->code, // أزله في الإنتاج
                    'expires_at' => $verification->expires_at
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء إرسال رمز التحقق',
                'error' => $e->getMessage()
            ], 500);
        }
    }

 /**
 * المرحلة 2: التحقق من رمز الهاتف
 */
public function verifyPhone(Request $request)
{
    $validator = Validator::make($request->all(), [
        'phone' => 'required|string',
        'code' => 'required|string|size:6',
        'temp_token' => 'required|string'
    ], [
        'phone.required' => 'رقم الجوال مطلوب',
        'code.required' => 'رمز التحقق مطلوب',
        'code.size' => 'رمز التحقق يجب أن يكون 6 أرقام',
        'temp_token.required' => 'رمز الجلسة مطلوب'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'خطأ في التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        // 🔹 جلب بيانات التسجيل من الكاش
        $tempData = cache()->get('registration_' . $request->temp_token);

        if (!$tempData) {
            return response()->json([
                'status' => false,
                'message' => 'انتهت صلاحية الجلسة، يرجى البدء من جديد'
            ], 400);
        }

        // 🔹 التأكد أن رقم الهاتف مطابق للجلسة
        if (!isset($tempData['phone']) || $tempData['phone'] !== $request->phone) {
            return response()->json([
                'status' => false,
                'message' => 'رقم الجوال غير مطابق لبيانات التسجيل'
            ], 400);
        }

        // 🔹 البحث عن محاولة التحقق النشطة
        $verification = PhoneVerification::where('phone', $request->phone)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (!$verification) {
            return response()->json([
                'status' => false,
                'message' => 'لم يتم طلب رمز تحقق لهذا الرقم'
            ], 400);
        }

        // 🔹 التحقق من صلاحية الكود (دقيقتان)
        if (Carbon::parse($verification->expires_at)->isPast()) {
            $verification->update(['status' => 'expired']);
            return response()->json([
                'status' => false,
                'message' => 'انتهت صلاحية رمز التحقق (دقيقتان). يرجى طلب رمز جديد'
            ], 400);
        }

        // 🔹 التحقق من صحة الكود
        if ($verification->code !== $request->code) {
            // زيادة عدد المحاولات الفاشلة
            $verification->incrementAttempts();
            
            // إذا وصل إلى 3 محاولات خاطئة لهذا الكود
            if ($verification->attempts >= 3) {
                $verification->update(['status' => 'blocked']);
                
                // ✅ التحقق من عدد الأكواد المرسلة لهذا الرقم
                $totalCodesCount = PhoneVerification::where('phone', $request->phone)->count();
                
                // إذا كان عدد الأكواد المرسلة أكثر من 3، نمنع إرسال المزيد
                if ($totalCodesCount >= 3) {
                    return response()->json([
                        'status' => false,
                        'message' => 'تم حظر هاتفك يرجى التواصل مع فريق الدعم',
                        'data' => [
                            'is_blocked' => true,
                            'reason' => 'تجاوز عدد محاولات إعادة إرسال رمز التحقق'
                        ]
                    ], 403);
                }
                
                return response()->json([
                    'status' => false,
                    'message' => 'لقد تجاوزت عدد المحاولات المسموحة (3 مرات). يرجى طلب رمز جديد'
                ], 429);
            }

            // المحاولات المتبقية
            $attemptsLeft = 3 - $verification->attempts;

            return response()->json([
                'status' => false,
                'message' => 'رمز التحقق غير صحيح',
                'data' => [
                    'attempts_remaining' => $attemptsLeft,
                    'expires_in' => now()->diffInSeconds($verification->expires_at)
                ]
            ], 400);
        }

        // 🔹 كود صحيح - تحديث حالة التحقق
        $verification->update([
            'status' => 'verified',
            'verified_at' => now()
        ]);

        // 🔹 تحديث الكاش بأن الهاتف موثق
        $tempData['phone_verified'] = true;
        cache()->put('registration_' . $request->temp_token, $tempData, now()->addMinutes(30));

        return response()->json([
            'status' => true,
            'message' => 'تم التحقق من رقم الجوال بنجاح',
            'data' => [
                'temp_token' => $request->temp_token,
                'next_step' => 2
            ]
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'حدث خطأ أثناء التحقق',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * المرحلة 3: رفع المستندات وبيانات الهوية
     */
    public function registerStep2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'temp_token' => 'required|string',
            'id_card_type' => 'required|in:هوية_وطنية,جواز_سفر,بطاقة_عائلية',
            'id_number' => 'required|string|unique:users,id_number',
            'issue_date' => 'required|date',
            'expiry_date' => 'required|date|after:issue_date',
            'place_of_issue' => 'required|string|max:255',
            'front_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'back_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'selfie_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ], [
            'temp_token.required' => 'رمز الجلسة مطلوب',
            'id_card_type.required' => 'نوع الهوية مطلوب',
            'id_card_type.in' => 'نوع الهوية غير صحيح',
            'id_number.required' => 'رقم الهوية مطلوب',
            'id_number.unique' => 'رقم الهوية مستخدم بالفعل',
            'issue_date.required' => 'تاريخ الإصدار مطلوب',
            'issue_date.date' => 'تاريخ الإصدار يجب أن يكون تاريخاً صحيحاً',
            'expiry_date.required' => 'تاريخ الانتهاء مطلوب',
            'expiry_date.date' => 'تاريخ الانتهاء يجب أن يكون تاريخاً صحيحاً',
            'expiry_date.after' => 'تاريخ الانتهاء يجب أن يكون بعد تاريخ الإصدار',
            'place_of_issue.required' => 'مكان الإصدار مطلوب',
            'front_image.required' => 'صورة وجه البطاقة مطلوبة',
            'front_image.image' => 'الملف يجب أن يكون صورة',
            'front_image.mimes' => 'الصورة يجب أن تكون من نوع jpeg, png, jpg',
            'front_image.max' => 'حجم الصورة لا يجب أن يتجاوز 2 ميجابايت',
            'back_image.required' => 'صورة ظهر البطاقة مطلوبة',
            'back_image.image' => 'الملف يجب أن يكون صورة',
            'back_image.mimes' => 'الصورة يجب أن تكون من نوع jpeg, png, jpg',
            'back_image.max' => 'حجم الصورة لا يجب أن يتجاوز 2 ميجابايت',
            'selfie_image.required' => 'صورة الشخص مع البطاقة مطلوبة',
            'selfie_image.image' => 'الملف يجب أن يكون صورة',
            'selfie_image.mimes' => 'الصورة يجب أن تكون من نوع jpeg, png, jpg',
            'selfie_image.max' => 'حجم الصورة لا يجب أن يتجاوز 2 ميجابايت',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tempData = cache()->get('registration_' . $request->temp_token);
            if (!$tempData || !isset($tempData['phone_verified']) || !$tempData['phone_verified']) {
                return response()->json([
                    'status' => false,
                    'message' => 'انتهت صلاحية الجلسة أو لم يتم التحقق من رقم الجوال'
                ], 400);
            }

            // رفع الصور
            $frontImagePath = $request->file('front_image')->store('documents/' . $request->id_number, 'public');
            $backImagePath = $request->file('back_image')->store('documents/' . $request->id_number, 'public');
            $selfieImagePath = $request->file('selfie_image')->store('documents/' . $request->id_number, 'public');

            // إضافة بيانات الهوية إلى البيانات المؤقتة
            $tempData['id_card_type'] = $request->id_card_type;
            $tempData['id_number'] = $request->id_number;
            $tempData['issue_date'] = $request->issue_date;
            $tempData['expiry_date'] = $request->expiry_date;
            $tempData['place_of_issue'] = $request->place_of_issue;
            $tempData['front_image'] = $frontImagePath;
            $tempData['back_image'] = $backImagePath;
            $tempData['selfie_image'] = $selfieImagePath;
            $tempData['step2_completed'] = true;

            cache()->put('registration_' . $request->temp_token, $tempData, now()->addMinutes(30));

            return response()->json([
                'status' => true,
                'message' => 'تم رفع المستندات بنجاح',
                'data' => [
                    'temp_token' => $request->temp_token,
                    'next_step' => 3 // الانتقال إلى خطوة بيانات المحل
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء رفع المستندات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * المرحلة 4: بيانات المحل
     */
    public function registerStep3(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'temp_token' => 'required|string',
            'store_type' => 'required|in:تاجر,بقالة',
            'store_name' => 'required|string|max:255',
            'address' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'area_id' => 'required|exists:areas,id',
            'owner_type' => 'required|in:فرد,شركة',
        ], [
            'temp_token.required' => 'رمز الجلسة مطلوب',
            'store_type.required' => 'نوع المحل مطلوب',
            'store_type.in' => 'نوع المحل يجب أن يكون تاجر أو بقالة',
            'store_name.required' => 'اسم المحل مطلوب',
            'address.required' => 'عنوان المحل مطلوب',
            'latitude.required' => 'خط العرض مطلوب',
            'latitude.numeric' => 'خط العرض يجب أن يكون رقماً',
            'latitude.between' => 'خط العرض يجب أن يكون بين -90 و 90',
            'longitude.required' => 'خط الطول مطلوب',
            'longitude.numeric' => 'خط الطول يجب أن يكون رقماً',
            'longitude.between' => 'خط الطول يجب أن يكون بين -180 و 180',
            'area_id.required' => 'المنطقة مطلوبة',
            'area_id.exists' => 'المنطقة غير موجودة',
            'owner_type.required' => 'نوع المالك مطلوب',
            'owner_type.in' => 'نوع المالك يجب أن يكون فرد أو شركة',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tempData = cache()->get('registration_' . $request->temp_token);
            if (!$tempData || !isset($tempData['step2_completed']) || !$tempData['step2_completed']) {
                return response()->json([
                    'status' => false,
                    'message' => 'انتهت صلاحية الجلسة أو لم تكتمل الخطوات السابقة'
                ], 400);
            }

            // إضافة بيانات المحل
            $tempData['store_type'] = $request->store_type;
            $tempData['store_name'] = $request->store_name;
            $tempData['address'] = $request->address;
            $tempData['latitude'] = $request->latitude;
            $tempData['longitude'] = $request->longitude;
            $tempData['area_id'] = $request->area_id;
            $tempData['owner_type'] = $request->owner_type;
            $tempData['step3_completed'] = true;

            cache()->put('registration_' . $request->temp_token, $tempData, now()->addMinutes(30));

            return response()->json([
                'status' => true,
                'message' => 'تم حفظ بيانات المحل بنجاح',
                'data' => [
                    'temp_token' => $request->temp_token,
                    'next_step' => 4 // الانتقال إلى خطوة إنشاء كلمة المرور
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء حفظ بيانات المحل',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * المرحلة 5: إنشاء كلمة المرور وإتمام التسجيل
     */
    public function registerStep4(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'temp_token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
        ], [
            'temp_token.required' => 'رمز الجلسة مطلوب',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
            'password_confirmation.required' => 'تأكيد كلمة المرور مطلوب',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tempData = cache()->get('registration_' . $request->temp_token);
            if (!$tempData || !isset($tempData['step3_completed']) || !$tempData['step3_completed']) {
                return response()->json([
                    'status' => false,
                    'message' => 'انتهت صلاحية الجلسة أو لم تكتمل الخطوات السابقة'
                ], 400);
            }

            DB::beginTransaction();

            // إنشاء المستخدم
            $user = User::create([
                'first_name' => $tempData['first_name'],
                'father_name' => $tempData['father_name'],
                'grandfather_name' => $tempData['grandfather_name'],
                'last_name' => $tempData['last_name'],
                'gender' => $tempData['gender'],
                'birth_date' => $tempData['birth_date'],
                'nationality' => $tempData['nationality'],
                'phone' => $tempData['phone'],
                'phone_verified_at' => Carbon::now(),
                'id_card_type' => $tempData['id_card_type'],
                'id_number' => $tempData['id_number'],
                'issue_date' => $tempData['issue_date'],
                'expiry_date' => $tempData['expiry_date'],
                'place_of_issue' => $tempData['place_of_issue'],
                'role' => 'مالك_محل',
                'owner_type' => $tempData['owner_type'],
                'area_id' => $tempData['area_id'],
                'password' => Hash::make($request->password),
                 'address' => $tempData['address'] ?? '',
                'registration_status' => 'بانتظار_الوثائق',
                'is_active' => false,
            ]);

            // إنشاء المحل
            $store = Store::create([
                'user_id' => $user->id,
                'store_type' => $tempData['store_type'],
                'store_name' => $tempData['store_name'],
                'latitude' => $tempData['latitude'],
                'longitude' => $tempData['longitude'],
                'address' => $tempData['address'],
                'area_id' => $tempData['area_id'],
                'is_approved' => false,
            ]);

            // حفظ المستندات
            UserUploadedDocument::create([
                'user_id' => $user->id,
                'document_type' => 'البطاقة الشخصية (وجه)',
                'document_number' => $tempData['id_number'],
                'document_image_url' => $tempData['front_image'],
                'verification_status' => 'بانتظار',
            ]);

            UserUploadedDocument::create([
                'user_id' => $user->id,
                'document_type' => 'البطاقة الشخصية (ظهر)',
                'document_number' => $tempData['id_number'],
                'document_image_url' => $tempData['back_image'],
                'verification_status' => 'بانتظار',
            ]);

            UserUploadedDocument::create([
                'user_id' => $user->id,
                'document_type' => 'صورة شخصية مع البطاقة',
                'document_number' => $tempData['id_number'],
                'document_image_url' => $tempData['selfie_image'],
                'verification_status' => 'بانتظار',
            ]);

            // حذف البيانات المؤقتة
            cache()->forget('registration_' . $request->temp_token);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم إنشاء الحساب بنجاح، في انتظار مراجعة المستندات والموافقة',
                'data' => [
                    'user_id' => $user->id,
                    'phone' => $user->phone,
                    'registration_status' => $user->registration_status,
                    'message' => 'سيتم إشعارك عند الموافقة على الحساب'
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء إنشاء الحساب',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * الحصول على قائمة المناطق للتسجيل
     */
    public function getAreas()
    {
        try {
            $areas = Area::select('id', 'name')->get();
            
            return response()->json([
                'status' => true,
                'data' => $areas
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء جلب المناطق'
            ], 500);
        }
    }



    /**
 * إعادة إرسال رمز التحقق
 */
// public function resendVerificationCode(Request $request)
// {
//     $validator = Validator::make($request->all(), [
//         'phone' => 'required|string',
//         'temp_token' => 'required|string'
//     ], [
//         'phone.required' => 'رقم الجوال مطلوب',
//         'temp_token.required' => 'رمز الجلسة مطلوب'
//     ]);

//     if ($validator->fails()) {
//         return response()->json([
//             'status' => false,
//             'message' => 'خطأ في التحقق من البيانات',
//             'errors' => $validator->errors()
//         ], 422);
//     }

//     try {
//         // التحقق من وجود الجلسة
//         $tempData = cache()->get('registration_' . $request->temp_token);
//         if (!$tempData) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'انتهت صلاحية الجلسة، يرجى البدء من جديد'
//             ], 400);
//         }

//         // التحقق من أن رقم الجوال المدخل يطابق الرقم المخزن في الجلسة
//         if ($tempData['phone'] !== $request->phone) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'رقم الجوال غير متطابق مع بيانات الجلسة'
//             ], 400);
//         }

//         // التحقق من أن المستخدم لم يسبق له التحقق من الرقم
//         if (isset($tempData['phone_verified']) && $tempData['phone_verified']) {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'رقم الجوال موثق بالفعل'
//             ], 400);
//         }

//         // التحقق من عدم إعادة الإرسال بشكل متكرر (حماية من السبام)
//         $lastVerification = PhoneVerification::where('phone', $request->phone)
//             ->where('is_used', false)
//             ->where('expires_at', '>', Carbon::now())
//             ->latest()
//             ->first();

//         if ($lastVerification) {
//             // التحقق من الوقت المنقضي منذ آخر إرسال (دقيقتين مثلاً)
//             $timeSinceLastRequest = Carbon::parse($lastVerification->created_at)->diffInSeconds(Carbon::now());
//             if ($timeSinceLastRequest < 120) { // 120 ثانية = دقيقتين
//                 $remainingSeconds = 120 - $timeSinceLastRequest;
//                 return response()->json([
//                     'status' => false,
//                     'message' => 'يجب الانتظار ' . ceil($remainingSeconds / 60) . ' دقائق قبل إعادة الإرسال',
//                     'data' => [
//                         'remaining_seconds' => $remainingSeconds,
//                         'remaining_minutes' => ceil($remainingSeconds / 60)
//                     ]
//                 ], 429); // Too Many Requests
//             }
//         }

//         // إلغاء أي رموز سابقة غير مستخدمة لهذا الرقم
//         PhoneVerification::where('phone', $request->phone)
//             ->where('is_used', false)
//             ->where('expires_at', '>', Carbon::now())
//             ->update(['is_used' => true]); // أو يمكن حذفها: delete()

//         // إنشاء رمز تحقق جديد
//         $verification = PhoneVerification::createVerification(
//             $request->phone,
//             $request->ip(),
//             ['user_agent' => $request->userAgent(), 'resend' => true]
//         );

//         // هنا سيتم إرسال الرسالة (SMS)
//         // sendSMS($request->phone, $verification->code);

//         // للاختبار فقط - أرجع الرمز في الرد
//         return response()->json([
//             'status' => true,
//             'message' => 'تم إعادة إرسال رمز التحقق بنجاح',
//             'data' => [
//                 'phone' => $request->phone,
//                 'code' => $verification->code, // أزله في الإنتاج
//                 'expires_at' => $verification->expires_at,
//                 'remaining_attempts' => $this->getRemainingAttempts($request->phone)
//             ]
//         ], 200);

//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => false,
//             'message' => 'حدث خطأ أثناء إعادة إرسال رمز التحقق',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }

/**
 * دالة مساعدة للتحقق من عدد محاولات إعادة الإرسال المسموحة
 */
/**
 * نسخة محسنة مع ميزات إضافية
 */
/**
 * إعادة إرسال رمز التحقق
 */
/**
 * إعادة إرسال رمز التحقق
 */
public function resendVerificationCode(Request $request)
{
    $validator = Validator::make($request->all(), [
        'phone' => 'required|string',
        'temp_token' => 'required|string'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'خطأ في التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $tempData = cache()->get('registration_' . $request->temp_token);
        
        if (!$tempData) {
            return response()->json([
                'status' => false,
                'message' => 'انتهت صلاحية الجلسة، يرجى البدء من جديد'
            ], 400);
        }

        if ($tempData['phone'] !== $request->phone) {
            return response()->json([
                'status' => false,
                'message' => 'رقم الجوال غير متطابق مع بيانات الجلسة'
            ], 400);
        }

        if (isset($tempData['phone_verified']) && $tempData['phone_verified']) {
            return response()->json([
                'status' => false,
                'message' => 'رقم الجوال موثق بالفعل'
            ], 400);
        }

        // التحقق من وجود رمز سابق وتحديد وقت الانتظار المتبقي
        $lastVerification = PhoneVerification::where('phone', $request->phone)
            ->where('status', 'pending')
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if ($lastVerification) {
            $timeSinceLastRequest = Carbon::parse($lastVerification->created_at)->diffInSeconds(Carbon::now());
            
            // إذا كان الوقت منذ آخر محاولة أقل من دقيقة
            if ($timeSinceLastRequest < 60) {
                $remainingSeconds = 60 - $timeSinceLastRequest;
                return response()->json([
                    'status' => false,
                    'message' => 'يرجى الانتظار دقيقة قبل طلب رمز جديد',
                    'data' => [
                        'remaining_seconds' => $remainingSeconds
                    ]
                ], 429);
            }
        }

        // حساب عدد محاولات إعادة الإرسال
        $resendCount = PhoneVerification::where('phone', $request->phone)->count();

        // إذا وصل إلى 3 محاولات سابقة، نمنع إعادة الإرسال
        if ($resendCount >= 3) {
            return response()->json([
                'status' => false,
                'message' => 'لقد استنفذت عدد محاولات إعادة الإرسال (3 مرات). يرجى التواصل مع فريق الدعم'
            ], 429);
        }

        // إنشاء رمز جديد
        $verification = PhoneVerification::createVerification(
            $request->phone,
            $request->ip(),
            [
                'user_agent' => $request->userAgent(),
                'resend' => true
            ]
        );

        // إرسال SMS
        // sendSMS($request->phone, $verification->code);

        return response()->json([
            'status' => true,
            'message' => 'تم إعادة إرسال رمز التحقق بنجاح',
            'data' => [
                'phone' => $request->phone,
                'code' => $verification->code, // أزله في الإنتاج
                'expires_in' => 120,
                'attempts_remaining' => 3,
                'resend_attempts_left' => 3 - ($resendCount + 1)
            ]
        ], 200);

    } catch (\Exception $e) {
        if ($e->getMessage() === 'لقد استنفذت عدد محاولات إعادة الإرسال (3 مرات). يرجى التواصل مع فريق الدعم') {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 429);
        }

        Log::error('Error resending verification code: ' . $e->getMessage(), [
            'phone' => $request->phone,
            'temp_token' => $request->temp_token
        ]);

        return response()->json([
            'status' => false,
            'message' => 'حدث خطأ أثناء إرسال رمز التحقق'
        ], 500);
    }
}
/**
 * دالة للتحقق من عدد المحاولات (بدون is_used)
 */

}