<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commentaire extends Model
{
    protected $table = 'commentaires';

    protected $guarded = [];

    public function carte(): BelongsTo
    {
        return $this->belongsTo(Carte::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
