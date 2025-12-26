<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BonCommande extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bons_commande';

    protected $fillable = [
        'numero',
        'budget_id',
        'fournisseur_id',
        'service_demandeur_id',
        'date_emission',
        'date_livraison_prevue',
        'date_livraison_effective',
        'objet',
        'observations',
        'montant_ht',
        'montant_tva',
        'montant_ir',
        'taux_ir',
        'montant_ttc',
        'statut',
        'valide_par',
        'date_validation',
        'engage',
        'montant_engage',
        'date_engagement',
    ];

    protected $casts = [
        'date_emission' => 'date',
        'date_livraison_prevue' => 'date',
        'date_livraison_effective' => 'date',
        'date_validation' => 'datetime',
        'date_engagement' => 'datetime',
        'montant_ht' => 'decimal:2',
        'montant_tva' => 'decimal:2',
        'montant_ir' => 'decimal:2',
        'taux_ir' => 'decimal:2',
        'montant_ttc' => 'decimal:2',
        'montant_engage' => 'decimal:2',
        'engage' => 'boolean',
    ];

    /**
     * Boot - Générer le numéro automatiquement
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bc) {
            if (empty($bc->numero)) {
                $bc->numero = $bc->genererNumero();
            }
        });

        // Calculer les montants automatiquement
        static::saving(function ($bc) {
            $bc->calculerMontants();
        });
    }

    /**
     * Relation : Budget
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * Relation : Fournisseur
     */
    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    /**
     * Relation : Service demandeur
     */
    public function serviceDemandeur(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_demandeur_id');
    }

    /**
     * Relation : Validateur
     */
    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    /**
     * Relation : Lignes du bon de commande
     */
    public function lignes(): HasMany
    {
        return $this->hasMany(LigneBonCommande::class, 'bon_commande_id');
    }

    /**
     * Relation : Engagement (polymorphique)
     */
    public function engagement(): MorphOne
    {
        return $this->morphOne(Engagement::class, 'engageable');
    }

    /**
     * Scope : Par statut
     */
    public function scopeStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Scope : Engagés
     */
    public function scopeEngages($query)
    {
        return $query->where('engage', true);
    }

    /**
     * Générer le numéro de BC
     */
    public function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = self::where('numero', 'like', "BC-{$annee}-%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($dernier) {
            $dernierNumero = intval(substr($dernier->numero, -4));
            $nouveauNumero = $dernierNumero + 1;
        } else {
            $nouveauNumero = 1;
        }

        return sprintf('BC-%d-%04d', $annee, $nouveauNumero);
    }

    /**
     * Calculer les montants à partir des lignes
     */
    public function calculerMontants(): void
    {
        if ($this->exists) {
            $this->montant_ht = $this->lignes()->sum('montant_ht');
            $this->montant_tva = $this->lignes()->sum('montant_tva');
            $this->montant_ir = $this->lignes()->sum('montant_ir');
            $this->montant_ttc = $this->lignes()->sum('montant_ttc');

            // Calculer le taux IR moyen si applicable
            if ($this->montant_ht > 0) {
                $this->taux_ir = ($this->montant_ir / $this->montant_ht) * 100;
            }
        }
    }

    /**
     * Valider le BC
     */
    public function valider(User $user): void
    {
        $this->statut = 'valide';
        $this->valide_par = $user->id;
        $this->date_validation = now();
        $this->save();
    }

    /**
     * Engager le budget
     */
    public function engagerBudget(): void
    {
        if ($this->statut !== 'valide') {
            throw new \Exception("Le BC doit être validé avant d'engager le budget");
        }

        if ($this->engage) {
            throw new \Exception("Le budget est déjà engagé pour ce BC");
        }

        // Vérifier qu'il y a des lignes
        if ($this->lignes()->count() === 0) {
            throw new \Exception("Le BC doit avoir au moins une ligne");
        }

        \DB::beginTransaction();
        try {
            // Net à payer = TTC - IR
            $netAPayer = $this->montant_ttc - $this->montant_ir;

            // Récupérer la première nomenclature (principale)
            $premiereLigne = $this->lignes()->first();
            $nomenclaturePrincipaleId = $premiereLigne ? $premiereLigne->nomenclature_id : null;

            if (!$nomenclaturePrincipaleId) {
                throw new \Exception("Impossible de déterminer la nomenclature principale");
            }

            // Créer l'engagement
            $engagement = Engagement::create([
                'budget_id' => $this->budget_id,
                'type_engagement' => 'BC',
                'nomenclature_principale_id' => $nomenclaturePrincipaleId,
                'reference_document' => $this->numero,
                'engageable_type' => self::class,
                'engageable_id' => $this->id,
                'beneficiaire_type' => Fournisseur::class,
                'beneficiaire_id' => $this->fournisseur_id,
                'date_engagement' => now(),
                'exercice' => now()->year,
                'objet' => $this->objet,
                'montant_engage' => $netAPayer,
                'statut' => 'provisoire',
            ]);

            // Engager chaque ligne budgétaire et créer les lignes d'engagement
            $lignesParNomenclature = [];
            foreach ($this->lignes as $ligne) {
                $nomenclatureId = $ligne->nomenclature_id;

                // Regrouper par nomenclature
                if (!isset($lignesParNomenclature[$nomenclatureId])) {
                    $lignesParNomenclature[$nomenclatureId] = [
                        'montant' => 0,
                        'libelles' => []
                    ];
                }

                $lignesParNomenclature[$nomenclatureId]['montant'] += $ligne->net_a_payer;
                $lignesParNomenclature[$nomenclatureId]['libelles'][] = $ligne->designation;
            }

            // Créer les lignes d'engagement et engager le budget
            $numeroLigne = 1;
            foreach ($lignesParNomenclature as $nomenclatureId => $data) {
                $ligneBudgetaire = LigneBudgetaire::where('budget_id', $this->budget_id)
                    ->where('nomenclature_id', $nomenclatureId)
                    ->firstOrFail();

                // Vérifier le crédit disponible
                if (!$ligneBudgetaire->peutEngager($data['montant'])) {
                    throw new \Exception(
                        "Crédit insuffisant sur la ligne {$ligneBudgetaire->nomenclature->code}. " .
                            "Disponible: " . number_format($ligneBudgetaire->disponible_engagement, 0, ',', ' ') . " FCFA"
                    );
                }

                // Créer la ligne d'engagement
                LigneEngagement::create([
                    'engagement_id' => $engagement->id,
                    'nomenclature_id' => $nomenclatureId,
                    'numero_ligne' => $numeroLigne++,
                    'libelle' => implode(', ', $data['libelles']),
                    'montant' => $data['montant'],
                ]);

                // Engager
                $ligneBudgetaire->enregistrerEngagement($data['montant']);
            }

            // Marquer le BC comme engagé
            $this->engage = true;
            $this->montant_engage = $netAPayer;
            $this->date_engagement = now();
            $this->statut = 'engage';
            $this->save();

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /**
     * Désengager le budget (en cas d'annulation)
     */
    public function desengagerBudget(): void
    {
        if (!$this->engage) {
            return; // Déjà désengagé
        }

        \DB::beginTransaction();
        try {
            // Récupérer l'engagement et l'annuler
            $engagement = $this->engagement;

            if ($engagement) {
                $engagement->annuler();
            }

            // Marquer le BC comme désengagé
            $this->engage = false;
            $this->montant_engage = 0;
            $this->save();

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /**
     * Annuler le BC
     */
    public function annuler(): void
    {
        // Désengager le budget si engagé
        if ($this->engage) {
            $this->desengagerBudget();
        }

        $this->statut = 'annule';
        $this->save();
    }

    /**
     * Vérifier si le BC est modifiable
     */
    public function estModifiable(): bool
    {
        return in_array($this->statut, ['brouillon', 'valide']);
    }

    /**
     * Obtenir le taux de livraison
     */
    public function getTauxLivraison(): float
    {
        $totalQuantite = $this->lignes()->sum('quantite');
        if ($totalQuantite == 0) {
            return 0;
        }

        $totalLivree = $this->lignes()->sum('quantite_livree');
        return ($totalLivree / $totalQuantite) * 100;
    }
}
