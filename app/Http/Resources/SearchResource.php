<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SearchResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => (int) $this->id,
            'store_name' => (string) $this->store_name,
            'address'    => $this->address,
            'latitude'   => $this->latitude  ? (float) $this->latitude  : null,
            'longitude'  => $this->longitude ? (float) $this->longitude : null,
            'distance'   => isset($this->distance)
                ? (float) $this->distance
                : null,
            'is_approved' => (bool) $this->is_approved,
        ];
    }
}