<?php

namespace App\Services\Budget;

use App\Models\ActivityLog;
use App\Models\Engagement;
use App\Models\LigneBudgetaire;
use App\Models\Tache;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Suppression EXCEPTIONNELLE d'une ligne budgétaire de dépenses, même budget adopté :
 * motivée, récupérable (suppression logique) et tracée (sur la ligne et au journal d'audit).
 *
 * Refusée si la ligne porte des engagements actifs, des mouvements de crédits non annulés,
 * un mouvement de collectif ou une ligne de régie. Les sous-tâches du compte voient leurs AE/CP
 * ramenés à 0 (concordance programmation / budget) ; leurs montants sont mémorisés et
 * rétablis à la restauration.
 */
class SuppressionLigneBudgetaireService
{
    public static function autorise(): bool
    {
        $u = auth()->user();

        return $u && ($u->hasAnyRole(['super_admin', 'admin']) || $u->can('supprimer_ligne_budgetaire_adoptee'));
    }

    /** Sous-tâches portant le compte de la ligne, pour l'exercice du budget. */
    public function sousTaches(LigneBudgetaire $ligne)
    {
        $code = $ligne->nomenclature?->code;
        $exerciceId = $ligne->budget?->exercice_id;

        return $code && $exerciceId
            ? Tache::withoutGlobalScope('exercice')->where('exercice_id', $exerciceId)->where('niveau', 'sous_tache')
            ->whereHas('nomenclature', fn($q) => $q->where('code', $code))->get()
            : collect();
    }

    /** Raisons empêchant la suppression (vide si possible). */
    public function obstacles(LigneBudgetaire $ligne): array
    {
        $obstacles = [];
        $sansSuppr = fn($q, string $table) => $q->when(Schema::hasColumn($table, 'deleted_at'), fn($x) => $x->whereNull('deleted_at'));

        $engagements = Engagement::withoutGlobalScope('exercice')
            ->where('budget_id', $ligne->budget_id)->where('nomenclature_principale_id', $ligne->nomenclature_id)
            ->whereNull('date_annulation')->where('statut', '!=', 'annule')->count();
        if ($engagements > 0 || (float) $ligne->engage > 0.01) {
            $obstacles[] = "{$engagements} engagement(s) actif(s) sur la ligne (engagé : " . number_format((float) $ligne->engage, 0, ',', ' ') . ' FCFA) : annulez-les ou changez leur imputation d\'abord.';
        }

        $virements = $sansSuppr(DB::table('virements_budgetaires'), 'virements_budgetaires')
            ->where(fn($q) => $q->where('ligne_source_id', $ligne->id)->orWhere('ligne_destination_id', $ligne->id))
            ->whereNotIn('statut', ['annule', 'rejete'])->count();
        if ($virements > 0) {
            $obstacles[] = "{$virements} mouvement(s) de crédits non annulé(s) portent sur la ligne : annulez-les d'abord.";
        }

        $collectif = $sansSuppr(DB::table('mouvements_collectifs'), 'mouvements_collectifs')
            ->where(function ($q) use ($ligne) {
                $q->where('ligne_depense_id', $ligne->id);
                if (Schema::hasColumn('mouvements_collectifs', 'nouvelle_ligne_depense_id')) {
                    $q->orWhere('nouvelle_ligne_depense_id', $ligne->id);
                }
            })->exists();
        if ($collectif) {
            $obstacles[] = 'La ligne a été modifiée ou créée par un collectif budgétaire : la supprimer fausserait le budget rectifié.';
        }

        if (Schema::hasTable('lignes_regies_avances') && DB::table('lignes_regies_avances')->where('ligne_budgetaire_id', $ligne->id)->exists()) {
            $obstacles[] = 'Une ligne de régie d\'avance (mini-budget) est rattachée à cette ligne.';
        }

        return $obstacles;
    }

    public function supprimer(LigneBudgetaire $ligne, string $motif, ?string $reference = null): void
    {
        if (!static::autorise()) {
            throw new DomainException('Suppression exceptionnelle non autorisée pour votre profil.');
        }
        if (mb_strlen(trim($motif)) < 15) {
            throw new DomainException('Le motif doit être explicite (15 caractères au moins).');
        }
        if ($obstacles = $this->obstacles($ligne)) {
            throw new DomainException(implode("\n", $obstacles));
        }

        DB::transaction(function () use ($ligne, $motif, $reference) {
            $taches = $this->sousTaches($ligne);

            // ⚠️ Montants d'origine capturés AVANT la remise à zéro (après save(), getOriginal() renvoie 0)
            $origines = $taches->map(fn($t) => ['id' => $t->id, 'code' => $t->code, 'ae' => (float) $t->ae, 'cp' => (float) $t->cp])->values()->all();

            // Concordance : AE/CP des sous-tâches du compte ramenés à 0 (montants mémorisés)
            SynchronisationTachesService::$enCours = true;
            try {
                foreach ($taches as $t) {
                    $t->ae = 0;
                    $t->cp = 0;
                    $t->save();
                }
            } finally {
                SynchronisationTachesService::$enCours = false;
            }

            $ligne->forceFill([
                'motif_suppression'     => trim($motif),
                'reference_suppression' => $reference,
                'supprime_par'          => auth()->id(),
                'donnees_suppression'   => json_encode([
                    'budget_initial' => (float) $ligne->budget_initial,
                    'dotation'       => (float) $ligne->getBudgetRectifieReel(),
                    'sous_taches'    => $origines,
                ]),
            ])->saveQuietly();

            $ligne->delete();

            ActivityLog::logAction($ligne, 'suppression_exceptionnelle', [
                'budget'         => $ligne->budget?->code,
                'statut_budget'  => $ligne->budget?->statut,
                'code'           => $ligne->nomenclature?->code,
                'libelle'        => $ligne->nomenclature?->libelle,
                'budget_initial' => (float) $ligne->budget_initial,
                'sous_taches_ramenees_a_zero' => $taches->pluck('code')->implode(', ') ?: 'aucune',
                'motif'          => trim($motif),
                'reference'      => $reference,
            ]);
        });
    }

    public function restaurer(LigneBudgetaire $ligne, string $motif): void
    {
        if (!static::autorise()) {
            throw new DomainException('Restauration non autorisée pour votre profil.');
        }

        DB::transaction(function () use ($ligne, $motif) {
            $ligne->restore();

            // Rétablissement des AE/CP d'origine des sous-tâches
            $donnees = is_array($ligne->donnees_suppression) ? $ligne->donnees_suppression : (json_decode((string) $ligne->donnees_suppression, true) ?: []);
            SynchronisationTachesService::$enCours = true;
            try {
                foreach ($donnees['sous_taches'] ?? [] as $st) {
                    if ($t = Tache::withoutGlobalScope('exercice')->find($st['id'])) {
                        $t->ae = $st['ae'];
                        $t->cp = $st['cp'];
                        $t->save();
                    }
                }
            } finally {
                SynchronisationTachesService::$enCours = false;
            }

            ActivityLog::logAction($ligne, 'restauration_ligne_budgetaire', [
                'code' => $ligne->nomenclature?->code,
                'motif' => $motif,
                'motif_suppression_initial' => $ligne->motif_suppression,
                'sous_taches_retablies' => count($donnees['sous_taches'] ?? []),
            ]);

            $ligne->forceFill(['motif_suppression' => null, 'reference_suppression' => null, 'supprime_par' => null, 'donnees_suppression' => null])->saveQuietly();
        });
    }
}
