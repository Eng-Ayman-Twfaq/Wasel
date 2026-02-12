<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'store_type',
        'commercial_info',
        'store_name',
        'latitude',
        'longitude',
        'address',
        'area_id',
        'is_approved',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'commercial_info' => 'array',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    // ========== العلاقات ==========

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function invoicesSent()
    {
        return $this->hasMany(Invoice::class, 'store_id');
    }

    public function invoicesReceived()
    {
        return $this->hasMany(Invoice::class, 'customer_store_id');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function promotions()
    {
        return $this->hasMany(Promotion::class);
    }

    // ========== طرق المساعدة ==========

    public function isMerchant()
    {
        return $this->store_type === 'تاجر';
    }

    public function isGrocery()
    {
        return $this->store_type === 'بقالة';
    }

    public function isActive()
    {
        return $this->is_approved && !$this->trashed();
    }
}