<?php
// app/Http/Resources/DeviceResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'device_id' => $this->device_id,
            'device_name' => $this->device_name,
            'is_approved' => $this->is_approved,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at ? $this->approved_at->toIso8601String() : null,
            'last_login_at' => $this->last_login_at ? $this->last_login_at->toIso8601String() : null,
        ];
    }
}