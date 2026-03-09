<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateIdentityRequest;
use App\Http\Resources\IdentityResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Exception;

class MerchantIdentityController extends Controller
{
    private function getUser()
    {
        $user = Auth::user();
        if (!$user || !$user->isStoreOwner()) {
            return null;
        }
        return $user;
    }

    // =========================================================
    // GET /api/auth/merchant/profile/identity
    // عرض بيانات الهوية والصور
    // =========================================================
    public function show()
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->forbidden();
            }

            // تحميل المستندات المرتبطة بالمستخدم
            $user->load('uploadedDocuments');

            // التحقق من وجود بيانات هوية
            if (!$user->id_number) {
                return response()->json([
                    'status'  => false,
                    'message' => 'لا توجد بيانات هوية مسجلة',
                ], 404);
            }

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب بيانات الهوية بنجاح',
                'data'    => new IdentityResource($user),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ في الخادم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =========================================================
    // PUT /api/auth/merchant/profile/identity
    // تعديل بيانات الهوية + رفع صور جديدة (اختياري)
    // =========================================================
    public function update(UpdateIdentityRequest $request)
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->forbidden();
            }

            // تحميل المستندات
            $user->load('uploadedDocuments');

            if (!$user->id_number) {
                return response()->json([
                    'status'  => false,
                    'message' => 'لا توجد بيانات هوية مسجلة',
                ], 404);
            }

            // ========== منع التعديل إذا كانت الحالة "موافق" ==========
            if ($user->registration_status === 'موافق') {
                return response()->json([
                    'status'  => false,
                    'message' => 'لا يمكن تعديل بيانات الهوية بعد الموافقة عليها. للتواصل مع الدعم الفني',
                ], 403);
            }

            // تحديث الحقول النصية في جدول users
            $userFields = [];

            if ($request->filled('id_card_type'))   $userFields['id_card_type']    = $request->id_card_type;
            if ($request->filled('id_number'))      $userFields['id_number']       = $request->id_number;
            if ($request->filled('issue_date'))     $userFields['issue_date']      = $request->issue_date;
            if ($request->filled('expiry_date'))    $userFields['expiry_date']     = $request->expiry_date;
            if ($request->filled('place_of_issue')) $userFields['place_of_issue']  = $request->place_of_issue;

            if (!empty($userFields)) {
                $userFields['registration_status'] = 'بانتظار_الوثائق'; // إعادة للمراجعة
                $user->update($userFields);
            }

            // تحديث الصور في جدول user_uploaded_documents
            $this->updateDocument($user, $request, 'front_image', 'البطاقة الشخصية (وجه)');
            $this->updateDocument($user, $request, 'back_image', 'البطاقة الشخصية (ظهر)');
            $this->updateDocument($user, $request, 'selfie_image', 'صورة شخصية مع البطاقة');

            // إعادة تحميل المستندات بعد التحديث
            $user->load('uploadedDocuments');

            return response()->json([
                'status'  => true,
                'message' => 'تم تحديث بيانات الهوية بنجاح، ستتم مراجعتها من قبل الإدارة',
                'data'    => new IdentityResource($user),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ في الخادم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * دالة مساعدة لتحديث المستندات
     */
    private function updateDocument($user, $request, $fieldName, $documentType)
    {
        if ($request->hasFile($fieldName)) {
            // البحث عن المستند القديم
            $oldDocument = $user->uploadedDocuments()
                ->where('document_type', $documentType)
                ->first();

            // حذف الملف القديم
            if ($oldDocument && $oldDocument->document_image_url) {
                Storage::disk('public')->delete($oldDocument->document_image_url);
                $oldDocument->delete();
            }

            // رفع الملف الجديد
            $path = $request->file($fieldName)->store('documents/' . $user->id, 'public');

            // إنشاء سجل جديد
            $user->uploadedDocuments()->create([
                'document_type' => $documentType,
                'document_number' => $user->id_number,
                'document_image_url' => $path,
                'verification_status' => 'بانتظار', // مطابق للـ enum في قاعدة البيانات
            ]);
        }
    }

    private function forbidden()
    {
        return response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403);
    }
}