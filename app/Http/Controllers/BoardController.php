<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Invitation;
use App\Models\Notif;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BoardController extends Controller
{
    /** Tableau auquel l'utilisateur a accès (membre), sinon 404. */
    public static function acces(Request $request, int $id): Board
    {
        $b = Board::findOrFail($id);
        abort_unless($b->aMembre($request->user()), 404);

        return $b;
    }

    private function proprio(Request $request, int $id): Board
    {
        $b = self::acces($request, $id);
        abort_unless($b->owner_id === $request->user()->id, 403, 'Seul le propriétaire du tableau peut faire cela.');

        return $b;
    }

    public function index(Request $request): JsonResponse
    {
        $liste = $request->user()->boards()->withCount('membres')->with(['listes' => fn ($q) => $q->withCount('cartes')])->latest('boards.updated_at')->get();

        return response()->json($liste->map(fn ($b) => ['id' => $b->id, 'nom' => $b->nom, 'couleur' => $b->couleur, 'proprietaire' => $b->owner_id === $request->user()->id, 'membres' => $b->membres_count, 'cartes' => $b->listes->sum('cartes_count'), 'listes' => $b->listes->count()]));
    }

    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['nom' => ['required', 'string', 'min:2', 'max:80'], 'couleur' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        $b = DB::transaction(function () use ($request, $d) {
            $b = Board::create(['owner_id' => $request->user()->id, 'nom' => strip_tags($d['nom']), 'couleur' => $d['couleur'] ?? '#0d9488']);
            $b->membres()->attach($request->user()->id, ['role' => 'owner']);
            foreach (['À faire', 'En cours', 'Terminé'] as $i => $n) {
                $b->listes()->create(['nom' => $n, 'ordre' => $i]);
            }
            foreach ([['Urgent', '#ef4444'], ['Design', '#8b5cf6'], ['Développement', '#3b82f6'], ['Bug', '#f59e0b'], ['Idée', '#10b981']] as [$n, $c]) {
                $b->etiquettes()->create(['nom' => $n, 'couleur' => $c]);
            }
            $b->log($request->user(), 'a créé le tableau');

            return $b;
        });

        return response()->json($b, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $b = self::acces($request, $id);
        $b->load(['listes.cartes' => fn ($q) => $q->with(['etiquettes:id,nom,couleur', 'assignee:id,name'])->withCount(['commentaires', 'fichiers']), 'etiquettes', 'membres:id,name,email', 'invitations:id,board_id,email']);

        return response()->json([
            'id' => $b->id, 'nom' => $b->nom, 'couleur' => $b->couleur, 'proprietaire' => $b->owner_id === $request->user()->id, 'version' => $b->version(),
            'membres' => $b->membres->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'email' => $m->email, 'role' => $m->pivot->role]),
            'invitations' => $b->invitations->pluck('email'),
            'etiquettes' => $b->etiquettes,
            'listes' => $b->listes->map(fn ($l) => ['id' => $l->id, 'nom' => $l->nom, 'ordre' => $l->ordre, 'cartes' => $l->cartes->map(fn ($c) => [
                'id' => $c->id, 'liste_id' => $c->liste_id, 'titre' => $c->titre, 'echeance' => $c->echeance?->toDateString(), 'ordre' => $c->ordre, 'has_description' => filled($c->description),
                'etiquettes' => $c->etiquettes, 'assignee' => $c->assignee, 'commentaires' => $c->commentaires_count, 'fichiers' => $c->fichiers_count,
            ])]),
        ]);
    }

    /** Appelé régulièrement par l'interface : ne renvoie que la signature de l'état (très léger). */
    public function version(Request $request, int $id): JsonResponse
    {
        return response()->json(['version' => self::acces($request, $id)->version()]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $b = $this->proprio($request, $id);
        $d = $request->validate(['nom' => ['required', 'string', 'min:2', 'max:80'], 'couleur' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        $b->update(['nom' => strip_tags($d['nom']), 'couleur' => $d['couleur'] ?? $b->couleur]);

        return response()->json($b);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->proprio($request, $id)->delete();

        return response()->json(['message' => 'Tableau supprimé.']);
    }

    public function activites(Request $request, int $id): JsonResponse
    {
        $b = self::acces($request, $id);

        return response()->json($b->activites()->with('user:id,name')->latest('id')->limit(60)->get()->map(fn ($a) => ['id' => $a->id, 'texte' => $a->texte, 'auteur' => $a->user?->name ?? 'Système', 'date' => $a->created_at->toIso8601String(), 'carte_id' => $a->carte_id]));
    }

    // ---- Membres
    public function inviter(Request $request, int $id): JsonResponse
    {
        $b = self::acces($request, $id);
        $email = strtolower($request->validate(['email' => ['required', 'email', 'max:120']])['email']);
        $u = User::where('email', $email)->first();
        if ($u) {
            abort_if($b->aMembre($u), 422, 'Cette personne est déjà membre du tableau.');
            $b->membres()->attach($u->id, ['role' => 'membre']);
            Notif::create(['user_id' => $u->id, 'board_id' => $b->id, 'type' => 'invitation', 'texte' => "{$request->user()->name} vous a ajouté au tableau « {$b->nom} »."]);
            $b->log($request->user(), "a invité {$u->name}");
            $b->touch();

            return response()->json(['message' => "{$u->name} a été ajouté(e) au tableau.", 'statut' => 'ajoute']);
        }
        Invitation::firstOrCreate(['board_id' => $b->id, 'email' => $email], ['invite_par' => $request->user()->id]);
        $b->log($request->user(), "a invité {$email} (invitation en attente)");
        $b->touch();

        return response()->json(['message' => "Invitation enregistrée : {$email} rejoindra le tableau à son inscription.", 'statut' => 'en_attente']);
    }

    public function retirerMembre(Request $request, int $id, int $userId): JsonResponse
    {
        $b = self::acces($request, $id);
        abort_unless($b->owner_id === $request->user()->id || $userId === $request->user()->id, 403, 'Seul le propriétaire peut retirer un membre.');
        abort_if($userId === $b->owner_id, 422, 'Le propriétaire ne peut pas quitter son propre tableau.');
        $b->membres()->detach($userId);
        DB::table('cartes')->whereIn('liste_id', $b->listes()->pluck('id'))->where('assignee_id', $userId)->update(['assignee_id' => null]);
        $b->touch();

        return response()->json(['message' => 'Membre retiré.']);
    }

    public function supprimerInvitation(Request $request, int $id): JsonResponse
    {
        $b = self::acces($request, $id);
        $b->invitations()->where('email', strtolower((string) $request->query('email')))->delete();

        return response()->json(['message' => 'Invitation annulée.']);
    }
}
