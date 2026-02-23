<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:6',
            'device_id' => 'required|string',
            'device_name' => 'nullable|string|max:255',
            'fcm_token' => 'nullable|string'
        ];
    }

    public function messages()
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
            'device_id.required' => 'معرف الجهاز مطلوب'
        ];
    }
}