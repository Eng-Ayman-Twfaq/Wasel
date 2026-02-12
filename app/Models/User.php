<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name', 'father_name', 'grandfather_name', 'last_name',
        'gender', 'birth_date', 'nationality', 'phone', 'email', 'password',
        'address', 'id_card_type', 'id_number', 'issue_date', 'expiry_date',
        'place_of_issue', 'location_latitude', 'location_longitude',
        'role', 'owner_type', 'area_id', 'device_id',
        'registration_status', 'is_active'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'birth_date' => 'date',
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->father_name} {$this->grandfather_name} {$this->last_name}");
    }

    // ========== العلاقات ==========

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function uploadedDocuments()
    {
        return $this->hasMany(UserUploadedDocument::class);
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    public function supportTeam()
    {
        return $this->hasOne(SupportTeam::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orders()
    {
        return $this->hasManyThrough(Order::class, Store::class, 'user_id', 'store_id');
    }

    public function reviewsGiven()
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function reviewsReceived()
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function searchHistories()
    {
        return $this->hasMany(SearchHistory::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'assigned_to');
    }

    // ========== طرق المساعدة ==========

    public function isAdmin()
    {
        return $this->role === 'مدير';
    }

    public function isSupport()
    {
        return $this->role === 'دعم';
    }

    public function isStoreOwner()
    {
        return $this->role === 'مالك_محل';
    }

    public function isApproved()
    {
        return $this->registration_status === 'موافق' && $this->is_active;
    }

    public function isCurrentDeviceApproved($deviceId)
    {
        return $this->devices()
            ->where('device_id', $deviceId)
            ->where('is_approved', true)
            ->exists();
    }

    public function requestDeviceApproval($deviceId, $deviceName = null)
    {
        return $this->devices()->updateOrCreate(
            ['device_id' => $deviceId],
            [
                'device_name' => $deviceName,
                'is_approved' => false,
                'last_login_at' => now(),
            ]
        );
    }
}