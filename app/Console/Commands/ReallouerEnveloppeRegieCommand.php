<?php
// app/Console/Commands/ReallouerEnveloppeRegieCommand.php

namespace App\Console\Commands;

use App\Models\DecaissementRegie;
use App\Models\RegieAvance;
use Illuminate\Console\Command;

/**
 * Re-gradation des régies dont l'enveloppe a été calculée sur le NET d'une DA.
 *
 * Une avance remise à une régie ne règle aucun fournisseur : les retenues de la
 * DA (IR, CNPS, IRNC, autres retenues) ne doivent pas la réduire, puisque la
 * régie retient elle-même l'IR à chaque paiement de fournisseur et le reverse
 * au vu de l'« État des retenues IR ». Les régies créées avant cette règle
 * portent donc une enveloppe amputée — pour le CHUY, 47 250 000 au lieu de
 * 50 000 000, soit des tranches de 23 625 000 au lieu de 25 000 000 — et leurs
 * BCR payés ne rentrent plus dans les encaisses (dépassement affiché en annexe
 * du compte d'emploi).
 *
 * La commande ne touche que les liaisons dont le `montant_da` sort la règle
 * d'avant — le net de la DA, ou le brut moins ses retenues saisies : un montant
 * retouché à la main par l'ordonnateur est respecté et seulement signalé.
 * Le delta est ensuite réparti sur les tranches versées/apurées au prorata de
 * leur montant accordé (la dernière absorbe l'arrondi), puis les lignes de
 * mini-budget et les provisions sont réalignées par `creerProvisions()`.
 *
 *   php artisan regie:reallouer-enveloppe                  → simulation, toutes les régies
 *   php artisan regie:reallouer-enveloppe RAV26-00001      → simulation, une régie
 *   php artisan regie:reallouer-enveloppe RAV26-00001 --appliquer
 */
class ReallouerEnveloppeRegieCommand extends Command
{
    protected $signature = 'regie:reallouer-enveloppe {regie? : Numéro de la régie} {--appliquer : Enregistrer (sinon simulation)}';

    protected $description = "Re-calculer l'enveloppe des régies sur le brut des DA qui les financent";

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');
        $f = fn($v) => number_format((float) $v, 0, ',', ' ');

        $regies = RegieAvance::withoutGlobalScopes()
            ->when($this->argument('regie'), fn($q, $n) => $q->where('numero', $n))
            ->orderBy('numero')
            ->get();

        if ($regies->isEmpty()) {
            $this->error('Aucune régie ne correspond.');
            return self::FAILURE;
        }

        if ($appliquer && !$this->confirm('Une sauvegarde complète de la base a-t-elle été faite ?', false)) {
            return self::FAILURE;
        }

        foreach ($regies as $regie) {
            $liaisons = $regie->decisionsSource()->with('decisionAdministrative')->get();

            if ($liaisons->isEmpty()) {
                $this->line("⟹ {$regie->numero} : aucune DA source, enveloppe saisie à la main — non touchée.");
                continue;
            }

            // ── 1. Ce que chaque DA devrait remettre ──────────────────
            $carte = [];
            foreach ($liaisons as $liaison) {
                $da = $liaison->decisionAdministrative;
                if (!$da) {
                    $this->warn("   {$regie->numero} : DA {$liaison->decision_administrative_id} introuvable — régie ignorée.");
                    continue 2;
                }

                // Ce que donnait l'ancienne règle : le net, c'est-à-dire le brut
                // amputé des retenues. Deux variantes selon l'état de la DA —
                // la colonne `montant_net` (recalculée ou saisie) et le brut
                // moins les retenues effectivement stockées, qui divergent quand
                // une DA a été retouchée après coup.
                $retenuesSaisies = (float) $da->montant_cnps
                    + (float) $da->montant_ir
                    + (float) $da->montant_irnc
                    + (float) $da->total_taxes
                    + (float) $da->autres_retenues;

                $carte[] = [
                    'liaison'     => $liaison,
                    'da'          => $da,
                    'actuel'      => (float) $liaison->montant_da,
                    'net'         => (float) $da->montant_net,
                    'netRetenues' => (float) $da->montant_brut - $retenuesSaisies,
                    'attendu'     => $da->montantPourRegie(),
                ];
            }

            $aCorriger = [];
            foreach ($carte as $c) {
                $presNet        = abs($c['actuel'] - $c['net']) < 0.5;
                $presNetRetenues = abs($c['actuel'] - $c['netRetenues']) < 0.5;
                $memeMontant    = abs($c['attendu'] - $c['actuel']) < 0.5;

                if ($memeMontant) {
                    continue;                       // déjà conforme
                }
                if ($presNet || $presNetRetenues) {
                    $aCorriger[] = $c;              // amputé par l'ancienne règle
                    continue;
                }

                // Valeur qui ne vient d'aucun calcul connu : saisie manuelle, ou
                // enveloppe volontairement inférieure au brut. On n'y touche pas.
                $this->warn(sprintf(
                    '   %s : DA %s — montant_da %s ne correspond ni au net (%s) ni au brut (%s) de la DA. Saisie respectée, non touchée.',
                    $regie->numero, $c['da']->numero, $f($c['actuel']), $f($c['net']), $f($c['attendu'])
                ));
            }

            if ($aCorriger === []) {
                $this->line("✓ {$regie->numero} : enveloppe déjà alignée sur le brut des DA (" . $f($regie->montant_alloue) . ').');
                continue;
            }

            $ancienneEnveloppe = (float) $regie->montant_alloue;
            $delta = array_sum(array_map(fn($c) => $c['attendu'] - $c['actuel'], $aCorriger));
            $nouvelleEnveloppe = $ancienneEnveloppe + $delta;

            $this->newLine();
            $this->info(($appliquer ? '' : '[simulation] ') . "{$regie->numero} — {$regie->labelType}");
            foreach ($aCorriger as $c) {
                $this->line(sprintf(
                    '   DA %-12s montant_da %s → %s   (net %s, brut %s)',
                    $c['da']->numero, $f($c['actuel']), $f($c['attendu']), $f($c['net']), $f($c['da']->montant_brut)
                ));
            }
            $this->line(sprintf(
                '   enveloppe : %s → %s (+' . '%s' . ')',
                $f($ancienneEnveloppe), $f($nouvelleEnveloppe), $f($delta)
            ));

            // ── 2. Répartition du delta sur les tranches déjà versées ──
            $tranches = $regie->decaissements()
                ->whereIn('statut', ['verse', 'apure'])
                ->orderBy('numero')
                ->get();

            $repartis = [];

            if ($tranches->isEmpty()) {
                $this->line('   aucune tranche versée : seule l\'enveloppe bouge, les demandes restent à saisir.');
            } else {
                $totalAccorde = (float) $tranches->sum('montant_accorde');
                $dejaAttribue = 0.0;

                foreach ($tranches->values() as $index => $t) {
                    $nouveau = $index === $tranches->count() - 1
                        ? round($totalAccorde + $delta - $dejaAttribue, 0)
                        : round($totalAccorde > 0
                            ? (float) $t->montant_accorde * (1 + $delta / $totalAccorde)
                            : ($delta / $tranches->count()), 0);
                    $dejaAttribue += $nouveau;
                    $repartis[$t->id] = $nouveau;

                    $this->line(sprintf(
                        '   %-14s %-10s %s → %s',
                        $t->numero, $t->statut, $f($t->montant_accorde), $f($nouveau)
                    ));
                }

                if ($appliquer) {
                    $this->warn('   les mandats déjà édités de ces tranches sont à réimprimer');
                }
            }

            if (!$appliquer) {
                continue;
            }

            // ── 3. Écrire ──────────────────────────────────────────────
            foreach ($aCorriger as $c) {
                $c['liaison']->updateQuietly(['montant_da' => $c['attendu']]);
            }

            $regie->updateQuietly([
                'montant_alloue'    => $nouvelleEnveloppe,
                'encaisse_annuelle' => (float) $regie->encaisse_annuelle === $ancienneEnveloppe
                    ? $nouvelleEnveloppe
                    : $regie->encaisse_annuelle,
            ]);

            foreach ($tranches as $t) {
                $nouveau = $repartis[$t->id];

                $t->updateQuietly([
                    'montant_accorde' => $nouveau,
                    'montant_demande' => (float) $t->montant_demande === (float) $t->montant_accorde
                        ? $nouveau
                        : $t->montant_demande,
                ]);
            }

            // Décaissé = Σ des tranches versées ; la provision suit l'accordé.
            $regie->updateQuietly([
                'montant_decaisse' => (float) DecaissementRegie::where('regie_avance_id', $regie->id)
                    ->whereIn('statut', ['verse', 'apure'])->sum('montant_accorde'),
            ]);

            $regie->refresh();

            foreach ($tranches as $t) {
                $t->creerProvisions($regie, $repartis[$t->id]);
                $t->refresh()->recalculerDepenses();
            }

            $regie->refresh();
            $this->line(sprintf(
                '   enregistré : alloué %s · décaissé %s · engagé %s · disponible %s',
                $f($regie->montant_alloue), $f($regie->montant_decaisse),
                $f($regie->montant_engage), $f($regie->montant_disponible)
            ));
        }

        if (!$appliquer) {
            $this->newLine();
            $this->warn('Aucune écriture. Relancer avec --appliquer après sauvegarde de la base.');
        }

        return self::SUCCESS;
    }
}
