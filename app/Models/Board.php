<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Board extends Model
{
    protected $table = 'boards';

    protected $guarded = [];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_membres')->withPivot('role');
    }

    public function listes(): HasMany
    {
        return $this->hasMany(Liste::class)->orderBy('ordre');
    }

    public function etiquettes(): HasMany
    {
        return $this->hasMany(Etiquette::class);
    }

    public function activites(): HasMany
    {
        return $this->hasMany(Activite::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function aMembre(User $u): bool
    {
        return $this->membres()->where('users.id', $u->id)->exists();
    }

    /** Signature de l'état du tableau : change dès qu'une carte, une liste ou un commentaire est modifié (sert au rafraîchissement automatique). */
    public function version(): string
    {
        $l = $this->listes()->max('updated_at');
        $c = Carte::whereIn('liste_id', $this->listes()->pluck('id'))->max('updated_at');
        $n = Carte::whereIn('liste_id', $this->listes()->pluck('id'))->count();
        $a = $this->activites()->max('id');

        return md5("$l|$c|$n|$a|{$this->updated_at}|".$this->membres()->count());
    }

    public function log(?User $u, string $texte, ?int $carteId = null): void
    {
        $this->activites()->create(['user_id' => $u?->id, 'carte_id' => $carteId, 'texte' => mb_substr($texte, 0, 240)]);
    }
}
