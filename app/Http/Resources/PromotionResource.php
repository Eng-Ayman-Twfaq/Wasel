<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => (int) $this->id,
            'title'       => (string) $this->title,
            'description' => $this->description,
            'image_url'   => $this->getFullImageUrl(),
            'position'    => $this->position,
            'start_date'  => $this->start_date?->format('Y-m-d'),
            'end_date'    => $this->end_date?->format('Y-m-d'),
        ];
    }

    private function getFullImageUrl(): ?string
    {
        if (!$this->image_url) {
            return null;
        }

        // استخدم رابط مشروع الواجهات (حيث الصور مخزنة)
        $frontendUrl = env('API_URL_promotions'); // غير هذا إلى IP مشروع الواجهات
        
        return $frontendUrl . '/storage/' . $this->image_url;
    }
}