<?php
// app/Jobs/GenererPrevisionsMensuelles.php

namespace App\Jobs;

use App\Models\LignePrevisionRecette;
use App\Models\PrevisionRecette;
use App\Models\PrevisionRecetteMensuelle;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenererPrevisionsMensuelles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout  = 300; // 5 minutes max
    public int $tries    = 1;

    public function __construct(
        public int $previsionId,
        public int $userId
    ) {}

    public function handle(): void
    {
        $count = 0;

        LignePrevisionRecette::where('prevision_recette_id', $this->previsionId)
            ->select('id', 'montant_rectifie', 'prevision_recette_id')
            ->chunk(10, function ($lignes) use (&$count) {
                foreach ($lignes as $ligne) {
                    $ligneComplete = LignePrevisionRecette::with([
                        'previsionRecette:id,exercice_id',
                        'previsionRecette.exerciceBudgetaire:id,annee',
                    ])->find($ligne->id);

                    if ($ligneComplete) {
                        PrevisionRecetteMensuelle::creerPrevisionsAnnuelles($ligneComplete);
                        $count++;
                    }

                    unset($ligneComplete);
                }
                gc_collect_cycles();
            });

        // Notifier l'utilisateur via DB notification
        $user = User::find($this->userId);
        if ($user) {
            \Filament\Notifications\Notification::make()
                ->title('✅ Prévisions mensuelles générées')
                ->success()
                ->body("{$count} lignes × 12 mois = " . ($count * 12) . " prévisions créées.")
                ->sendToDatabase($user);
        }
    }
}
