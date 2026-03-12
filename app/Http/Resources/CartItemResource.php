<?php
// app/Http/Resources/CartItemResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $product = $this->product;
        $store   = $product->store ?? null;

        // السعر الفعلي (بعد الخصم إن وُجد)
        $unitPrice = $product->discounted_price ?? $product->price;
        $subtotal  = round($unitPrice * $this->quantity, 2);

        return [
            'id'       => $this->id,
            'quantity' => (float) $this->quantity,
            'product'  => [
                'id'                  => $product->id,
                'name'                => $product->name,
                'price'               => (float) $product->price,
                'discounted_price'    => $product->discounted_price
                                            ? (float) $product->discounted_price
                                            : null,
                'unit_price'          => (float) $unitPrice,
                'unit_type'           => $product->unit_type,
                'pieces_per_unit'     => $product->pieces_per_unit,
                'allow_partial_unit'  => (bool) $product->allow_partial_unit,
                'min_order_quantity'  => (float) $product->min_order_quantity,
                'quantity_in_stock'   => $product->quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
                'image_url'           => $product->image_url,
                'is_available'        => (bool) $product->is_available,
            ],
            'subtotal' => $subtotal,
            'store'    => $store ? [
                'id'         => $store->id,
                'store_name' => $store->store_name,
                'latitude'   => $store->latitude  ? (float) $store->latitude  : null,
                'longitude'  => $store->longitude ? (float) $store->longitude : null,
                'address'    => $store->address,
            ] : null,
        ];
    }
}