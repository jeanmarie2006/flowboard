<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\Carte;
use App\Models\Commentaire;
use App\Models\Notif;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Deux tableaux d'équipe fictifs avec membres, cartes, étiquettes, commentaires et historique d'activité. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(9);
        $demo = User::create(['name' => 'Awa Sossou', 'email' => 'demo@flowboard.bj', 'password' => 'demo1234', 'role' => 'user']);
        $koffi = User::create(['name' => 'Koffi Adjovi', 'email' => 'koffi@flowboard.bj', 'password' => 'demo1234', 'role' => 'user']);
        $ines = User::create(['name' => 'Inès Dossou', 'email' => 'ines@flowboard.bj', 'password' => 'demo1234', 'role' => 'user']);
        $marc = User::create(['name' => 'Marc Hounkpè', 'email' => 'marc@flowboard.bj', 'password' => 'demo1234', 'role' => 'user']);

        $mk = function (User $owner, string $nom, string $couleur, array $membres) {
            $b = Board::create(['owner_id' => $owner->id, 'nom' => $nom, 'couleur' => $couleur]);
            $b->membres()->attach($owner->id, ['role' => 'owner']);
            foreach ($membres as $m) {
                $b->membres()->attach($m->id, ['role' => 'membre']);
            }
            foreach (['À faire', 'En cours', 'En revue', 'Terminé'] as $i => $n) {
                $b->listes()->create(['nom' => $n, 'ordre' => $i]);
            }
            foreach ([['Urgent', '#ef4444'], ['Design', '#8b5cf6'], ['Développement', '#3b82f6'], ['Bug', '#f59e0b'], ['Contenu', '#10b981']] as [$n, $c]) {
                $b->etiquettes()->create(['nom' => $n, 'couleur' => $c]);
            }
            $b->log($owner, 'a créé le tableau');

            return $b;
        };
        $d = fn (int $j) => now()->addDays($j)->toDateString();

        // ---- Tableau 1 : lancement d'un site vitrine
        $b1 = $mk($demo, 'Lancement du site Studio Kpanou', '#0d9488', [$koffi, $ines, $marc]);
        $L = $b1->listes->keyBy('nom');
        $E = $b1->etiquettes->keyBy('nom');
        $cartes1 = [
            ['À faire', 'Rédiger les textes de la page « À propos »', 'Présenter l’équipe, les valeurs et la méthode de travail. 300 mots maximum.', $d(3), $ines, ['Contenu']],
            ['À faire', 'Choisir les photos de l’équipe', null, $d(6), $marc, ['Contenu', 'Design']],
            ['À faire', 'Configurer le nom de domaine et l’e-mail professionnel', 'Domaine en .bj + adresse contact@.', $d(1), $demo, ['Urgent']],
            ['À faire', 'Préparer le plan de communication de lancement', null, null, null, []],
            ['En cours', 'Maquette de la page d’accueil (Figma)', 'Version mobile d’abord. Valider la palette avec le client.', $d(2), $ines, ['Design']],
            ['En cours', 'Intégration du formulaire de contact', 'Validation côté serveur + protection anti-spam.', $d(0), $koffi, ['Développement', 'Urgent']],
            ['En cours', 'Corriger le menu qui se coupe sur petit écran', null, $d(-1), $koffi, ['Bug', 'Urgent']],
            ['En revue', 'Page « Nos services » intégrée', 'À relire : fautes, liens et images.', $d(1), $demo, ['Développement']],
            ['En revue', 'Logo vectoriel et déclinaisons', null, null, $ines, ['Design']],
            ['Terminé', 'Cahier des charges validé par le client', null, $d(-8), $demo, []],
            ['Terminé', 'Choix de l’hébergement', 'InfinityFree pour la démo, offre payante après le lancement.', $d(-5), $marc, ['Développement']],
            ['Terminé', 'Charte graphique (couleurs, polices)', null, $d(-6), $ines, ['Design']],
        ];
        $c1 = [];
        foreach ($cartes1 as $i => [$liste, $titre, $desc, $ech, $ass, $tags]) {
            $c = $L[$liste]->cartes()->create(['titre' => $titre, 'description' => $desc, 'echeance' => $ech, 'assignee_id' => $ass?->id, 'ordre' => $L[$liste]->cartes()->count()]);
            $c->etiquettes()->sync(collect($tags)->map(fn ($t) => $E[$t]->id));
            $c1[] = $c;
        }
        foreach ([[$c1[4], $ines, 'Première version prête, je la partage ce soir.'], [$c1[4], $demo, 'Super ! Pense à prévoir la version tablette.'], [$c1[5], $koffi, 'Le formulaire envoie bien les e-mails, il reste les messages d’erreur.'], [$c1[6], $demo, 'Ça bloque nos tests sur téléphone, merci de regarder en priorité.'], [$c1[7], $marc, 'Relu : deux fautes corrigées, RAS pour le reste.']] as [$c, $u, $t]) {
            Commentaire::create(['carte_id' => $c->id, 'user_id' => $u->id, 'contenu' => $t, 'created_at' => now()->subHours(mt_rand(1, 30)), 'updated_at' => now()]);
        }
        foreach ([[$demo, 'a créé la carte « Configurer le nom de domaine et l’e-mail professionnel » dans « À faire »'], [$ines, 'a déplacé « Maquette de la page d’accueil (Figma) » de « À faire » vers « En cours »'], [$koffi, 'a déplacé « Intégration du formulaire de contact » de « À faire » vers « En cours »'], [$marc, 'a déplacé « Choix de l’hébergement » de « En cours » vers « Terminé »'], [$demo, 'a assigné « Page « Nos services » intégrée » à Awa Sossou']] as $k => [$u, $t]) {
            $b1->activites()->create(['user_id' => $u->id, 'texte' => $t, 'created_at' => now()->subHours(40 - $k * 7), 'updated_at' => now()]);
        }

        // ---- Tableau 2 : organisation personnelle de l'équipe (créé par Koffi)
        $b2 = $mk($koffi, 'Événement — Journée du numérique', '#7c3aed', [$demo, $ines]);
        $L2 = $b2->listes->keyBy('nom');
        foreach ([['À faire', 'Réserver la salle', $d(4), $demo], ['À faire', 'Inviter les intervenants', $d(2), $koffi], ['En cours', 'Imprimer les affiches', $d(5), $ines], ['En revue', 'Programme de la journée', $d(3), $demo], ['Terminé', 'Choisir la date', $d(-4), $koffi]] as [$liste, $titre, $ech, $ass]) {
            $L2[$liste]->cartes()->create(['titre' => $titre, 'echeance' => $ech, 'assignee_id' => $ass->id, 'ordre' => $L2[$liste]->cartes()->count()]);
        }

        Notif::create(['user_id' => $demo->id, 'board_id' => $b2->id, 'type' => 'invitation', 'texte' => 'Koffi Adjovi vous a ajouté au tableau « Événement — Journée du numérique ».']);
        Notif::create(['user_id' => $demo->id, 'board_id' => $b1->id, 'carte_id' => $c1[6]->id, 'type' => 'info', 'texte' => 'Koffi Adjovi a commenté « Corriger le menu qui se coupe sur petit écran ».', 'lu' => true]);
    }
}
