<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Carbon\Carbon;

class UpdateIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        
        return [
            // ── الحقول النصية — جميعها اختيارية ──
            'id_card_type' => [
                'sometimes',
                'string',
                'in:هوية_وطنية,جواز_سفر,بطاقة_عائلية'
            ],
            
            'id_number' => [
                'sometimes',
                'string',
                'max:30',
                // التحقق من uniqueness مع تجاهل المستخدم الحالي
                'unique:users,id_number,' . ($user ? $user->id : 'null') . ',id,deleted_at,NULL'
            ],
            
            'issue_date' => [
                'sometimes',
                'string',
                'date_format:Y/m/d',
                function ($attribute, $value, $fail) {
                    try {
                        Carbon::createFromFormat('Y/m/d', $value);
                    } catch (\Exception $e) {
                        $fail('صيغة تاريخ الإصدار يجب أن تكون YYYY/MM/DD');
                    }
                },
            ],
            
            'expiry_date' => [
                'sometimes',
                'string',
                'date_format:Y/m/d',
                function ($attribute, $value, $fail) {
                    try {
                        Carbon::createFromFormat('Y/m/d', $value);
                    } catch (\Exception $e) {
                        $fail('صيغة تاريخ الانتهاء يجب أن تكون YYYY/MM/DD');
                    }
                },
            ],
            
            'place_of_issue' => ['sometimes', 'string', 'max:100'],

            // ── الصور — اختيارية، صور فقط، حد 5MB ──
            'front_image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'back_image'  => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'selfie_image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_card_type.in'         => 'نوع الهوية يجب أن يكون: هوية وطنية، جواز سفر، أو بطاقة عائلية',
            'id_number.unique'        => 'رقم الهوية مستخدم بالفعل',
            'id_number.max'           => 'رقم الهوية يجب ألا يتجاوز 30 حرفاً',
            'issue_date.date_format'  => 'صيغة تاريخ الإصدار يجب أن تكون YYYY/MM/DD',
            'expiry_date.date_format' => 'صيغة تاريخ الانتهاء يجب أن تكون YYYY/MM/DD',
            'front_image.image'       => 'صورة الوجه يجب أن تكون صورة',
            'front_image.mimes'       => 'صورة الوجه يجب أن تكون من نوع: jpg, jpeg, png, webp',
            'front_image.max'         => 'صورة الوجه يجب ألا تتجاوز 5MB',
            'back_image.image'        => 'صورة الظهر يجب أن تكون صورة',
            'back_image.mimes'        => 'صورة الظهر يجب أن تكون من نوع: jpg, jpeg, png, webp',
            'back_image.max'          => 'صورة الظهر يجب ألا تتجاوز 5MB',
            'selfie_image.image'      => 'صورة السيلفي يجب أن تكون صورة',
            'selfie_image.mimes'      => 'صورة السيلفي يجب أن تكون من نوع: jpg, jpeg, png, webp',
            'selfie_image.max'        => 'صورة السيلفي يجب ألا تتجاوز 5MB',
        ];
    }

    // ── التحقق الإضافي بعد القواعد الأساسية ──
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $hasText = $this->filled('id_card_type') || $this->filled('id_number')
                    || $this->filled('issue_date')   || $this->filled('expiry_date')
                    || $this->filled('place_of_issue');
            
            $hasImage = $this->hasFile('front_image')
                     || $this->hasFile('back_image')
                     || $this->hasFile('selfie_image');

            if (!$hasText && !$hasImage) {
                $validator->errors()->add('general', 'يجب إرسال بيانات أو صور للتحديث');
            }

            // التحقق من أن expiry_date بعد issue_date إذا تم إرسال كلاهما
            if ($this->filled('issue_date') && $this->filled('expiry_date')) {
                try {
                    $issueDate = Carbon::createFromFormat('Y/m/d', $this->issue_date);
                    $expiryDate = Carbon::createFromFormat('Y/m/d', $this->expiry_date);
                    
                    if ($expiryDate->lte($issueDate)) {
                        $validator->errors()->add('expiry_date', 'تاريخ الانتهاء يجب أن يكون بعد تاريخ الإصدار');
                    }
                } catch (\Exception $e) {
                    // الأخطاء تم التعامل معها في rules
                }
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