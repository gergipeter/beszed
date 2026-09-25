<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Skip this file if Betűvarázs already has a Child model — just add the two relations. */
class Child extends Model
{
    protected $fillable = ['user_id', 'name', 'birth_date'];

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
}
