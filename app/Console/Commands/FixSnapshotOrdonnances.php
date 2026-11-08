<?php

namespace App\Console\Commands;

use App\Models\OrdonnancePaiement;
use App\Services\Budget\GelOrdonnancePaiementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Repose le gel sur les ordonnances déjà en base.
 *
 * Elles n'ont pas été gelées en naissant — l'observer arrive après elles. Depuis, leurs
 * gabarits relisent à chaque impression le bon de commande ou la décision administrative
 * vivant : une OP déjà payée peut donc imprimer autre chose que le papier qui a circulé.
 *
 * Comme pour le gel des certificats, c'est une reconstruction, pas une émission :
 * `snapshot_fige_le` porte la date du jour, et c'est ce qui permettra de distinguer, dans
 * un recours, un chiffre que l'ordonnance a connu de un chiffre qu'on lui a rendu.
 *
 * La reconstruction a deux limites, et le rapport doit les dire :
 * — une OP dont le bénéficiaire est introuvable aujourd'hui, sur l'OP comme sur le
 *   document source, n'a rien à geler : `poserGel()` refuse, le gabarit retomberait sur le
 *   calcul vif de toute façon.
 * — un engagement annulé ou supprimé, ou une source dont les retenues ont été corrigées
 *   depuis, rend ce que la base dit *maintenant*, pas ce que le papier tapait à l'époque.
 *   En cas de doute, c'est le papier qu'il faut regarder, pas la base.
 *
 * Le bloc est garanti entier par la contrainte `etat_gele_bloc` de la table : une gelée à
 * moitié est refusée par Postgres, pas seulement par le service. La commande sort en 2
 * (INVALID) quand des ordonnances se sont lues en erreur — « rien à faire » (0) et
 * « des papiers restent illisibles » (2) ne doivent pas se ressembler pour un script.
 */
class FixSnapshotOrdonnances extends Command
{
    protected $signature = 'ordonnances:fix-snapshots
                            {--exercice= : Ne traiter que les ordonnances de cet exercice}
                            {--forcer : Reposer un gel déjà présent}
                            {--max= : Nombre d\'ordonnances à traiter au plus}
                            {--sans-ecrire : Annoncer sans écrire}';
    protected $description = "Gèle ce que le papier de chaque ordonnance de paiement doit imprimer";

    public function handle(GelOrdonnancePaiementService $gel): int
    {
        $manquantes = collect(GelOrdonnancePaiementService::COLONNES_GEL)
            ->reject(fn($c) => Schema::hasColumn('ordonnances_paiement', $c));

        if ($manquantes->isNotEmpty()) {
            $this->error('Migration non appliquée — colonnes absentes : '.$manquantes->implode(', '));
            $this->line("Lance d'abord : php artisan migrate --force");

            return self::FAILURE;
        }

        $sansEcrire = (bool) $this->option('sans-ecrire');
        $max        = (int) $this->option('max');

        $compte = ['gelées' => 0, 'déjà gelées' => 0, 'sans identité imprimable' => 0,
            'en erreur' => 0, 'examinées' => 0];

        $erreurs = [];

        // Pas d'eager-load ici, contrairement au réflexe habituel : les relations
        // polymorphes (source du document, bénéficiaire) portent une chaîne de classe
        // qu'aucune contrainte ne valide. Charger `engagement.engageable` d'un bloc
        // ferait mourir le chunk entier — donc la commande — sur la première ligne au
        // type stale. Lu ligne à ligne, le même document illisible ne coûte que sa ligne
        // et se retrouve compté « en erreur ».
        OrdonnancePaiement::query()
            ->when($this->option('exercice'), fn($q, $exercice) => $q->where('exercice_id', (int) $exercice))
            ->chunkById(100, function ($ordonnances) use ($gel, $sansEcrire, $max, &$compte, &$erreurs) {
                foreach ($ordonnances as $op) {
                    if ($max && $compte['examinées'] >= $max) {
                        return false;
                    }
                    $compte['examinées']++;

                    if (! $this->option('forcer') && $gel->estGele($op)) {
                        $compte['déjà gelées']++;

                        continue;
                    }

                    try {
                        if (!$gel->poserGel($op)) {
                            $compte['sans identité imprimable']++;

                            continue;
                        }

                        if (!$sansEcrire) {
                            $op->saveQuietly();
                        }

                        $compte['gelées']++;
                    } catch (\Throwable $e) {
                        // L'écriture est dans le même filet que la lecture : une ligne qui
                        // refuse de se poser ne doit pas emmener les 99 autres du chunk.
                        $compte['en erreur']++;
                        $erreurs[$e->getMessage()] = ($erreurs[$e->getMessage()] ?? 0) + 1;
                    }
                }

                return true;
            });

        $this->table(
            ['Ordonnance', ''],
            [
                ['Examinées', $compte['examinées']],
                ['Gelées', $compte['gelées'].($sansEcrire ? ' (à écrire)' : '')],
                ['Déjà gelées, laissées telles quelles', $compte['déjà gelées']],
                ['Sans identité imprimable — rien à geler', $compte['sans identité imprimable']],
                ['En erreur — document source illisible', $compte['en erreur']],
            ]
        );

        foreach (array_slice($erreurs, 0, 3, true) as $message => $n) {
            $this->line("  {$n}× ".$message);
        }

        if ($sansEcrire) {
            $this->warn("--sans-ecrire : rien n'a été écrit.");

            return self::SUCCESS;
        }

        $this->info('Gel déposé. Les « sans identité imprimable » et les « en erreur » restent '
            ."exposés à la dérive : c'est le papier qu'il faut regarder pour eux.");

        return $compte['en erreur'] > 0 ? self::INVALID : self::SUCCESS;
    }
}
