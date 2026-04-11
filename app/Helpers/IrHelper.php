<?php

namespace App\Helpers;

use App\Models\ParametresStructure;

class IrHelper
{
    /**
     * Calcule l'IR selon les tranches définies dans les paramètres
     * 
     * @param float $montantHT Montant HT
     * @param float|null $tauxManuel Taux IR manuel (si fourni)
     * @return float Montant de l'IR
     */
    public static function calculerIR(float $montantHT, ?float $tauxManuel = null): float
    {
        // Si un taux manuel est fourni, l'utiliser
        if ($tauxManuel !== null && $tauxManuel > 0) {
            return $montantHT * ($tauxManuel / 100);
        }

        // Récupérer les paramètres de la structure active
        $parametres = ParametresStructure::where('actif', true)->first();

        if (!$parametres) {
            // Valeurs par défaut si pas de paramètres
            if ($montantHT <= 500000) {
                return $montantHT * 0.055;
            } elseif ($montantHT <= 3000000) {
                return $montantHT * 0.11;
            } else {
                return $montantHT * 0.15;
            }
        }

        // Utiliser les tranches des paramètres
        if ($montantHT <= $parametres->ir_tranche1_max) {
            return $montantHT * ($parametres->ir_tranche1_taux / 100);
        } elseif ($montantHT <= $parametres->ir_tranche2_max) {
            return $montantHT * ($parametres->ir_tranche2_taux / 100);
        } else {
            return $montantHT * ($parametres->ir_tranche3_taux / 100);
        }
    }

    /**
     * Calcule le net à payer (HT - IR)
     * 
     * @param float $montantHT
     * @param float|null $tauxManuel
     * @return float
     */
    public static function calculerNetAPayer(float $montantHT, ?float $tauxManuel = null): float
    {
        $ir = self::calculerIR($montantHT, $tauxManuel);
        return $montantHT - $ir;
    }
}
