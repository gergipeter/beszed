<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeszedContentItemEdit extends Model
{
    protected $fillable = ['content_item_id', 'editor_email', 'action', 'before', 'after'];

    protected $casts = ['before' => 'array', 'after' => 'array'];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(fn ($model) => $model->created_at = now());
    }
}
