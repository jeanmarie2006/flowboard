<?php

namespace Tests\Feature;

use App\Http\Controllers\TacheController;
use App\Models\Board;
use App\Models\Carte;
use App\Models\Notif;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FlowboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $nom = 'Awa', ?string $email = null): User
    {
        return User::create(['name' => $nom, 'email' => $email ?? strtolower($nom).'@test.bj', 'password' => 'motdepasse', 'role' => 'user']);
    }

    private function board(User $u): array
    {
        $id = $this->actingAs($u, 'sanctum')->postJson('/api/boards', ['nom' => 'Projet test'])->assertCreated()->json('id');
        $b = Board::with('listes')->find($id);

        return [$b, $b->listes[0], $b->listes[1], $b->listes[2]];
    }

    private function carte(User $u, $liste, string $titre): int
    {
        return $this->actingAs($u, 'sanctum')->postJson("/api/listes/{$liste->id}/cartes", ['titre' => $titre])->assertCreated()->json('id');
    }

    public function test_un_tableau_est_cree_avec_trois_listes_des_etiquettes_et_son_proprietaire(): void
    {
        $u = $this->user();
        [$b] = $this->board($u);
        $this->assertSame(['À faire', 'En cours', 'Terminé'], $b->listes->pluck('nom')->all());
        $this->assertSame(5, $b->etiquettes()->count());
        $this->assertTrue($b->aMembre($u));
        $this->actingAs($u, 'sanctum')->getJson('/api/boards')->assertOk()->assertJsonPath('0.proprietaire', true);
    }

    public function test_isolation_un_non_membre_ne_voit_rien(): void
    {
        $a = $this->user('Awa');
        $intrus = $this->user('Intrus');
        [$b, $l1] = $this->board($a);
        $c = $this->carte($a, $l1, 'Secret');
        $this->actingAs($intrus, 'sanctum')->getJson("/api/boards/{$b->id}")->assertNotFound();
        $this->actingAs($intrus, 'sanctum')->getJson("/api/cartes/{$c}")->assertNotFound();
        $this->actingAs($intrus, 'sanctum')->postJson("/api/listes/{$l1->id}/cartes", ['titre' => 'Piratage'])->assertNotFound();
        $this->actingAs($intrus, 'sanctum')->postJson("/api/cartes/{$c}/deplacer", ['liste_id' => $l1->id, 'index' => 0])->assertNotFound();
        $this->assertCount(0, $this->actingAs($intrus, 'sanctum')->getJson('/api/boards')->json());
    }

    public function test_deplacer_une_carte_renumerote_les_deux_listes(): void
    {
        $u = $this->user();
        [$b, $a, $enCours] = $this->board($u);
        $c1 = $this->carte($u, $a, 'C1');
        $c2 = $this->carte($u, $a, 'C2');
        $c3 = $this->carte($u, $a, 'C3');
        $d1 = $this->carte($u, $enCours, 'D1');

        $this->actingAs($u, 'sanctum')->postJson("/api/cartes/{$c2}/deplacer", ['liste_id' => $enCours->id, 'index' => 0])->assertOk();
        $this->assertSame([$c1, $c3], $a->cartes()->pluck('id')->all());
        $this->assertSame([$c2, $d1], $enCours->cartes()->pluck('id')->all());
        $this->assertSame([0, 1], $a->cartes()->pluck('ordre')->all());
        // réordonner dans la même liste : C3 en première position
        $this->actingAs($u, 'sanctum')->postJson("/api/cartes/{$c3}/deplacer", ['liste_id' => $a->id, 'index' => 0])->assertOk();
        $this->assertSame([$c3, $c1], $a->cartes()->pluck('id')->all());
        // une liste d'un autre tableau est refusée
        [, $autre] = $this->board($this->user('Autre'));
        $this->actingAs($u, 'sanctum')->postJson("/api/cartes/{$c1}/deplacer", ['liste_id' => $autre->id, 'index' => 0])->assertStatus(422);
    }

    public function test_la_version_du_tableau_change_a_chaque_modification(): void
    {
        $u = $this->user();
        [$b, $l1] = $this->board($u);
        $v1 = $this->actingAs($u, 'sanctum')->getJson("/api/boards/{$b->id}/version")->json('version');
        $this->assertSame($v1, $this->actingAs($u, 'sanctum')->getJson("/api/boards/{$b->id}/version")->json('version'));
        $this->carte($u, $l1, 'Nouvelle');
        $this->assertNotSame($v1, $this->actingAs($u, 'sanctum')->getJson("/api/boards/{$b->id}/version")->json('version'));
    }

    public function test_invitation_d_un_membre_existant_et_d_un_inconnu_rattache_a_l_inscription(): void
    {
        $a = $this->user('Awa');
        $ines = $this->user('Ines');
        [$b] = $this->board($a);
        $this->actingAs($a, 'sanctum')->postJson("/api/boards/{$b->id}/membres", ['email' => 'ines@test.bj'])->assertOk()->assertJsonPath('statut', 'ajoute');
        $this->actingAs($a, 'sanctum')->postJson("/api/boards/{$b->id}/membres", ['email' => 'ines@test.bj'])->assertStatus(422);
        $this->assertTrue($b->fresh()->aMembre($ines));
        $this->assertTrue(Notif::where('user_id', $ines->id)->where('type', 'invitation')->exists());

        $this->actingAs($a, 'sanctum')->postJson("/api/boards/{$b->id}/membres", ['email' => 'nouveau@test.bj'])->assertOk()->assertJsonPath('statut', 'en_attente');
        $r = $this->postJson('/api/auth/register', ['name' => 'Nouveau', 'email' => 'nouveau@test.bj', 'password' => 'motdepasse'])->assertCreated();
        $this->assertTrue($b->fresh()->aMembre(User::find($r->json('user.id'))));
        $this->assertSame(0, $b->invitations()->count());
    }

    public function test_seul_un_membre_peut_etre_assigne(): void
    {
        $a = $this->user('Awa');
        $ines = $this->user('Ines');
        [$b, $l1] = $this->board($a);
        $c = $this->carte($a, $l1, 'Tâche');
        $this->actingAs($a, 'sanctum')->putJson("/api/cartes/{$c}", ['assignee_id' => $ines->id])->assertStatus(422);
        $b->membres()->attach($ines->id, ['role' => 'membre']);
        $this->actingAs($a, 'sanctum')->putJson("/api/cartes/{$c}", ['assignee_id' => $ines->id, 'echeance' => now()->addDays(3)->toDateString()])->assertOk()->assertJsonPath('assignee.name', 'Ines');
        $this->assertTrue(Notif::where('user_id', $ines->id)->where('type', 'assigne')->exists());
        $this->actingAs($ines, 'sanctum')->getJson('/api/mes-taches')->assertOk()->assertJsonPath('0.titre', 'Tâche');
    }

    public function test_commentaires_et_droits_de_suppression(): void
    {
        $a = $this->user('Awa');
        $ines = $this->user('Ines');
        [$b, $l1] = $this->board($a);
        $b->membres()->attach($ines->id, ['role' => 'membre']);
        $c = $this->carte($a, $l1, 'Tâche');
        $m = $this->actingAs($ines, 'sanctum')->postJson("/api/cartes/{$c}/commentaires", ['contenu' => '<b>Bien vu</b>'])->assertCreated()->json('id');
        $this->assertSame('Bien vu', \App\Models\Commentaire::find($m)->contenu);
        $this->actingAs($this->user('Zoé'), 'sanctum')->deleteJson("/api/commentaires/{$m}")->assertNotFound();
        $this->actingAs($ines, 'sanctum')->deleteJson("/api/commentaires/{$m}")->assertOk();
    }

    public function test_pieces_jointes_taille_et_type_controles(): void
    {
        Storage::fake('local');
        $u = $this->user();
        [, $l1] = $this->board($u);
        $c = $this->carte($u, $l1, 'Tâche');
        $this->actingAs($u, 'sanctum')->post("/api/cartes/{$c}/fichiers", ['fichier' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
        $this->actingAs($u, 'sanctum')->post("/api/cartes/{$c}/fichiers", ['fichier' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->actingAs($u, 'sanctum')->post("/api/cartes/{$c}/fichiers", ['fichier' => UploadedFile::fake()->create('gros.pdf', 5000, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(1, $this->actingAs($u, 'sanctum')->getJson("/api/cartes/{$c}")->json('fichiers.0.id'));
    }

    public function test_seul_le_proprietaire_renomme_ou_supprime_le_tableau(): void
    {
        $a = $this->user('Awa');
        $ines = $this->user('Ines');
        [$b] = $this->board($a);
        $b->membres()->attach($ines->id, ['role' => 'membre']);
        $this->actingAs($ines, 'sanctum')->putJson("/api/boards/{$b->id}", ['nom' => 'Piraté'])->assertForbidden();
        $this->actingAs($ines, 'sanctum')->deleteJson("/api/boards/{$b->id}")->assertForbidden();
        $this->actingAs($ines, 'sanctum')->deleteJson("/api/boards/{$b->id}/membres/{$a->id}")->assertForbidden(); // un membre ne peut pas retirer le propriétaire
        $this->actingAs($a, 'sanctum')->deleteJson("/api/boards/{$b->id}/membres/{$a->id}")->assertStatus(422); // le propriétaire ne peut pas quitter son tableau
        $this->actingAs($ines, 'sanctum')->deleteJson("/api/boards/{$b->id}/membres/{$ines->id}")->assertOk(); // on peut quitter le tableau
        $this->actingAs($a, 'sanctum')->deleteJson("/api/boards/{$b->id}")->assertOk();
    }

    public function test_les_rappels_d_echeance_ne_sont_crees_qu_une_fois(): void
    {
        $u = $this->user();
        [, $l1, , $fini] = $this->board($u);
        $c = $this->carte($u, $l1, 'Urgent');
        Carte::find($c)->update(['assignee_id' => $u->id, 'echeance' => now()->toDateString()]);
        $d = Carte::create(['liste_id' => $fini->id, 'titre' => 'Déjà fait', 'assignee_id' => $u->id, 'echeance' => now()->toDateString()]);
        $this->assertSame(1, TacheController::genererEcheances($u->id));
        $this->assertSame(0, TacheController::genererEcheances($u->id));
        $n = $this->actingAs($u, 'sanctum')->getJson('/api/notifications')->assertOk()->json();
        $this->assertStringContainsString('due aujourd’hui', $n[0]['texte']);
        $this->assertStringNotContainsString('#', $n[0]['texte']);
    }

    public function test_une_liste_ne_peut_pas_etre_supprimee_si_c_est_la_derniere(): void
    {
        $u = $this->user();
        $id = $this->actingAs($u, 'sanctum')->postJson('/api/boards', ['nom' => 'Solo'])->json('id');
        $listes = Board::find($id)->listes;
        $this->actingAs($u, 'sanctum')->deleteJson("/api/listes/{$listes[0]->id}")->assertOk();
        $this->actingAs($u, 'sanctum')->deleteJson("/api/listes/{$listes[1]->id}")->assertOk();
        $this->actingAs($u, 'sanctum')->deleteJson("/api/listes/{$listes[2]->id}")->assertStatus(422);
    }
}
