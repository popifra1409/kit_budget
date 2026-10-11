<?php

namespace App\Services\Budget;

use App\Models\ActivityLog;
use App\Models\LignePrevisionRecette;
use App\Models\PrevisionRecetteMensuelle;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Suppression EXCEPTIONNELLE d'une ligne de prévision de recettes, même adoptée :
 * motivée, récupérable (suppression logique) et tracée (sur la ligne et au journal d'audit).
 * Refusée si des recettes sont enregistrées sur la ligne ou si un collectif l'a modifiée.
 */
class SuppressionLignePrevisionService
{
    public static function autorise(): bool
    {
        $u = auth()->user();

        return $u && ($u->hasAnyRole(['super_admin', 'admin']) || $u->can('supprimer_ligne_prevision_adoptee'));
    }

    /** Raisons empêchant la suppression (vide si possible). */
    public function obstacles(LignePrevisionRecette $ligne): array
    {
        $obstacles = [];

        $nbRecettes = DB::table('recettes_reelles as r')
            ->join('previsions_recettes_mensuelles as m', 'm.id', '=', 'r.prevision_recette_mensuelle_id')
            ->where('m.ligne_prevision_recette_id', $ligne->id)
            ->whereNull('r.deleted_at')
            ->count();
        if ($nbRecettes > 0) {
            $obstacles[] = "{$nbRecettes} recette(s) enregistrée(s) sur la ligne : réimputez-les d'abord sur une autre ligne.";
        }

        $mouvements = DB::table('mouvements_collectifs')->where('ligne_recette_id', $ligne->id)
            ->when(Schema::hasColumn('mouvements_collectifs', 'deleted_at'), fn($q) => $q->whereNull('deleted_at'))
            ->exists();
        if ($mouvements) {
            $obstacles[] = 'La ligne a été modifiée par un collectif budgétaire : la supprimer fausserait le budget rectifié.';
        }

        return $obstacles;
    }

    public function supprimer(LignePrevisionRecette $ligne, string $motif, ?string $reference = null): void
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
            $ligne->forceFill([
                'motif_suppression'     => trim($motif),
                'reference_suppression' => $reference,
                'supprime_par'          => auth()->id(),
            ])->saveQuietly();

            PrevisionRecetteMensuelle::where('ligne_prevision_recette_id', $ligne->id)->get()->each->delete();
            $ligne->delete();

            ActivityLog::logAction($ligne, 'suppression_exceptionnelle', [
                'prevision'      => $ligne->previsionRecette?->code,
                'statut_prevision' => $ligne->previsionRecette?->statut,
                'code'           => $ligne->code_nomenclature,
                'libelle'        => $ligne->libelle_nomenclature,
                'montant_prevu'  => (float) $ligne->montant_rectifie,
                'motif'          => trim($motif),
                'reference'      => $reference,
            ]);
        });
    }

    public function restaurer(LignePrevisionRecette $ligne, string $motif): void
    {
        if (!static::autorise()) {
            throw new DomainException('Restauration non autorisée pour votre profil.');
        }

        DB::transaction(function () use ($ligne, $motif) {
            $ligne->restore();
            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(PrevisionRecetteMensuelle::class), true)) {
                PrevisionRecetteMensuelle::onlyTrashed()->where('ligne_prevision_recette_id', $ligne->id)->get()->each->restore();
            }

            ActivityLog::logAction($ligne, 'restauration_ligne_prevision', [
                'code' => $ligne->code_nomenclature,
                'motif' => $motif,
                'motif_suppression_initial' => $ligne->motif_suppression,
            ]);

            $ligne->forceFill(['motif_suppression' => null, 'reference_suppression' => null, 'supprime_par' => null])->saveQuietly();
        });
    }
}
