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
        'approval_flow',
        'merchant_approval_status',
        'merchant_approved_at',
        'merchant_approved_by',
        'support_team_id',
        'support_approved_by',
        'support_approved_at',
        'customer_visible',
        'delivery_address',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'merchant_approved_at' => 'datetime',
        'support_approved_at' => 'datetime',
        'customer_visible' => 'boolean',
    ];

    // ========== العلاقات ==========

    public function grocery()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function supportTeam()
    {
        return $this->belongsTo(SupportTeam::class);
    }

    public function merchantApprover()
    {
        return $this->belongsTo(Store::class, 'merchant_approved_by');
    }

    public function supportApprover()
    {
        return $this->belongsTo(User::class, 'support_approved_by');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // ========== طرق المساعدة ==========

    public function getGrandTotalAttribute()
    {
        return $this->total_amount + $this->delivery_fee;
    }

    public function getMerchantsAttribute()
    {
        return Store::whereIn('id', $this->orderDetails()->pluck('store_id')->unique())->get();
    }

    public function setApprovalFlow()
    {
        if ($this->paymentMethod && $this->paymentMethod->name === 'دين') {
            $this->approval_flow = 'merchant';
            $this->customer_visible = false;
        } else {
            $this->approval_flow = 'support';
            $this->customer_visible = true;
        }
        $this->save();
    }

    public function approveByMerchant($merchantStoreId)
    {
        $this->merchant_approval_status = 'موافق';
        $this->merchant_approved_by = $merchantStoreId;
        $this->merchant_approved_at = now();
        $this->customer_visible = true;
        $this->save();
    }

    public function rejectByMerchant()
    {
        $this->merchant_approval_status = 'مرفوض';
        $this->customer_visible = false;
        $this->status = 'مرفوض';
        $this->save();
    }

    public function approveBySupport($supportUserId)
    {
        $this->support_approved_by = $supportUserId;
        $this->support_approved_at = now();
        $this->status = 'قيد_المعالجة';
        $this->customer_visible = true;
        $this->save();
    }
}