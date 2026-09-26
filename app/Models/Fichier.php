<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fichier extends Model
{
    protected $table = 'fichiers';

    protected $guarded = [];

    protected $hidden = ['chemin'];

    public function carte(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Carte::class);
    }
}
