<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TraderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'store_name' => $this->store_name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'products_count' => $this->products()->available()->count(),
            'distance' => isset($this->distance)
                ? round($this->distance, 2)
                : null
        ];
    }
}