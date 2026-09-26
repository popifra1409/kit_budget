<?php
// app/Console/Commands/CorrectifsDonnees.php

namespace App\Console\Commands;

use App\Models\Action;
use App\Models\Engagement;
use App\Models\LigneBudgetaire;
use App\Models\SousProgrammeEp;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Correctifs de donnees de septembre 2026, rejouables sur n'importe quelle base
 * (developpement, recette, production). Chaque etape DETECTE elle-meme les cas
 * a corriger : elle ne suppose rien des donnees de la base locale.
 *
 *   php artisan donnees:correctifs              → SIMULATION (rien n'est modifie)
 *   php artisan donnees:correctifs --appliquer  → application (apres sauvegarde)
 *
 * Idempotente : une seconde execution ne trouve plus rien a corriger.
 */
class CorrectifsDonnees extends Command
{
    protected $signature = 'donnees:correctifs
                            {--appliquer : Appliquer les corrections (sans cette option : simulation)}';

    protected $description = 'Correctifs de donnees (types polymorphes, lignes_engagement, arrondis, rattachement des sous-programmes), en simulation par defaut';

    protected bool $appliquer = false;

    public function handle(): int
    {
        $this->appliquer = (bool) $this->option('appliquer');

        $this->newLine();
        $this->appliquer
            ? $this->warn('⚠️  MODE APPLICATION : les corrections vont être écrites en base.')
            : $this->info('🔎 SIMULATION : aucune modification ne sera faite.');

        if ($this->appliquer && !$this->confirm('Une sauvegarde complète de la base a-t-elle été faite juste avant ?')) {
            $this->error('Arrêt : faites d\'abord une sauvegarde de la base.');
            return self::FAILURE;
        }

        $bilan = [];

        $this->titre('1. Types polymorphes (alias → nom complet de classe)');
        $bilan['types_polymorphes'] = $this->normaliserTypesPolymorphes();

        $this->titre('2. lignes_engagement alignées sur le montant des engagements');
        $bilan['lignes_engagement'] = $this->alignerLignesEngagement();

        $this->titre('3. Écarts d\'arrondi sur la colonne "engage" (< 1 FCFA)');
        $bilan['arrondis_engage'] = $this->corrigerArrondisEngage();

        $this->titre('4. Rattachement des sous-programmes au programme qui porte les actions');
        $bilan['sous_programmes'] = $this->rattacherSousProgrammes();

        $this->newLine();
        $this->table(['Étape', $this->appliquer ? 'Corrigés' : 'À corriger'], collect($bilan)->map(fn($n, $k) => [$k, $n])->values()->all());

        if ($this->appliquer) {
            activity('security')
                ->event('correctifs_donnees')
                ->withProperties(['bilan' => $bilan, 'commande' => $this->getName()])
                ->log('Correctifs de données appliqués : ' . array_sum($bilan) . ' correction(s)');

            $this->info('✅ Corrections appliquées et inscrites au journal d\'activité (catégorie Sécurité).');
        } else {
            $this->info('Simulation terminée. Relisez la liste, puis relancez avec --appliquer.');
        }

        return self::SUCCESS;
    }

    // ────────────────────────────────────────────────────────────────
    // 1. Alias de morph map → nom complet de classe
    // ────────────────────────────────────────────────────────────────
    protected function normaliserTypesPolymorphes(): int
    {
        $colonnes = [
            ['engagements', 'engageable_type'],
            ['engagements', 'beneficiaire_type'],
            ['ordonnances_paiement', 'beneficiaire_type'],
        ];

        $total = 0;

        foreach ($colonnes as [$table, $colonne]) {
            $alias = DB::table($table)
                ->whereNotNull($colonne)
                ->where($colonne, 'not like', '%\\\\%')
                ->distinct()
                ->pluck($colonne);

            foreach ($alias as $valeur) {
                $classe = Relation::getMorphedModel($valeur);

                if (!$classe) {
                    $this->line("  ⏭️  {$table}.{$colonne} = '{$valeur}' : valeur libre, non modifiée");
                    continue;
                }

                $nombre = DB::table($table)->where($colonne, $valeur)->count();
                $total += $nombre;
                $this->line("  {$table}.{$colonne} : '{$valeur}' → '{$classe}' ({$nombre} ligne(s))");

                if ($this->appliquer) {
                    DB::table($table)->where($colonne, $valeur)->update([$colonne => $classe]);
                }
            }
        }

        if ($total === 0) {
            $this->line('  Rien à corriger.');
        }

        return $total;
    }

    // ────────────────────────────────────────────────────────────────
    // 2. lignes_engagement desalignee apres un avenant
    //    (uniquement les engagements imputes sur UNE seule ligne)
    // ────────────────────────────────────────────────────────────────
    protected function alignerLignesEngagement(): int
    {
        $candidats = DB::table('engagements as e')
            ->join('lignes_engagement as le', 'le.engagement_id', '=', 'e.id')
            ->whereNull('e.deleted_at')
            ->groupBy('e.id', 'e.numero', 'e.montant_engage')
            ->havingRaw('COUNT(le.id) = 1')
            ->havingRaw('ABS(SUM(le.montant) - e.montant_engage) > 0.001')
            ->get(['e.id', 'e.numero', 'e.montant_engage', DB::raw('SUM(le.montant) AS montant_ligne'), DB::raw('MIN(le.id) AS ligne_id')]);

        foreach ($candidats as $c) {
            $this->line("  {$c->numero} : " . $this->fcfa($c->montant_ligne) . ' → ' . $this->fcfa($c->montant_engage));

            if (!$this->appliquer) {
                continue;
            }

            DB::transaction(function () use ($c) {
                DB::table('lignes_engagement')->where('id', $c->ligne_id)
                    ->update(['montant' => $c->montant_engage, 'updated_at' => now()]);

                activity('security')
                    ->performedOn(Engagement::withTrashed()->withoutGlobalScope('exercice')->find($c->id))
                    ->event('correction_donnees')
                    ->withProperties([
                        'table'   => 'lignes_engagement',
                        'ligne'   => $c->ligne_id,
                        'ancien'  => (float) $c->montant_ligne,
                        'nouveau' => (float) $c->montant_engage,
                        'motif'   => "Montant de l'engagement modifié (avenant) sans mise à jour de lignes_engagement",
                    ])
                    ->log("Alignement lignes_engagement sur l'engagement {$c->numero}");
            });
        }

        // Engagements multi-lignes desalignes : jamais corriges automatiquement (repartition inconnue)
        $multi = DB::table('engagements as e')
            ->join('lignes_engagement as le', 'le.engagement_id', '=', 'e.id')
            ->whereNull('e.deleted_at')
            ->groupBy('e.id', 'e.numero', 'e.montant_engage')
            ->havingRaw('COUNT(le.id) > 1')
            ->havingRaw('ABS(SUM(le.montant) - e.montant_engage) > 0.001')
            ->pluck('e.numero');

        foreach ($multi as $numero) {
            $this->warn("  ⚠️  {$numero} : réparti sur plusieurs lignes et désaligné → À EXAMINER MANUELLEMENT (non modifié)");
        }

        if ($candidats->isEmpty()) {
            $this->line('  Rien à corriger.');
        }

        return $candidats->count();
    }

    // ────────────────────────────────────────────────────────────────
    // 3. Ecarts d'arrondi (< 1 FCFA) entre la colonne 'engage' et lignes_engagement.
    //    Les ecarts >= 1 FCFA ne sont JAMAIS recalcules automatiquement.
    // ────────────────────────────────────────────────────────────────
    protected function corrigerArrondisEngage(): int
    {
        $corriges = 0;

        foreach (LigneBudgetaire::with(['nomenclature', 'budget'])->get() as $ligne) {
            $recalcule = (float) $ligne->requeteEngagementsActifs()->sum('le.montant');
            $ecart = abs($recalcule - (float) $ligne->engage);

            if ($ecart <= 0.001) {
                continue;
            }

            $libelle = ($ligne->nomenclature?->code ?? "#{$ligne->id}") . ' (' . ($ligne->budget?->code ?? $ligne->budget_id) . ')';

            if ($ecart < 1) {
                $corriges++;
                $this->line("  {$libelle} : " . $this->fcfa($ligne->engage) . ' → ' . $this->fcfa($recalcule));

                if ($this->appliquer) {
                    $ligne->recalculerDepuisEngagements();
                }
            } else {
                $this->warn("  ⚠️  {$libelle} : écart de " . $this->fcfa($ecart) . ' → À EXAMINER (non modifié)'
                    . ($this->appliquer ? '' : ' — normal en simulation si l\'étape 2 le corrige'));
            }
        }

        if ($corriges === 0) {
            $this->line('  Aucun écart d\'arrondi à corriger.');
        }

        return $corriges;
    }

    // ────────────────────────────────────────────────────────────────
    // 4. Sous-programmes sans programme EP : si leur programme de rattachement
    //    porte lui-meme les actions, on le reprend comme programme EP.
    // ────────────────────────────────────────────────────────────────
    protected function rattacherSousProgrammes(): int
    {
        $corriges = 0;

        foreach (SousProgrammeEp::whereNull('code_programme_ep')->with('programmeBudgetaire')->get() as $sp) {
            $programme = $sp->programmeBudgetaire;

            $porteActions = $programme && Action::withoutGlobalScope('exercice')
                ->where('programme_id', $programme->id)
                ->exists();

            if (!$porteActions) {
                $this->warn("  ⚠️  {$sp->code} : à rattacher manuellement (rattachement " . ($programme?->code ?? 'absent') . ' sans action)');
                continue;
            }

            $corriges++;
            $this->line("  {$sp->code} → programme EP {$programme->code}");

            if ($this->appliquer) {
                $sp->update(['code_programme_ep' => $programme->code]);
            }
        }

        if ($corriges === 0) {
            $this->line('  Rien à rattacher automatiquement.');
        }

        return $corriges;
    }

    // ────────────────────────────────────────────────────────────────
    protected function titre(string $texte): void
    {
        $this->newLine();
        $this->line("<options=bold>{$texte}</>");
    }

    protected function fcfa($montant): string
    {
        return number_format((float) $montant, 2, ',', ' ') . ' FCFA';
    }
}
