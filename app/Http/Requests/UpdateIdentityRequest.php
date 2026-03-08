<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── الحقول النصية — جميعها اختيارية ──
            'id_card_type'   => ['sometimes', 'string', 'in:هوية_وطنية,جواز_سفر,بطاقة_عائلية'],
            'id_number'      => ['sometimes', 'string', 'max:30'],
            'issue_date'     => ['sometimes', 'date_format:Y/m/d'],
            'expiry_date'    => ['sometimes', 'date_format:Y/m/d', 'after:issue_date'],
            'place_of_issue' => ['sometimes', 'string', 'max:100'],

            // ── الصور — اختيارية، صور فقط، حد 5MB ──
            'front_image'   => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'back_image'    => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'selfie_image'  => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_card_type.in'         => 'نوع الهوية يجب أن يكون: هوية وطنية، جواز سفر، أو بطاقة عائلية',
            'id_number.max'           => 'رقم الهوية يجب ألا يتجاوز 30 حرفاً',
            'issue_date.date_format'  => 'صيغة تاريخ الإصدار يجب أن تكون YYYY/MM/DD',
            'expiry_date.date_format' => 'صيغة تاريخ الانتهاء يجب أن تكون YYYY/MM/DD',
            'expiry_date.after'       => 'تاريخ الانتهاء يجب أن يكون بعد تاريخ الإصدار',
            'front_image.image'       => 'صورة الوجه يجب أن تكون صورة',
            'front_image.max'         => 'صورة الوجه يجب ألا تتجاوز 5MB',
            'back_image.image'        => 'صورة الظهر يجب أن تكون صورة',
            'back_image.max'          => 'صورة الظهر يجب ألا تتجاوز 5MB',
            'selfie_image.image'      => 'صورة السيلفي يجب أن تكون صورة',
            'selfie_image.max'        => 'صورة السيلفي يجب ألا تتجاوز 5MB',
        ];
    }

    // ── التأكد أنه تم إرسال شيء على الأقل ──
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $hasText  = $this->filled('id_card_type') || $this->filled('id_number')
                     || $this->filled('issue_date')   || $this->filled('expiry_date')
                     || $this->filled('place_of_issue');
            $hasImage = $this->hasFile('front_image')
                     || $this->hasFile('back_image')
                     || $this->hasFile('selfie_image');

            if (!$hasText && !$hasImage) {
                $validator->errors()->add('general', 'يجب إرسال بيانات أو صور للتحديث');
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => false,
                'message' => 'خطأ في البيانات المدخلة',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}