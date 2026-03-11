<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->discounted_price,
            'quantity' => $this->quantity,
            'original_price' => (float) $this->price,
            'unit_type' => $this->unit_type,
            'min_order_quantity' => $this->min_order_quantity,
            'is_low_stock' => $this->is_low_stock,
            'store' => [
                'id' => $this->store->id,
                'name' => $this->store->store_name,
                'latitude' => $this->store->latitude,
                'longitude' => $this->store->longitude,
                'distance' => isset($this->store->distance)
                    ? round($this->store->distance, 2)
                    : null
            ]
        ];
    }
}