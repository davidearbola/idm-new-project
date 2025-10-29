<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PreventivoAccessCode extends Model
{
    protected $table = 'preventivi_access_codes';

    protected $fillable = [
        'email',
        'otp_code',
        'expires_at',
        'attempts',
        'blocked_until',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'blocked_until' => 'datetime',
    ];

    /**
     * Genera un nuovo codice OTP di 6 cifre
     */
    public static function generateOtp(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica se il codice è scaduto
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Verifica se l'email è bloccata
     */
    public function isBlocked(): bool
    {
        return $this->blocked_until !== null && $this->blocked_until->isFuture();
    }

    /**
     * Incrementa i tentativi e blocca se necessario
     */
    public function incrementAttempts(): void
    {
        $this->attempts++;

        // Blocca per 1 ora dopo 5 tentativi
        if ($this->attempts >= 5) {
            $this->blocked_until = now()->addHour();
        }

        $this->save();
    }

    /**
     * Scope per trovare codici validi (non scaduti e non bloccati)
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now())
                     ->where(function ($q) {
                         $q->whereNull('blocked_until')
                           ->orWhere('blocked_until', '<', now());
                     });
    }

    /**
     * Scope per trovare un codice specifico per email
     */
    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', $email);
    }
}
