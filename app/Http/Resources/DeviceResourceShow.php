<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResourceShow extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'device_id'     => $this->device_id,
            'device_name'   => $this->device_name ?? 'جهاز غير معروف',
            'is_approved'   => $this->is_approved,
            'status_label'  => $this->is_approved ? 'موثوق' : 'قيد المراجعة',
            'approved_at'   => $this->approved_at?->format('Y-m-d'),
            'last_login_at' => $this->last_login_at
                ? $this->last_login_at->diffForHumans()
                : 'لم يتصل بعد',
            'last_login_raw' => $this->last_login_at?->format('Y-m-d H:i'),
            'created_at'    => $this->created_at->format('Y-m-d'),
        ];
    }
}