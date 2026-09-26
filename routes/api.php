<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\CarteController;
use App\Http\Controllers\TacheController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Tableaux
    Route::get('/boards', [BoardController::class, 'index']);
    Route::post('/boards', [BoardController::class, 'store']);
    Route::get('/boards/{id}', [BoardController::class, 'show'])->whereNumber('id');
    Route::get('/boards/{id}/version', [BoardController::class, 'version'])->whereNumber('id');
    Route::put('/boards/{id}', [BoardController::class, 'update'])->whereNumber('id');
    Route::delete('/boards/{id}', [BoardController::class, 'destroy'])->whereNumber('id');
    Route::get('/boards/{id}/activites', [BoardController::class, 'activites'])->whereNumber('id');
    Route::post('/boards/{id}/membres', [BoardController::class, 'inviter'])->whereNumber('id');
    Route::delete('/boards/{id}/membres/{userId}', [BoardController::class, 'retirerMembre'])->whereNumber(['id', 'userId']);
    Route::delete('/boards/{id}/invitations', [BoardController::class, 'supprimerInvitation'])->whereNumber('id');
    Route::post('/boards/{boardId}/listes', [CarteController::class, 'creerListe'])->whereNumber('boardId');
    Route::post('/boards/{boardId}/etiquettes', [CarteController::class, 'creerEtiquette'])->whereNumber('boardId');

    // Listes et cartes
    Route::put('/listes/{id}', [CarteController::class, 'majListe'])->whereNumber('id');
    Route::delete('/listes/{id}', [CarteController::class, 'supprimerListe'])->whereNumber('id');
    Route::post('/listes/{listeId}/cartes', [CarteController::class, 'creer'])->whereNumber('listeId');
    Route::get('/cartes/{id}', [CarteController::class, 'show'])->whereNumber('id');
    Route::put('/cartes/{id}', [CarteController::class, 'maj'])->whereNumber('id');
    Route::delete('/cartes/{id}', [CarteController::class, 'supprimer'])->whereNumber('id');
    Route::post('/cartes/{id}/deplacer', [CarteController::class, 'deplacer'])->whereNumber('id');
    Route::post('/cartes/{id}/commentaires', [CarteController::class, 'commenter'])->whereNumber('id')->middleware('throttle:60,1');
    Route::delete('/commentaires/{id}', [CarteController::class, 'supprimerCommentaire'])->whereNumber('id');
    Route::post('/cartes/{id}/fichiers', [CarteController::class, 'joindre'])->whereNumber('id')->middleware('throttle:20,1');
    Route::get('/fichiers/{id}', [CarteController::class, 'telecharger'])->whereNumber('id');
    Route::delete('/fichiers/{id}', [CarteController::class, 'supprimerFichier'])->whereNumber('id');

    // Mes tâches et notifications
    Route::get('/mes-taches', [TacheController::class, 'mesTaches']);
    Route::get('/notifications', [TacheController::class, 'notifications']);
    Route::post('/notifications/lues', [TacheController::class, 'lues']);
});
