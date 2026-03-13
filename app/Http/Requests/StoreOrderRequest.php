<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ✅ كلمة المرور مطلوبة للتحقق من الهوية
            'password'              => ['required', 'string'],

            'payment_method_id'     => ['required', 'integer', 'exists:payment_methods,id'],
            'delivery_address'      => ['required', 'string', 'max:500'],
            'notes'                 => ['nullable', 'string', 'max:1000'],

            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'      => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required'           => 'كلمة المرور مطلوبة للتأكيد',

            'payment_method_id.required'  => 'طريقة الدفع مطلوبة',
            'payment_method_id.exists'    => 'طريقة الدفع المحددة غير موجودة',
            'delivery_address.required'   => 'عنوان التوصيل مطلوب',
            'delivery_address.max'        => 'عنوان التوصيل يجب ألا يتجاوز 500 حرف',
            'notes.max'                   => 'الملاحظات يجب ألا تتجاوز 1000 حرف',

            'items.required'              => 'يجب إضافة منتج واحد على الأقل',
            'items.min'                   => 'يجب إضافة منتج واحد على الأقل',
            'items.array'                 => 'صيغة المنتجات غير صحيحة',

            'items.*.product_id.required' => 'معرّف المنتج مطلوب',
            'items.*.product_id.exists'   => 'أحد المنتجات المحددة غير موجود',
            'items.*.quantity.required'   => 'الكمية مطلوبة لكل منتج',
            'items.*.quantity.min'        => 'الكمية يجب أن تكون أكبر من صفر',
        ];
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