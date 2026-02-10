<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    // العلاقات
    
    /**
     * المنتج الذي ينطبق عليه العرض
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * تحديد إذا كان العرض نشطاً حالياً
     */
    public function getIsCurrentlyActiveAttribute()
    {
        return $this->is_active && 
               now()->between($this->start_date, $this->end_date);
    }
}