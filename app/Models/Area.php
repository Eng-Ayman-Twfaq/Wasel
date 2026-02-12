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
        'polygon_coordinates' => 'array',
    ];

    // ========== العلاقات ==========

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function supportTeams()
    {
        return $this->hasMany(SupportTeam::class);
    }

    public function deliveryFeeRulesFrom()
    {
        return $this->hasMany(DeliveryFeeRule::class, 'from_area_id');
    }

    public function deliveryFeeRulesTo()
    {
        return $this->hasMany(DeliveryFeeRule::class, 'to_area_id');
    }
}