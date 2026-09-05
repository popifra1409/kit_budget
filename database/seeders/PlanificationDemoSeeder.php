<?php

namespace Database\Seeders;

use App\Models\ActionSousProgramme;
use App\Models\CspMinistereSante;
use App\Models\ParametresStructure;
use App\Models\PlanStrategiqueEp;
use App\Models\SousProgrammeEp;
use Illuminate\Database\Seeder;
use App\Models\ProjetStrategique;

/**
 * Seeder de demonstration base sur l'exemple concret du
 * "Guide d'arrimage des Etablissements Publics aux Politiques
 * Sectorielles" (edition 2025), page 60 - illustration reelle
 * sur l'Hopital General de Yaounde.
 */
class PlanificationDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. CSP - Cadre Strategique de Performance du Ministere de la Sante
        $csp = CspMinistereSante::firstOrCreate(
            ['code' => 'CSP-MINSANTE-2026'],
            [
                'libelle' => 'Cadre Stratégique de Performance du Ministère de la Santé Publique',
                'description' => "Document de cadrage stratégique de la tutelle technique, déclinant "
                    . "les orientations de la Stratégie du Secteur Santé et de la SND30 en objectifs "
                    . "de performance opposables aux établissements publics sous tutelle.",
                'periode_debut' => '2026-01-01',
                'periode_fin' => '2030-12-31',
                'statut' => 'en_vigueur',
            ]
        );

        // 2. PSP - Plan Strategique de Performance de l'Hopital General de Yaounde
        $psp = PlanStrategiqueEp::firstOrCreate(
            ['code' => 'PSP-HGY-2026-2030'],
            [
                'csp_ministere_id' => $csp->id,
                'parametres_structure_id' => ParametresStructure::getParametres()?->id,
                'libelle' => 'Plan Stratégique de Performance 2026-2030 — Hôpital Général de Yaoundé',
                'description' => "Décline les objectifs poursuivis par l'établissement ainsi que les "
                    . "interventions menées sur la période, en cohérence avec le Cadre Stratégique de "
                    . "Performance du Ministère de la Santé Publique.",
                'periode_debut' => '2026-01-01',
                'periode_fin' => '2030-12-31',
                'statut' => 'en_vigueur',
            ]
        );

        // 3. Sous-programme "Approvisionnement en medicament"
        $sousProgramme = SousProgrammeEp::firstOrCreate(
            ['code' => 'SP-02'],
            [
                'plan_strategique_ep_id' => $psp->id,
                'libelle' => 'Approvisionnement en médicament',
                'description' => "Sous-programme opérationnel visant à garantir la disponibilité "
                    . "des médicaments et intrants médicaux, et à améliorer le cadre de travail "
                    . "des services associés.",
                'statut' => 'en_vigueur',
            ]
        );

        // 4. Actions du sous-programme (exemple exact du guide, page 60)
        ActionSousProgramme::firstOrCreate(
            ['code' => '01'],
            [
                'sous_programme_ep_id' => $sousProgramme->id,
                'libelle' => 'Amélioration du cadre de travail',
                'description' => "Acquisition de mobilier de bureau pour les services impliqués "
                    . "dans l'approvisionnement en médicament.",
                'statut' => 'en_vigueur',
            ]
        );

        ActionSousProgramme::firstOrCreate(
            ['code' => '02'],
            [
                'sous_programme_ep_id' => $sousProgramme->id,
                'libelle' => 'Hospitalisation',
                'description' => "Acquisition de lits d'hospitalisation.",
                'statut' => 'en_vigueur',
            ]
        );

        $action1 = ActionSousProgramme::where('code', '01')->first();
        $action2 = ActionSousProgramme::where('code', '02')->first();

        if ($action1) {
            ProjetStrategique::firstOrCreate(
                ['code' => 'PST-MOBILIER-2026'],
                [
                    'action_sous_programme_id' => $action1->id,
                    'libelle' => 'Acquisition du mobilier de bureau',
                    'description' => "Acquisition de mobilier de bureau pour l'amélioration du cadre de travail.",
                    'statut' => 'en_cours',
                ]
            );
        }

        if ($action2) {
            ProjetStrategique::firstOrCreate(
                ['code' => 'PST-LITS-2026'],
                [
                    'action_sous_programme_id' => $action2->id,
                    'libelle' => 'Acquisition des lits d\'hospitalisation',
                    'description' => "Acquisition de lits d'hospitalisation.",
                    'statut' => 'en_cours',
                ]
            );
        }

        $this->command->info('✔ Données de démonstration Planification (CSP/PSP/Sous-programme/Actions) créées.');
    }
}
