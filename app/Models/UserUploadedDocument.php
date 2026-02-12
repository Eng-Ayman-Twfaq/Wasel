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

    // ========== العلاقات ==========

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function requiredDocument()
    {
        return $this->belongsTo(RequiredDocument::class, 'document_type', 'document_type');
    }

    // ========== طرق المساعدة ==========

    public function getDocumentNameAttribute()
    {
        return $this->requiredDocument?->document_name ?? $this->document_type;
    }

    public function isApproved()
    {
        return $this->verification_status === 'موافق';
    }

    public function isRejected()
    {
        return $this->verification_status === 'مرفوض';
    }

    public function isPending()
    {
        return $this->verification_status === 'بانتظار';
    }
}