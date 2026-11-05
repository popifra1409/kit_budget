<?php

namespace App\Console\Commands;

use App\Models\Engagement;
use App\Models\LigneBudgetaire;
use App\Services\Budget\HistoriqueLigneBudgetaireService as Historique;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Repose le gel à l'émission sur les documents déjà en base.
 *
 * Ils n'ont pas été gelés en naissant — l'observer arrive après eux. Le gel se
 * reconstruit donc avec la coupure chronologique du volet 1 : l'état de la ligne au jour
 * de chaque document. C'est une reconstruction, pas une émission : `snapshot_fige_le`
 * porte la date du jour, et c'est ce qui permettra de distinguer, dans un recours, un
 * chiffre que le document a connu de un chiffre qu'on lui a rendu.
 *
 * La reconstruction a une limite, et le rapport doit la dire : un engagement passé par
 * Engagement::annuler() est en `forceDelete()`, donc absent du « engagé avant »
 * reconstitué. Un document retrouvé ainsi n'est pas faux, il est reconstruit — en cas de
 * doute, c'est le papier qu'il faut regarder, pas la base.
 */
class FixSnapshotEngagements extends Command
{
    protected $signature   = 'engagements:fix-snapshots
                            {--exercice= : Ne traiter que les engagements de cet exercice}
                            {--forcer : Reposer un gel déjà présent}
                            {--max= : Nombre de documents à traiter au plus}
                            {--sans-ecrire : Annoncer sans écrire}';
    protected $description = "Gèle l'état budgétaire au jour de chaque certificat d'engagement et ordonnance";

    /** Dotation du jour d'une ligne, mémoïsée : elle sert au seul rapport. */
    private array $dotations = [];

    public function handle(Historique $historique): int
    {
        $manquantes = collect(Historique::COLONNES_GEL)
            ->reject(fn($c) => Schema::hasColumn('engagements', $c));

        if ($manquantes->isNotEmpty()) {
            $this->error('Migration non appliquée — colonnes absentes : '.$manquantes->implode(', '));
            $this->line("Lance d'abord : php artisan migrate --force");

            return self::FAILURE;
        }

        $sansEcrire = (bool) $this->option('sans-ecrire');
        $max        = (int) $this->option('max');

        $compte = ['gelés' => 0, 'déjà gelés' => 0, 'sans ligne' => 0, 'retrouvés' => 0, 'examinés' => 0];

        Engagement::withoutGlobalScope('exercice')->withTrashed()
            ->when($this->option('exercice'), fn($q, $annee) => $q->where('exercice', (int) $annee))
            ->chunkById(100, function ($engagements) use ($historique, $sansEcrire, $max, &$compte) {
                foreach ($engagements as $engagement) {
                    if ($max && $compte['examinés'] >= $max) {
                        return false;
                    }
                    $compte['examinés']++;

                    if (! $this->option('forcer') && $engagement->snapshot_disponible_avant !== null) {
                        $compte['déjà gelés']++;

                        continue;
                    }

                    if (!$historique->poserGel($engagement)) {
                        $compte['sans ligne']++;

                        continue;
                    }

                    if (!$sansEcrire) {
                        $engagement->saveQuietly();
                    }

                    $compte['gelés']++;

                    // Ce que le gel rend au papier : la dotation du jour ne coïncide plus.
                    if (abs((float) $engagement->snapshot_budget_rectifie
                        - $this->dotationDuJour($engagement)) > 0.005) {
                        $compte['retrouvés']++;
                    }
                }

                return true;
            });

        $this->table(
            ['Document', ''],
            [
                ['Examinés', $compte['examinés']],
                ['Gelés', $compte['gelés'].($sansEcrire ? ' (à écrire)' : '')],
                ['Déjà gelés, laissés tels quels', $compte['déjà gelés']],
                ['Sans ligne budgétaire — rien à geler', $compte['sans ligne']],
                ["Dont l'état reconstitué diffère de l'état du jour", $compte['retrouvés']],
            ]
        );

        if ($sansEcrire) {
            $this->warn('--sans-ecrire : rien n\'a été écrit.');
        } else {
            $this->info('Gel déposé. Les « sans ligne budgétaire » restent sur la reconstruction du volet 1.');
        }

        return self::SUCCESS;
    }

    private function dotationDuJour(Engagement $engagement): float
    {
        if (!$engagement->nomenclature_principale_id) {
            return 0.0;
        }

        $cle = $engagement->budget_id.'|'.$engagement->nomenclature_principale_id;

        return $this->dotations[$cle] ??= (float) (LigneBudgetaire::where('budget_id', $engagement->budget_id)
            ->where('nomenclature_id', $engagement->nomenclature_principale_id)
            ->value('budget_rectifie') ?? 0);
    }
}
