<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A read-only progress link for the speech therapist; only the token's hash is kept. */
class BeszedShare extends Model
{
    protected $fillable = ['child_id', 'token_hash', 'label', 'expires_at', 'revoked_at', 'last_viewed_at', 'views'];

    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_viewed_at' => 'datetime'];

    protected $hidden = ['token_hash'];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return ! $this->revoked_at && $this->expires_at->isFuture();
    }

    /** @return array{0: self, 1: string} the share and its token (shown to the parent once) */
    public static function issue(Child $child, int $days, ?string $label): array
    {
        $token = Str::random(40);
        $share = self::create([
            'child_id' => $child->id,
            'token_hash' => self::hash($token),
            'label' => $label,
            'expires_at' => now()->addDays($days),
        ]);

        return [$share, $token];
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token_hash', self::hash($token))->first();
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
