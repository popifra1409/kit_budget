<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ArchiveActivityLogs extends Command
{
    protected $signature = 'activity:archive {--months=6} {--chunk=500}';
    protected $description = 'Archive les anciennes activités pour préserver les performances';

    public function handle(): int
    {
        $seuil = Carbon::now()->subMonths((int) $this->option('months'));
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

                DB::table('activity_log_archives')->insert($aInserer);

                $ids = $rows->pluck('id')->toArray();
                DB::table('activity_log')->whereIn('id', $ids)->delete();

                $archivees += count($ids);
                $bar->advance(count($ids));
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Terminé — {$archivees} activités archivées et purgées de la table active.");

        return self::SUCCESS;
    }
}
