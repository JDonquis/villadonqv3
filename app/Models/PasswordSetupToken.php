<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PasswordSetupToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'token',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    protected $enumValues = ['setup', 'reset'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return is_null($this->used_at) && $this->expires_at->isFuture();
    }

    public function markAsUsed(): void
    {
        $this->used_at = now();
        $this->save();
    }

    public static function generateForUser(User $user, int $expiresInHours = 12, string $type = 'setup'): self
    {
        $token = self::create([
            'user_id' => $user->id,
            'type' => $type,
            'token' => Str::random(64),
            'expires_at' => now()->addHours($expiresInHours),
        ]);

        return $token;
    }

    public static function findValidToken(string $token): ?self
    {
        return self::where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
