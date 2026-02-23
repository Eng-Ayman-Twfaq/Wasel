<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DeviceVerificationAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'verification_code',
        'ip_address',
        'attempts',
        'max_attempts',
        'status',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending')
                     ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function isValid(): bool
    {
        return $this->status === 'pending' 
               && Carbon::parse($this->expires_at)->isFuture()
               && $this->attempts < $this->max_attempts;
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
        
        if ($this->attempts >= $this->max_attempts) {
            $this->update(['status' => 'blocked']);
        }
    }

    public static function generateCode(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * ✅ تعديل هذه الدالة - إزالة unique constraint
     */
    public static function createAttempt($userId, $deviceId, $deviceName = null, $ip = null): self
    {
        // ✅ إلغاء المحاولات السابقة المعلقة (بدون unique constraint)
        self::where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        // إنشاء محاولة جديدة
        return self::create([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'device_name' => $deviceName,
            'verification_code' => self::generateCode(),
            'ip_address' => $ip,
            'expires_at' => now()->addMinutes(5),
            'status' => 'pending'
        ]);
    }
}