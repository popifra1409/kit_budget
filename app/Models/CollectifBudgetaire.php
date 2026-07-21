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
    public function appliquer(): void
    {
        if ($this->statut !== 'adopte') {
            throw new \Exception('Seul un collectif adopté peut être appliqué.');
        }

        // Appliquer les mouvements sur les lignes
        foreach ($this->mouvements as $mouvement) {
            $mouvement->appliquer();
        }

        // Attacher les budgets impactés
        $budgetIds = $this->mouvements
            ->filter(fn($m) => $m->type === 'depense')
            ->map(function ($m) {
                if ($m->ligne_depense_id) {
                    return $m->ligneDepense->budget_id;
                } elseif ($m->nouvelle_ligne_depense_id) {
                    return $m->nouvelleLigneDepense->budget_id;
                }
                return null;
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

        // Recalcul des totaux
        $this->exercice->budgets->each->recalculerTotaux();
        $this->exercice->previsionRecettes->each->recalculerTotaux();
    }


    /**
     * Annuler le collectif : inverse les modifications.
     */
    public function annuler(): void
    {
        if ($this->statut !== 'adopte') {
            return;
        }

        DB::transaction(function () {
            foreach ($this->mouvements as $mouvement) {
                $mouvement->annuler();
            }
            $this->statut = 'annule';
            $this->save();
            // Recalculer les totaux
            $this->exercice->budgets->each->recalculerTotaux();
            $this->exercice->previsionRecettes->each->recalculerTotaux();
        });
    }

    /**
     * Vérifier si le collectif est modifiable (pas encore adopté).
     */
    public function estModifiable(): bool
    {
        return $this->statut === 'projet';
    }
}
