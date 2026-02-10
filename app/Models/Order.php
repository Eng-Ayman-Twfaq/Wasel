<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'total_amount',
        'delivery_fee',
        'status',
        'payment_method_id',
        'payment_status',
        'support_team_id',
        'delivery_address',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
    ];

    protected $dates = ['deleted_at'];

    // العلاقات
    
    /**
     * محل البقالة الذي أنشأ الطلب
     */
    public function grocery()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * طريقة الدفع المستخدمة
     */
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * فريق الدعم المشرف على الطلب
     */
    public function supportTeam()
    {
        return $this->belongsTo(SupportTeam::class);
    }

    /**
     * تفاصيل الطلب (المنتجات)
     */
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    /**
     * الفواتير المرتبطة بالطلب
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * عملية التوصيل المرتبطة بالطلب
     */
    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    /**
     * التقييمات على الطلب
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * الحصول على المبلغ الإجمالي مع التوصيل
     */
    public function getGrandTotalAttribute()
    {
        return $this->total_amount + $this->delivery_fee;
    }

    /**
     * الحصول على قائمة التجار في الطلب
     */
    public function getMerchantsAttribute()
    {
        return $this->orderDetails()
            ->with('store')
            ->get()
            ->pluck('store')
            ->unique('id');
    }
}