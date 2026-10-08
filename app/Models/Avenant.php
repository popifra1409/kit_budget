<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class Avenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'document_source_type',
        'document_source_id',
        'document_corrige_type',
        'document_corrige_id',
        'engagement_original_id',
        'engagement_differentiel_id',
        'numero_avenant',
        'motif',
        'type_correction',
        'montant_original',
        'montant_corrige',
        'delta_montant',
        'nomenclature_originale_id',
        'nomenclature_corrigee_id',
        'statut',
        'applique_par',
        'date_application',
        'created_by',

        // ✅ AJOUT — colonnes existantes en base, utilisées par l'avenant de changement de bénéficiaire.
        //    (ViewEngagement ne les transmet pas : aucun effet sur les avenants de montant/taxes.)
        'montant_taxes_original',
        'montant_taxes_corrige',
        'corriger_ordonnances',

        // ✅ AJOUT — avenant « changement de bénéficiaire »
        'beneficiaire_original_type',
        'beneficiaire_original_id',
        'beneficiaire_corrige_type',
        'beneficiaire_corrige_id',
        'dossier_fournisseur_cree_id',

        // ✅ CORRIGÉ — données de correction (taxes, montant brut, objet) : absentes de $fillable,
        //    elles étaient ignorées silencieusement → taxes de l'OP standard remises à zéro.
        'donnees_correction',
    ];

    /** Type de correction de l'avenant de changement de bénéficiaire. */
    public const TYPE_BENEFICIAIRE = 'beneficiaire';

    protected $casts = [
        'montant_original' => 'decimal:2',
        'montant_corrige'  => 'decimal:2',
        'delta_montant'    => 'decimal:2',
        'date_application' => 'datetime',
        'montant_taxes_original' => 'decimal:2',
        'montant_taxes_corrige'  => 'decimal:2',
        'donnees_correction'     => 'array',
        'corriger_ordonnances'   => 'boolean',
    ];

    public function documentSource(): MorphTo
    {
        return $this->morphTo('document_source');
    }

    public function documentCorrige(): MorphTo
    {
        return $this->morphTo('document_corrige');
    }

    public function engagementOriginal(): BelongsTo
    {
        return $this->belongsTo(Engagement::class, 'engagement_original_id');
    }

    public function engagementDifferentiel(): BelongsTo
    {
        return $this->belongsTo(Engagement::class, 'engagement_differentiel_id');
    }

    public function nomenclatureOriginale(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_originale_id');
    }

    public function nomenclatureCorrigee(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_corrigee_id');
    }

    public function appliqueParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applique_par');
    }

    // ── Avenant « changement de bénéficiaire » ───────────────

    /** Ancien bénéficiaire (Fournisseur ou Personnel). */
    public function beneficiaireOriginal(): MorphTo
    {
        return $this->morphTo('beneficiaire_original');
    }

    /** Nouveau bénéficiaire (Fournisseur ou Personnel). */
    public function beneficiaireCorrige(): MorphTo
    {
        return $this->morphTo('beneficiaire_corrige');
    }

    /** Dossier fournisseur ouvert pour le nouveau fournisseur. */
    public function dossierFournisseurCree(): BelongsTo
    {
        return $this->belongsTo(DossierFournisseur::class, 'dossier_fournisseur_cree_id');
    }

    public function estChangementBeneficiaire(): bool
    {
        return $this->type_correction === self::TYPE_BENEFICIAIRE;
    }

    // ── Appliquer l'avenant ───────────────────────────────────
    public function appliquer(): void
    {
        if ($this->statut !== 'brouillon') {
            throw new \Exception("Cet avenant a déjà été appliqué ou annulé.");
        }

        DB::beginTransaction();
        try {
            // ✅ Recharger sans global scope
            $engagement = \App\Models\Engagement::withoutGlobalScope('exercice')
                ->find($this->engagement_original_id);

            if (!$engagement) {
                throw new \Exception("Engagement original introuvable.");
            }

            $budgetId = $engagement->budget_id;

            // ✅ CORRIGE — comparaison par valeur entiere : le formulaire renvoie l'id en texte ("12")
            //    et la base en nombre (12). L'ancien !== traitait a tort un avenant SANS changement
            //    de ligne comme un changement de ligne (controle de credit sur le montant total
            //    au lieu du delta).
            $changementNomenclature = $this->nomenclature_corrigee_id !== null
                && (int) $this->nomenclature_originale_id !== (int) $this->nomenclature_corrigee_id;

            // ── Cas 1 : Changement de nomenclature ───────────
            if ($changementNomenclature) {

                $lbOriginale = LigneBudgetaire::where('budget_id', $budgetId)
                    ->where('nomenclature_id', $this->nomenclature_originale_id)
                    ->first();

                if ($lbOriginale) {
                    $lbOriginale->engage = max(0, $lbOriginale->engage - $this->montant_original);
                    $lbOriginale->save();
                }

                $lbCorrigee = LigneBudgetaire::where('budget_id', $budgetId)
                    ->where('nomenclature_id', $this->nomenclature_corrigee_id)
                    ->firstOrFail();

                if (!$lbCorrigee->peutEngager($this->montant_corrige)) {
                    throw new \Exception(
                        "Crédit insuffisant sur la nouvelle ligne budgétaire.\n" .
                            "Disponible : " . number_format($lbCorrigee->disponible_engagement, 0, ',', ' ') . " FCFA"
                    );
                }

                $lbCorrigee->engage += $this->montant_corrige;
                $lbCorrigee->save();

                $engagement->update([
                    'nomenclature_principale_id' => $this->nomenclature_corrigee_id,
                    'montant_engage'             => $this->montant_corrige,
                ]);

                // ✅ AJOUT — la ligne d'imputation suit l'engagement (montant ET nomenclature)
                $this->synchroniserLignesEngagement($engagement, (int) $this->nomenclature_corrigee_id);
            } else {
                // ── Cas 2 : Delta de montant ──────────────────
                $delta = $this->delta_montant;

                if ($delta != 0) {
                    $lb = LigneBudgetaire::where('budget_id', $budgetId)
                        ->where('nomenclature_id', $this->nomenclature_originale_id)
                        ->firstOrFail();

                    if ($delta > 0) {
                        if (!$lb->peutEngager($delta)) {
                            throw new \Exception(
                                "Crédit insuffisant pour l'augmentation.\n" .
                                    "Delta : " . number_format($delta, 0, ',', ' ') . " FCFA\n" .
                                    "Disponible : " . number_format($lb->disponible_engagement, 0, ',', ' ') . " FCFA"
                            );
                        }
                        $lb->engage += $delta;
                    } else {
                        $lb->engage = max(0, $lb->engage + $delta);
                    }
                    $lb->save();

                    $engagement->update(['montant_engage' => $this->montant_corrige]);

                    // ✅ AJOUT — la ligne d'imputation suit le nouveau montant
                    $this->synchroniserLignesEngagement($engagement, (int) $this->nomenclature_originale_id);
                }
            }

            if ($this->engagement_differentiel_id) {
                \App\Models\Engagement::withoutGlobalScope('exercice')
                    ->find($this->engagement_differentiel_id)
                    ?->update(['statut' => 'definitif']);
            }

            $this->mettreAJourOrdonnances();

            // ✅ AJOUT — recalcul des lignes budgétaires touchées, depuis les engagements :
            //    garantit l'engagé exact, sans transfert manuel supplémentaire (supprimé de ViewEngagement).
            foreach (array_unique(array_filter([$this->nomenclature_originale_id, $this->nomenclature_corrigee_id])) as $nomenclatureId) {
                $ligne = LigneBudgetaire::where('budget_id', $budgetId)->where('nomenclature_id', $nomenclatureId)->first();
                if ($ligne && method_exists($ligne, 'recalculerDepuisEngagements')) {
                    $ligne->recalculerDepuisEngagements();
                }
            }

            $this->update([
                'statut'           => 'applique',
                'applique_par'     => auth()->id(),
                'date_application' => now(),
            ]);

            DB::commit();

            \Log::info("Avenant #{$this->numero_avenant} appliqué", [
                'engagement' => $engagement->numero,
                'delta'      => $this->delta_montant,
                'type'       => $this->type_correction,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * ✅ AJOUT — Aligne la ligne de lignes_engagement sur l'engagement corrige.
     *
     * Sans cela, lignes_engagement gardait le montant d'origine : la colonne 'engage'
     * restait juste, mais tout calcul fonde sur lignes_engagement (recalcul d'une ligne,
     * engage par periode du Suivi-Evaluation) sous-estimait l'engagement.
     *
     * Cas traites :
     *  - une seule ligne (cas de tous les engagements actuels) : montant + nomenclature mis a jour ;
     *  - plusieurs lignes : repartition impossible a deduire → aucune modification, alerte au log
     *    pour correction manuelle (la transaction de l'avenant n'est PAS bloquee).
     */
    protected function synchroniserLignesEngagement(\App\Models\Engagement $engagement, int $nomenclatureId): void
    {
        $lignes = \App\Models\LigneEngagement::where('engagement_id', $engagement->id)->get();

        if ($lignes->count() === 1) {
            $ligne = $lignes->first();

            $ligne->update([
                'montant'         => $this->montant_corrige,
                'nomenclature_id' => $nomenclatureId,
            ]);

            return;
        }

        \Log::warning('Avenant : lignes_engagement non synchronisé automatiquement', [
            'avenant'      => $this->numero_avenant,
            'engagement'   => $engagement->numero,
            'nb_lignes'    => $lignes->count(),
            'motif'        => $lignes->isEmpty()
                ? 'aucune ligne d\'imputation'
                : 'engagement réparti sur plusieurs lignes : répartition à corriger manuellement',
        ]);
    }

    /**
     * ✅ Taxes corrigées : valeur saisie dans l'avenant, sinon valeur du document source.
     * Utilisé pour l'OP standard ET l'OP impôt, qui restent ainsi cohérentes.
     */
    protected function taxesCorrigees($document, array $corrections): array
    {
        $valeur = fn(string $cle) => (float) ($corrections[$cle] ?? $document?->{$cle} ?? 0);

        $taxes = ['montant_cnps' => 0.0, 'montant_ir' => 0.0, 'montant_irnc' => 0.0, 'montant_tva' => 0.0, 'montant_tsr' => 0.0, 'autres_retenues' => 0.0];

        if ($document instanceof \App\Models\DecisionAdministrative) {
            foreach (['montant_cnps', 'montant_ir', 'montant_irnc', 'montant_tva', 'autres_retenues'] as $cle) {
                $taxes[$cle] = $valeur($cle);
            }
        } elseif ($document instanceof \App\Models\BonCommande) {
            foreach (['montant_ir', 'montant_tva', 'montant_tsr'] as $cle) {
                $taxes[$cle] = $valeur($cle);
            }
        }

        return $taxes + ['total' => array_sum($taxes)];
    }

    protected function mettreAJourOrdonnances(): void
    {
        // ✅ Option « corriger les ordonnances » de l'avenant
        if ($this->corriger_ordonnances === false) {
            \Log::info("Avenant {$this->numero_avenant} : ordonnances non corrigées (option désactivée)");
            return;
        }

        // Recharger l'engagement depuis la base
        $engagement = Engagement::withoutGlobalScope('exercice')
            ->with('ordonnancesPaiement')
            ->find($this->engagement_original_id);

        if (!$engagement) {
            \Log::warning("Engagement introuvable pour l'avenant {$this->id}");
            return;
        }

        $ordonnances = $engagement->ordonnancesPaiement;

        if ($ordonnances->isEmpty()) {
            \Log::info("Aucune ordonnance trouvée pour l'engagement {$engagement->numero}");
            return;
        }

        $document = $engagement->engageable;

        // Données corrigées éventuelles
        $corrections = $this->donnees_correction ?? [];

        $montantBrutCorrige = (float) ($this->montant_corrige ?? 0);

        // ✅ CORRIGÉ — taxes : corrigées, sinon celles du document (et non 0 par défaut)
        $taxes = $this->taxesCorrigees($document, $corrections);
        $montantTaxesCorrige = $taxes['total'];

        $montantNetCorrige = max(0, $montantBrutCorrige - $montantTaxesCorrige);

        foreach ($ordonnances as $op) {

            // ✅ Une OP déjà PAYÉE n'est jamais modifiée : le décaissement est fait.
            //    L'écart se régularise par une OP complémentaire ou un ordre de recette.
            if ($op->statut === 'payee') {
                \Log::warning("Avenant {$this->numero_avenant} : OP {$op->numero} déjà payée, non modifiée — écart à régulariser", [
                    'montant_brut_paye' => $op->montant_brut,
                    'montant_brut_corrige' => $montantBrutCorrige,
                ]);
                continue;
            }

            // ====================================================
            // OP STANDARD
            // ====================================================
            if ($op->type_ordonnance === 'standard') {

                $ancien = $op->montant_net;

                $op->updateQuietly([
                    'montant_brut'   => $montantBrutCorrige,
                    'montant_impot'  => $montantTaxesCorrige,
                    'montant_net'    => $montantNetCorrige,
                ]);

                $op->refresh();

                \Log::info("OP Standard mise à jour", [
                    'op'      => $op->numero,
                    'ancien'  => $ancien,
                    'nouveau' => $op->montant_net,
                ]);
            }

            // ====================================================
            // OP IMPÔT
            // ====================================================
            elseif ($op->type_ordonnance === 'impot') {

                // ✅ Même calcul que l'OP standard (helper taxesCorrigees)
                $montantCnps    = $taxes['montant_cnps'];
                $montantIr      = $taxes['montant_ir'];
                $montantIrnc    = $taxes['montant_irnc'];
                $montantTva     = $taxes['montant_tva'];
                $montantTsr     = $taxes['montant_tsr'];
                $autresRetenues = $taxes['autres_retenues'];
                $totalTaxes     = $taxes['total'];

                $op->updateQuietly([
                    'montant_brut'         => $montantBrutCorrige,
                    'montant_impot'        => $totalTaxes,
                    'montant_net'          => $totalTaxes,
                    'montant_cnps'         => $montantCnps,
                    'montant_ir'           => $montantIr,
                    'montant_irnc'         => $montantIrnc,
                    'montant_tva'          => $montantTva,
                    'montant_tsr'          => $montantTsr,
                    'montant_autres_taxes' => $autresRetenues,
                ]);

                $op->refresh();

                \Log::info("OP Impôt mise à jour", [
                    'op'           => $op->numero,
                    'montant_taxe' => $totalTaxes,
                ]);
            }
        }

        \Log::info("Mise à jour des ordonnances terminée", [
            'engagement'   => $engagement->numero,
            'ordonnances'  => $ordonnances->count(),
            'avenant'      => $this->numero_avenant,
        ]);
    }

    // ── Numéro avenant suivant pour un engagement ─────────────
    public static function prochainNumero(int $engagementId): int
    {
        return (static::where('engagement_original_id', $engagementId)->max('numero_avenant') ?? 0) + 1;
    }
}
