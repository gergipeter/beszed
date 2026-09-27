<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeszedPetMeal extends Model
{
    protected $fillable = ['pet_id', 'type', 'hunger_before'];

    public $timestamps = false;

    protected $attributes = [
        'created_at' => null,
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_at = now();
        });
    }

    public function pet()
    {
        return $this->belongsTo(BeszedPet::class);
    }
}
