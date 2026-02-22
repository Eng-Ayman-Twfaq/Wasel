<?php
// app/Http/Requests/RegisterDeviceRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'device_id' => 'required|string|max:255',
            'device_name' => 'required|string|max:255',
            'device_model' => 'required|string|max:255',
            'device_brand' => 'required|string|max:255',
            'os_version' => 'required|string|max:50',
            'app_version' => 'required|string|max:20',
            'fcm_token' => 'nullable|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'device_id.required' => 'معرف الجهاز مطلوب',
            'device_name.required' => 'اسم الجهاز مطلوب',
            'device_model.required' => 'طراز الجهاز مطلوب',
            'device_brand.required' => 'العلامة التجارية للجهاز مطلوبة',
            'os_version.required' => 'إصدار نظام التشغيل مطلوب',
            'app_version.required' => 'إصدار التطبيق مطلوب',
        ];
    }
}