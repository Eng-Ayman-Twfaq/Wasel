<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryFeeRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_area_id',
        'to_area_id',
        'base_fee',
        'per_km_fee',
        'min_distance_km',
        'max_distance_km',
    ];

    // ========== العلاقات ==========

    public function fromArea()
    {
        return $this->belongsTo(Area::class, 'from_area_id');
    }

    public function toArea()
    {
        return $this->belongsTo(Area::class, 'to_area_id');
    }

    // ========== طرق المساعدة ==========

    public function calculateFee($distance)
    {
        $distance = max($distance, $this->min_distance_km);
        $distance = min($distance, $this->max_distance_km);
        return $this->base_fee + ($distance * $this->per_km_fee);
    }
}