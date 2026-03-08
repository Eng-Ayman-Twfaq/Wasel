<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'body'       => $this->body,
            'data'       => $this->data,    // array — بيانات إضافية (route, id, ...)
            'is_read'    => $this->is_read,
            'created_at' => $this->created_at->diffForHumans(), // "منذ 5 دقائق"
            'created_at_raw' => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}