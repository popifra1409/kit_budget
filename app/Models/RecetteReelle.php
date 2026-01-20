<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasExercice;

class RecetteReelle extends Model
{
    use HasFactory, SoftDeletes, HasExercice;

    protected $table = 'recettes_reelles';

    protected $fillable = [
        'exercice_id',
        'prevision_recette_mensuelle_id',
        'nomenclature_id',
        'numero',
        'code_nomenclature',
        'libelle',
        'mois',
        'annee',
        'date_recette',
        'date_comptabilisation',
        'montant',
        'payeur',
        'mode_paiement',
        'reference_paiement',
        'statut',
        'validee_par',
        'validee_le',
        'observations',
    ];

    protected $casts = [
        'mois' => 'integer',
        'annee' => 'integer',
        'date_recette' => 'date',
        'date_comptabilisation' => 'date',
        'montant' => 'decimal:2',
        'validee_le' => 'datetime',
    ];

    // ====================================
    // RELATIONS
    // ====================================

    /**
     * Exercice budgétaire
     */
    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    /**
     * Prévision mensuelle associée
     */
    public function previsionRecetteMensuelle(): BelongsTo
    {
        return $this->belongsTo(PrevisionRecetteMensuelle::class);
    }

    /**
     * Ligne de prévision annuelle (via prévision mensuelle)
     * Accesseur pour faciliter l'accès
     */
    public function getLignePrevisionRecetteAttribute()
    {
        return $this->previsionRecetteMensuelle?->lignePrevisionRecette;
    }

    /**
     * Nomenclature budgétaire
     */
    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class);
    }

    /**
     * Validateur
     */
    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }

    // ====================================
    // ACCESSEURS
    // ====================================

    /**
     * Nom du mois en français
     */
    public function getNomMoisAttribute(): string
    {
        $mois = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        
        return $mois[$this->mois] ?? '';
    }

    /**
     * Période complète (ex: Janvier 2026)
     */
    public function getPeriodeAttribute(): string
    {
        return $this->nom_mois . ' ' . $this->annee;
    }

    // ====================================
    // STATUTS
    // ====================================

    /**
     * Est prévue
     */
    public function estPrevue(): bool
    {
        return $this->statut === 'prevue';
    }

    /**
     * Est encaissée
     */
    public function estEncaissee(): bool
    {
        return $this->statut === 'encaissee';
    }

    /**
     * Est comptabilisée
     */
    public function estComptabilisee(): bool
    {
        return $this->statut === 'comptabilisee';
    }

    /**
     * Est validée
     */
    public function estValidee(): bool
    {
        return $this->statut === 'validee';
    }

    // ====================================
    // ACTIONS
    // ====================================

    /**
     * Comptabiliser la recette
     */
    public function comptabiliser(?string $date = null): bool
    {
        if (!$this->estEncaissee()) {
            return false;
        }

        $this->update([
            'statut' => 'comptabilisee',
            'date_comptabilisation' => $date ?? now(),
        ]);

        // Mettre à jour la prévision mensuelle
        $this->mettreAJourPrevisionMensuelle();

        return true;
    }

    /**
     * Valider la recette
     */
    public function valider(int $userId): bool
    {
        if (!$this->estComptabilisee()) {
            return false;
        }

        $this->update([
            'statut' => 'validee',
            'validee_par' => $userId,
            'validee_le' => now(),
        ]);

        return true;
    }

    /**
     * Mettre à jour la prévision mensuelle associée
     */
    protected function mettreAJourPrevisionMensuelle(): void
    {
        if ($this->prevision_recette_mensuelle_id) {
            $prevision = $this->previsionRecetteMensuelle;
            $prevision->recalculer();
            $prevision->recalculerMoisSuivants();
            
            // Mettre à jour la ligne annuelle
            $prevision->lignePrevisionRecette->calculerMontantRecouvre();
            $prevision->lignePrevisionRecette->calculerEcart();
            $prevision->lignePrevisionRecette->calculerTauxRecouvrement();
        }
    }

    // ====================================
    // SCOPES
    // ====================================

    /**
     * Scope: Par exercice
     */
    public function scopeExercice($query, $exercice)
    {
        return $query->whereHas('exercice', function ($q) use ($exercice) {
            $q->where('annee', $exercice);
        });
    }

    /**
     * Scope: Par statut
     */
    public function scopeStatut($query, string $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Scope: Encaissées
     */
    public function scopeEncaissees($query)
    {
        return $query->where('statut', 'encaissee');
    }

    /**
     * Scope: Validées
     */
    public function scopeValidees($query)
    {
        return $query->where('statut', 'validee');
    }

    /**
     * Scope: Par période
     */
    public function scopePeriode($query, $dateDebut, $dateFin)
    {
        return $query->whereBetween('date_recette', [$dateDebut, $dateFin]);
    }

    /**
     * Scope: Par mode de paiement
     */
    public function scopeModePaiement($query, string $mode)
    {
        return $query->where('mode_paiement', $mode);
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function boot()
    {
        parent::boot();

        // Générer numéro automatique et extraire mois/année
        static::creating(function ($recette) {
            if (!$recette->numero) {
                $recette->numero = 'REC-' . now()->format('Y') . '-' . str_pad(
                    static::whereYear('created_at', now()->year)->count() + 1,
                    6,
                    '0',
                    STR_PAD_LEFT
                );
            }
            
            // Extraire mois et année de la date_recette
            if ($recette->date_recette && !$recette->mois) {
                $date = \Carbon\Carbon::parse($recette->date_recette);
                $recette->mois = $date->month;
                $recette->annee = $date->year;
            }
        });

        // Après sauvegarde, mettre à jour la prévision mensuelle
        static::saved(function ($recette) {
            $recette->mettreAJourPrevisionMensuelle();
        });

        // Après suppression, mettre à jour la prévision mensuelle
        static::deleted(function ($recette) {
            $recette->mettreAJourPrevisionMensuelle();
        });
    }
}