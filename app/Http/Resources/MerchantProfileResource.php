<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $store = $this->store;

        return [
            // ── بيانات المستخدم ──
            'user' => [
                'id'         => $this->id,
                'full_name'  => $this->full_name,
                'first_name' => $this->first_name,
                'father_name'      => $this->father_name,
                'grandfather_name' => $this->grandfather_name,
                'last_name'  => $this->last_name,
                'phone'      => $this->phone,
                'email'      => $this->email,
                'role'       => $this->role,
                'is_active'  => $this->is_active,
                'registration_status' => $this->registration_status,
                'created_at' => $this->created_at?->format('Y-m-d'),
            ],

            // ── بيانات المتجر ──
            'store' => $store ? [
                'id'          => $store->id,
                'store_name'  => $store->store_name,
                'store_type'  => $store->store_type,
                'address'     => $store->address,
                'latitude'    => $store->latitude,
                'longitude'   => $store->longitude,
                'is_approved' => $store->is_approved,
                'approved_at' => $store->approved_at?->format('Y-m-d'),
                'area'        => $store->relationLoaded('area') && $store->area ? [
                    'id'   => $store->area->id,
                    'name' => $store->area->name,
                ] : null,
            ] : null,
        ];
    }
}