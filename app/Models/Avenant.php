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

        \DB::beginTransaction();
        try {
            $budgetId        = $this->engagementOriginal->budget_id;
            $montantOriginal = (float) $this->montant_original;
            $montantCorrige  = (float) $this->montant_corrige;
            $delta           = (float) $this->delta_montant;

            // ── ÉTAPE 1 : Ajustement ligne(s) budgétaire(s) ──────
            if ($this->type_correction !== 'objet') {

                if (
                    $this->nomenclature_originale_id !== $this->nomenclature_corrigee_id
                    && $this->nomenclature_corrigee_id !== null
                ) {

                    // Changement de nomenclature — libérer ancienne
                    $lbOriginale = \App\Models\LigneBudgetaire::where('budget_id', $budgetId)
                        ->where('nomenclature_id', $this->nomenclature_originale_id)
                        ->first();

                    if ($lbOriginale) {
                        $lbOriginale->engage = max(0, $lbOriginale->engage - $montantOriginal);
                        $lbOriginale->save();
                    }

                    // Engager la nouvelle ligne
                    $lbCorrigee = \App\Models\LigneBudgetaire::where('budget_id', $budgetId)
                        ->where('nomenclature_id', $this->nomenclature_corrigee_id)
                        ->firstOrFail();

                    if (!$lbCorrigee->peutEngager($montantCorrige)) {
                        throw new \Exception(
                            "Crédit insuffisant sur la nouvelle ligne budgétaire.\n" .
                                "Disponible : " . number_format($lbCorrigee->disponible_engagement, 0, ',', ' ') . " FCFA\n" .
                                "Demandé : "    . number_format($montantCorrige, 0, ',', ' ') . " FCFA"
                        );
                    }

                    $lbCorrigee->engage += $montantCorrige;
                    $lbCorrigee->save();

                    // Mettre à jour l'engagement
                    $this->engagementOriginal->update([
                        'nomenclature_principale_id' => $this->nomenclature_corrigee_id,
                        'montant_engage'             => $montantCorrige,
                    ]);
                } elseif ($delta != 0) {
                    // Même nomenclature, delta de montant
                    $lb = \App\Models\LigneBudgetaire::where('budget_id', $budgetId)
                        ->where('nomenclature_id', $this->nomenclature_originale_id)
                        ->firstOrFail();

                    if ($delta > 0) {
                        if (!$lb->peutEngager($delta)) {
                            throw new \Exception(
                                "Crédit insuffisant pour l'augmentation.\n" .
                                    "Delta : "      . number_format($delta, 0, ',', ' ') . " FCFA\n" .
                                    "Disponible : " . number_format($lb->disponible_engagement, 0, ',', ' ') . " FCFA"
                            );
                        }
                        $lb->engage += $delta;
                    } else {
                        $lb->engage = max(0, $lb->engage + $delta);
                    }
                    $lb->save();

                    $this->engagementOriginal->update([
                        'montant_engage' => $montantCorrige,
                    ]);
                }
            }

            // ── ÉTAPE 2 : Mise à jour des Ordonnances de Paiement ──
            if ($this->corriger_ordonnances) {
                $this->mettreAJourOrdonnances();
            }

            // ── ÉTAPE 3 : Marquer l'avenant comme appliqué ────────
            $this->update([
                'statut'           => 'applique',
                'applique_par'     => auth()->id(),
                'date_application' => now(),
            ]);

            \DB::commit();

            \Log::info("Avenant #{$this->numero_avenant} appliqué", [
                'engagement' => $this->engagementOriginal->numero,
                'delta'      => $this->delta_montant,
                'type'       => $this->type_correction,
                'op_mises_a_jour' => $this->corriger_ordonnances,
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    protected function mettreAJourOrdonnances(): void
    {
        $engagement = $this->engagementOriginal;
        $ordonnances = $engagement->ordonnancesPaiement()->get();

        if ($ordonnances->isEmpty()) return;

        $montantTaxesCorrige = (float) ($this->montant_taxes_corrige ?? 0);
        $montantBrutCorrige  = (float) $this->montant_corrige;
        $montantNetCorrige   = $montantBrutCorrige - $montantTaxesCorrige;

        foreach ($ordonnances as $op) {
            if ($op->type_ordonnance === 'standard') {
                // ✅ OP Standard = montant net (brut - taxes)
                $op->update([
                    'montant_net' => max(0, $montantNetCorrige),
                ]);

                \Log::info("OP Standard {$op->numero} mise à jour", [
                    'ancien_montant' => $op->getOriginal('montant_net'),
                    'nouveau_montant' => $montantNetCorrige,
                ]);
            } elseif ($op->type_ordonnance === 'impot') {
                // ✅ OP Impôt = total taxes
                $op->update([
                    'montant_net' => max(0, $montantTaxesCorrige),
                ]);

                \Log::info("OP Impôt {$op->numero} mise à jour", [
                    'ancien_montant'  => $op->getOriginal('montant_net'),
                    'nouveau_montant' => $montantTaxesCorrige,
                ]);
            }
        }
    }

    // ── Numéro avenant suivant pour un engagement ─────────────
    public static function prochainNumero(int $engagementId): int
    {
        return (static::where('engagement_original_id', $engagementId)->max('numero_avenant') ?? 0) + 1;
    }
}
