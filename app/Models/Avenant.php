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
    ];

    protected $casts = [
        'montant_original' => 'decimal:2',
        'montant_corrige'  => 'decimal:2',
        'delta_montant'    => 'decimal:2',
        'date_application' => 'datetime',
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

            // ── Cas 1 : Changement de nomenclature ───────────
            if (
                $this->nomenclature_originale_id !== $this->nomenclature_corrigee_id
                && $this->nomenclature_corrigee_id !== null
            ) {

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
                }
            }

            if ($this->engagement_differentiel_id) {
                \App\Models\Engagement::withoutGlobalScope('exercice')
                    ->find($this->engagement_differentiel_id)
                    ?->update(['statut' => 'definitif']);
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

    protected function mettreAJourOrdonnances(): void
    {
        $engagement  = $this->engagementOriginal;
        $ordonnances = $engagement->ordonnancesPaiement()->get();

        if ($ordonnances->isEmpty()) return;

        $doc                 = $engagement->engageable;
        $corrections         = $this->donnees_correction ?? [];
        $montantBrutCorrige  = (float) $this->montant_corrige;
        $montantTaxesCorrige = (float) ($this->montant_taxes_corrige ?? 0);
        $montantNetCorrige   = $montantBrutCorrige - $montantTaxesCorrige;

        foreach ($ordonnances as $op) {

            // ── OP Standard ───────────────────────────────────────
            if ($op->type_ordonnance === 'standard') {
                $op->updateQuietly([
                    'montant_net' => max(0, $montantNetCorrige),
                ]);

                \Log::info("OP Standard {$op->numero} mise à jour", [
                    'ancien_montant'  => $op->getOriginal('montant_net'),
                    'nouveau_montant' => $montantNetCorrige,
                ]);

                // ── OP Impôt ──────────────────────────────────────────
            } elseif ($op->type_ordonnance === 'impot') {

                if ($doc instanceof \App\Models\DecisionAdministrative) {
                    $montantCnps    = (float)($corrections['montant_cnps']    ?? $op->montant_cnps        ?? $doc->montant_cnps    ?? 0);
                    $montantIr      = (float)($corrections['montant_ir']      ?? $op->montant_ir           ?? $doc->montant_ir      ?? 0);
                    $montantIrnc    = (float)($corrections['montant_irnc']    ?? $op->montant_irnc         ?? $doc->montant_irnc    ?? 0);
                    $montantTva     = (float)($corrections['montant_tva']     ?? $op->montant_tva          ?? $doc->montant_tva     ?? 0);
                    $autresRetenues = (float)($corrections['autres_retenues'] ?? $op->montant_autres_taxes ?? $doc->autres_retenues ?? 0);
                    $totalTaxes     = $montantCnps + $montantIr + $montantIrnc + $montantTva + $autresRetenues;

                    $op->updateQuietly([
                        'montant_net'          => max(0, $totalTaxes),
                        'montant_cnps'         => $montantCnps,
                        'montant_ir'           => $montantIr,
                        'montant_irnc'         => $montantIrnc,
                        'montant_tva'          => $montantTva,
                        'montant_autres_taxes' => $autresRetenues,
                        // ✅ Plus de reconstruction d'objet
                    ]);
                } elseif ($doc instanceof \App\Models\BonCommande) {
                    $montantIr  = (float)($corrections['montant_ir']  ?? $op->montant_ir  ?? $doc->montant_ir  ?? 0);
                    $montantTva = (float)($corrections['montant_tva'] ?? $op->montant_tva ?? $doc->montant_tva ?? 0);
                    $montantTsr = (float)($corrections['montant_tsr'] ?? $op->montant_tsr ?? $doc->montant_tsr ?? 0);
                    $totalTaxes = $montantIr + $montantTva + $montantTsr;

                    $op->updateQuietly([
                        'montant_net' => max(0, $totalTaxes),
                        'montant_ir'  => $montantIr,
                        'montant_tva' => $montantTva,
                        'montant_tsr' => $montantTsr,
                        // ✅ Plus de reconstruction d'objet
                    ]);
                }
            }
        }
    }

    // ── Numéro avenant suivant pour un engagement ─────────────
    public static function prochainNumero(int $engagementId): int
    {
        return (static::where('engagement_original_id', $engagementId)->max('numero_avenant') ?? 0) + 1;
    }
}
