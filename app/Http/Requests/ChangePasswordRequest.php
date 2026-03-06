<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password'     => [
                'required',
                'string',
                'min:5',
                'confirmed',                    // يتطلب new_password_confirmation
                'different:current_password',   // يجب أن تختلف عن الحالية
            ],
            'new_password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required'          => 'كلمة المرور الحالية مطلوبة',
            'new_password.required'              => 'كلمة المرور الجديدة مطلوبة',
            'new_password.min'                   => 'كلمة المرور يجب أن تكون 5 أحرف على الأقل',
            'new_password.confirmed'             => 'تأكيد كلمة المرور غير متطابق',
            'new_password.different'             => 'كلمة المرور الجديدة يجب أن تختلف عن الحالية',
            'new_password_confirmation.required' => 'تأكيد كلمة المرور مطلوب',
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