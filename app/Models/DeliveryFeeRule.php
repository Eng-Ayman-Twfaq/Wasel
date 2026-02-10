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

    protected $casts = [
        'base_fee' => 'decimal:2',
        'per_km_fee' => 'decimal:2',
        'min_distance_km' => 'decimal:2',
        'max_distance_km' => 'decimal:2',
    ];

    // العلاقات
    
    /**
     * منطقة الانطلاق
     */
    public function fromArea()
    {
        return $this->belongsTo(Area::class, 'from_area_id');
    }

    /**
     * منطقة الوصول
     */
    public function toArea()
    {
        return $this->belongsTo(Area::class, 'to_area_id');
    }

    /**
     * حساب رسوم التوصيل بناءً على المسافة
     */
    public function calculateFee($distance)
    {
        if ($distance < $this->min_distance_km) {
            $distance = $this->min_distance_km;
        }
        
        if ($distance > $this->max_distance_km) {
            $distance = $this->max_distance_km;
        }
        
        return $this->base_fee + ($distance * $this->per_km_fee);
    }
}