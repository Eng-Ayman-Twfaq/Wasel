<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderMerchantApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'merchant_store_id',
        'approved_by',
        'status',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    // ========== العلاقات ==========

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function merchantStore()
    {
        return $this->belongsTo(Store::class, 'merchant_store_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ========== طرق المساعدة ==========

    public function isPending(): bool
    {
        return $this->status === 'بانتظار';
    }

    public function isApproved(): bool
    {
        return $this->status === 'موافق';
    }

    public function isRejected(): bool
    {
        return $this->status === 'مرفوض';
    }
}