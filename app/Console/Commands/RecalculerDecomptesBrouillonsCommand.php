<?php

namespace App\Console\Commands;

use App\Models\BonCommande;
use Illuminate\Console\Command;

/**
 * Recalcule le décompte de la TÊTE des bons de commande en brouillon (THT brut / exonéré / taxable,
 * rabais, TVA, AIR, taux_ir, TTC, net) à partir de leurs lignes, avec le décompte commun
 * BonCommande::decompte() — sans retoucher les lignes.
 *
 * Les brouillons n'ont pas d'engagement : aucun montant_engage ne peut dériver.
 *
 *   php artisan bc:recalculer-decomptes              → simulation
 *   php artisan bc:recalculer-decomptes --appliquer  → correction
 */
class RecalculerDecomptesBrouillonsCommand extends Command
{
    protected $signature = 'bc:recalculer-decomptes
        {--appliquer : Enregistrer (sinon simulation)}
        {--backup= : Chemin du script SQL de retour arrière (défaut : storage/app/backups/)}
        {--sans-backup : Ne pas écrire le script de retour arrière}';

    protected $description = 'Recalcule le décompte enregistré des BC en brouillon depuis leurs lignes';

    /** Champs de tête comparés, libellé => attribut. */
    private const CHAMPS = [
        'brut'  => 'montant_ht_brut',
        'exon.' => 'montant_ht_exonere',
        'tax.'  => 'montant_ht_taxable',
        'rabais' => 'montant_rabais',
        'THT'   => 'montant_ht',
        'TVA'   => 'montant_tva',
        'AIR'   => 'montant_ir',
        'tauxIR' => 'taux_ir',
        'TTC'   => 'montant_ttc',
        'net'   => 'net_a_payer',
    ];

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        // Montant : décimales affichées seulement s'il y en a (les centimes hérités sont visibles).
        $f = function ($v) {
            if ($v === null) return 'NULL';
            $d = (float) $v;
            return number_format($d, abs($d - floor($d)) < 0.005 ? 0 : 2, ',', ' ');
        };
        // Taux : toujours 2 décimales.
        $t = fn($v) => number_format((float) $v, 2, ',', ' ') . ' %';

        $lignes = [];
        $vides = [];
        $sansLignes = 0;
        $traites = 0;
        $queTaux = 0;

        // Script de retour arrière : écrit ligne par ligne (flush) avant chaque sauvegarde,
        // pour rester exploitable même si la commande est interrompue en cours de route.
        $fichier = null;
        $fh = null;
        if ($appliquer && !$this->option('sans-backup')) {
            $fichier = $this->option('backup')
                ?: storage_path('app/backups/bc-decomptes-' . now()->format('Ymd-His') . '.sql');
            $dossier = dirname($fichier);
            if (!is_dir($dossier)) {
                mkdir($dossier, 0775, true);
            }
            $fh = fopen($fichier, 'w');
            fwrite($fh, "-- Retour arrière bc:recalculer-decomptes — " . now() . "\nBEGIN;\n");
            fflush($fh);
        }

        foreach (
            BonCommande::withoutGlobalScope('exercice')
                ->where('statut', 'brouillon')
                ->with('lignes')
                ->orderBy('numero')
                ->lazy(100) as $bc
        ) {
            $traites++;

            if ($bc->lignes->isEmpty()) {
                $sansLignes++;
                $vides[] = $bc->numero;
                continue;
            }

            $avant = [];
            foreach (self::CHAMPS as $attr) {
                $avant[$attr] = $bc->getAttribute($attr);
            }
            $avant['net_a_percevoir'] = $bc->getAttribute('net_a_percevoir');
            $avant['updated_at'] = $bc->updated_at;

            $bc->calculerMontants();   // ne fait qu'assigner : aucune écriture ici

            $ecarts = [];
            foreach (self::CHAMPS as $attr) {
                $n = $bc->getAttribute($attr);
                if (abs((float) $n - (float) $avant[$attr]) > 0.004) {
                    $fmt = $attr === 'taux_ir' ? $t : $f;
                    $ancien = $avant[$attr];
                    $ecarts[] = $attr . ' ' . ($ancien === null ? 'NULL' : $fmt($ancien)) . ' → ' . $fmt($n);
                }
            }

            if (abs((float) $bc->getAttribute('net_a_percevoir') - (float) $avant['net_a_percevoir']) > 0.004) {
                $ecarts[] = 'net_a_percevoir ' . $f($avant['net_a_percevoir']) . ' → ' . $f($bc->getAttribute('net_a_percevoir'));
            }

            if (!$ecarts) {
                continue;
            }

            if (count($ecarts) === 1 && str_starts_with($ecarts[0], 'taux_ir')) {
                $queTaux++;
            }

            $lignes[] = array_merge(
                [$bc->numero, $bc->lignes->count(), $f($avant['montant_ht']), $f($bc->montant_ht), $f($bc->net_a_payer)],
                [implode(' | ', $ecarts)]
            );

            if ($appliquer) {
                if ($fh) {
                    fwrite($fh, $this->rollbackSql($bc, $avant) . "\n");
                    fflush($fh);
                }
                $bc->saveQuietly();
            } else {
                foreach ($avant as $attr => $v) {
                    if ($attr === 'updated_at') {
                        continue;
                    }
                    $bc->setAttribute($attr, $v);
                }
            }
        }

        $this->line("Brouillons examinés : {$traites} (dont {$sansLignes} sans ligne, ignorés).");
        $this->line("Seul le taux d'AIR change (aucun montant touché) : {$queTaux} ; montants recalculés : " . (count($lignes) - $queTaux) . '.');

        if ($sansLignes > 0) {
            $this->line('⚠ Sans lignes (montants de tête non recalculables, à vérifier à la main) : ' . implode(', ', $vides));
        }

        if (empty($lignes)) {
            $this->info('✅ Tous les décomptes de tête des brouillons sont conformes à leurs lignes.');
            return self::SUCCESS;
        }

        $this->table(['Numéro', 'lg', 'THT avant', 'THT après', 'net après', 'Écarts'], $lignes);

        $n = count($lignes);
        if ($appliquer) {
            if ($fh) {
                fwrite($fh, "COMMIT;\n");
                fclose($fh);
                $this->line('Script de retour arrière : ' . $fichier);
                $this->line('Pour annuler : psql -f "' . $fichier . '" (ou mysql < fichier).');
            }
            $this->info("✅ {$n} brouillon(s) recalculé(s) et enregistré(s).");
        } else {
            $this->line("Simulation : {$n} brouillon(s) à corriger. Relancez avec --appliquer.");
            $this->line("Note : un rabais enregistré supérieur à sa base est ramené à cette base, et les centimes disparaissent si mode_arrondi = true.");
        }

        return self::SUCCESS;
    }

    /**
     * UPDATE restaurant les valeurs d'origine d'une ligne, prêt à ré-exécuter.
     * Les montants sortent en littéraux numériques bruts ; updated_at est remis tel quel
     * (saveQuietly() décale updated_at, lui seul, puisque les événements du modèle sont ignorés).
     */
    private function rollbackSql(BonCommande $bc, array $avant): string
    {
        $set = [];

        foreach ($avant as $col => $v) {
            if ($v === null) {
                $set[] = "{$col} = NULL";
                continue;
            }

            if ($col === 'updated_at') {
                $d = $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : (string) $v;
                $set[] = "{$col} = '" . str_replace("'", "''", $d) . "'";
                continue;
            }

            if (is_numeric($v)) {
                $nombre = rtrim(rtrim(sprintf('%.4f', (float) $v), '0'), '.');
                $set[] = "{$col} = " . ($nombre === '' ? '0' : $nombre);
                continue;
            }

            $set[] = "{$col} = '" . str_replace("'", "''", (string) $v) . "'";
        }

        return 'UPDATE ' . $bc->getTable() . ' SET ' . implode(', ', $set) . ' WHERE id = ' . (int) $bc->id . ';';
    }
}
