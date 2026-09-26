<?php

namespace App\Http\Controllers;

use App\Models\Carte;
use App\Models\Notif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TacheController extends Controller
{
    /** Vue « Mes tâches » : toutes les cartes qui me sont assignées, tous tableaux confondus. */
    public function mesTaches(Request $request): JsonResponse
    {
        $ids = $request->user()->boards()->pluck('boards.id');
        $cartes = Carte::with(['liste.board:id,nom,couleur', 'etiquettes:id,nom,couleur'])->where('assignee_id', $request->user()->id)
            ->whereHas('liste', fn ($q) => $q->whereIn('board_id', $ids))->orderByRaw('echeance IS NULL')->orderBy('echeance')->get();

        return response()->json($cartes->map(fn ($c) => [
            'id' => $c->id, 'titre' => $c->titre, 'echeance' => $c->echeance?->toDateString(), 'liste' => $c->liste->nom, 'terminee' => mb_strtolower($c->liste->nom) === 'terminé',
            'board' => ['id' => $c->liste->board->id, 'nom' => $c->liste->board->nom, 'couleur' => $c->liste->board->couleur], 'etiquettes' => $c->etiquettes,
        ]));
    }

    /** Génère (sans tâche planifiée) les rappels d'échéance de l'utilisateur : cartes assignées dues dans les 24 h ou en retard. */
    public static function genererEcheances(int $userId): int
    {
        $n = 0;
        $cartes = Carte::with('liste.board')->where('assignee_id', $userId)->whereNotNull('echeance')->whereDate('echeance', '<=', now()->addDay()->toDateString())
            ->whereHas('liste', fn ($q) => $q->where('nom', '!=', 'Terminé'))->get();
        foreach ($cartes as $c) {
            $tag = "#{$c->id}:{$c->echeance->toDateString()}";
            if (Notif::where('user_id', $userId)->where('type', 'echeance')->where('texte', 'like', "%{$tag}")->exists()) {
                continue;
            }
            $quand = $c->echeance->isPast() && ! $c->echeance->isToday() ? 'est en retard' : ($c->echeance->isToday() ? 'est due aujourd’hui' : 'est due demain');
            Notif::create(['user_id' => $userId, 'board_id' => $c->liste->board_id, 'carte_id' => $c->id, 'type' => 'echeance', 'texte' => "La tâche « {$c->titre} » {$quand}. {$tag}"]);
            $n++;
        }

        return $n;
    }

    public function notifications(Request $request): JsonResponse
    {
        self::genererEcheances($request->user()->id);

        return response()->json(Notif::where('user_id', $request->user()->id)->latest('id')->limit(40)->get()->map(fn ($n) => ['id' => $n->id, 'type' => $n->type, 'texte' => preg_replace('/ #\d+:\d{4}-\d{2}-\d{2}$/', '', $n->texte), 'lu' => $n->lu, 'board_id' => $n->board_id, 'carte_id' => $n->carte_id, 'date' => $n->created_at->toIso8601String()]));
    }

    public function lues(Request $request): JsonResponse
    {
        Notif::where('user_id', $request->user()->id)->update(['lu' => true]);

        return response()->json(['ok' => true]);
    }
}
