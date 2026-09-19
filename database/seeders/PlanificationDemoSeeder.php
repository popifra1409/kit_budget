<?php
namespace Database\Seeders;

use App\Models\CspMinistereSante;
use App\Models\ParametresStructure;
use App\Models\PlanStrategiqueEp;
use Illuminate\Database\Seeder;

/**
 * Cree/actualise le CSP et le PSP de base pour l'Hopital General
 * de Yaounde.
 *
 * NOTE : la generation des Sous-Programmes (a partir des vrais
 * Programmes budgetaires 412/413/414...) se fait desormais via
 * SousProgrammesStrategiquesSeeder, pas ici. Les anciennes sections
 * qui creaient un sous-programme fictif ("Approvisionnement en
 * medicament") ainsi que des ActionSousProgramme/ProjetStrategique
 * ont ete retirees suite a la reconciliation architecturale : ces
 * deux classes n'existent plus (fusionnees avec Action/Activite
 * existants du module Budget).
 */
class PlanificationDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. CSP - Cadre Strategique de Performance du Ministere de la Sante
        $csp = CspMinistereSante::updateOrCreate(
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
        PlanStrategiqueEp::updateOrCreate(
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

        $this->command->info('✔ CSP et PSP de base créés/actualisés. Lancez SousProgrammesStrategiquesSeeder pour générer les Sous-Programmes.');
    }
}
