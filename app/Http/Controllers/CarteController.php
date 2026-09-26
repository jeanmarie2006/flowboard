<?php

namespace App\Http\Controllers;

use App\Models\Carte;
use App\Models\Commentaire;
use App\Models\Etiquette;
use App\Models\Fichier;
use App\Models\Liste;
use App\Models\Notif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Listes, cartes (tâches), étiquettes, commentaires et pièces jointes d'un tableau. */
class CarteController extends Controller
{
    private function liste(Request $request, int $id): Liste
    {
        $l = Liste::findOrFail($id);
        BoardController::acces($request, $l->board_id);

        return $l;
    }

    private function carte(Request $request, int $id): Carte
    {
        $c = Carte::with('liste')->findOrFail($id);
        BoardController::acces($request, $c->liste->board_id);

        return $c;
    }

    // ---- Listes
    public function creerListe(Request $request, int $boardId): JsonResponse
    {
        $b = BoardController::acces($request, $boardId);
        $nom = strip_tags($request->validate(['nom' => ['required', 'string', 'min:1', 'max:60']])['nom']);
        $l = $b->listes()->create(['nom' => $nom, 'ordre' => ($b->listes()->max('ordre') ?? -1) + 1]);
        $b->log($request->user(), "a ajouté la liste « {$nom} »");

        return response()->json($l, 201);
    }

    public function majListe(Request $request, int $id): JsonResponse
    {
        $l = $this->liste($request, $id);
        $d = $request->validate(['nom' => ['sometimes', 'required', 'string', 'max:60'], 'direction' => ['sometimes', 'in:gauche,droite']]);
        if (isset($d['nom'])) {
            $l->update(['nom' => strip_tags($d['nom'])]);
        }
        if (isset($d['direction'])) {
            $listes = $l->board->listes()->get()->values();
            $i = $listes->search(fn ($x) => $x->id === $l->id);
            $j = $d['direction'] === 'gauche' ? $i - 1 : $i + 1;
            if (isset($listes[$j])) {
                [$a, $b] = [$listes[$i]->ordre, $listes[$j]->ordre];
                $listes[$i]->update(['ordre' => $j]);
                $listes[$j]->update(['ordre' => $i]);
            }
        }

        return response()->json($l->fresh());
    }

    public function supprimerListe(Request $request, int $id): JsonResponse
    {
        $l = $this->liste($request, $id);
        abort_if($l->board->listes()->count() <= 1, 422, 'Un tableau doit garder au moins une liste.');
        $l->board->log($request->user(), "a supprimé la liste « {$l->nom} »");
        $l->delete();

        return response()->json(['message' => 'Liste supprimée.']);
    }

    // ---- Cartes
    public function creer(Request $request, int $listeId): JsonResponse
    {
        $l = $this->liste($request, $listeId);
        $titre = strip_tags($request->validate(['titre' => ['required', 'string', 'min:1', 'max:160']])['titre']);
        $c = $l->cartes()->create(['titre' => $titre, 'ordre' => ($l->cartes()->max('ordre') ?? -1) + 1]);
        $l->board->log($request->user(), "a créé la carte « {$titre} » dans « {$l->nom} »", $c->id);

        return response()->json($c, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $c = $this->carte($request, $id);
        $c->load(['etiquettes:id,nom,couleur', 'assignee:id,name', 'commentaires.user:id,name', 'fichiers', 'liste:id,nom,board_id']);
        $hist = $c->liste->board->activites()->where('carte_id', $c->id)->with('user:id,name')->latest('id')->limit(15)->get();

        return response()->json([
            'id' => $c->id, 'titre' => $c->titre, 'description' => $c->description, 'echeance' => $c->echeance?->toDateString(), 'liste' => $c->liste->only(['id', 'nom']), 'assignee_id' => $c->assignee_id,
            'etiquettes' => $c->etiquettes, 'assignee' => $c->assignee,
            'commentaires' => $c->commentaires->map(fn ($m) => ['id' => $m->id, 'contenu' => $m->contenu, 'auteur' => $m->user->name, 'user_id' => $m->user_id, 'date' => $m->created_at->toIso8601String()]),
            'fichiers' => $c->fichiers->map(fn ($f) => ['id' => $f->id, 'nom' => $f->nom, 'taille' => $f->taille, 'mime' => $f->mime, 'date' => $f->created_at->toDateString()]),
            'historique' => $hist->map(fn ($a) => ['texte' => $a->texte, 'auteur' => $a->user?->name, 'date' => $a->created_at->toIso8601String()]),
        ]);
    }

    public function maj(Request $request, int $id): JsonResponse
    {
        $c = $this->carte($request, $id);
        $b = $c->liste->board;
        $d = $request->validate([
            'titre' => ['sometimes', 'required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:5000'], 'echeance' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'integer'], 'etiquettes' => ['sometimes', 'array', 'max:10'], 'etiquettes.*' => ['integer'],
        ]);
        if (array_key_exists('assignee_id', $d) && $d['assignee_id']) {
            abort_unless($b->membres()->where('users.id', $d['assignee_id'])->exists(), 422, 'La personne assignée doit être membre du tableau.');
        }
        $avant = $c->assignee_id;
        $c->update(collect($d)->only(['titre', 'description', 'echeance', 'assignee_id'])->map(fn ($v, $k) => in_array($k, ['titre', 'description'], true) && is_string($v) ? strip_tags($v) : $v)->all());
        if (isset($d['etiquettes'])) {
            $c->etiquettes()->sync(Etiquette::where('board_id', $b->id)->whereIn('id', $d['etiquettes'])->pluck('id'));
        }
        if (array_key_exists('assignee_id', $d) && $d['assignee_id'] && $d['assignee_id'] !== $avant) {
            $nom = $b->membres()->where('users.id', $d['assignee_id'])->value('name');
            $b->log($request->user(), "a assigné « {$c->titre} » à {$nom}", $c->id);
            if ($d['assignee_id'] !== $request->user()->id) {
                Notif::create(['user_id' => $d['assignee_id'], 'board_id' => $b->id, 'carte_id' => $c->id, 'type' => 'assigne', 'texte' => "{$request->user()->name} vous a assigné la tâche « {$c->titre} »."]);
            }
        } else {
            $b->log($request->user(), "a modifié la carte « {$c->titre} »", $c->id);
        }

        return $this->show($request, $id);
    }

    public function supprimer(Request $request, int $id): JsonResponse
    {
        $c = $this->carte($request, $id);
        foreach ($c->fichiers as $f) {
            Storage::disk('local')->delete($f->chemin);
        }
        $c->liste->board->log($request->user(), "a supprimé la carte « {$c->titre} »");
        $c->delete();

        return response()->json(['message' => 'Carte supprimée.']);
    }

    /** Glisser-déposer : place la carte à la position `index` de la liste `liste_id` et renumérote proprement. */
    public function deplacer(Request $request, int $id): JsonResponse
    {
        $c = $this->carte($request, $id);
        $d = $request->validate(['liste_id' => ['required', 'integer'], 'index' => ['required', 'integer', 'min:0', 'max:1000']]);
        $dest = Liste::findOrFail($d['liste_id']);
        abort_unless($dest->board_id === $c->liste->board_id, 422, 'Liste invalide.');
        $depuis = $c->liste;
        $ancienne = $c->liste_id;
        DB::transaction(function () use ($c, $dest, $d, $ancienne) {
            $ids = $dest->cartes()->where('id', '!=', $c->id)->pluck('id')->all();
            array_splice($ids, min($d['index'], count($ids)), 0, [$c->id]);
            $c->update(['liste_id' => $dest->id]);
            foreach ($ids as $i => $cid) {
                Carte::whereKey($cid)->update(['ordre' => $i, 'updated_at' => now()]);
            }
            if ($ancienne !== $dest->id) {
                foreach (Carte::where('liste_id', $ancienne)->orderBy('ordre')->pluck('id') as $i => $cid) {
                    Carte::whereKey($cid)->update(['ordre' => $i]);
                }
            }
        });
        if ($depuis->id !== $dest->id) {
            $dest->board->log($request->user(), "a déplacé « {$c->titre} » de « {$depuis->nom} » vers « {$dest->nom} »", $c->id);
        }

        return response()->json(['ok' => true]);
    }

    // ---- Étiquettes
    public function creerEtiquette(Request $request, int $boardId): JsonResponse
    {
        $b = BoardController::acces($request, $boardId);
        $d = $request->validate(['nom' => ['required', 'string', 'max:30'], 'couleur' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']]);

        return response()->json($b->etiquettes()->create(['nom' => strip_tags($d['nom']), 'couleur' => $d['couleur']]), 201);
    }

    // ---- Commentaires
    public function commenter(Request $request, int $id): JsonResponse
    {
        $c = $this->carte($request, $id);
        $texte = strip_tags($request->validate(['contenu' => ['required', 'string', 'min:1', 'max:1000']])['contenu']);
        $m = Commentaire::create(['carte_id' => $c->id, 'user_id' => $request->user()->id, 'contenu' => $texte]);
        $c->touch();
        $c->liste->board->log($request->user(), "a commenté « {$c->titre} »", $c->id);
        if ($c->assignee_id && $c->assignee_id !== $request->user()->id) {
            Notif::create(['user_id' => $c->assignee_id, 'board_id' => $c->liste->board_id, 'carte_id' => $c->id, 'type' => 'info', 'texte' => "{$request->user()->name} a commenté « {$c->titre} »."]);
        }

        return response()->json($m, 201);
    }

    public function supprimerCommentaire(Request $request, int $id): JsonResponse
    {
        $m = Commentaire::with('carte.liste.board')->findOrFail($id);
        $b = $m->carte->liste->board;
        BoardController::acces($request, $b->id);
        abort_unless($m->user_id === $request->user()->id || $b->owner_id === $request->user()->id, 403);
        $m->carte->touch();
        $m->delete();

        return response()->json(['message' => 'Commentaire supprimé.']);
    }

    // ---- Pièces jointes (2 Mo max, types courants)
    public function joindre(Request $request, int $id): JsonResponse
    {
        $c = $this->carte($request, $id);
        $request->validate(['fichier' => ['required', 'file', 'max:2048', 'mimes:pdf,png,jpg,jpeg,gif,txt,docx,xlsx,pptx,csv,zip', 'extensions:pdf,png,jpg,jpeg,gif,txt,docx,xlsx,pptx,csv,zip']], ['fichier.max' => 'Le fichier ne doit pas dépasser 2 Mo.', 'fichier.mimes' => 'Type de fichier non autorisé.', 'fichier.extensions' => 'Type de fichier non autorisé.']);
        $f = $request->file('fichier');
        $chemin = $f->storeAs('pieces/'.$c->id, bin2hex(random_bytes(8)).'.'.$f->extension(), 'local');
        $rec = Fichier::create(['carte_id' => $c->id, 'user_id' => $request->user()->id, 'nom' => mb_substr(preg_replace('/[^\pL\pN._ \-]/u', '', $f->getClientOriginalName()), 0, 150), 'chemin' => $chemin, 'taille' => $f->getSize(), 'mime' => $f->getMimeType()]);
        $c->touch();
        $c->liste->board->log($request->user(), "a joint « {$rec->nom} » à « {$c->titre} »", $c->id);

        return response()->json($rec, 201);
    }

    public function telecharger(Request $request, int $id)
    {
        $f = Fichier::with('carte.liste')->findOrFail($id);
        BoardController::acces($request, $f->carte->liste->board_id);
        abort_unless(Storage::disk('local')->exists($f->chemin), 404);

        return Storage::disk('local')->download($f->chemin, $f->nom, ['Content-Type' => $f->mime, 'X-Content-Type-Options' => 'nosniff']);
    }

    public function supprimerFichier(Request $request, int $id): JsonResponse
    {
        $f = Fichier::with('carte.liste.board')->findOrFail($id);
        $b = $f->carte->liste->board;
        BoardController::acces($request, $b->id);
        abort_unless($f->user_id === $request->user()->id || $b->owner_id === $request->user()->id, 403);
        Storage::disk('local')->delete($f->chemin);
        $f->carte->touch();
        $f->delete();

        return response()->json(['message' => 'Fichier supprimé.']);
    }
}
