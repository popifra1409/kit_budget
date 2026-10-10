<?php

namespace App\Console\Commands;

use App\Models\NomenclatureBudgetaire;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Regroupe les nomenclatures qui partagent le même (exercice, code, type) : le code est la clé
 * métier, on garde la nomenclature canonique (celle référencée par les lignes, sinon la plus
 * ancienne) et on met les autres en corbeille après avoir réimputé leurs lignes.
 *
 * Aperçu par défaut — aucune écriture sans --run.
 */
class DedupNomenclature extends Command
{
    protected $signature = 'budget:dedup-nomenclature
        {--exercice= : Restreindre à un id d\'exercice}
        {--run : Appliquer les correctifs (sans cette option : aperçu seulement)}
        {--sans-index : Ne pas créer l\'index unique (exercice, code, type) après le nettoyage}';

    protected $description = 'Fusionner les nomenclatures en doublon de code puis contraindre le code en base';

    private const INDEX = 'nomenclature_exercice_code_type_active';

    public function handle(): int
    {
        $groupes = $this->groupesEnDoublon();

        if ($groupes->isEmpty()) {
            $this->info('Aucun doublon de code : (exercice, code, type) est déjà unique parmi les nomenclatures actives.');

            return $this->creerIndex() ? self::SUCCESS : self::FAILURE;
        }

        $plan = $groupes->map(fn($g) => $this->analyserGroupe($g));

        foreach ($plan as $groupe) {
            $this->rendreGroupe($groupe);
        }

        $aFusionner = $plan->where('refus', null)->values();
        $refuses = $plan->whereNotNull('refus')->values();

        $this->newLine();
        $this->line("Groupes traitables : <options=bold>{$aFusionner->count()}</> · groupes bloqués : <fg=red>{$refuses->count()}</>");

        if (!$this->option('run')) {
            $this->warn("APERÇU UNIQUEMENT — rien n'a été écrit. Relancez avec --run pour appliquer.");

            return self::SUCCESS;
        }

        $this->appliquerPlan($aFusionner);

        if ($refuses->isNotEmpty()) {
            $this->warn(count($refuses) . " groupe(s) laissé(s) en l'état : traitez-les à la main (voir ci-dessus), "
                . "l'index unique ne sera pas créé tant qu'ils subsistent.");

            return self::FAILURE;
        }

        return $this->creerIndex() ? self::SUCCESS : self::FAILURE;
    }

    // =========================================================
    // ANALYSE
    // =========================================================

    private function groupesEnDoublon(): \Illuminate\Support\Collection
    {
        return DB::table('nomenclature_budgetaire')
            ->whereNull('deleted_at')
            ->whereNotNull('exercice_id')
            ->when($this->option('exercice'), fn($q) => $q->where('exercice_id', (int) $this->option('exercice')))
            ->groupBy('exercice_id', 'code', 'type')
            ->havingRaw('COUNT(*) > 1')
            ->selectRaw('exercice_id, code, type, COUNT(*) as nb, array_agg(id ORDER BY id) as ids')
            ->get();
    }

    private function analyserGroupe(object $g): array
    {
        $ids = array_map('intval', explode(',', trim((string) $g->ids, '{}')));

        $noms = NomenclatureBudgetaire::parExercice((int) $g->exercice_id)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        $canonique = $noms->first(fn($n) => $this->lignesSur((int) $n->id)->isNotEmpty()) ?? $noms->first();

        // Réimputer une ligne sur la canonique peut la faire se heurter à une ligne déjà inscrite
        // dans la même prévision ou le même budget : le groupe est alors laissé au décideur.
        $previsionCanonique = $this->lignesDePrevisionSur((int) $canonique->id)->pluck('prevision_recette_id')->all();
        $budgetCanonique = $this->lignesBudgetairesSur((int) $canonique->id)->pluck('budget_id')->all();

        $refus = null;

        foreach ($noms as $n) {
            if ((int) $n->id === (int) $canonique->id) continue;

            foreach ($this->lignesDePrevisionSur((int) $n->id) as $ligne) {
                if (in_array((int) $ligne->prevision_recette_id, $previsionCanonique, true)) {
                    $refus = "la prévision n°{$ligne->prevision_recette_id} porte déjà une ligne sur ce code : "
                        . 'les montants doivent être fusionnés à la main.';
                }
            }

            foreach ($this->lignesBudgetairesSur((int) $n->id) as $ligne) {
                if (in_array((int) $ligne->budget_id, $budgetCanonique, true)) {
                    $refus = "le budget n°{$ligne->budget_id} porte déjà une ligne sur ce code : "
                        . 'les montants doivent être fusionnés à la main.';
                }
            }
        }

        return [
            'exercice' => (int) $g->exercice_id,
            'code' => (string) $g->code,
            'type' => (string) $g->type,
            'canonique' => $canonique,
            'doublons' => $noms->reject(fn($n) => (int) $n->id === (int) $canonique->id)->values(),
            'refs' => $this->referencesHorsLignes($noms->pluck('id')->all()),
            'refus' => $refus,
        ];
    }

    private function lignesSur(int $id): \Illuminate\Support\Collection
    {
        return $this->lignesDePrevisionSur($id)->concat($this->lignesBudgetairesSur($id));
    }

    private function lignesDePrevisionSur(int $id): \Illuminate\Support\Collection
    {
        return DB::table('lignes_previsions_recettes')
            ->whereNull('deleted_at')
            ->where('nomenclature_id', $id)
            ->get(['id', 'prevision_recette_id', 'code_nomenclature', 'libelle_nomenclature']);
    }

    private function lignesBudgetairesSur(int $id): \Illuminate\Support\Collection
    {
        return DB::table('lignes_budgetaires')
            ->whereNull('deleted_at')
            ->where('nomenclature_id', $id)
            ->get(['id', 'budget_id']);
    }

    /**
     * Toutes les tables qui pointent sur ces nomenclatures, hors lignes de travail : un document
     * historique garde sa référence (la nomenclature reste en base, seulement en corbeille).
     */
    private function referencesHorsLignes(array $ids): array
    {
        $colonnes = DB::select("
            SELECT DISTINCT kcu.table_name, kcu.column_name
            FROM information_schema.constraint_column_usage ccu
            JOIN information_schema.key_column_usage kcu
              ON kcu.constraint_name = ccu.constraint_name
             AND kcu.table_schema = ccu.table_schema
            WHERE ccu.table_name = 'nomenclature_budgetaire'
              AND ccu.table_schema = 'public'
              AND kcu.table_name <> 'nomenclature_budgetaire'
        ");

        $refs = [];
        $schema = DB::getSchemaBuilder();

        foreach ($colonnes as $c) {
            $table = (string) $c->table_name;
            $col = (string) $c->column_name;

            if (in_array($table, ['lignes_budgetaires', 'lignes_previsions_recettes'], true)) continue;

            try {
                $nb = DB::table($table)
                    ->when($schema->hasColumn($table, 'deleted_at'), fn($q) => $q->whereNull('deleted_at'))
                    ->whereIn($col, $ids)
                    ->count();
            } catch (\Throwable) {
                continue;
            }

            if ($nb > 0) $refs[] = "{$table}.{$col} : {$nb}";
        }

        return $refs;
    }

    // =========================================================
    // RENDU
    // =========================================================

    private function rendreGroupe(array $groupe): void
    {
        $c = $groupe['canonique'];

        $this->newLine();
        $this->line("<fg=yellow>exercice {$groupe['exercice']} · code {$groupe['code']} · type {$groupe['type']}</>");
        $this->line("  conservée — n°{$c->id} « {$c->libelle} » (" . $this->lignesSur((int) $c->id)->count() . ' ligne(s) de travail)');

        foreach ($groupe['doublons'] as $d) {
            $nbLignes = $this->lignesSur((int) $d->id)->count();
            $this->line("  en corbeille — n°{$d->id} « {$d->libelle} » ({$nbLignes} ligne(s) à réimputer)");
        }

        if ($groupe['refs'] !== []) {
            $this->line('  références non modifiées (documents, historique) : ' . implode(' · ', $groupe['refs']));
        }

        // La ligne de travail est réimputée sur la nomenclature conservée : si les libellés diffèrent,
        // c'est celle qui reste qu'il faut corriger ensuite depuis « Nomenclatures ».
        $libellD = $groupe['doublons']->first(
            fn($d) => mb_strtolower(trim((string) $d->libelle)) !== mb_strtolower(trim((string) $c->libelle))
        );

        if ($libellD) {
            $this->line("  <fg=cyan>à vérifier</> — « {$c->libelle} » (conservée) diffère de « {$libellD->libelle} » (n°{$libellD->id}) : "
                . 'corrigez le libellé de la nomenclature conservée.');
        }

        if ($groupe['refus']) {
            $this->line("<fg=red>  BLOQUÉ</> — {$groupe['refus']}");
        }
    }

    // =========================================================
    // ÉCRITURE
    // =========================================================

    private function appliquerPlan(\Illuminate\Support\Collection $plan): void
    {
        $maintenant = now();

        DB::transaction(function () use ($plan, $maintenant) {
            foreach ($plan as $groupe) {
                $canonique = $groupe['canonique'];

                foreach ($groupe['doublons'] as $d) {
                    $lignesPrev = $this->lignesDePrevisionSur((int) $d->id);
                    $lignesBud = $this->lignesBudgetairesSur((int) $d->id);

                    if ($lignesPrev->isNotEmpty()) {
                        DB::table('lignes_previsions_recettes')
                            ->whereNull('deleted_at')
                            ->where('nomenclature_id', $d->id)
                            ->update([
                                'nomenclature_id' => $canonique->id,
                                'code_nomenclature' => $canonique->code,
                                'libelle_nomenclature' => $canonique->libelle,
                                'updated_at' => $maintenant,
                            ]);
                    }

                    if ($lignesBud->isNotEmpty()) {
                        DB::table('lignes_budgetaires')
                            ->whereNull('deleted_at')
                            ->where('nomenclature_id', $d->id)
                            ->update(['nomenclature_id' => $canonique->id, 'updated_at' => $maintenant]);
                    }

                    // Les enfants directs suivent la canonique, à condition qu'elle soit rattachée
                    // au même parent (sinon la hiérarchie serait déformée).
                    if ($this->parentsIdentiques($d, $canonique)) {
                        $nbEnfants = DB::table('nomenclature_budgetaire')
                            ->whereNull('deleted_at')
                            ->where('parent_id', $d->id)
                            ->count();

                        if ($nbEnfants > 0) {
                            DB::table('nomenclature_budgetaire')
                                ->whereNull('deleted_at')
                                ->where('parent_id', $d->id)
                                ->update(['parent_id' => $canonique->id, 'updated_at' => $maintenant]);

                            $this->line("  n°{$d->id} : {$nbEnfants} enfant(s) rattaché(s) à n°{$canonique->id}");
                        }
                    }

                    // Soft delete : les références des documents et mouvements restent valides.
                    NomenclatureBudgetaire::find($d->id)?->delete();

                    $this->line('  n°' . $d->id . " → corbeille, "
                        . ($lignesPrev->count() + $lignesBud->count()) . " ligne(s) réimputée(s) sur n°{$canonique->id}");
                }
            }
        });
    }

    private function parentsIdentiques(NomenclatureBudgetaire $a, NomenclatureBudgetaire $b): bool
    {
        return (int) $a->parent_id === (int) $b->parent_id;
    }

    private function creerIndex(): bool
    {
        if ($this->option('sans-index')) {
            $this->line('Index unique non créé (--sans-index).');

            return true;
        }

        $restants = $this->groupesEnDoublon()->count();

        if ($restants > 0) {
            $this->warn("Index non créé : il reste {$restants} groupe(s) en doublon.");

            return false;
        }

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS ' . self::INDEX . '
            ON nomenclature_budgetaire (exercice_id, code, type)
            WHERE deleted_at IS NULL');

        $this->info('Index unique créé : un même code ne peut plus être doublonné dans un exercice.');

        return true;
    }
}
