<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('nom', 80);
            $table->string('couleur', 9)->default('#0d9488');
            $table->timestamps();
        });

        Schema::create('board_membres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('boards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 8)->default('membre');           // owner | membre
            $table->timestamps();
            $table->unique(['board_id', 'user_id']);
        });

        // Invitations en attente : l'adresse e-mail n'a pas encore de compte
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('boards')->cascadeOnDelete();
            $table->string('email', 120);
            $table->foreignId('invite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['board_id', 'email']);
        });

        Schema::create('listes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('boards')->cascadeOnDelete();
            $table->string('nom', 60);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('cartes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liste_id')->constrained('listes')->cascadeOnDelete();
            $table->string('titre', 160);
            $table->text('description')->nullable();
            $table->date('echeance')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('etiquettes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('boards')->cascadeOnDelete();
            $table->string('nom', 30);
            $table->string('couleur', 9);
        });

        Schema::create('carte_etiquette', function (Blueprint $table) {
            $table->foreignId('carte_id')->constrained('cartes')->cascadeOnDelete();
            $table->foreignId('etiquette_id')->constrained('etiquettes')->cascadeOnDelete();
            $table->primary(['carte_id', 'etiquette_id']);
        });

        Schema::create('commentaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carte_id')->constrained('cartes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('contenu', 1000);
            $table->timestamps();
        });

        Schema::create('fichiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carte_id')->constrained('cartes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nom', 160);
            $table->string('chemin', 200);
            $table->unsignedInteger('taille');
            $table->string('mime', 100);
            $table->timestamps();
        });

        Schema::create('activites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('boards')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('carte_id')->nullable();
            $table->string('texte', 240);
            $table->timestamps();
            $table->index(['board_id', 'id']);
        });

        Schema::create('notifications_app', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('board_id')->nullable()->constrained('boards')->cascadeOnDelete();
            $table->unsignedBigInteger('carte_id')->nullable();
            $table->string('type', 12)->default('info');            // info | echeance | assigne | invitation
            $table->string('texte', 240);
            $table->boolean('lu')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications_app', 'activites', 'fichiers', 'commentaires', 'carte_etiquette', 'etiquettes', 'cartes', 'listes', 'invitations', 'board_membres', 'boards'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
