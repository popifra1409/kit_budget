<?php

namespace Database\Seeders;

use App\Models\CspMinistereSante;
use App\Models\ParametresStructure;
use App\Models\PlanStrategiqueEp;
use App\Models\Programme;
use App\Models\SousProgrammeEp;
use Illuminate\Database\Seeder;

/**
 * Genere/actualise les Sous-Programmes strategiques (SousProgrammeEp)
 * a partir des Programmes budgetaires REELS deja en production
 * (niveau='programme' : codes 410, 412, 413, 414...).
 *
 * Conforme au Guide Methodologique de Planification Strategique
 * (MINEPAT, 3e edition, janvier 2026) : "Le sous-programme d'un EP
 * doit trouver son ancrage strategique dans un des programmes
 * techniques de l'administration de tutelle."
 *
 * Idempotent : relancable sans creer de doublons.
 */
class SousProgrammesStrategiquesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. S'assurer qu'un CSP et un PSP existent (container englobant)
        $csp = CspMinistereSante::firstOrCreate(
            ['code' => 'CSP-MINSANTE-2026'],
            [
                'libelle' => 'Cadre Stratégique de Performance du Ministère de la Santé Publique',
                'description' => "Document de cadrage stratégique de la tutelle technique.",
                'periode_debut' => '2026-01-01',
                'periode_fin' => '2030-12-31',
                'statut' => 'en_vigueur',
            ]
        );

        $psp = PlanStrategiqueEp::firstOrCreate(
            ['code' => 'PSP-HGY-2026-2030'],
            [
                'csp_ministere_id' => $csp->id,
                'parametres_structure_id' => ParametresStructure::getParametres()?->id,
                'libelle' => 'Plan Stratégique de Performance 2026-2030 — Hôpital Général de Yaoundé',
                'periode_debut' => '2026-01-01',
                'periode_fin' => '2030-12-31',
                'statut' => 'en_vigueur',
            ]
        );

        // 2. Nettoyage de l'exemple fictif "Approvisionnement en medicament" (SP-02),
        //    devenu incoherent : il inventait un sous-programme au lieu d'utiliser
        //    le vrai Programme de rattachement. Decommentez pour le retirer :
        // SousProgrammeEp::where('code', 'SP-02')->delete();

        // 3. Un Sous-Programme strategique par Programme budgetaire REEL
        $programmes = Programme::where('niveau', 'programme')->orderBy('code')->get();

        if ($programmes->isEmpty()) {
            $this->command->warn("⚠ Aucun Programme (niveau='programme') trouvé en base. Rien à générer.");
            return;
        }

        foreach ($programmes as $programme) {
            $sousProgramme = SousProgrammeEp::updateOrCreate(
                [
                    'plan_strategique_ep_id' => $psp->id,
                    'programme_budgetaire_id' => $programme->id,
                ],
                [
                    'code' => 'SP-' . $programme->code,
                    'libelle' => $programme->libelle,
                    'description' => $programme->description,
                    'statut' => 'en_vigueur',
                ]
            );

            $this->command->info("✔ Sous-Programme '{$sousProgramme->libelle}' (code {$sousProgramme->code}) synchronisé avec Programme {$programme->code}.");
        }

        $this->command->info('✔ ' . $programmes->count() . ' Sous-Programme(s) stratégique(s) généré(s)/actualisé(s) depuis les Programmes budgétaires réels.');
    }
}
