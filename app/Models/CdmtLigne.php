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
}
