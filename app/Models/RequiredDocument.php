<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequiredDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_role',
        'document_type',
        'document_name',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    // العلاقات
    
    /**
     * الوثائق المرفوعة من هذا النوع
     * 
     * ملاحظة: العلاقة تعتمد على document_type وليس على المفتاح الأساسي
     */
    public function uploadedDocuments()
    {
        return $this->hasMany(UserUploadedDocument::class, 'document_type', 'document_type');
    }

    /**
     * الحصول على الوثائق المرفوعة لمستخدم معين
     */
    public function uploadedDocumentsForUser($userId)
    {
        return $this->uploadedDocuments()
            ->where('user_id', $userId)
            ->get();
    }

    /**
     * التحقق إذا كان المستخدم قد رفع هذه الوثيقة
     */
    public function isUploadedByUser($userId)
    {
        return $this->uploadedDocuments()
            ->where('user_id', $userId)
            ->where('verification_status', 'موافق')
            ->exists();
    }

    /**
     * الحصول على الوثائق المطلوبة لدور معين
     */
    public static function getRequiredForRole($role)
    {
        return self::where('user_role', $role)
            ->where('is_required', true)
            ->get();
    }

    /**
     * التحقق من اكتمال وثائق المستخدم
     */
    public static function checkUserDocumentsCompletion($userId, $role)
    {
        $requiredDocs = self::getRequiredForRole($role);
        
        foreach ($requiredDocs as $doc) {
            if (!$doc->isUploadedByUser($userId)) {
                return false;
            }
        }
        
        return true;
    }
}