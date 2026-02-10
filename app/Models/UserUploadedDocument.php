<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserUploadedDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'document_type',
        'document_number',
        'document_image_url',
        'verified_by',
        'verified_at',
        'verification_status',
        'rejection_reason',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    // العلاقات
    
    /**
     * المستخدم الذي رفع الوثيقة
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * المستخدم الذي تحقق من الوثيقة
     */
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * نوع الوثيقة المطلوبة (المرجع)
     */
    public function requiredDocument()
    {
        return $this->belongsTo(RequiredDocument::class, 'document_type', 'document_type');
    }

    /**
     * الحصول على اسم الوثيقة من الجدول المرجعي
     */
    public function getDocumentNameAttribute()
    {
        if ($this->requiredDocument) {
            return $this->requiredDocument->document_name;
        }
        
        return $this->document_type;
    }

    /**
     * التحقق إذا كانت الوثيقة مقبولة
     */
    public function getIsApprovedAttribute()
    {
        return $this->verification_status === 'موافق';
    }

    /**
     * التحقق إذا كانت الوثيقة مرفوضة
     */
    public function getIsRejectedAttribute()
    {
        return $this->verification_status === 'مرفوض';
    }

    /**
     * التحقق إذا كانت الوثيقة قيد الانتظار
     */
    public function getIsPendingAttribute()
    {
        return $this->verification_status === 'بانتظار';
    }
}