<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Skip this file if Betűvarázs already has a Child model — just add the beszed* relations. */
class Child extends Model
{
    protected $fillable = ['user_id', 'name', 'sign', 'birth_date'];

    protected $casts = ['birth_date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function beszedLevels(): HasMany
    {
        return $this->hasMany(BeszedSkillLevel::class);
    }

    public function beszedAttempts(): HasMany
    {
        return $this->hasMany(BeszedAttempt::class);
    }

    public function beszedSessions(): HasMany
    {
        return $this->hasMany(BeszedSession::class);
    }

    public function beszedBadges(): HasMany
    {
        return $this->hasMany(BeszedBadge::class);
    }

    public function beszedProfile(): HasOne
    {
        return $this->hasOne(BeszedProfile::class);
    }

    public function pets(): HasMany
    {
        return $this->hasMany(BeszedPet::class);
    }
}
