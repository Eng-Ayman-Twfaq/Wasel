<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'area_id',
    ];

    // العلاقات
    
    /**
     * عضو فريق الدعم (المستخدم)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * المنطقة المسؤول عنها
     */
    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * الطلبات التي أشرف عليها فريق الدعم
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}