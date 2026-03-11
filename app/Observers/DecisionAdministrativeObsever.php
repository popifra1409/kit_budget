<?php

namespace App\Observers;

use App\Models\DecisionAdministrative;

/**
 * Observer pour les Décisions Administratives
 * 
 * ✅ VERSION OPTIMISÉE : Utilise les colonnes existantes !
 * - type_tva (existe déjà) au lieu de créer mode_tva
 * - montant_tva (existe déjà) pour le forfait TVA
 * - montant_ir_forfait (nouvelle colonne) pour le forfait IR
 */
class DecisionAdministrativeObserver
{
    /**
     * AVANT la création
     */
    public function creating(DecisionAdministrative $da): void
    {
        $this->calculerMontants($da);
    }

    /**
     * AVANT la mise à jour
     */
    public function updating(DecisionAdministrative $da): void
    {
        $this->calculerMontants($da);
    }

    /**
     * Calculer les montants selon le mode
     */
    protected function calculerMontants(DecisionAdministrative $da): void
    {
        // Si mode alternatif
        if ($da->mode_saisie_montants === 'alternatif') {
            $this->calculerModeAlternatif($da);
        } else {
            $this->calculerModeStandard($da);
        }
    }

    /**
     * Calculs Mode Standard (Brut → HT)
     * 
     * Formule : HT = Brut / (1 + TVA/100)
     */
    protected function calculerModeStandard(DecisionAdministrative $da): void
    {
        $brut = (float) ($da->montant_brut ?? 0);

        if ($brut <= 0) {
            return;
        }

        // Calculer HT selon type_tva
        if ($da->type_tva === 'taux') {
            $tauxTva = (float) ($da->taux_tva ?? 19.25);
            $montantHT = $brut / (1 + ($tauxTva / 100));
            $montantTVA = $montantHT * ($tauxTva / 100);
            $da->montant_tva = round($montantTVA, 2);
        } else {
            // type_tva = 'forfait'
            $montantTVA = (float) ($da->montant_tva ?? 0);
            $montantHT = $brut - $montantTVA;
        }

        $da->montant_ht = round($montantHT, 2);

        // Calculer les retenues sur HT
        $tauxCnps = (float) ($da->taux_cnps ?? 0);
        $montantCnps = $montantHT * ($tauxCnps / 100);

        // IR : Taux ou Forfait
        if (!empty($da->montant_ir_forfait)) {
            // IR en forfait
            $montantIR = (float) $da->montant_ir_forfait;
        } else {
            // IR par taux
            $tauxIrnc = (float) ($da->taux_irnc ?? 0);
            $montantIR = $montantHT * ($tauxIrnc / 100);
        }

        // Calculer redevance et FEICOM
        $montantRedevance = $this->calculerRedevance($da, $montantHT);
        $montantFeicom = $this->calculerFeicom($da, $montantHT);

        $autresRetenues = (float) ($da->autres_retenues ?? 0);

        // Total retenues et net
        $totalRetenues = $montantCnps + $montantIR + $montantRedevance + $montantFeicom + $autresRetenues;
        $montantNet = $montantHT - $totalRetenues;

        // Enregistrer
        $da->montant_cnps = round($montantCnps, 2);
        $da->montant_irnc = round($montantIR, 2);
        $da->montant_net = round($montantNet, 2);
    }

    /**
     * Calculs Mode Alternatif (HT Taxable + HT Non Taxable)
     * 
     * ✅ Utilise les colonnes existantes :
     * - type_tva (au lieu de mode_tva)
     * - montant_tva (pour le forfait TVA)
     * - montant_ir_forfait (nouvelle colonne)
     * 
     * Formules :
     * - HT Total = HT Non Taxable + HT Taxable
     * - TVA = Forfait (montant_tva) OU (HT Taxable × Taux / 100)
     * - IR = Forfait (montant_ir_forfait) OU (HT Total × Taux / 100)
     * - Net = HT Total - Total Retenues
     */
    protected function calculerModeAlternatif(DecisionAdministrative $da): void
    {
        $htNonTaxable = (float) ($da->montant_ht_non_taxable ?? 0);
        $htTaxable = (float) ($da->montant_ht_taxable ?? 0);
        $htTotal = $htNonTaxable + $htTaxable;

        if ($htTotal <= 0) {
            return;
        }

        // ═══════════════════════════════════════════════════════════
        // TVA : Utilise type_tva et montant_tva EXISTANTS
        // ═══════════════════════════════════════════════════════════
        if ($da->type_tva === 'forfait') {
            // TVA en forfait (colonne existante montant_tva)
            $montantTVA = (float) ($da->montant_tva ?? 0);
        } else {
            // TVA par taux (calculé)
            $tauxTVA = (float) ($da->taux_tva ?? 19.25);
            $montantTVA = ($htTaxable * $tauxTVA) / 100;
            $da->montant_tva = round($montantTVA, 2);
        }

        // ═══════════════════════════════════════════════════════════
        // IR : Utilise montant_ir_forfait (nouvelle colonne)
        // ═══════════════════════════════════════════════════════════
        if (!empty($da->montant_ir_forfait)) {
            // IR en forfait (nouvelle colonne)
            $montantIR = (float) $da->montant_ir_forfait;
        } else {
            // IR par taux (calculé)
            $tauxIR = (float) ($da->taux_irnc ?? 11);
            $montantIR = ($htTotal * $tauxIR) / 100;
        }

        // ═══════════════════════════════════════════════════════════
        // AUTRES RETENUES (toujours en forfait)
        // ═══════════════════════════════════════════════════════════
        $tauxCNPS = (float) ($da->taux_cnps ?? 0);
        $montantCNPS = ($htTotal * $tauxCNPS) / 100;

        $redevance = (float) ($da->montant_redevance_audiovisuelle ?? 0);
        $feicom = (float) ($da->montant_feicom ?? 0);
        $autres = (float) ($da->autres_retenues ?? 0);

        // ═══════════════════════════════════════════════════════════
        // CALCULS FINAUX
        // ═══════════════════════════════════════════════════════════
        $montantBrutCalcule = $htTotal + $montantTVA;
        $totalRetenues = $montantIR + $montantCNPS + $redevance + $feicom + $autres;
        $montantNet = $htTotal - $totalRetenues;

        // ═══════════════════════════════════════════════════════════
        // ENREGISTRER LES MONTANTS
        // ═══════════════════════════════════════════════════════════
        $da->montant_ht = round($htTotal, 2);
        $da->montant_irnc = round($montantIR, 2);
        $da->montant_cnps = round($montantCNPS, 2);
        $da->montant_net = round($montantNet, 2);

        // ⚠️ IMPORTANT : Ne pas écraser montant_brut car c'est saisi par l'utilisateur
        // Le brut calculé (htTotal + TVA) sert de vérification
    }

    /**
     * Calculer la redevance audiovisuelle
     */
    protected function calculerRedevance(DecisionAdministrative $da, float $montantHT): float
    {
        if ($da->type_redevance_audiovisuelle === 'taux') {
            $taux = (float) ($da->taux_redevance_audiovisuelle ?? 0);
            return ($montantHT * $taux) / 100;
        }

        return (float) ($da->montant_redevance_audiovisuelle ?? 0);
    }

    /**
     * Calculer le FEICOM
     */
    protected function calculerFeicom(DecisionAdministrative $da, float $montantHT): float
    {
        if ($da->type_feicom === 'taux') {
            $taux = (float) ($da->taux_feicom ?? 0);
            return ($montantHT * $taux) / 100;
        }

        return (float) ($da->montant_feicom ?? 0);
    }
}
