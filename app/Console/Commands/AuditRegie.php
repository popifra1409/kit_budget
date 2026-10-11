<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\BonCommandeRegie;
use App\Models\LigneRegieAvance;
use App\Models\ProvisionConsommation;
use App\Models\ProvisionLigneRegie;
use App\Models\RegieAvance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit rétroactif des régies (RAV et Menu Dépense).
 *
 * Deux réparations, dans cet ordre :
 *
 *  1. SCISION des lignes de mini-budget qui empilent plusieurs encaisses.
 *     Avant : la contrainte unique (régie, nomenclature) obligeait chaque
 *     décaissement trimestriel à se cumuler sur la ligne du précédent, déjà en
 *     cours de consommation — son alloué et son taux mélangeaient donc deux
 *     encaisses (taux > 100 % sans dépense réelle correspondante).
 *     Après : une ligne par encaisse, rattachée à son décaissement, allouée du
 *     montant que CET encaisse a reçu.
 *
 *  2. RETISSAGE des traces de consommation. Un BCR engagé sans aucune ligne dans
 *     provision_consommations est invisible côté décaissement : sa provision
 *     reste « disponible » alors que l'argent est sorti. La trace est recréée au
 *     FIFO sur les encaisses de la même nomenclature, et le BCR est rattaché à la
 *     ligne qui en absorbe la plus grosse part.
 *
 * Sans --run tout est annulé : l'aperçu exécute réellement les corrections, donc
 * ce qu'il affiche est exactement ce que --run écrira.
 *
 *   php artisan budget:audit-regie                  -- aperçu
 *   php artisan budget:audit-regie --run           -- applique
 *   php artisan budget:audit-regie --regie=RAV26-00001 --run
 */
class AuditRegie extends Command
{
    protected $signature = 'budget:audit-regie
                {--regie= : Une seule régie — son id ou son numéro (toutes par défaut)}
                {--run : Écrire en base — sans cette option l audit est annulé}';

    protected $description = 'Scinder les lignes de mini-budget qui cumulent plusieurs encaisses et retisser la traçabilité des consommations';

    private array $ecritures = [];

    public function handle(): int
    {        $regies = RegieAvance::query()
            ->withTrashed()
            ->when(
                $this->option('regie'),
                fn($q, $identifiant) => $q->where(
                    fn($w) => $w->where('id', $identifiant)->orWhere('numero', $identifiant)
                )
            )
            ->orderBy('id')
            ->get();

        if ($regies->isEmpty()) {
            $this->error('Aucune régie trouvée.');

            return self::FAILURE;
        }

        $this->line($regies->count() . ' régie(s) à auditer.');

        DB::beginTransaction();

        try {
            $this->ecritures = [
                'lignes'      => 0,
                'provisions'  => 0,
                'bcr_relies'  => 0,
                'traces'      => 0,
                'imprime'     => 0.0,
                'non_impute'  => 0.0,
            ];

            foreach ($regies as $regie) {
                $this->line("\n<comment>══ {$regie->numero}</comment> — {$regie->labelType}"
                    . ($regie->deleted_at ? ' <error>(corbeille)</error>' : '')
                    . ' — ' . $this->fmt($regie->montant_decaisse) . ' FCFA encaissés');

                $this->scinderLesLignes($regie);

                // Les colonnes montant_disponible/montant_consomme sont périmées
                // (engager() écrit en updateQuietly) : sans resynchronisation
                // préalable le retissage imputerait sur des disponibilités fictives.
                $this->recalculer($regie);

                $this->retisserLesTraces($regie);
                $this->alignerLesBonsCommande($regie);
                $this->recalculer($regie);
                $this->afficherApres($regie);
            }

            $this->line("\n<comment>Écritures :</comment> " . $this->ecritures['lignes']
                . ' ligne(s) d encaisse, ' . $this->ecritures['provisions']
                . ' provision(s) reliée(s), ' . $this->ecritures['traces']
                . ' trace(s) de consommation recréée(s) pour '
                . $this->fmt($this->ecritures['imprime']) . ' FCFA, '
                . $this->ecritures['bcr_relies'] . ' BCR rattaché(s) à leur encaisse.');

            if ($this->ecritures['non_impute'] > 0.01) {
                $this->line('<error>Consommations sans financement : '
                    . $this->fmt($this->ecritures['non_impute'])
                    . ' FCFA — aucun encaisse ne les couvre (dépassement historique).</error>');
                $this->line(' régularisation à décider : nouvelle tranche/DA d encaissement, '
                    . 'ou annulation des BCR concernés.');
            }

            if (!$this->option('run')) {
                DB::rollBack();
                $this->warn('APERÇU — rien n est écrit. Relancez avec --run.');

                return self::SUCCESS;
            }

            DB::commit();
            $this->info('Audit appliqué et enregistré.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Échec — tout est annulé : ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    // =========================================================
    // 1. SCISION — une ligne par encaisse
    // =========================================================
    private function scinderLesLignes(RegieAvance $regie): void
    {
        foreach ($regie->lignes()->with('nomenclature')->get() as $ligne) {
            $code = $ligne->nomenclature?->code ?? 'sans code';

            $provisions = ProvisionLigneRegie::where('ligne_regie_avance_id', $ligne->id)
                ->with('decaissement')
                ->get()
                ->sortBy(fn($p) => [
                    $p->decaissement?->date_decaissement?->format('Ymd') ?? '99999999',
                    $p->decaissement?->numero ?? '',
                    $p->id,
                ])
                ->values();

            if ($provisions->isEmpty()) {
                $this->line("  ligne {$ligne->id} [$code] : aucune provision — hors encaisse, inchangée (alloué "
                    . $this->fmt($ligne->montant_alloue) . ' FCFA)');

                continue;
            }

            if ($provisions->count() > 1) {
                $this->line("  ligne {$ligne->id} [$code] : <comment>"
                    . $provisions->count() . " encaisses cumulés</comment> (alloué "
                    . $this->fmt($ligne->montant_alloue) . ', consommé affiché '
                    . $this->fmt($ligne->montant_consomme) . ') → scission');
            }

            foreach ($provisions as $index => $provision) {
                $tranche = $provision->decaissement;

                if (!$tranche) {
                    $this->line("    provision {$provision->id} sans décaissement — ignorée");

                    continue;
                }

                // La 1re tranche garde la ligne (les BCR déjà enregistre pointent
                // dessus) ; les autres reçoivent leur propre ligne.
                $destination = $index === 0
                    ? $this->lignePourTranche($regie, $ligne, $tranche, $provision, false)
                    : $this->lignePourTranche($regie, $ligne, $tranche, $provision, true);

                if ($provision->ligne_regie_avance_id !== $destination->id) {
                    $provision->update(['ligne_regie_avance_id' => $destination->id]);
                    $this->ecritures['provisions']++;
                }

                $destination->update(['montant_alloue' => (float) $provision->montant_provisionne]);

                $this->line(sprintf(
                    '    %-14s %-22s encaisse %14s → ligne %-4d %s',
                    $tranche->numero,
                    mb_substr((string) $tranche->libelle_tranche, 0, 22),
                    $this->fmt($provision->montant_provisionne),
                    $destination->id,
                    $destination->is($ligne) ? '(rattachée)' : ($destination->wasRecentlyCreated ? '(NOUVELLE)' : '(existante)')
                ));
            }
        }
    }

    /**
     * La ligne qui doit porter cette provision : celle de la tranche si elle
     * existe, sinon — pour la première tranche — la ligne héritée elle-même,
     * sinon une ligne créée pour la tranche.
     */
    private function lignePourTranche(
        RegieAvance $regie,
        LigneRegieAvance $heritée,
        $tranche,
        ProvisionLigneRegie $provision,
        bool $Creer = false
    ): LigneRegieAvance {
        $existante = LigneRegieAvance::where([
            'decaissement_regie_id' => $tranche->id,
            'nomenclature_id'       => $heritée->nomenclature_id,
        ])->first();

        if ($existante) {
            return $existante;
        }

        if (!$Creer && !$heritée->decaissement_regie_id) {
            $heritée->update(['decaissement_regie_id' => $tranche->id]);

            return $heritée;
        }

        $nouvelle = LigneRegieAvance::create([
            'regie_avance_id'       => $regie->id,
            'decaissement_regie_id' => $tranche->id,
            'nomenclature_id'       => $heritée->nomenclature_id,
            'ligne_budgetaire_id'   => $heritée->ligne_budgetaire_id,
            'montant_alloue'        => (float) $provision->montant_provisionne,
            'montant_consomme'      => 0,
        ]);

        $this->ecritures['lignes']++;

        return $nouvelle;
    }

    // =========================================================
    // 2. RETISSAGE — les BCR engagés sans aucune trace
    // =========================================================
    private function retisserLesTraces(RegieAvance $regie): void
    {
        $dejaTraces = ProvisionConsommation::query()
            ->join('bons_commande_regies', 'bons_commande_regies.id', '=', 'provision_consommations.bon_commande_regie_id')
            ->where('bons_commande_regies.regie_avance_id', $regie->id)
            ->distinct()
            ->pluck('provision_consommations.bon_commande_regie_id')
            ->all();

        $orphelins = BonCommandeRegie::where('regie_avance_id', $regie->id)
            ->where('engage', true)
            ->whereNotIn('statut', ['annule', 'brouillon'])
            ->when(
                $dejaTraces !== [],
                fn($q) => $q->whereNotIn('id', $dejaTraces)
            )
            ->orderBy('id')
            ->get();

        if ($orphelins->isEmpty()) {
            return;
        }

        $this->line('  <comment>' . $orphelins->count() . ' BCR engagé(s) sans trace de consommation</comment> :');

        foreach ($orphelins as $bcr) {
            $montant = (float) ($bcr->montant_engage > 0 ? $bcr->montant_engage : $bcr->montant_ttc);
            $ligne   = $bcr->ligneRegieAvance;

            if (!$ligne) {
                $this->line("    {$bcr->numero} : aucune ligne de régie — <error>à traiter à la main</error>");

                continue;
            }

            // D'abord la provision que le BCR déclare (l'encaisse sur lequel il a
            // été saisi), puis FIFO sur les autres encaisses de la même nomenclature.
            $candidats = ProvisionLigneRegie::whereHas('ligneRegie', fn($q) => $q
                ->where('regie_avance_id', $regie->id)
                ->where('nomenclature_id', $ligne->nomenclature_id))
                ->whereHas('decaissement', fn($q) => $q->whereIn('statut', ['verse', 'apure']))
                ->where('montant_disponible', '>', 0)
                ->with(['decaissement', 'ligneRegie'])
                ->get()
                ->sortBy(fn($p) => [
                    $p->id === $bcr->provision_ligne_regie_id ? 0 : 1,
                    $p->decaissement?->date_decaissement?->format('Ymd') ?? '99999999',
                    $p->id,
                ]);

            $reste    = $montant;
            $imputees = [];

            foreach ($candidats as $provision) {
                if ($reste <= 0.01) {
                    break;
                }

                $pris = min($reste, (float) $provision->montant_disponible);
                if ($pris <= 0) {
                    continue;
                }

                ProvisionConsommation::create([
                    'provision_ligne_regie_id' => $provision->id,
                    'bon_commande_regie_id'    => $bcr->id,
                    'montant'                  => $pris,
                ]);
                $provision->debiter($pris);

                $imputees[] = ['provision' => $provision, 'montant' => $pris];
                $reste      = round($reste - $pris, 2);
                $this->ecritures['traces']++;
            }

            if ($imputees === []) {
                $this->ecritures['non_impute'] += $montant;

                $this->line("    {$bcr->numero} " . $this->fmt($montant)
                    . ' : <error>aucun encaisse disponible</error> — la sortie n est pas finançable en l état');

                continue;
            }

            $bcr->forceFill([
                'provision_ligne_regie_id' => $bcr->provision_ligne_regie_id
                    ?: $imputees[0]['provision']->id,
                'montant_engage'           => $montant - $reste,
                'pourcentage_engage'       => (float) $bcr->montant_ttc > 0
                    ? round(($montant - $reste) / (float) $bcr->montant_ttc * 100, 2)
                    : 100,
                'reste_a_engager'          => $reste,
            ])->saveQuietly();

            ActivityLog::logAction($bcr, 'audit_regie_trace', [
                'montant_trace'      => $montant - $reste,
                'reste_non_impute'   => $reste,
                'montant_ttc'        => (float) $bcr->montant_ttc,
                'encaisses'       => implode(', ', array_map(
                    fn($i) => $i['provision']->decaissement?->numero . ' (' . $this->fmt($i['montant']) . ')',
                    $imputees
                )),
                'par'             => 'budget:audit-regie',
            ]);

            $this->ecritures['imprime'] += $montant - $reste;

            if ($reste > 0.01) {
                $this->ecritures['non_impute'] += $reste;
            }

            $this->line(sprintf(
                '    %-14s %14s → %s%s',
                $bcr->numero,
                $this->fmt($montant - $reste),
                implode(' + ', array_map(
                    fn($i) => $i['provision']->decaissement?->numero . ' ' . $this->fmt($i['montant']),
                    $imputees
                )),
                $reste > 0.01
                    ? ' — <error>' . $this->fmt($reste) . ' FCFA non imputables</error>'
                    : ''
            ));
        }
    }

    /**
     * Un BCR est rattaché à la ligne de l'encaisse qui absorbe la plus grosse
     * part de sa consommation — sinon le mini-budget afficherait une
     * consommation sur une ligne que le BCR n'a pas consommée.
     */
    private function alignerLesBonsCommande(RegieAvance $regie): void
    {
        $parts = DB::select("
            SELECT c.bon_commande_regie_id AS bcr, p.ligne_regie_avance_id AS ligne, SUM(c.montant) AS part
            FROM provision_consommations c
            JOIN provisions_lignes_regies p ON p.id = c.provision_ligne_regie_id
            JOIN bons_commande_regies b ON b.id = c.bon_commande_regie_id
            WHERE b.regie_avance_id = ? AND b.deleted_at IS NULL
            GROUP BY c.bon_commande_regie_id, p.ligne_regie_avance_id
        ", [$regie->id]);

        $meilleures = [];
        foreach ($parts as $p) {
            if (!isset($meilleures[$p->bcr]) || (float) $p->part > (float) $meilleures[$p->bcr]->part) {
                $meilleures[$p->bcr] = $p;
            }
        }

        foreach ($meilleures as $bcrId => $meilleure) {
            $actuelle = DB::table('bons_commande_regies')->where('id', $bcrId)->value('ligne_regie_avance_id');
            if ((int) $actuelle === (int) $meilleure->ligne) {
                continue;
            }

            DB::table('bons_commande_regies')
                ->where('id', $bcrId)
                ->update(['ligne_regie_avance_id' => $meilleure->ligne, 'updated_at' => now()]);
            $this->ecritures['bcr_relies']++;
        }
    }

    private function recalculer(RegieAvance $regie): void
    {
        foreach ($regie->decaissements as $tranche) {
            $tranche->load('provisions');
            $tranche->recalculerDepenses();
        }

        foreach ($regie->lignes()->get() as $ligne) {
            $ligne->recalculerMontants();
        }

        $regie->updateQuietly([
            'montant_decaisse' => $regie->decaissements()
                ->whereIn('statut', ['verse', 'apure'])
                ->sum('montant_accorde'),
        ]);
        $regie->recalculerMontants();
    }

    private function afficherApres(RegieAvance $regie): void
    {
        $this->line('  <comment>après audit :</comment>');

        foreach ($regie->fresh()->lignes()->with(['nomenclature', 'decaissementRegie'])->orderBy('id')->get() as $ligne) {
            $this->line(sprintf(
                '    ligne %-4d %-8s %-24s alloué %14s conso %14s dispo %14s <info>%6s %%</info>',
                $ligne->id,
                $ligne->nomenclature?->code ?? '—',
                mb_substr((string) ($ligne->decaissementRegie?->libelle_tranche ?? 'hors encaisse'), 0, 24),
                $this->fmt($ligne->montant_alloue),
                $this->fmt($ligne->montant_consomme),
                $this->fmt($ligne->montant_disponible),
                number_format($ligne->taux_consommation, 1, ',', ' ')
            ));
        }

        $regie->refresh();
        $this->line('    régie : encaissé ' . $this->fmt($regie->montant_decaisse)
            . ' — dépensé ' . $this->fmt($regie->montant_depense)
            . ' — taux ' . number_format($regie->taux_consommation, 1, ',', ' ') . ' %');

        // Rapprochement : ce qui est sorti (BCR) doit être couvert par un encaisse.
        $bonsCommande = (float) BonCommandeRegie::where('regie_avance_id', $regie->id)
            ->where('engage', true)
            ->whereNotIn('statut', ['annule', 'brouillon'])
            ->sum('montant_ttc');

        $retrace = (float) DB::table('provision_consommations')
            ->join('bons_commande_regies', 'bons_commande_regies.id', '=', 'provision_consommations.bon_commande_regie_id')
            ->where('bons_commande_regies.regie_avance_id', $regie->id)
            ->where('bons_commande_regies.engage', true)
            ->whereNotIn('bons_commande_regies.statut', ['annule', 'brouillon'])
            ->whereNull('bons_commande_regies.deleted_at')
            ->sum('provision_consommations.montant');

        $this->line('    rapprochement : BCR engagés ' . $this->fmt($bonsCommande)
            . ' FCFA — dont financés par un encaisse ' . $this->fmt($retrace) . ' FCFA'
            . ($bonsCommande - $retrace > 0.01
                ? ' — <error>' . $this->fmt($bonsCommande - $retrace) . ' FCFA sans encaisse</error>'
                : ' — <info>couverts</info>'));
    }

    private function fmt($montant): string
    {
        return number_format((float) $montant, 0, ',', ' ');
    }
}
