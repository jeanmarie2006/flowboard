<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carte extends Model
{
    protected $table = 'cartes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['echeance' => 'date:Y-m-d'];
    }

    public function liste(): BelongsTo
    {
        return $this->belongsTo(Liste::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function etiquettes(): BelongsToMany
    {
        return $this->belongsToMany(Etiquette::class, 'carte_etiquette');
    }

    public function commentaires(): HasMany
    {
        return $this->hasMany(Commentaire::class)->oldest();
    }

    public function fichiers(): HasMany
    {
        return $this->hasMany(Fichier::class);
    }

    public function board(): Board
    {
        return $this->liste->board;
    }
}
