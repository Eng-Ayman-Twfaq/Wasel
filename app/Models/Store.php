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
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'commercial_info' => 'array',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    protected $dates = ['deleted_at'];

    // العلاقات
    
    /**
     * مالك المحل
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * المنطقة التي يقع فيها المحل
     */
    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * المستخدم الذي وافق على المحل
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * المنتجات التي يبيعها المحل (إذا كان تاجراً)
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * الطلبات التي أنشأها المحل (إذا كان بقالة)
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * الفواتير الصادرة من المحل (إذا كان تاجراً)
     */
    public function invoicesSent()
    {
        return $this->hasMany(Invoice::class, 'store_id');
    }

    /**
     * الفواتير الواردة إلى المحل (إذا كان بقالة)
     */
    public function invoicesReceived()
    {
        return $this->hasMany(Invoice::class, 'customer_store_id');
    }

    /**
     * تفاصيل الطلبات التي تحتوي على منتجات من هذا المحل
     */
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    /**
     * تحديد إذا كان المحل تاجراً
     */
    public function isMerchant()
    {
        return $this->store_type === 'تاجر';
    }

    /**
     * تحديد إذا كان المحل بقالة
     */
    public function isGrocery()
    {
        return $this->store_type === 'بقالة';
    }
}