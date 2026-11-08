<?php

namespace App\Console\Commands;

use App\Support\ConservationJournal;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class ViderAnciensLogs extends Command
{
    protected $signature = 'logs:clean
                            {--days= : Nombre de jours à conserver (par défaut : la durée la plus longue des établissements)}
                            {--force : Forcer sans confirmation}';

    protected $description = 'Supprimer les anciens logs d\'activité selon la configuration';

    public function handle(): int
    {
        $jours = $this->option('days') ? (int) $this->option('days') : null;
        $conservation = ConservationJournal::dureePurge($jours);

        $datePurge = ConservationJournal::seuilPurge($conservation);
        $dateArchivage = ConservationJournal::seuilArchivage();
        $mois = ConservationJournal::ageArchivage();

        // Le garde-fou : une entrée plus vieille que la purge mais plus jeune que le seuil
        // d'archivage n'a encore été déplacée nulle part. La supprimer là, ce n'est pas
        // appliquer une durée de conservation, c'est détruire une pièce d'audit avant
        // qu'elle ait eu le temps d'être conservée.
        $menacees = Activity::where('created_at', '<', $datePurge)
            ->where('created_at', '>=', $dateArchivage)
            ->count();

        if ($menacees > 0) {
            $this->error("Purge refusée : {$menacees} entrée(s) sont plus vieilles que {$conservation} jours"
                ." mais n'ont pas encore l'âge d'être archivées ({$mois} mois).");
            $this->line('  date de purge     : '.$datePurge->format('d/m/Y'));
            $this->line("  date d'archivage : ".$dateArchivage->format('d/m/Y')
                .' — tout ce qui tombe entre les deux partirait sans laisser de copie.');
            $this->line("  S'en sortir : archiver d'abord (php artisan activity:archive), ou allonger");
            $this->line('  la durée de conservation (Administration → Paramètres de la structure), ou relever');
            $this->line('  audit.conservation.archivage_mois dans config/audit.php.');

            return Command::FAILURE;
        }

        $count = Activity::where('created_at', '<', $datePurge)->count();

        if ($count === 0) {
            $this->info("Aucun log à supprimer (conservation : {$conservation} jours, soit avant le "
                .$datePurge->format('d/m/Y').')');

            return Command::SUCCESS;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("Supprimer {$count} logs datant de plus de {$conservation} jours ?")) {
                $this->info('Opération annulée');

                return Command::FAILURE;
            }
        }

        $deleted = Activity::where('created_at', '<', $datePurge)->delete();

        $this->info("{$deleted} logs supprimés (conservation : {$conservation} jours).");

        return Command::SUCCESS;
    }
}
