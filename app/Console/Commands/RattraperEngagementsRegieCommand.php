<?php
// app/Console/Commands/RattraperEngagementsRegieCommand.php

namespace App\Console\Commands;

use App\Models\BonCommandeRegie;
use App\Models\DecaissementRegie;
use App\Models\ProvisionConsommation;
use App\Models\ProvisionLigneRegie;
use App\Models\RegieAvance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rattrapage des BCR PAYÉS dont la consommation n'est pas (entièrement) tracée sur les encaisses
 * (BCR payés avant la mise en place de l'engagement). Pour chacun, du plus ancien au plus récent,
 * la part non tracée est imputée sur les provisions VERSÉES de la régie, de l'encaisse le plus
 * ancien au plus récent, dans la limite de leur disponible. Le reliquat reste un dépassement à
 * financer (nouvel encaisse) : relancer la commande après le versement.
 *
 *   php artisan regie:rattraper-engagements                   → simulation, toutes les régies
 *   php artisan regie:rattraper-engagements RAV26-00001       → simulation, une régie
 *   php artisan regie:rattraper-engagements RAV26-00001 --appliquer
 */
class RattraperEngagementsRegieCommand extends Command
{
    protected $signature = 'regie:rattraper-engagements {regie? : Numéro de la régie} {--appliquer : Enregistrer (sinon simulation)}';

    protected $description = 'Impute sur les encaisses la consommation non tracée des BCR payés';

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');
        $f = fn($v) => number_format((float) $v, 0, ',', ' ');

        $regies = RegieAvance::withoutGlobalScopes()
            ->when($this->argument('regie'), fn($q, $n) => $q->where('numero', $n))
            ->get();

        if ($appliquer && !$this->confirm('Une sauvegarde complète de la base a-t-elle été faite ?', false)) {
            return self::FAILURE;
        }

        foreach ($regies as $regie) {
            // Remise à niveau préalable des provisions depuis la source de vérité
            $decaissements = $regie->decaissements()->whereIn('statut', ['verse', 'apure'])->get();
            if ($appliquer) {
                $decaissements->each->recalculerDepenses();
            }

            // ⚠️ Part non tracée gardée dans un tableau séparé (et non sur le modèle : un attribut
            //    ajouté au BCR serait inclus dans sa mise à jour, comme une colonne inexistante).
            $nonTrace = [];
            $bons = BonCommandeRegie::where('regie_avance_id', $regie->id)->where('statut', 'paye')
                ->orderBy('date_emission')->orderBy('id')->get()
                ->filter(function ($b) use (&$nonTrace) {
                    $nonTrace[$b->id] = max(0, round((float) $b->montant_ttc - (float) ProvisionConsommation::where('bon_commande_regie_id', $b->id)->sum('montant'), 2));
                    return $nonTrace[$b->id] >= 1;
                })
                ->values();

            if ($bons->isEmpty()) {
                continue;
            }

            // Provisions versées, de l'encaisse le plus ancien au plus récent, avec leur disponible
            $provisions = ProvisionLigneRegie::whereIn('decaissement_regie_id', $decaissements->pluck('id'))
                ->with('decaissement')
                ->get()
                ->sortBy(fn($p) => [$p->decaissement?->date_decaissement?->format('Ymd') ?? '99999999', $p->decaissement?->numero ?? '', $p->id])
                ->values();
            $dispo = $provisions->mapWithKeys(fn($p) => [$p->id => max(0, (float) $p->montant_disponible)])->all();

            $this->info("\n" . ($appliquer ? 'RATTRAPAGE' : 'SIMULATION') . " — {$regie->numero} {$regie->libelle}");
            $lignes = [];
            $reliquatTotal = 0.0;

            DB::transaction(function () use ($bons, $provisions, &$dispo, $appliquer, &$lignes, &$reliquatTotal, $f, $nonTrace) {
                foreach ($bons as $b) {
                    $restant = $nonTrace[$b->id];
                    $imputations = [];

                    foreach ($provisions as $p) {
                        if ($restant < 1) break;
                        $pris = min($restant, $dispo[$p->id]);
                        if ($pris < 1) continue;

                        $dispo[$p->id] -= $pris;
                        $restant -= $pris;
                        $imputations[] = ($p->decaissement?->numero ?? '?') . ' ' . $f($pris);

                        if ($appliquer) {
                            ProvisionConsommation::create([
                                'provision_ligne_regie_id' => $p->id,
                                'bon_commande_regie_id'    => $b->id,
                                'montant'                  => $pris,
                            ]);
                        }
                    }

                    $impute = $nonTrace[$b->id] - $restant;
                    $reliquatTotal += $restant;

                    if ($appliquer && $impute > 0) {
                        $engage = (float) ($b->montant_engage ?? 0) + $impute;
                        $b->updateQuietly([
                            'engage'             => true,
                            'montant_engage'     => $engage,
                            'pourcentage_engage' => (float) $b->montant_ttc > 0 ? round($engage / (float) $b->montant_ttc * 100, 2) : 100,
                            'reste_a_engager'    => max(0, (float) $b->montant_ttc - $engage),
                            'date_engagement'    => $b->date_engagement ?? $b->date_emission,
                        ]);
                        \App\Models\ActivityLog::logAction($b, 'rattrapage_engagement', [
                            'montant_impute' => $impute,
                            'reliquat_non_finance' => $restant,
                            'motif' => 'BCR payé avant la mise en place de l\'engagement',
                        ]);
                    }

                    $lignes[] = [$b->numero, $f($nonTrace[$b->id]), $f($impute), $restant >= 1 ? '⚠️ ' . $f($restant) : '—', implode(' + ', $imputations) ?: '—'];
                }
            });

            $this->table(['BCR', 'Non tracé', 'Imputé', 'Non financé', 'Encaisses'], $lignes);

            if ($appliquer) {
                $regie->decaissements()->whereIn('statut', ['verse', 'apure'])->get()->each->recalculerDepenses();
                $regie->refresh()->recalculerMontants();
            }

            $reliquatTotal >= 1
                ? $this->warn("Dépassement à financer : {$f($reliquatTotal)} FCFA — nouvelle DA sur la régie, « Accorder & Verser » une tranche, puis relancer cette commande.")
                : $this->info('✅ Toute la consommation des BCR payés est désormais imputée sur les encaisses.');
        }

        if (!$appliquer) {
            $this->line("\nSimulation : relancez avec --appliquer pour enregistrer.");
        }

        return self::SUCCESS;
    }
}
