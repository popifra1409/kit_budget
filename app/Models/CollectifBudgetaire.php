<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CollectifBudgetaire extends Model
{
    use HasFactory;

    protected $table = 'collectifs_budgetaires';

    protected $fillable = [
        'exercice_id',
        'numero',
        'reference',
        'document_path',
        'libelle',
        'date_collectif',
        'date_adoption',
        'statut',
        'observations',
        'created_by',
    ];
    protected $casts = [
        'date_collectif' => 'date',
        'date_adoption' => 'date',
    ];


    // Génération automatique du numéro avant création
    protected static function booted()
    {
        static::creating(function ($collectif) {
            if (empty($collectif->numero)) {
                $collectif->numero = self::generateNumero($collectif->exercice_id);
            }
        });
    }

    public static function generateNumero($exerciceId): string
    {
        $year = (int) (Exercice::find($exerciceId)?->annee ?: date('Y'));
        $prefixe = 'CB-' . $year . '-';
        // Une séquence par exercice : le compteur ne redémarre pas et ne revient jamais
        // en arrière, même si le collectif le plus récent est supprimé (pas de SoftDeletes ici).
        $sequence = 'collectifs_budgetaires_numero_' . $year;

        return DB::transaction(function () use ($prefixe, $sequence) {
            DB::statement("CREATE SEQUENCE IF NOT EXISTS {$sequence}");

            $suite = (int) DB::selectOne("SELECT nextval('{$sequence}') AS suite")->suite;

            // Rattraper les numéros posés avant l'existence de la séquence.
            $dejaPosee = self::where('numero', 'like', $prefixe . '%')
                ->pluck('numero')
                ->map(fn(string $numero) => (int) substr($numero, strlen($prefixe)))
                ->max() ?? 0;

            if ($suite <= $dejaPosee) {
                DB::statement("SELECT setval('{$sequence}', ?)", [$dejaPosee]);
                $suite = (int) DB::selectOne("SELECT nextval('{$sequence}') AS suite")->suite;
            }

            return $prefixe . str_pad($suite, 3, '0', STR_PAD_LEFT);
        });
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementCollectif::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function budgets()
    {
        return $this->belongsToMany(Budget::class, 'budget_collectif');
    }

    public function previsionRecettes()
    {
        return $this->belongsToMany(PrevisionRecette::class, 'prevision_recette_collectif');
    }

    /**
     * Appliquer le collectif : met à jour les lignes de dépenses/recettes.
     */
    public function appliquer(?User $user = null): void
    {
        if ($this->statut !== 'adopte') {
            throw new \Exception('Seul un collectif adopté peut être appliqué.');
        }

        // Appliquer les mouvements sur les lignes
        foreach ($this->mouvements as $mouvement) {
            $mouvement->appliquer($user);
        }

        // Attacher les budgets impactés (dépenses ET virements)
        $budgetIds = $this->mouvements
            ->flatMap(function ($m) {
                if ($m->type === 'depense') {
                    if ($m->ligne_depense_id) {
                        return [$m->ligneDepense?->budget_id];
                    } elseif ($m->nouvelle_ligne_depense_id) {
                        return [$m->nouvelleLigneDepense?->budget_id];
                    }
                } elseif ($m->type === 'virement' && $m->virement_budgetaire_id) {
                    $virement = $m->virementBudgetaire;
                    return [
                        $virement?->ligneSource?->budget_id,
                        $virement?->ligneDestination?->budget_id,
                    ];
                }
                return [];
            })
            ->filter()
            ->unique()
            ->values();

        if ($budgetIds->isNotEmpty()) {
            $this->budgets()->syncWithoutDetaching($budgetIds);
        }

        // Attacher les prévisions impactées
        $previsionIds = $this->mouvements
            ->filter(fn($m) => $m->type === 'recette')
            ->map(function ($m) {
                if ($m->ligne_recette_id) {
                    return $m->ligneRecette->prevision_recette_id;
                } elseif ($m->nouvelle_ligne_recette_id) {
                    return $m->nouvelleLigneRecette->prevision_recette_id;
                }
                return null;
            })
            ->filter()
            ->unique()
            ->values();

        if ($previsionIds->isNotEmpty()) {
            $this->previsionRecettes()->syncWithoutDetaching($previsionIds);
        }

        // Recalcul des totaux — null-safe
        $this->exercice->budgets?->each(fn($b) => method_exists($b, 'recalculerTotaux') ? $b->recalculerTotaux() : null);
        $this->exercice->previsionRecettes?->each(fn($p) => method_exists($p, 'recalculerTotaux') ? $p->recalculerTotaux() : null);
    }


    /**
     * Annuler le collectif : inverse les modifications.
     */
    public function annuler(): void
    {
        if ($this->statut === 'adopte' && !$this->exercice->estCloture()) {
            foreach ($this->mouvements as $mouvement) {
                $mouvement->annuler();
            }
            $this->statut = 'annule';
            $this->save();

            // Recalcul des totaux
            if ($this->exercice) {
                // Recalcul budgets
                $budgets = $this->exercice->budgets;
                if ($budgets) {
                    foreach ($budgets as $budget) {
                        $budget->recalculerTotaux();
                    }
                }
                // Recalcul prévisions recettes
                $previsions = $this->exercice->previsionRecettes;
                if ($previsions) {
                    foreach ($previsions as $prevision) {
                        $prevision->recalculerTotaux();
                    }
                }
            }
        } else {
            throw new \Exception('Impossible d\'annuler ce collectif.');
        }
    }

    /**
     * Vérifier si le collectif est modifiable (pas encore adopté).
     */
    public function estModifiable(): bool
    {
        return $this->statut === 'projet';
    }
}
