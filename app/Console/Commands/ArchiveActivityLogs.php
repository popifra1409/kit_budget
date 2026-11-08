<?php

namespace App\Console\Commands;

use App\Support\ConservationJournal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveActivityLogs extends Command
{
    protected $signature = 'activity:archive {--months=} {--chunk=500}';
    protected $description = 'Archive les anciennes activités pour préserver les performances';

    public function handle(): int
    {
        // Les deux seuils, celui d'archivage et celui de purge, se lisent via
        // ConservationJournal : c'est ce qui permet à ViderAnciensLogs de refuser une purge
        // qui détruirait une entrée avant que l'archivage ait pu la déplacer.
        $mois = ConservationJournal::ageArchivage($this->option('months') ? (int) $this->option('months') : null);
        $seuil = ConservationJournal::seuilArchivage($mois);
        $chunkSize = (int) $this->option('chunk');

        $total = DB::table('activity_log')->where('created_at', '<', $seuil)->count();

        if ($total === 0) {
            $this->info('Aucune activité à archiver.');
            return self::SUCCESS;
        }

        $this->info("{$total} activités antérieures au {$seuil->format('d/m/Y')} à archiver.");
        $bar = $this->output->createProgressBar($total);
        $archivees = 0;

        DB::table('activity_log')
            ->where('created_at', '<', $seuil)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$archivees, $bar) {
                $maintenant = now();

                $aInserer = $rows->map(fn($row) => [
                    'log_name' => $row->log_name ?? null,
                    'description' => $row->description,
                    'subject_type' => $row->subject_type,
                    'subject_id' => $row->subject_id,
                    'event' => $row->event ?? null,
                    'causer_type' => $row->causer_type,
                    'causer_id' => $row->causer_id,
                    'properties' => $row->properties,
                    'ip_address' => $row->ip_address ?? null,
                    'user_agent' => $row->user_agent ?? null,
                    'batch_uuid' => $row->batch_uuid ?? null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'archived_at' => $maintenant,
                ])->toArray();

                // Insérer et supprimer d'un seul tenant : coupés, les deux gestes laissent
                // une entrée en double dans l'archive — le run suivant redéplace les lignes
                // encore présentes dans activity_log sans savoir qu'elles ont déjà leur
                // équivalent archivé. Un recyclage du pool IIS en plein chunk suffit.
                DB::transaction(function () use ($aInserer, $rows) {
                    DB::table('activity_log_archives')->insert($aInserer);

                    DB::table('activity_log')->whereIn('id', $rows->pluck('id')->toArray())->delete();
                });

                $archivees += count($aInserer);
                $bar->advance(count($aInserer));
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Terminé — {$archivees} activités archivées et retirées de la table active (seuil : {$mois} mois).");

        return self::SUCCESS;
    }
}
