<?php
// app/Http/Requests/AddToCartRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity'   => ['required', 'numeric', 'min:0.1'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'يرجى تحديد المنتج',
            'product_id.exists'   => 'المنتج غير موجود',
            'quantity.required'   => 'يرجى تحديد الكمية',
            'quantity.min'        => 'الكمية يجب أن تكون أكبر من صفر',
        ];
    }
}