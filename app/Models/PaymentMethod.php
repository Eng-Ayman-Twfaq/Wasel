<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // العلاقات
    
    /**
     * الطلبات التي استخدمت طريقة الدفع هذه
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * المعاملات المالية التي استخدمت طريقة الدفع هذه
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}