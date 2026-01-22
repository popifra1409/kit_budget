<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasExercice;
use Illuminate\Database\Eloquent\Builder;

class PrevisionRecette extends Model
{
    use HasFactory, SoftDeletes, HasExercice;

    protected $table = 'previsions_recettes';

    protected $fillable = [
        'exercice_id',
        'code',
        'libelle',
        'exercice',
        'date_adoption',
        'date_revision',
        'statut',
        'actif',
        'observations',
    ];

    protected $casts = [
        'date_adoption' => 'date',
        'date_revision' => 'date',
        'actif' => 'boolean',
        'exercice' => 'integer',
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
     * Lignes de prévisions de recettes
     */
    public function lignesPrevisions(): HasMany
    {
        return $this->hasMany(LignePrevisionRecette::class);
    }

    /**
     * Recettes réelles liées (via lignes)
     */
    public function recettesReelles()
    {
        return RecetteReelle::whereIn(
            'ligne_prevision_recette_id',
            $this->lignesPrevisions()->pluck('id')
        );
    }

    // ====================================
    // CALCULS - PRÉVISIONS
    // ====================================

    /**
     * Total des prévisions initiales
     */
    public function getTotalPrevuInitial(): float
    {
        return $this->lignesPrevisions()->sum('montant_prevu_initial');
    }

    /**
     * Total des prévisions rectifiées
     */
    public function getTotalPrevuRectifie(): float
    {
        return $this->lignesPrevisions()->sum('montant_rectifie');
    }

    /**
     * Total effectivement recouvré
     */
    public function getTotalRecouvre(): float
    {
        return $this->lignesPrevisions()->sum('montant_recouvre');
    }

    /**
     * Écart global (recouvré - prévu rectifié)
     */
    public function getEcartGlobal(): float
    {
        return $this->getTotalRecouvre() - $this->getTotalPrevuRectifie();
    }

    /**
     * Taux de recouvrement global
     */
    public function getTauxRecouvrement(): float
    {
        $prevu = $this->getTotalPrevuRectifie();

        if ($prevu == 0) {
            return 0;
        }

        return ($this->getTotalRecouvre() / $prevu) * 100;
    }

    // ====================================
    // STATUTS & ÉTATS
    // ====================================

    /**
     * Est en élaboration
     */
    public function estEnElaboration(): bool
    {
        return $this->statut === 'elaboration';
    }

    /**
     * Est adopté
     */
    public function estAdopte(): bool
    {
        return $this->statut === 'adopte';
    }

    /**
     * Est en exécution
     */
    public function estEnExecution(): bool
    {
        return $this->statut === 'execution';
    }

    /**
     * Est clôturé
     */
    public function estCloture(): bool
    {
        return $this->statut === 'cloture';
    }

    /**
     * Est modifiable
     * Combine la logique de la prévision (statut) et de l'exercice
     */
    public function estModifiable(): bool
    {
        // D'abord vérifier le statut de la prévision
        $modifiableParStatut = in_array($this->statut, ['elaboration', 'adopte']);

        if (!$modifiableParStatut) {
            return false;
        }

        // Ensuite vérifier l'exercice (utilise la logique du trait)
        // Charger la relation si pas déjà chargée
        if (!$this->relationLoaded('exercice')) {
            $this->load('exercice');
        }

        $exercice = $this->exercice;

        // Si pas d'exercice lié = modifiable
        if (!$exercice) {
            return true;
        }

        // Si c'est un int (ID), récupérer l'objet
        if (is_int($exercice)) {
            $exercice = \App\Models\Exercice::find($exercice);
            if (!$exercice) {
                return true;
            }
        }

        // Vérifier que c'est bien un objet Exercice
        if (!$exercice instanceof \App\Models\Exercice) {
            return true;
        }

        return $exercice->estModifiable();
    }

    /**
     * Est en lecture seule
     * Combine la logique de la prévision (statut clôturé) et de l'exercice
     */
    public function estLectureSeule(): bool
    {
        // Si la prévision elle-même est clôturée
        if ($this->estCloture()) {
            return true;
        }

        // Charger la relation si pas déjà chargée
        if (!$this->relationLoaded('exercice')) {
            $this->load('exercice');
        }

        $exercice = $this->exercice;

        // Si pas d'exercice lié = pas en lecture seule
        if (!$exercice) {
            return false;
        }

        // Si c'est un int (ID), récupérer l'objet
        if (is_int($exercice)) {
            $exercice = \App\Models\Exercice::find($exercice);
            if (!$exercice) {
                return false;
            }
        }

        // Vérifier que c'est bien un objet Exercice
        if (!$exercice instanceof \App\Models\Exercice) {
            return false;
        }

        return $exercice->estLectureSeule();
    }

    // ====================================
    // ACTIONS
    // ====================================

    /**
     * Adopter la prévision de recettes
     */
    public function adopter(?string $date = null): bool
    {
        if ($this->estAdopte()) {
            return false;
        }

        $this->update([
            'statut' => 'adopte',
            'date_adoption' => $date ?? now(),
        ]);

        return true;
    }

    /**
     * Mettre en exécution
     */
    public function mettreEnExecution(): bool
    {
        if (!$this->estAdopte()) {
            return false;
        }

        $this->update(['statut' => 'execution']);

        return true;
    }

    /**
     * Clôturer
     */
    public function cloturer(): bool
    {
        if (!$this->estEnExecution()) {
            return false;
        }

        $this->update(['statut' => 'cloture']);

        return true;
    }

    /**
     * Réviser (créer une nouvelle version rectificative)
     */
    public function reviser(array $modifications = []): self
    {
        $nouvellePrevision = $this->replicate();
        $nouvellePrevision->code = $this->code . '-REV-' . now()->format('Ymd');
        $nouvellePrevision->libelle = $this->libelle . ' (Révisé)';
        $nouvellePrevision->date_revision = now();
        $nouvellePrevision->save();

        // Copier les lignes
        foreach ($this->lignesPrevisions as $ligne) {
            $nouvelleLigne = $ligne->replicate();
            $nouvelleLigne->prevision_recette_id = $nouvellePrevision->id;
            $nouvelleLigne->save();
        }

        return $nouvellePrevision;
    }

    // ====================================
    // SCOPES
    // ====================================

    /**
     * Scope: Prévisions actives
     */
    public function scopeActives($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope: Par exercice
     */
    public function scopeExercice($query, $exercice)
    {
        return $query->where('exercice', $exercice);
    }

    /**
     * Scope: Par statut
     */
    public function scopeStatut($query, string $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Scope: En élaboration
     */
    public function scopeEnElaboration($query)
    {
        return $query->where('statut', 'elaboration');
    }

    /**
     * Scope: Adoptées
     */
    public function scopeAdoptees($query)
    {
        return $query->where('statut', 'adopte');
    }

    /**
     * Scope: En exécution
     */
    public function scopeEnExecution($query)
    {
        return $query->where('statut', 'execution');
    }

    /**
     * Scope pour charger les relations courantes
     */
    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with([
            'exercice',
            'lignesPrevisions',
            'createdBy',
            'updatedBy',
        ]);
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function boot()
    {
        parent::boot();

        // Auto-remplir l'exercice depuis exercice_id
        static::creating(function ($prevision) {
            if (!$prevision->exercice && $prevision->exercice_id) {
                $exercice = Exercice::find($prevision->exercice_id);
                if ($exercice) {
                    $prevision->exercice = $exercice->annee;
                }
            }
        });
    }
}
