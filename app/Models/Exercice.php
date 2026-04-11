<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Exercice extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'exercices';

    protected $fillable = [
        'annee',
        'libelle',
        'description',
        'statut',
        'date_debut',
        'date_fin',
        'date_cloture',
        'date_archive',
        'cloture_par',
        'archive_par',
        'actif',
        'reconduction_effectuee',
        'exercice_source_id',
        'statistiques',
        'observations',
    ];

    protected $casts = [
        'annee' => 'integer',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date_cloture' => 'date',
        'date_archive' => 'date',
        'actif' => 'boolean',
        'reconduction_effectuee' => 'boolean',
        'statistiques' => 'array',
    ];

    /**
     * Boot - Gestion automatique de l'exercice actif unique
     */
    protected static function boot()
    {
        parent::boot();

        // Avant sauvegarde : s'assurer qu'un seul exercice est actif
        static::saving(function ($exercice) {
            if ($exercice->actif) {
                // Désactiver tous les autres exercices
                static::where('id', '!=', $exercice->id)->update(['actif' => false]);
            }
        });
    }

    // ========================================
    // RELATIONS
    // ========================================

    /**
     * Relation : Utilisateur qui a clôturé
     */
    public function cloturePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cloture_par');
    }

    /**
     * Relation : Utilisateur qui a archivé
     */
    public function archivePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archive_par');
    }

    /**
     * Relation : Exercice source (pour reconduction)
     */
    public function exerciceSource(): BelongsTo
    {
        return $this->belongsTo(Exercice::class, 'exercice_source_id');
    }

    /**
     * Relation : Exercices dérivés (reconduits depuis cet exercice)
     */
    public function exercicesDerives(): HasMany
    {
        return $this->hasMany(Exercice::class, 'exercice_source_id');
    }

    /**
     * Relation : Programmes liés à cet exercice
     */
    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class, 'exercice_id');
    }

    /**
     * Relation : Budgets liés à cet exercice
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class, 'exercice_id');
    }

    /**
     * Relation : Bordereaux d'engagement
     */
    public function bordereaux(): HasMany
    {
        return $this->hasMany(BordereauEngagement::class, 'exercice');
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope : Exercice actif
     */
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope : Par statut
     */
    public function scopeStatut($query, string $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Scope : Exercices ouverts (brouillon ou actif)
     */
    public function scopeOuverts($query)
    {
        return $query->whereIn('statut', ['brouillon', 'actif']);
    }

    /**
     * Scope : Exercices fermés (clôturé ou archivé)
     */
    public function scopeFermes($query)
    {
        return $query->whereIn('statut', ['cloture', 'archive']);
    }

    // ========================================
    // MÉTHODES DE STATUT
    // ========================================

    /**
     * Vérifier si l'exercice est en brouillon
     */
    public function estBrouillon(): bool
    {
        return $this->statut === 'brouillon';
    }

    /**
     * Vérifier si l'exercice est actif
     */
    public function estActif(): bool
    {
        return $this->statut === 'actif' && $this->actif;
    }

    /**
     * Vérifier si l'exercice est clôturé
     */
    public function estCloture(): bool
    {
        return $this->statut === 'cloture';
    }

    /**
     * Vérifier si l'exercice est archivé
     */
    public function estArchive(): bool
    {
        return $this->statut === 'archive';
    }

    /**
     * Vérifier si l'exercice est modifiable
     */
    public function estModifiable(): bool
    {
        return in_array($this->statut, ['brouillon', 'actif']);
    }

    /**
     * Vérifier si l'exercice est en lecture seule
     */
    public function estLectureSeule(): bool
    {
        return in_array($this->statut, ['cloture', 'archive']);
    }

    // ========================================
    // MÉTHODES D'ACTION
    // ========================================

    public function activer(User $user): void
    {
        if ($this->statut !== 'brouillon') {
            throw new \Exception("Seul un exercice en brouillon peut être activé");
        }

        \DB::transaction(function () use ($user) {
            // Désactiver tous les autres exercices
            static::where('id', '!=', $this->id)->update(['actif' => false]);

            $this->statut = 'actif';
            $this->actif = true;
            $this->save();

            // Log l'action
            activity()
                ->performedOn($this)
                ->causedBy($user)
                ->withProperties(['ancien_statut' => 'brouillon', 'nouveau_statut' => 'actif'])
                ->log("Exercice {$this->annee} activé");
        });
    }

    public function cloturer(User $user): void
    {
        if ($this->statut !== 'actif') {
            throw new \Exception("Seul un exercice actif peut être clôturé");
        }

        \DB::transaction(function () use ($user) {
            $this->statut = 'cloture';
            $this->actif = false;
            $this->date_cloture = now();
            $this->cloture_par = $user->id;
            $this->save();

            // Log l'action
            activity()
                ->performedOn($this)
                ->causedBy($user)
                ->withProperties([
                    'ancien_statut' => 'actif',
                    'nouveau_statut' => 'cloture',
                    'statistiques' => $this->statistiques
                ])
                ->log("Exercice {$this->annee} clôturé");
        });
    }

    public function archiver(User $user): void
    {
        if ($this->statut !== 'cloture') {
            throw new \Exception("Seul un exercice clôturé peut être archivé");
        }

        \DB::transaction(function () use ($user) {
            $this->statut = 'archive';
            $this->date_archive = now();
            $this->archive_par = $user->id;
            $this->save();

            // Log l'action
            activity()
                ->performedOn($this)
                ->causedBy($user)
                ->withProperties(['ancien_statut' => 'cloture', 'nouveau_statut' => 'archive'])
                ->log("Exercice {$this->annee} archivé");
        });
    }

    public function rouvrir(User $user): void
    {
        if (!in_array($this->statut, ['cloture', 'archive'])) {
            throw new \Exception("Seul un exercice clôturé ou archivé peut être rouvert");
        }

        \DB::transaction(function () use ($user) {
            $ancienStatut = $this->statut;

            $this->statut = 'actif';
            $this->actif = true;
            $this->date_cloture = null;
            $this->date_archive = null;
            $this->cloture_par = null;
            $this->archive_par = null;
            $this->save();

            // Log l'action
            activity()
                ->performedOn($this)
                ->causedBy($user)
                ->withProperties([
                    'ancien_statut' => $ancienStatut,
                    'nouveau_statut' => 'actif',
                    'action_admin' => true
                ])
                ->log("Exercice {$this->annee} rouvert par admin");
        });
    }

    // ========================================
    // MÉTHODES UTILITAIRES
    // ========================================

    /**
     * Obtenir l'exercice actif
     */
    public static function getActif(): ?self
    {
        return static::where('actif', true)->first();
    }

    /**
     * Obtenir l'exercice par année
     */
    public static function parAnnee(int $annee): ?self
    {
        return static::where('annee', $annee)->first();
    }

    /**
     * Obtenir le badge de statut (pour Filament)
     */
    public function getBadgeStatut(): string
    {
        return match ($this->statut) {
            'brouillon' => '📝 Brouillon',
            'actif' => '✅ Actif',
            'cloture' => '🔒 Clôturé',
            'archive' => '📦 Archivé',
            default => $this->statut,
        };
    }

    /**
     * Obtenir la couleur du badge (pour Filament)
     */
    public function getCouleurStatut(): string
    {
        return match ($this->statut) {
            'brouillon' => 'gray',
            'actif' => 'success',
            'cloture' => 'warning',
            'archive' => 'danger',
            default => 'primary',
        };
    }

    /**
     * Calculer les statistiques de l'exercice
     */
    public function calculerStatistiques(): array
    {
        // Calculer AE et CP depuis les tâches
        $montantAE = \App\Models\Tache::where('exercice_id', $this->id)
            ->sum('ae');

        $montantCP = \App\Models\Tache::where('exercice_id', $this->id)
            ->sum('cp');

        return [
            'nb_programmes' => $this->programmes()->count(),
            'nb_budgets' => $this->budgets()->count(),
            'nb_bordereaux' => $this->bordereaux()->count(),
            'montant_total_ae' => $montantAE,
            'montant_total_cp' => $montantCP,
            'date_calcul' => now()->toDateTimeString(),
        ];
    }

    /**
     * Mettre à jour les statistiques
     */
    public function mettreAJourStatistiques(): void
    {
        $this->statistiques = $this->calculerStatistiques();
        $this->saveQuietly();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['annee', 'libelle', 'statut', 'actif', 'date_debut', 'date_fin'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Exercice {$eventName}");
    }
}
