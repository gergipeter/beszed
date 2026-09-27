<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeszedPetMinigame extends Model
{
    protected $fillable = ['pet_id', 'game_type', 'score', 'won', 'reward'];

    protected $casts = ['score' => 'integer', 'won' => 'boolean'];

    public function pet()
    {
        return $this->belongsTo(BeszedPet::class);
    }
}
