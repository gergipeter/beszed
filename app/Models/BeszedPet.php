<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeszedPet extends Model
{
    protected $fillable = [
        'child_id', 'name', 'species', 'generation',
        'hunger', 'happiness', 'energy', 'hygiene', 'health',
        'level', 'experience', 'stage',
        'born_at', 'last_fed_at', 'last_played_at', 'last_cleaned_at',
        'is_alive', 'death_reason',
    ];

    protected $casts = [
        'hunger' => 'integer',
        'happiness' => 'integer',
        'energy' => 'integer',
        'hygiene' => 'integer',
        'health' => 'integer',
        'level' => 'integer',
        'experience' => 'integer',
        'born_at' => 'datetime',
        'last_fed_at' => 'datetime',
        'last_played_at' => 'datetime',
        'last_cleaned_at' => 'datetime',
        'is_alive' => 'boolean',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function items()
    {
        return $this->hasMany(BeszedPetItem::class);
    }

    public function meals()
    {
        return $this->hasMany(BeszedPetMeal::class);
    }

    public function minigames()
    {
        return $this->hasMany(BeszedPetMinigame::class);
    }

    /**
     * Get current stage based on age (egg, baby, child, teen, adult, elder)
     */
    public function getStageAttribute(): string
    {
        if (!$this->born_at) return 'egg';

        $hoursOld = $this->born_at->diffInHours(now());
        return match (true) {
            $hoursOld < 1 => 'egg',
            $hoursOld < 6 => 'baby',
            $hoursOld < 24 => 'child',
            $hoursOld < 72 => 'teen',
            $hoursOld < 336 => 'adult',
            default => 'elder',
        };
    }

    /**
     * Calculate health based on care stats
     */
    public function calculateHealth(): int
    {
        $avgCare = ($this->hunger + $this->happiness + $this->hygiene) / 3;
        return max(0, min(100, intval($avgCare * 0.8 + (100 - abs($this->hunger - 50)) * 0.2)));
    }

    /**
     * Apply time decay (pet gets hungry, sad, dirty over time)
     */
    public function tick(): void
    {
        if (!$this->is_alive) return;

        $minutesSinceFed = $this->last_fed_at?->diffInMinutes(now()) ?? 999;
        $minutesSincePlay = $this->last_played_at?->diffInMinutes(now()) ?? 999;
        $minutesSinceCleaned = $this->last_cleaned_at?->diffInMinutes(now()) ?? 999;

        // Decay over time
        $this->hunger = min(100, $this->hunger + intval($minutesSinceFed / 5));
        $this->happiness = max(0, $this->happiness - intval($minutesSincePlay / 10));
        $this->hygiene = max(0, $this->hygiene - intval($minutesSinceCleaned / 8));
        $this->energy = min(100, $this->energy + intval((now()->hour >= 22 || now()->hour < 8) ? 2 : -1));

        // Health calculation
        $this->health = $this->calculateHealth();

        // Death condition
        if ($this->hunger >= 100 && $minutesSinceFed > 1440) { // 24 hours unfed
            $this->is_alive = false;
            $this->death_reason = 'starvation';
        } elseif ($this->health <= 0) {
            $this->is_alive = false;
            $this->death_reason = 'illness';
        }

        $this->save();
    }

    /**
     * Feed the pet
     */
    public function feed(string $foodType = 'standard'): array
    {
        if (!$this->is_alive) {
            return ['error' => 'Pet is no longer alive'];
        }

        $effects = [
            'standard' => ['hunger' => -30, 'health' => 5],
            'nutritious' => ['hunger' => -40, 'health' => 15],
            'treat' => ['hunger' => -20, 'happiness' => 15, 'health' => 0],
        ];

        $effect = $effects[$foodType] ?? $effects['standard'];

        $this->hunger = max(0, min(100, $this->hunger + $effect['hunger']));
        $this->health = max(0, min(100, $this->health + ($effect['health'] ?? 0)));
        if (isset($effect['happiness'])) {
            $this->happiness = max(0, min(100, $this->happiness + $effect['happiness']));
        }
        $this->last_fed_at = now();
        $this->save();

        BeszedPetMeal::create([
            'pet_id' => $this->id,
            'type' => $foodType,
            'hunger_before' => $this->hunger,
        ]);

        return ['success' => true, 'hunger' => $this->hunger, 'health' => $this->health];
    }

    /**
     * Play with the pet
     */
    public function play(): array
    {
        if (!$this->is_alive) {
            return ['error' => 'Pet is no longer alive'];
        }

        if ($this->energy < 20) {
            return ['error' => 'Pet is too tired'];
        }

        $happiness_gain = rand(10, 30);
        $energy_loss = rand(15, 25);
        $hunger_gain = rand(5, 15);

        $this->happiness = min(100, $this->happiness + $happiness_gain);
        $this->energy = max(0, $this->energy - $energy_loss);
        $this->hunger = min(100, $this->hunger + $hunger_gain);
        $this->experience += rand(5, 15);
        $this->last_played_at = now();

        $this->save();

        return [
            'success' => true,
            'happiness' => $this->happiness,
            'energy' => $this->energy,
            'hunger' => $this->hunger,
            'experience' => $this->experience,
        ];
    }

    /**
     * Clean the pet
     */
    public function clean(): array
    {
        if (!$this->is_alive) {
            return ['error' => 'Pet is no longer alive'];
        }

        $this->hygiene = min(100, $this->hygiene + 50);
        $this->health = min(100, $this->health + 10);
        $this->happiness = min(100, $this->happiness + 5);
        $this->last_cleaned_at = now();
        $this->save();

        return [
            'success' => true,
            'hygiene' => $this->hygiene,
            'health' => $this->health,
        ];
    }

    /**
     * Pet sleep/rest
     */
    public function sleep(): array
    {
        if (!$this->is_alive) {
            return ['error' => 'Pet is no longer alive'];
        }

        $this->energy = min(100, $this->energy + 50);
        $this->hunger = min(100, $this->hunger + 20); // Gets hungry while sleeping
        $this->save();

        return [
            'success' => true,
            'energy' => $this->energy,
            'hunger' => $this->hunger,
        ];
    }

    /**
     * Check for level up
     */
    public function checkLevelUp(): bool
    {
        $expPerLevel = 100;
        $newLevel = intval($this->experience / $expPerLevel) + 1;

        if ($newLevel > $this->level) {
            $this->level = $newLevel;
            $this->save();
            return true;
        }

        return false;
    }
}
