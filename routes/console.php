<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ✅ Relances transmissions en retard — tous les matins à 8h
Schedule::command('workflow:traiter-expirees')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground();

// Conservation du journal — chose à savoir avant de lire les trois lignes qui suivent :
// sur ce déploiement (IIS/Windows), aucune entrée « Exécuter toutes les minutes :
// php artisan schedule:run » n'existe dans l'Échéanceur. Cette file d'attente n'est donc
// jamais réveillée, et les deux jobs d'écritures métier ci-dessous (relances de
// transmissions, synchronisation des tâches) dorment avec elle. La conservation est
// déclenchée par deux tâches Windows qui lui sont propres, posées le 1er du mois, archivage
// d'abord puis purge 30 minutes après — dans l'autre ordre, la purge mange l'archive.
// Le --months reste lu dans la même config que le garde-fou de logs:clean, pour que les
// deux seuils ne puissent pas diverger si schedule:run est branché un jour.
Schedule::command('activity:archive --months='.config('audit.conservation.archivage_mois', 6))
    ->monthly();

Schedule::command('budget:synchroniser-taches --appliquer --force')->dailyAt('02:00');
