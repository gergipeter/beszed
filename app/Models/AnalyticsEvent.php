<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'category',
        'action',
        'properties',
        'session_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'json',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function track(string $eventType, ?int $userId = null, array $properties = []): self
    {
        return self::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'category' => $properties['category'] ?? null,
            'action' => $properties['action'] ?? null,
            'properties' => array_diff_key($properties, array_flip(['category', 'action'])),
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
