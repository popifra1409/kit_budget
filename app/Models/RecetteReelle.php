<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecetteReelle extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'recettes_reelles';

    protected $fillable = [
        'prevision_recette_mensuelle_id',
        'exercice_id',
        'annee',
        'mois',
        'code_nomenclature',
        'numero',
        'libelle',
        'montant',
        'date_recette',
        'payeur',
        'mode_paiement',
        'reference_paiement',
        'statut',
        'observations',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_recette' => 'date',
        'date_comptabilisation' => 'date',
        'mois' => 'integer',
        'annee' => 'integer',
    ];

    // ====================================
    // RELATIONS
    // ====================================

    public function previsionRecetteMensuelle(): BelongsTo
    {
        return $this->belongsTo(PrevisionRecetteMensuelle::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'validateur_id');
    }

    // ====================================
    // STATUTS
    // ====================================

    public function estPrevue(): bool
    {
        return $this->statut === 'prevue';
    }

    public function estEncaissee(): bool
    {
        return $this->statut === 'encaissee';
    }

    public function estComptabilisee(): bool
    {
        return $this->statut === 'comptabilisee';
    }

    public function estValidee(): bool
    {
        return $this->statut === 'validee';
    }

    // ====================================
    // ACTIONS
    // ====================================

    public function comptabiliser(): bool
    {
        if (!$this->estEncaissee()) {
            return false;
        }
        $this->update(['statut' => 'comptabilisee']);
        return true;
    }

    public function valider(int $userId): bool
    {
        if (!$this->estComptabilisee()) {
            return false;
        }
        $this->update([
            'statut' => 'validee',
            'validateur_id' => $userId,
            'validee_le' => now(),
        ]);
        return true;
    }

    // ====================================
    // BOOT
    // ====================================
    /**
     * Générer un numéro unique de recette réelle
     * Format : REC-2026-000001
     */
    public static function genererNumero(int $annee): string
    {
        $dernier = static::where('annee', $annee)
            ->orderByDesc('id')
            ->first();

        $numero = 1;

        if ($dernier && preg_match('/REC-\d{4}-(\d+)/', $dernier->numero, $matches)) {
            $numero = intval($matches[1]) + 1;
        }

        return sprintf(
            'REC-%d-%06d',
            $annee,
            $numero
        );
    }

    protected static $processing = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($recette) {

            $mensuelle = $recette->previsionRecetteMensuelle;
            if (!$mensuelle) {
                throw new \RuntimeException('Prévision mensuelle manquante');
            }

            $ligne = $mensuelle->lignePrevisionRecette;
            if (!$ligne) {
                throw new \RuntimeException('Ligne de prévision introuvable');
            }

            $recette->exercice_id = $mensuelle->exercice_id;
            $recette->mois = $mensuelle->mois;
            $recette->annee = $mensuelle->annee;
            $recette->code_nomenclature = $ligne->code_nomenclature;

            if (empty($recette->numero)) {
                $recette->numero = self::genererNumero($recette->exercice_id);
            }
        });

        static::created(function ($recette) {
            // ⚠️ Éviter la récursion
            if (self::$processing) return;

            self::$processing = true;

            $prevision = $recette->previsionRecetteMensuelle;
            if ($prevision) {
                // ⚠️ Mettre à jour uniquement les champs cumulés sans déclencher d'observer
                $montantRecouvre = $prevision->recettesReelles()
                    ->whereIn('statut', ['encaissee', 'comptabilisee', 'validee'])
                    ->sum('montant');

                $prevision->updateQuietly([
                    'montant_recouvre' => $montantRecouvre,
                    'ecart' => $montantRecouvre - $prevision->montant_prevu,
                    'taux_realisation' => $prevision->montant_prevu == 0 ? 0 : ($montantRecouvre / $prevision->montant_prevu) * 100,
                ]);

                // Calculer cumulés sans déclencher d'observer
                foreach ($prevision->lignePrevisionRecette->previsionsMensuelles as $m) {
                    $m->updateQuietly([
                        'montant_cumule_prevu' => $m->lignePrevisionRecette->previsionsMensuelles()->where('mois', '<=', $m->mois)->sum('montant_prevu'),
                        'montant_cumule_recouvre' => $m->lignePrevisionRecette->previsionsMensuelles()->where('mois', '<=', $m->mois)->sum('montant_recouvre'),
                        'taux_realisation_cumule' => $m->montant_cumule_prevu == 0 ? 0 : ($m->montant_cumule_recouvre / $m->montant_cumule_prevu) * 100,
                    ]);
                }
            }

            self::$processing = false;
        });
    }

    /**
     * Mettre à jour la prévision mensuelle et la ligne annuelle
     */
    public function recalculerPrevisions(): void
    {
        $mensuelle = $this->previsionRecetteMensuelle;
        if ($mensuelle) {
            // Montant recouvré du mois
            $mensuelle->calculerMontantRecouvre();
            $mensuelle->calculerEcart();
            $mensuelle->calculerTauxRealisation();
            $mensuelle->calculerCumules();

            // Montant recouvré de la ligne annuelle
            $ligne = $mensuelle->lignePrevisionRecette;
            if ($ligne) {
                $ligne->calculerMontantRecouvre();
                $ligne->calculerEcart();
                $ligne->calculerTauxRecouvrement();
            }
        }
    }
}
