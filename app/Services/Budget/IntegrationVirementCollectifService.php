<?php

namespace App\Services\Budget;

use App\Models\ActivityLog;
use App\Models\CollectifBudgetaire;
use App\Models\MouvementCollectif;
use App\Models\VirementBudgetaire;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Intégration dans un collectif d'un mouvement de crédits de GESTION déjà existant :
 *  - collectif en projet : virements approuvés (exécutés à l'adoption) ou exécutés (non réexécutés) ;
 *  - collectif adopté   : virements exécutés seulement (simple rattachement, sans effet sur les dotations).
 * Le virement passe à l'origine « collectif » (hors plafond des virements de gestion) ; le retrait le
 * rend à la gestion, inchangé.
 */
class IntegrationVirementCollectifService
{
    public function virementsIntegrables(CollectifBudgetaire $collectif): Collection
    {
        if (!in_array($collectif->statut, ['projet', 'adopte'], true)) {
            return collect();
        }

        $dejaRattaches = MouvementCollectif::whereNotNull('virement_budgetaire_id')->pluck('virement_budgetaire_id');
        $statuts = $collectif->statut === 'adopte' ? ['execute'] : ['approuve', 'execute'];

        return VirementBudgetaire::withoutGlobalScope('exercice')
            ->where('exercice_id', $collectif->exercice_id)
            ->where(fn($q) => $q->whereNull('origine')->orWhere('origine', '!=', 'collectif'))
            ->whereIn('statut', $statuts)
            ->whereNotIn('id', $dejaRattaches)
            ->with(['ligneSource.nomenclature', 'ligneDestination.nomenclature'])
            ->orderBy('date_virement')
            ->get();
    }

    public function libelle(VirementBudgetaire $v): string
    {
        return sprintf(
            '%s — %s : %s → %s — %s FCFA (%s, %s)',
            $v->numero,
            MouvementCreditService::libelleType($v->type_mouvement),
            $v->ligneSource?->nomenclature?->code ?? '?',
            $v->ligneDestination?->nomenclature?->code ?? '?',
            number_format((float) $v->montant, 0, ',', ' '),
            VirementBudgetaire::STATUTS[$v->statut] ?? $v->statut,
            $v->date_virement?->format('d/m/Y') ?? '—'
        );
    }

    public function integrer(CollectifBudgetaire $collectif, array $ids): int
    {
        $eligibles = $this->virementsIntegrables($collectif)->whereIn('id', $ids);
        if ($eligibles->count() !== count(array_unique($ids))) {
            throw new DomainException('Un ou plusieurs mouvements ne sont plus intégrables (déjà rattachés, statut modifié, ou non exécutés pour un collectif adopté).');
        }

        DB::transaction(function () use ($collectif, $eligibles) {
            foreach ($eligibles as $v) {
                MouvementCollectif::create([
                    'collectif_budgetaire_id' => $collectif->id,
                    'type'                    => 'virement',
                    'virement_budgetaire_id'  => $v->id,
                    'montant_modification'    => (float) $v->montant,
                    'motif'                   => '[Intégré] ' . ($v->motif ?? $v->numero),
                    'statut'                  => 'actif',
                ]);
                DB::table('mouvements_collectifs')->where('virement_budgetaire_id', $v->id)
                    ->where('collectif_budgetaire_id', $collectif->id)->update(['integre_hors_collectif' => true]);

                $v->forceFill(['origine' => 'collectif'])->saveQuietly();

                ActivityLog::logAction($v, 'integration_collectif', ['collectif' => $collectif->numero, 'statut_virement' => $v->statut]);
            }

            ActivityLog::logAction($collectif, 'integration_mouvements', ['mouvements' => $eligibles->pluck('numero')->implode(', ')]);
        });

        return $eligibles->count();
    }

    public function mouvementsIntegres(CollectifBudgetaire $collectif): Collection
    {
        return MouvementCollectif::where('collectif_budgetaire_id', $collectif->id)
            ->where('integre_hors_collectif', true)
            ->whereNull('date_annulation')
            ->with(['virementBudgetaire.ligneSource.nomenclature', 'virementBudgetaire.ligneDestination.nomenclature'])
            ->get();
    }

    /** Retrait : le mouvement redevient un mouvement de gestion, inchangé (aucune contre-passation). */
    public function retirer(MouvementCollectif $mouvement): void
    {
        if (!$mouvement->integre_hors_collectif) {
            throw new DomainException("Ce mouvement a été saisi dans le collectif : il ne peut pas être retiré par cette action.");
        }

        DB::transaction(function () use ($mouvement) {
            $v = $mouvement->virementBudgetaire;
            $collectif = $mouvement->collectif;

            DB::table('mouvements_collectifs')->where('id', $mouvement->id)->delete();
            $v?->forceFill(['origine' => 'gestion'])->saveQuietly();

            if ($v) {
                ActivityLog::logAction($v, 'retrait_collectif', ['collectif' => $collectif?->numero]);
            }
            if ($collectif) {
                ActivityLog::logAction($collectif, 'retrait_mouvement_integre', ['mouvement' => $v?->numero]);
            }
        });
    }
}
