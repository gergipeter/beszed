<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeszedPetItem extends Model
{
    protected $fillable = ['pet_id', 'item_type', 'item_name', 'quantity', 'rarity'];

    protected $casts = ['quantity' => 'integer'];

    public function pet()
    {
        return $this->belongsTo(BeszedPet::class);
    }
}
