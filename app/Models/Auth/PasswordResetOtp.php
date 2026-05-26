<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class PasswordResetOtp extends Model
{
    public $timestamps   = false;
    public $incrementing = false;
    protected $primaryKey = 'email';
    protected $keyType    = 'string';

    protected $fillable = ['email', 'otp', 'expires_at', 'used_at', 'created_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
        'created_at' => 'datetime',
    ];

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }

    public function verifyOtp(string $otp): bool
    {
        return Hash::check($otp, $this->otp);
    }

    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    // ──────────────────────────────────────────────────────────────
    // Fábrica
    // ──────────────────────────────────────────────────────────────

    /**
     * Genera y persiste un nuevo OTP para el correo dado.
     * Retorna el código en texto plano para enviarlo por correo.
     */
    public static function generate(string $email): string
    {
        $plainOtp    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiryMinutes = (int) config('auth.otp_expiry_minutes', 1440); // 24h por defecto

        static::updateOrCreate(
            ['email' => $email],
            [
                'otp'        => Hash::make($plainOtp),
                'expires_at' => now()->addMinutes($expiryMinutes),
                'used_at'    => null,
                'created_at' => now(),
            ]
        );

        return $plainOtp;
    }
}
