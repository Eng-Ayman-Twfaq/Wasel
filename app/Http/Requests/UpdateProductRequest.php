<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'name' => 'sometimes|required|string|max:255',

            'description' => 'nullable|string',

            'price' => 'sometimes|required|numeric|min:0',

            'quantity' => 'sometimes|required|integer|min:0',

            'low_stock_threshold' => 'sometimes|required|integer|min:0',

            'unit_type' => [
                'sometimes',
                'required',
                Rule::in(['وحدة', 'كرتون', 'صندوق', 'طبق', 'حبة', 'كيلو', 'لتر'])
            ],

            'pieces_per_unit' => 'sometimes|required|integer|min:1',

            'allow_partial_unit' => 'sometimes|boolean',

            'min_order_quantity' => 'sometimes|required|numeric|min:0',

            'is_available' => 'sometimes|boolean',

            'password' => 'required|string'
        ];
    }

    public function messages(): array
    {
        return [

            'name.required' => 'اسم المنتج مطلوب',
            'name.string' => 'اسم المنتج يجب أن يكون نصًا',
            'name.max' => 'اسم المنتج يجب ألا يتجاوز 255 حرفًا',

            'description.string' => 'الوصف يجب أن يكون نصًا',

            'price.required' => 'السعر مطلوب',
            'price.numeric' => 'السعر يجب أن يكون رقمًا',
            'price.min' => 'السعر لا يمكن أن يكون أقل من صفر',

            'quantity.required' => 'الكمية مطلوبة',
            'quantity.integer' => 'الكمية يجب أن تكون رقمًا صحيحًا',
            'quantity.min' => 'الكمية لا يمكن أن تكون أقل من صفر',

            'low_stock_threshold.required' => 'حد المخزون المنخفض مطلوب',
            'low_stock_threshold.integer' => 'حد المخزون يجب أن يكون رقمًا صحيحًا',
            'low_stock_threshold.min' => 'حد المخزون لا يمكن أن يكون أقل من صفر',

            'unit_type.required' => 'نوع الوحدة مطلوب',
            'unit_type.in' => 'نوع الوحدة غير صالح',

            'pieces_per_unit.required' => 'عدد القطع في الوحدة مطلوب',
            'pieces_per_unit.integer' => 'عدد القطع يجب أن يكون رقمًا صحيحًا',
            'pieces_per_unit.min' => 'عدد القطع يجب أن يكون على الأقل 1',

            'allow_partial_unit.boolean' => 'قيمة السماح بالبيع الجزئي يجب أن تكون صحيحة أو خاطئة',

            'min_order_quantity.required' => 'أقل كمية للطلب مطلوبة',
            'min_order_quantity.numeric' => 'أقل كمية للطلب يجب أن تكون رقمًا',
            'min_order_quantity.min' => 'أقل كمية للطلب لا يمكن أن تكون أقل من صفر',

            'is_available.boolean' => 'حالة التوفر يجب أن تكون صحيحة أو خاطئة',

            'password.required' => 'كلمة المرور مطلوبة للتأكيد'
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'description' => $this->description ?? '',
        ]);
    }
}