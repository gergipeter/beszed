<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'stripe_id',
        'stripe_status',
        'stripe_price_id',
        'quantity',
        'trial_ends_at',
        'ends_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'user_id', 'user_id');
    }

    public function isActive(): bool
    {
        return $this->stripe_status === 'active' &&
               ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function isCancelled(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function getPlan(): string
    {
        return $this->user->subscription_plan ?? 'free';
    }
}
