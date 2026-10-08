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
        'date_constatation',   // ✅ créance certaine enregistrée
        'date_encaissement',   // ✅ paiement effectif
        'montant_constate',    // ✅ recette ATTENDUE ; « montant » = recette ENCAISSÉE
        'tiers_recette_id',    // ✅ débiteur / payeur (référentiel)
    ];

    /** Statuts : seuls « encaissee », « comptabilisee » et « validee » comptent dans le recouvré. */
    public const STATUTS = [
        'prevue'        => 'Prévue',
        'constatee'     => 'Constatée (à recouvrer)',
        'encaissee'     => 'Encaissée',
        'comptabilisee' => 'Comptabilisée',
        'validee'       => 'Validée',
    ];

    public const STATUTS_RECOUVRES = ['encaissee', 'comptabilisee', 'validee'];

    protected $casts = [
        'montant'               => 'decimal:2',
        'montant_constate'      => 'decimal:2',
        'date_recette'          => 'date',
        'date_comptabilisation' => 'date',
        'date_constatation'     => 'date',
        'date_encaissement'     => 'date',
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

    /** ✅ Débiteur / payeur (référentiel des tiers). */
    public function tiers(): BelongsTo
    {
        return $this->belongsTo(TiersRecette::class, 'tiers_recette_id')->withTrashed();
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
    /** ✅ Créance certaine, non encore encaissée (RAR à la clôture). */
    public function estConstatee(): bool
    {
        return $this->statut === 'constatee';
    }

    /** ✅ Recette comptée dans le recouvré. */
    public function estRecouvree(): bool
    {
        return in_array($this->statut, self::STATUTS_RECOUVRES, true);
    }

    public function scopeConstatees($query)
    {
        return $query->where('statut', 'constatee');
    }

    /** ✅ RAR de la recette : attendu − encaissé (jamais négatif). */
    public function getResteARecouvrerAttribute(): float
    {
        return max(0, round((float) ($this->montant_constate ?? $this->montant) - (float) $this->montant, 2));
    }

    /** Expression SQL du reste à recouvrer (pour les totaux). */
    public static function sqlResteARecouvrer(string $alias = ''): string
    {
        $p = $alias ? "{$alias}." : '';

        return "GREATEST(COALESCE(CAST({$p}montant_constate AS FLOAT), CAST({$p}montant AS FLOAT)) - CAST({$p}montant AS FLOAT), 0)";
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

    /**
     * ✅ Encaissement (total ou PARTIEL) d'une recette attendue : le montant encaissé augmente,
     * le reste à recouvrer diminue ; le statut se met à jour automatiquement à l'enregistrement.
     */
    public function encaisser(array $paiement = []): bool
    {
        $reste = $this->reste_a_recouvrer;
        $montant = round((float) ($paiement['montant'] ?? $reste), 2);

        if ($reste <= 0 || $montant <= 0 || $montant > $reste + 0.01) {
            return false;
        }

        $this->update([
            'montant'            => (float) $this->montant + $montant,
            'date_encaissement'  => $paiement['date_encaissement'] ?? now()->toDateString(),
            'mode_paiement'      => $paiement['mode_paiement'] ?? $this->mode_paiement,
            'reference_paiement' => $paiement['reference_paiement'] ?? $this->reference_paiement,
        ]);

        \App\Models\ActivityLog::logAction($this, 'encaisser', [
            'montant_encaisse' => $montant,
            'reste_a_recouvrer' => $this->reste_a_recouvrer,
            'date' => $this->date_encaissement?->format('d/m/Y'),
        ]);

        return true;
    }

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

        // ✅ Statut automatique : « constatée » tant qu'il reste à recouvrer, « encaissée » une fois soldée.
        //    Les étapes comptabilisée / validée ne sont pas modifiées.
        static::saving(function ($recette) {
            // ✅ Le champ texte « payeur » reprend le nom du tiers (compatibilité des états existants)
            if ($recette->isDirty('tiers_recette_id')) {
                $recette->payeur = $recette->tiers_recette_id
                    ? \App\Models\TiersRecette::withTrashed()->whereKey($recette->tiers_recette_id)->value('nom')
                    : $recette->payeur;
            }

            if ($recette->montant_constate === null) {
                $recette->montant_constate = $recette->montant;
            }

            if (!in_array($recette->statut, ['comptabilisee', 'validee', 'prevue'], true)) {
                $recette->statut = (float) $recette->montant + 0.01 >= (float) $recette->montant_constate ? 'encaissee' : 'constatee';
            }

            if ((float) $recette->montant > 0 && !$recette->date_encaissement) {
                $recette->date_encaissement = $recette->date_recette ?? now()->toDateString();
            }
            if (!$recette->date_constatation) {
                $recette->date_constatation = $recette->date_recette ?? now()->toDateString();
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
                ->where('statut', '!=', 'prevue')   // « montant » = part ENCAISSÉE (0 pour une créance non perçue)
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
