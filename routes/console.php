<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rappels d'échéance de tous les utilisateurs (à planifier avec le cron ; l'application les génère aussi à la consultation des notifications).
Artisan::command('flowboard:echeances', function () {
    $n = 0;
    foreach (\App\Models\User::pluck('id') as $id) {
        $n += \App\Http\Controllers\TacheController::genererEcheances($id);
    }
    $this->info("$n rappel(s) créé(s).");
})->purpose('Crée les rappels d’échéance des tâches assignées');

\Illuminate\Support\Facades\Schedule::command('flowboard:echeances')->dailyAt('07:00');
