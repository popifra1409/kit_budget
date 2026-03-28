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
        'montant'               => 'decimal:2',
        'date_recette'          => 'date',
        'date_comptabilisation' => 'date',
        'mois'                  => 'integer',
        'annee'                 => 'integer',
    ];

    protected static bool $processing = false;

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
        if (!$this->estEncaissee()) return false;
        $this->update(['statut' => 'comptabilisee']);
        return true;
    }

    public function valider(int $userId): bool
    {
        if (!$this->estComptabilisee()) return false;
        $this->update([
            'statut'        => 'validee',
            'validateur_id' => $userId,
            'validee_le'    => now(),
        ]);
        return true;
    }

    // ====================================
    // GÉNÉRATION NUMÉRO
    // ====================================

    public static function genererNumero(int $annee): string
    {
        return \DB::transaction(function () use ($annee) {
            $dernier = static::withTrashed()
                ->where('annee', $annee)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $numero = 1;
            if ($dernier && preg_match('/REC-\d{4}-(\d+)/', $dernier->numero, $matches)) {
                $numero = intval($matches[1]) + 1;
            }

            return sprintf('REC-%d-%06d', $annee, $numero);
        });
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function boot()
    {
        parent::boot();

        // ── Création ────────────────────────────────────────────
        static::creating(function ($recette) {
            $mensuelle = \App\Models\PrevisionRecetteMensuelle::find(
                $recette->prevision_recette_mensuelle_id
            );
            if (!$mensuelle) throw new \RuntimeException('Prévision mensuelle manquante');

            $ligne = \App\Models\LignePrevisionRecette::find(
                $mensuelle->ligne_prevision_recette_id
            );
            if (!$ligne) throw new \RuntimeException('Ligne de prévision introuvable');

            $recette->exercice_id       = $mensuelle->exercice_id;
            $recette->mois              = $mensuelle->mois;
            $recette->annee             = $mensuelle->annee;
            $recette->code_nomenclature = $ligne->code_nomenclature;

            if (empty($recette->numero)) {
                $recette->numero = self::genererNumero($mensuelle->annee);
            }
        });

        // ── Après création ───────────────────────────────────────
        static::created(function ($recette) {
            self::recalculerDepuisId($recette->prevision_recette_mensuelle_id);
        });

        // ✅ NOUVEAU — Après modification
        static::updated(function ($recette) {
            // Recalculer la mensuelle courante
            self::recalculerDepuisId($recette->prevision_recette_mensuelle_id);

            // Si la mensuelle liée a changé → recalculer aussi l'ancienne
            if ($recette->wasChanged('prevision_recette_mensuelle_id')) {
                $ancienId = $recette->getOriginal('prevision_recette_mensuelle_id');
                if ($ancienId && $ancienId !== $recette->prevision_recette_mensuelle_id) {
                    self::recalculerDepuisId($ancienId);
                }
            }
        });

        // ✅ NOUVEAU — Après suppression (soft delete)
        static::deleted(function ($recette) {
            self::recalculerDepuisId($recette->prevision_recette_mensuelle_id);
        });
    }

    // ====================================
    // RECALCUL CENTRALISÉ
    // ====================================

    /**
     * Recalcule la prévision mensuelle, ses cumulés et la ligne annuelle
     * depuis l'ID de la prévision mensuelle — sans passer par les accessors decimal
     */
    protected static function recalculerDepuisId(?int $mensuelleId): void
    {
        if (!$mensuelleId || self::$processing) return;

        self::$processing = true;

        try {
            $prevision = \App\Models\PrevisionRecetteMensuelle::find($mensuelleId);
            if (!$prevision) return;

            // ── 1. Recalculer le montant recouvré du mois ────────
            $montantRecouvre = \DB::table('recettes_reelles')
                ->where('prevision_recette_mensuelle_id', $mensuelleId)
                ->whereIn('statut', ['encaissee', 'comptabilisee', 'validee'])
                ->whereNull('deleted_at')
                ->sum(\DB::raw('CAST(montant AS FLOAT)'));

            $montantRecouvre = (float) $montantRecouvre;
            $montantPrevu    = (float) $prevision->montant_prevu;

            $prevision->updateQuietly([
                'montant_recouvre'  => $montantRecouvre,
                'ecart'             => $montantRecouvre - $montantPrevu,
                'taux_realisation'  => $montantPrevu > 0
                    ? round($montantRecouvre / $montantPrevu * 100, 2)
                    : 0,
            ]);

            // ── 2. Recalculer les cumulés de toute la ligne ───────
            $ligneId    = $prevision->ligne_prevision_recette_id;
            $mensuelles = \App\Models\PrevisionRecetteMensuelle::where(
                'ligne_prevision_recette_id',
                $ligneId
            )->orderBy('mois')->get();

            $cumulPrevu    = 0;
            $cumulRecouvre = 0;

            foreach ($mensuelles as $m) {
                $cumulPrevu    += (float) $m->montant_prevu;
                $cumulRecouvre += (float) $m->montant_recouvre;

                $m->updateQuietly([
                    'montant_cumule_prevu'    => $cumulPrevu,
                    'montant_cumule_recouvre' => $cumulRecouvre,
                    'taux_realisation_cumule' => $cumulPrevu > 0
                        ? round($cumulRecouvre / $cumulPrevu * 100, 2)
                        : 0,
                ]);
            }

            // ── 3. Mettre à jour la ligne annuelle ────────────────
            $totalRecouvre = $mensuelles->sum(fn($m) => (float)$m->montant_recouvre);
            $ligne = \App\Models\LignePrevisionRecette::find($ligneId);

            if ($ligne) {
                $montantRectifie = (float) \DB::table('lignes_previsions_recettes')
                    ->where('id', $ligneId)
                    ->value(\DB::raw('CAST(montant_rectifie AS FLOAT)'));

                $ligne->updateQuietly([
                    'montant_recouvre'  => $totalRecouvre,
                    'ecart'             => $totalRecouvre - $montantRectifie,
                    'taux_recouvrement' => $montantRectifie > 0
                        ? round($totalRecouvre / $montantRectifie * 100, 2)
                        : 0,
                ]);
            }
        } finally {
            self::$processing = false;
        }
    }

    // ====================================
    // RECALCUL PUBLIC (appelable manuellement)
    // ====================================

    public function recalculerPrevisions(): void
    {
        self::recalculerDepuisId($this->prevision_recette_mensuelle_id);
    }
}
