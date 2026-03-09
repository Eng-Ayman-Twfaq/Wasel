<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IdentityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // الحصول على حالة المستخدم الشاملة
        $overallStatus = $this->getOverallVerificationStatus();
        
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
            'verification_status' => $overallStatus, // حالة التحقق الشاملة
            'verified_at'         => $this->getVerificationDate(),
            'images' => [
                'front'  => $this->getDocumentData('البطاقة الشخصية (وجه)'),
                'back'   => $this->getDocumentData('البطاقة الشخصية (ظهر)'),
                'selfie' => $this->getDocumentData('صورة شخصية مع البطاقة'),
            ],
        ];
    }

    /**
     * الحصول على بيانات المستند كاملة (الرابط والحالة)
     */
    private function getDocumentData($type)
    {
        $document = $this->uploadedDocuments
            ->where('document_type', $type)
            ->first();

        if (!$document) {
            return [
                'url' => null,
                'status' => null,
                'rejection_reason' => null,
            ];
        }

        return [
            'url' => $document->document_image_url 
                ? asset('storage/' . $document->document_image_url) 
                : null,
            'status' => $document->verification_status, // 'بانتظار', 'موافق', 'مرفوض'
            'rejection_reason' => $document->rejection_reason,
            'verified_at' => $document->verified_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * تحديد حالة التحقق الشاملة للمستخدم
     */
    private function getOverallVerificationStatus()
    {
        $documents = $this->uploadedDocuments;
        
        if ($documents->isEmpty()) {
            return 'بانتظار_الوثائق';
        }

        // إذا كان أي مستند مرفوض
        if ($documents->contains('verification_status', 'مرفوض')) {
            return 'مرفوض';
        }

        // إذا كانت جميع المستندات مقبولة
        if ($documents->every(fn($doc) => $doc->verification_status === 'موافق')) {
            return 'موافق';
        }

        // إذا كان هناك أي مستند بانتظار
        if ($documents->contains('verification_status', 'بانتظار')) {
            return 'قيد_المراجعة';
        }

        return 'قيد_المراجعة';
    }

    /**
     * الحصول على تاريخ التحقق (أحدث تاريخ)
     */
    private function getVerificationDate()
    {
        $dates = $this->uploadedDocuments
            ->pluck('verified_at')
            ->filter()
            ->map(fn($date) => \Carbon\Carbon::parse($date))
            ->sort()
            ->last();

        return $dates?->format('Y-m-d');
    }
}