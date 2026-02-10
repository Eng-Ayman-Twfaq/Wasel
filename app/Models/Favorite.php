<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
    ];

    // العلاقات
    
    /**
     * المستخدم الذي أضاف المنتج للمفضلة
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * المنتج المضاف للمفضلة
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}