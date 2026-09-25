<?php
// database/seeders/SousProgrammesStrategiquesSeeder.php

namespace Database\Seeders;

use App\Models\Action;
use App\Models\CspMinistereSante;
use App\Models\Exercice;
use App\Models\ParametresStructure;
use App\Models\PlanStrategiqueEp;
use App\Models\Programme;
use App\Models\SousProgrammeEp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Genere les Sous-Programmes strategiques (SousProgrammeEp) a partir des
 * Programmes budgetaires REELS (niveau='programme' : 412, 413, 414...).
 *
 * Conforme au Guide Methodologique de Planification Strategique
 * (MINEPAT, 3e edition, janvier 2026) : "Le sous-programme d'un EP
 * doit trouver son ancrage strategique dans un des programmes
 * techniques de l'administration de tutelle."
 *
 * Idempotent : relancable sans creer de doublons.
 * Ne remplace JAMAIS un libelle, une description ou un type deja saisis
 * (ils peuvent avoir ete corriges apres la revue des libelles).
 */
class SousProgrammesStrategiquesSeeder extends Seeder
{
    public function run(): void
    {
        $exercice = Exercice::getActif();

        if (!$exercice) {
            $this->command->error('✖ Aucun exercice actif : activez un exercice avant de lancer ce seeder.');
            return;
        }

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

        // 2. Retrait de l'exemple fictif "SP-02" (ancien seeder de demonstration).
        //    Supprime UNIQUEMENT s'il n'est rattache a aucun programme : un SP-02
        //    saisi volontairement avec un rattachement n'est jamais touche.
        //    A faire AVANT la creation : la limite de 4 sous-programmes par EP s'applique.
        $fictifs = SousProgrammeEp::where('plan_strategique_ep_id', $psp->id)
            ->where('code', 'SP-02')
            ->whereNull('programme_budgetaire_id')
            ->get();

        foreach ($fictifs as $fictif) {
            $fictif->delete();
            $this->command->warn("⚠ Sous-programme fictif '{$fictif->libelle}' (SP-02) supprimé : il n'était rattaché à aucun programme.");
        }

        // 3. Un Sous-Programme strategique par Programme budgetaire REEL de l'exercice actif
        $programmes = Programme::withoutGlobalScope('exercice')
            ->where('exercice_id', $exercice->id)
            ->where('niveau', 'programme')
            ->orderBy('code')
            ->get();

        if ($programmes->isEmpty()) {
            $this->command->warn("⚠ Aucun Programme (niveau='programme') pour l'exercice {$exercice->annee}. Rien à générer.");
            return;
        }

        foreach ($programmes as $programme) {
            $sousProgramme = SousProgrammeEp::firstOrNew([
                'plan_strategique_ep_id'  => $psp->id,
                'programme_budgetaire_id' => $programme->id,
            ]);

            $nouveau = !$sousProgramme->exists;

            // Donnees initiales : uniquement a la creation (ne jamais ecraser une saisie)
            if ($nouveau) {
                $sousProgramme->fill([
                    'code'        => 'SP-' . $programme->code,
                    'libelle'     => $programme->libelle,
                    'description' => $programme->description,
                    'statut'      => 'en_vigueur',
                    // Instruction du 22/01/2026 : 3 operationnels + 1 support maximum
                    'type'        => Str::contains(Str::upper(Str::ascii($programme->libelle)), 'GOUVERNANCE')
                        ? 'support'
                        : 'operationnel',
                ]);
            }

            // Programme qui porte les actions : si le programme de rattachement a lui-meme
            // des actions (cas 412/413/414), c'est lui. Sinon (cas P-410 -> SP-1), a renseigner
            // manuellement dans le formulaire.
            $porteActions = Action::withoutGlobalScope('exercice')
                ->where('programme_id', $programme->id)
                ->exists();

            if (blank($sousProgramme->code_programme_ep) && $porteActions) {
                $sousProgramme->code_programme_ep = $programme->code;
            }

            $sousProgramme->save();

            $lien = $sousProgramme->code_programme_ep
                ? "actions portées par {$sousProgramme->code_programme_ep}"
                : 'programme portant les actions À RENSEIGNER dans le formulaire';

            $this->command->info(($nouveau ? '✔ Créé' : '✔ Existant') . " : {$sousProgramme->code} — {$sousProgramme->libelle} ({$lien})");
        }

        $this->command->info('✔ ' . $programmes->count() . " sous-programme(s) stratégique(s) traité(s) pour l'exercice {$exercice->annee}.");
    }
}
