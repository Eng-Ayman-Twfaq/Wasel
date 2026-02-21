<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PhoneVerification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'phone',
        'code',
        'attempts',
        'max_attempts',
        'status',
        'ip_address',
        'device_info',
        'expires_at',
        'verified_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'device_info' => 'array',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    /**
     * الحصول على المستخدم المرتبط برقم الهاتف
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'phone', 'phone');
    }

    /**
     * التحقق مما إذا كان الرمز منتهي الصلاحية
     */
    public function isExpired(): bool
    {
        return Carbon::now()->gt($this->expires_at);
    }

    /**
     * التحقق مما إذا كان يمكن إعادة المحاولة
     */
    public function canAttempt(): bool
    {
        return $this->attempts < $this->max_attempts && 
               $this->status === 'pending' && 
               !$this->isExpired();
    }

    /**
     * زيادة عدد المحاولات
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
        
        if ($this->attempts >= $this->max_attempts) {
            $this->status = 'blocked';
            $this->save();
        }
    }

    /**
     * تأكيد التحقق
     */
    public function markAsVerified(): void
    {
        $this->status = 'verified';
        $this->verified_at = Carbon::now();
        $this->save();
        
        // تحديث حقل phone_verified_at في جدول المستخدمين
        if ($this->user) {
            $this->user->update([
                'phone_verified_at' => Carbon::now()
            ]);
        }
    }

    /**
     * إنشاء رمز تحقق جديد
     */
    public static function generateCode(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * إنشاء طلب تحقق جديد
     */
    public static function createVerification(string $phone, string $ipAddress = null, array $deviceInfo = null): self
    {
        // إلغاء أي محاولات سابقة معلقة
        static::where('phone', $phone)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);
        
        return static::create([
            'phone' => $phone,
            'code' => static::generateCode(),
            'attempts' => 0,
            'max_attempts' => 5,
            'status' => 'pending',
            'ip_address' => $ipAddress,
            'device_info' => $deviceInfo,
            'expires_at' => Carbon::now()->addMinutes(5), // الرمز صالح لـ 5 دقائق
        ]);
    }

    /**
     * التحقق من الرمز
     */
    public static function verifyCode(string $phone, string $code): bool
    {
        $verification = static::where('phone', $phone)
            ->where('status', 'pending')
            ->latest()
            ->first();
        
        if (!$verification) {
            return false;
        }
        
        if (!$verification->canAttempt()) {
            return false;
        }
        
        if ($verification->code !== $code) {
            $verification->incrementAttempts();
            return false;
        }
        
        $verification->markAsVerified();
        return true;
    }

    /**
     * نطاق للطلبات المنتهية
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', Carbon::now())
            ->where('status', 'pending');
    }

    /**
     * نطاق للطلبات المعلقة
     */
    public function scopePending($query, string $phone)
    {
        return $query->where('phone', $phone)
            ->where('status', 'pending')
            ->where('expires_at', '>', Carbon::now());
    }
}