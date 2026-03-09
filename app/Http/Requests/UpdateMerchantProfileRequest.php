<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class UpdateMerchantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = Auth::id();

        return [
            // ── بيانات المستخدم ──
            // 'first_name'       => ['sometimes', 'string', 'max:50'],
            // 'father_name'      => ['sometimes', 'string', 'max:50'],
            // 'grandfather_name' => ['sometimes', 'string', 'max:50'],
            // 'last_name'        => ['sometimes', 'string', 'max:50'],

            // رقم الهاتف فريد ما عدا المستخدم الحالي
            // 'phone' => [
            //     'sometimes',
            //     'string',
            //     'regex:/^[0-9]{9,15}$/',
            //     "unique:users,phone,{$userId}",
            // ],

            // ── بيانات المتجر ──
            'store_name' => ['sometimes', 'string', 'max:100'],
            'address'    => ['sometimes', 'string', 'max:255'],
            'latitude'   => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude'  => ['sometimes', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            // 'first_name.max'       => 'الاسم الأول يجب ألا يتجاوز 50 حرفاً',
            // 'father_name.max'      => 'اسم الأب يجب ألا يتجاوز 50 حرفاً',
            // 'grandfather_name.max' => 'اسم الجد يجب ألا يتجاوز 50 حرفاً',
            // 'last_name.max'        => 'اسم العائلة يجب ألا يتجاوز 50 حرفاً',

            // 'phone.regex'  => 'رقم الهاتف يجب أن يحتوي على أرقام فقط (9-15 رقماً)',
            // 'phone.unique' => 'رقم الهاتف مستخدم بالفعل',

            'store_name.max' => 'اسم المتجر يجب ألا يتجاوز 100 حرف',
            'address.max'    => 'العنوان يجب ألا يتجاوز 255 حرفاً',

            'latitude.between'  => 'خط العرض غير صحيح',
            'longitude.between' => 'خط الطول غير صحيح',
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