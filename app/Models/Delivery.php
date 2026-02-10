<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'assigned_to',
        'delivery_man_phone',
        'status',
        'picked_up_at',
        'delivered_at',
    ];

    protected $casts = [
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // العلاقات
    
    /**
     * الطلب المرتبط بالتوصيل
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * مندوب التوصيل المكلف
     */
    public function deliveryMan()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}