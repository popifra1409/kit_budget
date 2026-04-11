<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;
use App\Models\ParametresStructure;

class ViderAnciensLogs extends Command
{
    protected $signature = 'logs:clean 
                            {--days= : Nombre de jours à conserver (par défaut: paramètre global)}
                            {--force : Forcer sans confirmation}';

    protected $description = 'Supprimer les anciens logs d\'activité selon la configuration';

    public function handle()
    {
        // Récupérer la durée de conservation
        $jours = $this->option('days');

        if (!$jours) {
            $parametres = ParametresStructure::first();
            $jours = $parametres->duree_conservation_logs ?? 90; // 90 jours par défaut
        }

        $dateLimit = now()->subDays($jours);

        // Compter les logs à supprimer
        $count = Activity::where('created_at', '<', $dateLimit)->count();

        if ($count === 0) {
            $this->info("✅ Aucun log à supprimer (conservation : {$jours} jours)");
            return Command::SUCCESS;
        }

        // Confirmation
        if (!$this->option('force')) {
            if (!$this->confirm("🗑️  Voulez-vous supprimer {$count} logs datant de plus de {$jours} jours ?")) {
                $this->info('❌ Opération annulée');
                return Command::FAILURE;
            }
        }

        // Suppression
        $deleted = Activity::where('created_at', '<', $dateLimit)->delete();

        $this->info("✅ {$deleted} logs supprimés avec succès !");

        return Command::SUCCESS;
    }
}
