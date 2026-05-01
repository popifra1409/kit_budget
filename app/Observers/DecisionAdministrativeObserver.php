<?php

namespace App\Observers;

use App\Models\DecisionAdministrative;

class DecisionAdministrativeObserver
{
    public function creating(DecisionAdministrative $da): void
    {
        $this->calculerMontants($da);
    }

    public function updating(DecisionAdministrative $da): void
    {
        $this->calculerMontants($da);
    }

    public function updated(DecisionAdministrative $da): void
    {
        if ($da->isDirty('engagee') && $da->engagee) {
            $memoire = \App\Models\MemoireDepense::where(
                'decision_administrative_id',
                $da->id
            )->first();

            if ($memoire) {
                $numeroEngagement = \App\Models\Engagement::where('engageable_type', get_class($da))
                    ->where('engageable_id', $da->id)
                    ->value('numero');

                $memoire->updateQuietly([
                    'numero_ce' => $numeroEngagement,
                    'date_ce'   => $da->date_engagement,
                ]);
            }
        }
    }

    protected function calculerMontants(DecisionAdministrative $da): void
    {

        $mode = $da->getAttribute('mode_saisie')
            ?? $da->getOriginal('mode_saisie')
            ?? 'calcule';

        // ✅ Mode forfait — NE RIEN recalculer, préserver les montants saisis
        if ($mode === 'forfait') {
            \Log::info('Observer DA — mode forfait, calcul ignoré', [
                'montant_cnps'  => $da->montant_cnps,
                'montant_irnc'  => $da->montant_irnc,
                'montant_net'   => $da->montant_net,
            ]);
            return;
        }

        // ✅ Mode calculé — recalcul standard
        $this->calculerModeStandard($da);
    }

    protected function calculerModeStandard(DecisionAdministrative $da): void
    {
        $brut = (float) ($da->montant_brut ?? 0);

        if ($brut <= 0) return;

        // HT
        if ($da->type_tva === 'taux') {
            $tauxTva    = (float) ($da->taux_tva ?? 19.25);
            $montantHT  = round($brut / (1 + $tauxTva / 100), 2);
            $da->montant_tva = round($montantHT * ($tauxTva / 100), 2);
        } else {
            $montantHT = round($brut - (float) ($da->montant_tva ?? 0), 2);
        }

        $da->montant_ht = $montantHT;

        // ✅ CNPS
        $da->montant_cnps = round(
            $montantHT * ((float) ($da->taux_cnps ?? 0) / 100),
            2
        );

        // ✅ IRNC
        $da->montant_irnc = round(
            $montantHT * ((float) ($da->taux_irnc ?? 0) / 100),
            2
        );

        // Redevance
        $montantRedevance = $da->type_redevance_audiovisuelle === 'taux'
            ? round($montantHT * ((float) ($da->taux_redevance_audiovisuelle ?? 0) / 100), 2)
            : (float) ($da->montant_redevance_audiovisuelle ?? 0);
        $da->montant_redevance_audiovisuelle = $montantRedevance;

        // FEICOM
        $montantFeicom = $da->type_feicom === 'taux'
            ? round($montantHT * ((float) ($da->taux_feicom ?? 0) / 100), 2)
            : (float) ($da->montant_feicom ?? 0);
        $da->montant_feicom = $montantFeicom;

        $autres = (float) ($da->autres_retenues ?? 0);

        // ✅ Total et net
        $da->total_taxes = $da->montant_cnps
            + $da->montant_irnc
            + $montantRedevance
            + $montantFeicom
            + $autres;

        $da->montant_net = round($montantHT - $da->total_taxes, 2);
    }
}
