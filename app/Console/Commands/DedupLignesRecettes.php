<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\LignePrevisionRecette;
use App\Models\PrevisionRecette;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Le code de nomenclature est la clé métier d'une ligne de prévision de recettes :
 * deux lignes du même code dans la même prévision sont un doublon, même quand elles
 * pointent deux enregistrements de nomenclature différents (cas des 731207 créés par
 * un mouvement supprimé puis recréé). L'index unique existant ne porte que sur
 * (prevision_recette_id, nomenclature_id) et ne les voit donc pas.
 *
 * Une ligne orpheline — sans recouvrement et sans mouvement collectif encore actif —
 * ne sert à rien : elle part en corbeille, ses 12 mois avec elle. Tout ce qui porte
 * encore une trace comptable est signalé et laissé au décideur.
 *
 * Aperçu par défaut — aucune écriture sans --run.
 */
class DedupLignesRecettes extends Command
{
    protected $signature = 'budget:dedup-lignes-recettes
        {--prevision= : Restreindre à un id de prévision de recettes}
        {--run : Appliquer les correctifs (sans cette option : aperçu seulement)}
        {--fusionner : Reporter le montant de la ligne retirée sur la ligne conservée (doublon de montants distincts)}
        {--sans-index : Ne pas créer l\'index unique (prévision, code) après le nettoyage}';

    protected $description = 'Retirer les lignes de prévision de recettes en doublon de code puis contraindre le code en base';

    private const INDEX = 'lignes_prev_recettes_prevision_code_active';

    public function handle(): int
    {
        $groupes = $this->groupesEnDoublon();

        if ($groupes->isEmpty()) {
            $this->info('Aucun doublon de code : chaque code n\'apparaît qu\'une fois par prévision active.');

            return $this->option('sans-index') ? self::SUCCESS : ($this->creerIndex() ? self::SUCCESS : self::FAILURE);
        }

        $plan = $groupes->map(fn($g) => $this->analyserGroupe($g));

        foreach ($plan as $groupe) {
            $this->rendreGroupe($groupe);
        }

        $nettoyables = $plan->whereNull('refus')->values();
        $refuses = $plan->whereNotNull('refus')->values();
        $totalLignes = $nettoyables->sum(fn($g) => $g['aRetirer']->count());

        $this->newLine();
        $this->line("Groupes nettoyables : <options=bold>{$nettoyables->count()}</> ({$totalLignes} ligne(s) en corbeille)"
            . " · groupes bloqués : <fg=red>{$refuses->count()}</>");

        if (!$this->option('run')) {
            $this->warn("APERÇU UNIQUEMENT — rien n'a été écrit. Relancez avec --run pour appliquer.");

            return self::SUCCESS;
        }

        if ($totalLignes > 0) {
            $this->appliquerPlan($nettoyables);
        }

        if ($refuses->isNotEmpty()) {
            $this->warn(count($refuses) . " groupe(s) laissé(s) en l'état : traitez-les d'abord (voir ci-dessus), "
                . "l'index unique ne sera pas créé tant qu'ils subsistent.");

            return self::FAILURE;
        }

        return $this->option('sans-index') ? self::SUCCESS : ($this->creerIndex() ? self::SUCCESS : self::FAILURE);
    }

    // =========================================================
    // ANALYSE
    // =========================================================

    private function groupesEnDoublon(): \Illuminate\Support\Collection
    {
        return DB::table('lignes_previsions_recettes')
            ->whereNull('deleted_at')
            ->whereNotNull('code_nomenclature')
            ->when($this->option('prevision'), fn($q) => $q->where('prevision_recette_id', (int) $this->option('prevision')))
            ->groupBy('prevision_recette_id', 'code_nomenclature')
            ->havingRaw('COUNT(*) > 1')
            ->selectRaw('prevision_recette_id, code_nomenclature, COUNT(*) as nb, array_agg(id ORDER BY id) as ids')
            ->orderBy('prevision_recette_id')
            ->orderBy('code_nomenclature')
            ->get();
    }

    private function analyserGroupe(object $g): array
    {
        $ids = array_map('intval', explode(',', trim((string) $g->ids, '{}')));

        $lignes = LignePrevisionRecette::whereIn('id', $ids)
            ->orderBy('id')
            ->get()
            ->map(fn($ligne) => $this->sonEtat($ligne));

        // La ligne conservée est celle qui porte encore une trace : mouvement collectif
        // actif ou recouvrements. À défaut la plus ancienne (le saisie initiale).
        $conseree = $lignes->first(fn($e) => $e['trace']) ?? $lignes->first();

        $aRetirer = $lignes->reject(fn($e) => $e['id'] === $conseree['id'])->values();

        $refus = null;

        // La ligne conservée a le droit de porter une trace — c'est même pour ça
        // qu'elle est conservée. Rien d'autre ne doit en porter sur les autres.
        foreach ($aRetirer as $etat) {
            if ($etat['nb_recettes'] > 0) {
                $refus = "la ligne n°{$etat['id']} porte {$etat['nb_recettes']} recette(s) réelle(s) "
                    . '(' . number_format($etat['recouvre'], 0, ',', ' ') . ' FCFA) : réimputez-les sur la '
                    . "ligne n°{$conseree['id']} avant de la retirer.";
                break;
            }

            if ($etat['mouvements'] !== []) {
                $refus = "la ligne n°{$etat['id']} est encore adossée au(x) collectif "
                    . implode(', ', $etat['mouvements'])
                    . ' : réinitialisez ce collectif depuis « Réinitialisation des collectifs budgétaires », '
                    . 'il retirera la ligne et ses effets en une fois.';
                break;
            }
        }

        return [
            'prevision' => (int) $g->prevision_recette_id,
            'code' => (string) $g->code_nomenclature,
            'lignes' => $lignes,
            'conseree' => $conseree,
            'aRetirer' => $aRetirer,
            'refus' => $refus,
        ];
    }

    private function sonEtat(LignePrevisionRecette $ligne): array
    {
        $moisIds = DB::table('previsions_recettes_mensuelles')
            ->where('ligne_prevision_recette_id', $ligne->id)
            ->whereNull('deleted_at')
            ->pluck('id');

        $recettes = DB::table('recettes_reelles')
            ->whereIn('prevision_recette_mensuelle_id', $moisIds)
            ->whereNull('deleted_at')
            ->get();
        // Un mouvement annulé ne pèse plus sur le budget : il ne retient pas la ligne.
        $mouvements = DB::table('mouvements_collectifs as m')
            ->join('collectifs_budgetaires as c', 'c.id', '=', 'm.collectif_budgetaire_id')
            ->where(fn($q) => $q->where('m.ligne_recette_id', $ligne->id)
                ->orWhere('m.nouvelle_ligne_recette_id', $ligne->id))
            ->where(fn($q) => $q->whereNull('m.statut')->orWhereNotIn('m.statut', ['annule', 'annulee']))
            ->whereNull('m.date_annulation')
            ->pluck('c.numero')
            ->unique()
            ->values()
            ->all();

        return [
            'id' => (int) $ligne->id,
            'nomenclature_id' => (int) $ligne->nomenclature_id,
            'libelle' => (string) ($ligne->libelle_nomenclature ?? ''),
            'initial' => (float) $ligne->montant_prevu_initial,
            'rectifie' => (float) $ligne->montant_rectifie,
            'recouvre' => (float) $recettes->sum('montant'),
            'nb_recettes' => $recettes->count(),
            'mouvements' => $mouvements,
            'issue_collectif' => (bool) $ligne->est_issue_collectif,
            'actif' => (bool) $ligne->actif,
            'trace' => $mouvements !== [] || $recettes->isNotEmpty(),
        ];
    }

    // =========================================================
    // RENDU
    // =========================================================

    private function rendreGroupe(array $groupe): void
    {
        $prevision = PrevisionRecette::find($groupe['prevision']);
        $entete = ($prevision ? $prevision->code : "prévision n°{$groupe['prevision']}")
            . " — code {$groupe['code']} (" . $groupe['lignes']->first()['libelle'] . ')';

        $this->newLine();
        $this->line("<options=bold>{$entete}</>");

        foreach ($groupe['lignes'] as $etat) {
            $sort = $etat['id'] === $groupe['conseree']['id'] ? '<fg=green>conservée</>' : (
                $groupe['refus'] ? '<fg=yellow>à vérifier</>' : '<fg=red>en corbeille</>'
            );

            $traces = [];
            if ($etat['mouvements'] !== []) $traces[] = 'collectif ' . implode('/', $etat['mouvements']);
            if ($etat['nb_recettes'] > 0) $traces[] = "{$etat['nb_recettes']} recette(s), "
                . number_format($etat['recouvre'], 0, ',', ' ') . ' FCFA';
            if ($etat['issue_collectif']) $traces[] = 'créée par collectif';

            $this->line(sprintf(
                '  n°%-6d %-11s nom=%-6s initial=%15s rectifie=%15s  %s%s',
                $etat['id'],
                $sort,
                $etat['nomenclature_id'],
                number_format($etat['initial'], 0, ',', ' '),
                number_format($etat['rectifie'], 0, ',', ' '),
                $traces ? '· ' . implode(' · ', $traces) : '· aucune trace',
                $etat['actif'] ? '' : ' · ligne inactive'
            ));
        }

        if ($groupe['refus']) {
            $this->line("  <fg=red>BLOQUÉ —</> {$groupe['refus']}");
        }
    }

    // =========================================================
    // APPLICATION
    // =========================================================

    private function appliquerPlan(\Illuminate\Support\Collection $plan): void
    {
        $previsions = $plan->pluck('prevision')->unique()
            ->mapWithKeys(fn($id) => [(int) $id => $this->totalLive((int) $id)]);

        $fusion = (bool) $this->option('fusionner');

        DB::transaction(function () use ($plan, $fusion, $previsions) {
            foreach ($plan as $groupe) {
                foreach ($groupe['aRetirer'] as $etat) {
                    $ligne = LignePrevisionRecette::find($etat['id']);
                    if (!$ligne) continue;

                    // Un doublon porte souvent le MÊME montant que la ligne conservée
                    // (mouvement recréé) : fusionner est donc optionnel, sinon la
                    // prévision serait doublée.
                    if ($fusion) {
                        $gardee = LignePrevisionRecette::find($groupe['conseree']['id']);
                        if ($gardee) {
                            // rectifie n'est pas dérivé de initial à l'enregistrement :
                            // sans lui, la fusion resterait invisible au total de la prévision.
                            $gardee->montant_prevu_initial = (float) $gardee->montant_prevu_initial + $etat['initial'];
                            $gardee->montant_rectifie = (float) $gardee->montant_rectifie + $etat['rectifie'];
                            $gardee->save();
                        }
                    }

                    // L'observateur emporte les 12 mois avec la ligne.
                    $ligne->delete();

                    ActivityLog::logAction($ligne, 'dedup_ligne_prevision', [
                        'prevision_recette_id' => $groupe['prevision'],
                        'code' => $groupe['code'],
                        'ligne_conservee' => $groupe['conseree']['id'],
                        'montant_retire' => $etat['rectifie'],
                        'fusionne' => $fusion,
                        'par' => 'budget:dedup-lignes-recettes',
                    ]);
                }
            }

            foreach ($previsions->keys() as $previsionId) {
                LignePrevisionRecette::where('prevision_recette_id', $previsionId)
                    ->whereNull('deleted_at')
                    ->get()
                    ->each
                    ->recalculer();
            }
        });

        $this->info('Doublons retirés (corbeille, restaurables par id) :');

        foreach ($previsions as $previsionId => $avant) {
            $apres = $this->totalLive($previsionId);
            $ecart = $avant - $apres;

            $this->line(sprintf(
                '  prévision n°%-5d total %18s → %18s FCFA %s',
                $previsionId,
                number_format($avant, 0, ',', ' '),
                number_format($apres, 0, ',', ' '),
                abs($ecart) < 0.01
                    ? ''
                    : '<fg=yellow>retiré : ' . number_format($ecart, 0, ',', ' ') . ' FCFA</>'
            ));
        }
    }

    private function totalLive(int $previsionId): float
    {
        return (float) DB::table('lignes_previsions_recettes')
            ->where('prevision_recette_id', $previsionId)
            ->whereNull('deleted_at')
            ->sum('montant_rectifie');
    }

    // =========================================================
    // CONTRAINTE
    // =========================================================

    private function creerIndex(): bool
    {
        if ($this->option('sans-index')) return true;

        $restants = count(DB::select(
            'SELECT 1 FROM lignes_previsions_recettes
                WHERE deleted_at IS NULL AND code_nomenclature IS NOT NULL
                GROUP BY prevision_recette_id, code_nomenclature
                HAVING COUNT(*) > 1'
        ));

        if ($restants > 0) {
            $this->warn("Index non créé : {$restants} doublon(s) de code subsistent.");

            return false;
        }

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS ' . self::INDEX
            . ' ON lignes_previsions_recettes (prevision_recette_id, code_nomenclature) WHERE deleted_at IS NULL');

        $this->info('Index unique posé : ' . self::INDEX . ' (prevision_recette_id, code_nomenclature) WHERE deleted_at IS NULL');

        return true;
    }
}
