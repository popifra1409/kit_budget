<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasExercice;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Traits\HasCorbeille;

class Tache extends Model
{

    /** Relations dont les lignes partent avec ce document dans la corbeille. */
    public static function corbeilleEnfants(): array
    {
        return ['enfants'];
    }

    use HasFactory, SoftDeletes, HasExercice, LogsActivity, HasCorbeille;

    protected $table = 'taches';

    protected $fillable = [
        'exercice_id',
        'activite_id',
        'parent_id',
        'niveau',
        'nomenclature_id',
        'code',
        'libelle',
        'description',
        'delai',
        'guichet',
        'service_id',
        'ae',
        'cp',
        'resultat_attendu',
        'indicateur_resultat',
        'ordre',
        'actif',
    ];

    protected $casts = [
        'ae' => 'decimal:2',
        'cp' => 'decimal:2',
        'ordre' => 'integer',
        'actif' => 'boolean',
    ];

    /**
     * Relation : Activité parent
     */
    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class, 'activite_id');
    }

    /**
     * Relation : Tâche parent
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Tache::class, 'parent_id');
    }

    /**
     * Relation : Sous-tâches
     */
    public function sousTaches(): HasMany
    {
        return $this->hasMany(Tache::class, 'parent_id')->where('niveau', 'sous_tache');
    }

    /**
     * Relation : Enfants (tâches ou sous-tâches)
     */
    public function enfants(): HasMany
    {
        return $this->hasMany(Tache::class, 'parent_id');
    }

    /**
     * Relation : Nomenclature budgétaire liée
     * Portée par une sous-tâche, ou par une tâche principale SANS sous-tâche (liaison directe).
     */
    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class);
    }

    /**
     * Resout la LigneBudgetaire reelle (module Budget/Execution) correspondant
     * a cette sous-tache, pour l'exercice de la tache. Uniquement pertinent
     * pour les sous-taches (niveau le plus fin, seul niveau qui porte
     * nomenclature_id).
     */
    public function ligneBudgetaire(): ?\App\Models\LigneBudgetaire
    {
        if (!$this->estPorteuseBudget()) {
            return null;
        }

        return \App\Models\LigneBudgetaire::where('nomenclature_id', $this->nomenclature_id)
            ->whereHas('budget', fn($q) => $q->where('exercice_id', $this->exercice_id))
            ->first();
    }

    /**
     * Relation : Service responsable
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * Scope : Tâches principales (sans parent)
     */
    public function scopeTachesPrincipales($query)
    {
        return $query->whereNull('parent_id')->where('niveau', 'tache');
    }

    /**
     * ✅ Scope : tâches PORTEUSES du budget (le niveau le plus fin, celui qui porte compte et AE/CP) :
     *    les sous-tâches, et les tâches principales liées directement à une nomenclature, sans sous-tâche.
     *    À utiliser partout où l'on additionnait les sous-tâches (concordance, CDMT…).
     */
    public function scopePorteusesBudget($query)
    {
        return $query->where(function ($q) {
            $q->where('niveau', 'sous_tache')
                ->orWhere(function ($t) {
                    $t->where('niveau', 'tache')
                        ->whereNotNull('nomenclature_id')
                        ->whereNotExists(function ($e) {
                            $e->selectRaw('1')->from('taches as st')
                                ->whereColumn('st.parent_id', 'taches.id')
                                ->where('st.niveau', 'sous_tache')
                                ->whereNull('st.deleted_at');
                        });
                });
        });
    }

    /** ✅ Porte-t-elle directement compte et AE/CP ? (sous-tâche, ou tâche liée directement sans sous-tâche) */
    public function estPorteuseBudget(): bool
    {
        if (!$this->nomenclature_id) {
            return false;
        }

        return $this->estSousTache()
            || ($this->niveau === 'tache' && (!$this->exists || !$this->sousTaches()->exists()));
    }

    /**
     * Scope : Sous-tâches uniquement
     */
    public function scopeSousTaches($query)
    {
        return $query->where('niveau', 'sous_tache');
    }

    /** Scope : ce qu'un nouveau document a le droit de choisir. */
    public function scopeActives($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Lignes de budget portées par la nomenclature de la tâche, dans SON exercice.
     *
     * `nomenclature_id` null est traité à part : comparée telle quelle, la clause devient
     * `nomenclature_id IS NULL` et ramène toutes les lignes sans nomenclature.
     *
     * Lu en DB::select brut : `budgets` porte un scope exercice global, qui masquerait la ligne
     * d'un autre exercice — celle-là même qu'il faut compter pour refuser.
     *
     * @return array<int, object> id, budget_rectifie, engage, paye
     */
    public function lignesBudgetPortees(): array
    {
        if (!$this->nomenclature_id) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::select(
            'select lb.id, lb.budget_rectifie, lb.engage, lb.paye
               from lignes_budgetaires lb
               join budgets b on b.id = lb.budget_id
              where lb.nomenclature_id = ?
                and b.exercice_id = ?
                and lb.deleted_at is null',
            [(int) $this->nomenclature_id, (int) $this->exercice_id]
        );
    }

    /**
     * Motifs qui EMPÊCHENT de désactiver la tâche — liste vide = désactivable.
     *
     * « Utilisée » se juge sur l'exercice de la tâche : la planification est répliquée chaque année,
     * une tâche de l'année close peut être vide pendant que sa copie porte le budget.
     *
     * Une tâche principale n'est pas bloquée par le seul fait d'AVOIR des sous-tâches : elle est
     * bloquée si l'une d'elles est utilisée ; sinon la désactivation descend dessus (cascade).
     *
     * @return string[] motifs libellés, prêts à mettre dans le refus affiché
     */
    public function motifsDesactivation(): array
    {
        if (!$this->exists) {
            return [];
        }

        $motifs = [];
        $fr = fn($v): string => number_format((float) $v, 0, ',', ' ') . ' FCFA';

        foreach (static::withoutGlobalScope('exercice')->where('parent_id', $this->id)->get() as $sousTache) {
            $motifsSousTache = $sousTache->motifsDesactivation();
            if ($motifsSousTache !== []) {
                $motifs[] = "sa sous-tâche {$sousTache->code} : " . implode(' ; ', $motifsSousTache);
            }
        }

        if ((float) $this->ae != 0 || (float) $this->cp != 0) {
            $motifs[] = 'ses AE/CP chiffrés (AE ' . $fr($this->ae) . ', CP ' . $fr($this->cp) . ')';
        }

        $lignes = $this->lignesBudgetPortees();
        $engage = 0.0;
        $paye = 0.0;
        foreach ($lignes as $lb) {
            $engage += (float) $lb->engage;
            $paye += (float) $lb->paye;
        }
        if ($engage != 0.0) {
            $motifs[] = 'sa ligne de budget est engagée à hauteur de ' . $fr($engage)
                . ($paye != 0.0 ? ' (dont ' . $fr($paye) . ' payé)' : '');
        } elseif ($lignes !== []) {
            $motifs[] = 'sa nomenclature porte ' . count($lignes) . ' ligne(s) de budget de l\'exercice '
                . $this->exercice_id;
        }

        $cdmt = (int) \Illuminate\Support\Facades\DB::table('cdmt_lignes')->where('tache_id', $this->id)->count();
        if ($cdmt > 0) {
            $motifs[] = "$cdmt ligne(s) de CDMT la citent";
        }

        $rapports = (int) \Illuminate\Support\Facades\DB::table('rapport_activite_lignes')
            ->where('tache_id', $this->id)->count();
        if ($rapports > 0) {
            $motifs[] = "$rapports ligne(s) de rapport d'activité la citent";
        }

        return $motifs;
    }

    /** Se désactive sans rien casser ? */
    public function estDesactivable(): bool
    {
        return $this->motifsDesactivation() === [];
    }

    /**
     * Sur quoi la désactivation de cette tâche se propage : ses sous-tâches. Une sous-tâche n'a
     * pas d'enfant (le `creating` du modèle le refuse), la liste est donc vide dans ce cas-là.
     */
    public function tachesADesactiver(): \Illuminate\Support\Collection
    {
        return static::withoutGlobalScope('exercice')->where('parent_id', $this->id)->get();
    }

    /**
     * Vérifier si c'est une tâche principale
     */
    public function estTachePrincipale(): bool
    {
        return $this->niveau === 'tache' && is_null($this->parent_id);
    }

    /**
     * Vérifier si c'est une sous-tâche
     */
    public function estSousTache(): bool
    {
        return $this->niveau === 'sous_tache';
    }

    /**
     * Obtenir le chemin hiérarchique
     */
    public function getCheminHierarchique(): string
    {
        if ($this->estTachePrincipale()) {
            return $this->code . ' - ' . $this->libelle;
        }

        return ($this->parent ? $this->parent->code . ' > ' : '') . $this->code . ' - ' . $this->libelle;
    }

    /**
     * Obtenir le chemin complet du cadre logique
     */
    public function getCheminComplet(): array
    {
        $activite = $this->activite;
        $action = $activite->action;
        $programme = $action->programme;

        return [
            'programme' => $programme->code . ' - ' . $programme->libelle,
            'objectif_principal' => $programme->objectifsPrincipaux->first()?->libelle ?? '',
            'action' => $action->code . ' - ' . $action->libelle,
            'objectif_specifique' => $action->objectifsSpecifiques->first()?->libelle ?? '',
            'activite' => $activite->code . ' - ' . $activite->libelle,
            'tache' => $this->getCheminHierarchique(),
        ];
    }

    /**
     * Obtenir le programme
     */
    public function getProgramme(): Programme
    {
        return $this->activite->action->programme;
    }

    /**
     * Obtenir l'action
     */
    public function getAction(): Action
    {
        return $this->activite->action;
    }

    /**
     * Obtenir la nomenclature (même si c'est une tâche parent)
     * Retourne la nomenclature de la première sous-tâche si c'est une tâche parent
     */
    public function getNomenclatureEffective(): ?NomenclatureBudgetaire
    {
        if ($this->estSousTache() || $this->nomenclature_id) {
            return $this->nomenclature;   // sous-tâche, ou tâche liée directement
        }

        // Si c'est une tâche parent, prendre la nomenclature de la première sous-tâche
        return $this->sousTaches->first()?->nomenclature;
    }

    /**
     * Boot - Calculer automatiquement AE/CP des tâches parentes
     */
    protected static function boot()
    {
        parent::boot();

        // ✅ Code généré automatiquement : T<n> pour une tâche (par exercice), <code parent>-<nn> pour une sous-tâche
        static::creating(function ($tache) {
            if (blank($tache->code)) {
                $tache->code = static::genererCode($tache);
            }

            // Une tâche liée DIRECTEMENT à une nomenclature ne reçoit pas de sous-tâche (double compte)
            if ($tache->niveau === 'sous_tache' && $tache->parent_id) {
                $parent = static::withoutGlobalScope('exercice')->find($tache->parent_id);
                if ($parent && $parent->nomenclature_id && !$parent->sousTaches()->exists()) {
                    throw new \DomainException(
                        "La tâche {$parent->code} est liée directement à une nomenclature budgétaire : elle ne peut pas recevoir de sous-tâche. "
                            . "Retirez d'abord la nomenclature (et ses AE/CP) de la tâche, puis créez ses sous-tâches."
                    );
                }
            }
        });

        // Une tâche qui a des sous-tâches ne porte pas de nomenclature propre
        static::saving(function ($tache) {
            if (
                $tache->exists && $tache->niveau === 'tache' && $tache->isDirty('nomenclature_id')
                && $tache->nomenclature_id && $tache->sousTaches()->exists()
            ) {
                throw new \DomainException(
                    "La tâche {$tache->code} a des sous-tâches : la nomenclature se renseigne sur chaque sous-tâche, pas sur la tâche."
                );
            }
        });

        // ✅ AJOUT — Concordance programmation / budget : une fois le budget de l'exercice ADOPTÉ,
        //    les AE/CP d'une sous-tâche ne se modifient plus à la main (paramètre d'exécution) :
        //    on passe par un collectif ou un virement, et la synchronisation ajuste les sous-tâches.
        static::saving(function ($tache) {
            if (!$tache->exists || !$tache->estPorteuseBudget() || !$tache->isDirty(['ae', 'cp'])) {
                return;
            }
            if (\App\Services\Budget\SynchronisationTachesService::$enCours) {
                return;
            }
            if (!\App\Services\ParametresExecution::get('verrouiller_taches_budget_adopte')) {
                return;
            }

            $budgetAdopte = \App\Models\Budget::withoutGlobalScope('exercice')
                ->where('exercice_id', $tache->exercice_id)
                ->where('statut', 'adopte')
                ->exists();

            if ($budgetAdopte) {
                throw new \DomainException(
                    "Le budget de l'exercice est adopté : les AE/CP de la tâche {$tache->code} ne se modifient plus directement. "
                        . "Passez par un collectif budgétaire ou un mouvement de crédits : la sous-tâche sera ajustée automatiquement."
                );
            }
        });

        // Recalculer après sauvegarde d'une sous-tâche
        static::saved(function ($tache) {
            if ($tache->estSousTache() && $tache->parent) {
                $tache->parent->recalculerBudget();
            }
        });

        // Recalculer après suppression d'une sous-tâche
        static::deleted(function ($tache) {
            if ($tache->estSousTache() && $tache->parent) {
                $tache->parent->recalculerBudget();
            }
        });
    }

    /**
     * ✅ Code automatique :
     *  - tâche principale : « T » + numéro suivant parmi les tâches de l'exercice (T1, T2…) ;
     *  - sous-tâche : code de la tâche parent + « - » + numéro sur deux chiffres (T2-01, T2-02…).
     */
    public static function genererCode(self $tache): string
    {
        $base = static::withoutGlobalScope('exercice')->withTrashed()->where('exercice_id', $tache->exercice_id);

        if ($tache->niveau === 'sous_tache' && $tache->parent_id) {
            $parent = static::withoutGlobalScope('exercice')->withTrashed()->find($tache->parent_id);
            $prefixe = ($parent?->code ?: 'T') . '-';
            $max = (clone $base)->where('parent_id', $tache->parent_id)->pluck('code')
                ->map(fn($c) => str_starts_with((string) $c, $prefixe) ? (int) substr($c, strlen($prefixe)) : 0)
                ->max() ?? 0;

            return $prefixe . str_pad($max + 1, 2, '0', STR_PAD_LEFT);
        }

        $max = (clone $base)->where('niveau', 'tache')->pluck('code')
            ->map(fn($c) => preg_match('/^T(\d+)$/', (string) $c, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return 'T' . ($max + 1);
    }

    /**
     * Recalculer les montants AE et CP (pour tâches principales)
     */
    public function recalculerBudget(): void
    {
        if ($this->estTachePrincipale() && $this->sousTaches()->exists()) {
            $this->ae = $this->sousTaches()->sum('ae');
            $this->cp = $this->sousTaches()->sum('cp');

            // Sauvegarder sans déclencher les événements (éviter boucle infinie)
            $this->saveQuietly();
        }
    }

    /**
     * Obtenir le total AE (calculé ou saisi)
     */
    public function getTotalAe(): float
    {
        if ($this->estTachePrincipale()) {
            // La collection est déjà chargée dans les listes et les états :
            // sommer en mémoire évite une requête par tâche.
            if ($this->relationLoaded('sousTaches')) {
                if ($this->sousTaches->isNotEmpty()) {
                    return (float) $this->sousTaches->sum('ae');
                }
            } elseif ($this->sousTaches()->exists()) {
                return $this->sousTaches()->sum('ae');
            }
        }

        return (float) $this->ae;
    }

    /**
     * Obtenir le total CP (calculé ou saisi)
     */
    public function getTotalCp(): float
    {
        if ($this->estTachePrincipale()) {
            if ($this->relationLoaded('sousTaches')) {
                if ($this->sousTaches->isNotEmpty()) {
                    return (float) $this->sousTaches->sum('cp');
                }
            } elseif ($this->sousTaches()->exists()) {
                return $this->sousTaches()->sum('cp');
            }
        }

        return (float) $this->cp;
    }

    /**
     * Accessor : AE affiché
     */
    public function getAeFormatteAttribute(): string
    {
        return number_format($this->getTotalAe(), 0, ',', ' ') . ' FCFA';
    }

    /**
     * Accessor : CP affiché
     */
    public function getCpFormatteAttribute(): string
    {
        return number_format($this->getTotalCp(), 0, ',', ' ') . ' FCFA';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['numero', 'budget_id', 'exercice_id', 'statut', 'date_bordereau', 'montant_total'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Bordereau {$eventName}");
    }
}
