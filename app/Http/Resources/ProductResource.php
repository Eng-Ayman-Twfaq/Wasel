<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ======================
            // البيانات الأساسية
            // ======================
            'id' => $this->id,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->store_name,
            'name' => $this->name,
            'description' => $this->description ?? '',
            
            // ======================
            // الأسعار والكميات
            // ======================
            'price' => (float) $this->price,
            'quantity' => $this->quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'min_order_quantity' => (float) $this->min_order_quantity,

            // ======================
            // معلومات الوحدة
            // ======================
            'unit_type' => $this->unit_type,
            'pieces_per_unit' => $this->pieces_per_unit,
            'allow_partial_unit' => (bool) $this->allow_partial_unit,

            // ======================
            // الحالة
            // ======================
            'is_available' => (bool) $this->is_available,

            // ======================
            // القيم المحسوبة (من الموديل)
            // ======================
            'available_units' => $this->available_units,
            'discounted_price' => (float) $this->discounted_price,
            'is_low_stock' => $this->is_low_stock,

            // ======================
            // التواريخ
            // ======================
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i'),
        ];
    }
}