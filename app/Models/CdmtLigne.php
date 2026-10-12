<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CdmtLigne extends Model
{
    protected $table = 'cdmt_lignes';

    protected $fillable = [
        'cdmt_exercice_id',
        'sous_programme_ep_id',
        'action_id',
        'activite_id',
        'tache_id',          // ✅ sous-tâche du Module 01 (tâche × code budgétaire)
        'nomenclature_id',
        'code',
        'titre',
        'ordre',
        'libelle',
        'nature',
        'maturite',
        'avant_n_moins_1',
        'est_investissement',
        'n_ae',
        'n_cp',
        'n_plus_1_ae',
        'n_plus_1_cp',
        'n_plus_2_ae',
        'n_plus_2_cp',
        'n_plus_3_ae',
        'n_plus_3_cp',
        'commentaire',
    ];

    protected $casts = [
        'avant_n_moins_1' => 'decimal:2',
        'n_ae' => 'decimal:2',
        'n_cp' => 'decimal:2',
        'est_investissement' => 'boolean',
        'n_plus_1_ae' => 'decimal:2',
        'n_plus_1_cp' => 'decimal:2',
        'n_plus_2_ae' => 'decimal:2',
        'n_plus_2_cp' => 'decimal:2',
        'n_plus_3_ae' => 'decimal:2',
        'n_plus_3_cp' => 'decimal:2',
        'titre' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $model) {
            static::validerMaturite($model);
        });
    }

    /**
     * Guide d'arrimage 2025, Encadre 6 : "la programmation budgetaire
     * d'un projet d'investissement public dans le CDMT est subordonnee
     * au visa de maturite."
     */
    protected static function validerMaturite(self $model): void
    {
        if (!$model->est_investissement) {
            return;
        }

        if (empty($model->maturite) || $model->maturite === 'non_requise') {
            throw new \Exception(
                "« {$model->libelle} » est marquée comme dépense d'investissement : elle doit "
                    . "obligatoirement porter un élément de maturité (études, DAO, en cours, ou mature). "
                    . "La programmation d'un projet d'investissement est subordonnée au visa de maturité "
                    . "(Guide d'arrimage 2025, Encadré 6)."
            );
        }
    }

    /**
     * Alerte visuelle (non bloquante) : investissement dont la maturite
     * n'est pas encore au niveau "mature" (visa obtenu).
     */
    public function getAlerteMaturite(): ?string
    {
        if (!$this->est_investissement) {
            return null;
        }

        return match ($this->maturite) {
            'mature' => null,
            'etudes' => '⚠️ Études en cours — maturité insuffisante pour engagement définitif',
            'dao' => '⚠️ DAO en préparation — vérifier l\'échéancier avant engagement',
            'en_cours' => 'ℹ️ Réalisation en cours',
            default => '🛑 Maturité non renseignée',
        };
    }
    public function cdmtExercice(): BelongsTo
    {
        return $this->belongsTo(CdmtExercice::class);
    }

    public function sousProgrammeEp(): BelongsTo
    {
        return $this->belongsTo(SousProgrammeEp::class, 'sous_programme_ep_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class, 'action_id');
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class, 'activite_id');
    }

    public function tache(): BelongsTo
    {
        return $this->belongsTo(Tache::class, 'tache_id')->withoutGlobalScope('exercice');
    }

    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_id');
    }

    /**
     * ✅ Montants de programmation non renseignés (AE ou CP à zéro pour N+1..N+3),
     * libellés avec l'année : ['2028 AE', '2028 CP', ...]. Vide si la ligne est complète.
     */
    public function montantsManquants(?int $anneeReference = null): array
    {
        $n = $anneeReference ?? $this->cdmtExercice?->anneeReference() ?? (int) now()->year;
        $manquants = [];

        foreach ([1, 2, 3] as $d) {
            foreach (['ae' => 'AE', 'cp' => 'CP'] as $champ => $libelle) {
                if ((float) $this->{"n_plus_{$d}_{$champ}"} <= 0) {
                    $manquants[] = ($n + $d) . " {$libelle}";
                }
            }
        }

        return $manquants;
    }

    /** Condition SQL : au moins un AE ou CP de N+1..N+3 non renseigné. */
    public function scopeACompleter($query)
    {
        return $query->where(function ($q) {
            foreach ([1, 2, 3] as $d) {
                $q->orWhere("n_plus_{$d}_ae", '<=', 0)->orWhereNull("n_plus_{$d}_ae")
                    ->orWhere("n_plus_{$d}_cp", '<=', 0)->orWhereNull("n_plus_{$d}_cp");
            }
        });
    }

    public function getLibelleTitreAttribute(): string
    {
        return $this->titre ? "Titre {$this->titre} — " . (CbmtLigne::TITRES_DEPENSES[$this->titre] ?? '') : 'Sans titre';
    }
}
