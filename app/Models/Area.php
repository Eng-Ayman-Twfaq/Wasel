<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'center_latitude',
        'center_longitude',
        'polygon_coordinates',
    ];

    protected $casts = [
        'center_latitude' => 'decimal:8',
        'center_longitude' => 'decimal:8',
        'polygon_coordinates' => 'array',
    ];

    // العلاقات
    
    /**
     * المستخدمون في هذه المنطقة
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * المحلات في هذه المنطقة
     */
    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    /**
     * فريق الدعم المسؤول عن هذه المنطقة
     */
    public function supportTeams()
    {
        return $this->hasMany(SupportTeam::class);
    }

    /**
     * قواعد رسوم التوصيل من هذه المنطقة
     */
    public function deliveryFeeRulesFrom()
    {
        return $this->hasMany(DeliveryFeeRule::class, 'from_area_id');
    }

    /**
     * قواعد رسوم التوصيل إلى هذه المنطقة
     */
    public function deliveryFeeRulesTo()
    {
        return $this->hasMany(DeliveryFeeRule::class, 'to_area_id');
    }
}