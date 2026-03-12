<?php
// app/Http/Requests/UpdateCartItemRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0.1'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'يرجى تحديد الكمية',
            'quantity.min'      => 'الكمية يجب أن تكون أكبر من صفر',
        ];
    }
}