<?php

namespace App\Traits;

use App\Models\Exercice;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

trait HasExercice
{
    /**
     * Boot du trait
     */
    protected static function bootHasExercice(): void
    {
        // Lors de la création, assigner automatiquement l'exercice actif
        static::creating(function ($model) {
            if (!$model->exercice_id) {
                $exerciceActif = Exercice::getActif();
                if ($exerciceActif) {
                    $model->exercice_id = $exerciceActif->id;
                }
            }
        });

        // Global scope : filtrer automatiquement par exercice actif (si activé)
        if (config('app.filter_by_exercice', true)) {
            static::addGlobalScope('exercice', function (Builder $builder) {
                // Ne pas appliquer le scope si on est dans une commande de migration/seed
                if (app()->runningInConsole() && !app()->runningUnitTests()) {
                    return;
                }

                // Si un exercice spécifique est sélectionné en session
                $exerciceId = session('exercice_id');
                if ($exerciceId) {
                    $builder->where(static::getExerciceColumn(), $exerciceId);
                    return;
                }

                // Sinon, filtrer par exercice actif
                $exerciceActif = Exercice::getActif();
                if ($exerciceActif) {
                    $builder->where(static::getExerciceColumn(), $exerciceActif->id);
                }
            });
        }
    }

    /**
     * Relation : Exercice
     */
    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class, static::getExerciceColumn());
    }

    /**
     * Scope : Sans filtre exercice
     * Utile pour obtenir toutes les données sans filtrage
     */
    public function scopeSansExercice(Builder $query): Builder
    {
        return $query->withoutGlobalScope('exercice');
    }

    /**
     * Scope : Par exercice spécifique
     */
    public function scopeParExercice(Builder $query, int|Exercice $exercice): Builder
    {
        $exerciceId = $exercice instanceof Exercice ? $exercice->id : $exercice;

        return $query->withoutGlobalScope('exercice')
            ->where(static::getExerciceColumn(), $exerciceId);
    }

    /**
     * Scope : Par année
     */
    public function scopeParAnnee(Builder $query, int $annee): Builder
    {
        return $query->withoutGlobalScope('exercice')
            ->whereHas('exercice', function ($q) use ($annee) {
                $q->where('annee', $annee);
            });
    }

    /**
     * Scope : Exercice actif uniquement
     */
    public function scopeExerciceActif(Builder $query): Builder
    {
        return $query->withoutGlobalScope('exercice')
            ->whereHas('exercice', function ($q) {
                $q->where('actif', true);
            });
    }

    /**
     * Scope : Exercices ouverts (brouillon ou actif)
     */
    public function scopeExercicesOuverts(Builder $query): Builder
    {
        return $query->withoutGlobalScope('exercice')
            ->whereHas('exercice', function ($q) {
                $q->whereIn('statut', ['brouillon', 'actif']);
            });
    }

    /**
     * Scope : Exercices fermés (cloturé ou archivé)
     */
    public function scopeExercicesFermes(Builder $query): Builder
    {
        return $query->withoutGlobalScope('exercice')
            ->whereHas('exercice', function ($q) {
                $q->whereIn('statut', ['cloture', 'archive']);
            });
    }

    /**
     * Vérifier si l'enregistrement est modifiable
     * (selon le statut de l'exercice)
     */
    public function estModifiable(): bool
    {
        // Charger la relation si elle n'est pas chargée
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
                return true; // Exercice introuvable = modifiable par défaut
            }
        }

        // Vérifier que c'est bien un objet Exercice
        if (!$exercice instanceof \App\Models\Exercice) {
            return true; // Si ce n'est pas un objet Exercice, autoriser par défaut
        }

        return $exercice->estModifiable();
    }

    /**
     * Vérifier si l'enregistrement est en lecture seule
     */
    public function estLectureSeule(): bool
    {
        // Charger la relation si elle n'est pas chargée
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
                return false; // Exercice introuvable = pas en lecture seule
            }
        }

        // Vérifier que c'est bien un objet Exercice
        if (!$exercice instanceof \App\Models\Exercice) {
            return false; // Si ce n'est pas un objet Exercice, pas en lecture seule
        }

        return $exercice->estLectureSeule();
    }

    /**
     * Obtenir le nom de la colonne exercice
     * (peut être surchargé dans les modèles si différent)
     */
    protected static function getExerciceColumn(): string
    {
        return 'exercice_id';
    }

    /**
     * Obtenir l'exercice actif
     */
    public static function getExerciceActif(): ?Exercice
    {
        return Exercice::getActif();
    }

    /**
     * Définir l'exercice en session
     */
    public static function setExerciceSession(int|Exercice $exercice): void
    {
        $exerciceId = $exercice instanceof Exercice ? $exercice->id : $exercice;
        session(['exercice_id' => $exerciceId]);
    }

    /**
     * Effacer l'exercice de la session (retour au mode auto)
     */
    public static function clearExerciceSession(): void
    {
        session()->forget('exercice_id');
    }

    /**
     * Obtenir l'exercice depuis la session
     */
    public static function getExerciceSession(): ?int
    {
        return session('exercice_id');
    }
}
