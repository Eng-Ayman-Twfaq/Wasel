<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class IdentityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'id_card_type'        => $this->id_card_type,
            'id_number'           => $this->id_number,
            'issue_date'          => $this->issue_date
                ? \Carbon\Carbon::parse($this->issue_date)->format('Y/m/d')
                : null,
            'expiry_date'         => $this->expiry_date
                ? \Carbon\Carbon::parse($this->expiry_date)->format('Y/m/d')
                : null,
            'place_of_issue'      => $this->place_of_issue,
            'verification_status' => $this->getVerificationStatus(), // حالة التحقق
            'verified_at'         => $this->phone_verified_at?->format('Y-m-d'),

            // روابط الصور من جدول المستندات
            'images' => [
                'front'  => $this->getDocumentUrl('البطاقة الشخصية (وجه)'),
                'back'   => $this->getDocumentUrl('البطاقة الشخصية (ظهر)'),
                'selfie' => $this->getDocumentUrl('صورة شخصية مع البطاقة'),
            ],
        ];
    }

    /**
     * الحصول على رابط الصورة
     */
    private function getDocumentUrl($type)
    {
        $document = $this->uploadedDocuments
            ->where('document_type', $type)
            ->first();

        if (!$document || !$document->document_image_url) {
            return null;
        }

        // استخدام asset بدلاً من Storage::url() لتجنب المشاكل
        return asset('storage/' . $document->document_image_url);
    }

    /**
     * تحديد حالة التحقق بناءً على المستندات
     */
    private function getVerificationStatus()
    {
        $documents = $this->uploadedDocuments;
        
        if ($documents->isEmpty()) {
            return 'pending';
        }

        // إذا كان أي مستند مرفوض
        if ($documents->contains('verification_status', 'مرفوض')) {
            return 'rejected';
        }

        // إذا كانت جميع المستندات مقبولة
        if ($documents->every(fn($doc) => $doc->verification_status === 'مقبول')) {
            return 'approved';
        }

        // وإلا فالحالة قيد المراجعة
        return 'pending';
    }
}