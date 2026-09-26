<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Liste extends Model
{
    protected $table = 'listes';

    protected $guarded = [];

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function cartes(): HasMany
    {
        return $this->hasMany(Carte::class)->orderBy('ordre');
    }
}
