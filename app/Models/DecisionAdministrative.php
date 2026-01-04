<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use App\Traits\HasExercice;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class DecisionAdministrative extends Model
{
    use HasFactory, SoftDeletes, HasExercice, LogsActivity;

    protected $table = 'decisions_administratives';

    protected $fillable = [
        'exercice_id',
        'numero',
        'budget_id',
        'personnel_id',
        'nom_personnel',
        'matricule',
        'fonction',
        'type_decision',
        'date_decision',
        'date_effet',
        'date_fin',
        'objet',
        'montant_brut',
        'cnps',
        'ir',
        'autres_retenues',
        'montant_net',
        'reference_decision',
        'signataire',
        'statut',
        'validee_par',
        'date_validation',
        'engagee',
        'montant_engage',
        'date_engagement',
        'observations',
    ];

    protected $casts = [
        'date_decision' => 'date',
        'date_effet' => 'date',
        'date_fin' => 'date',
        'date_validation' => 'datetime',
        'date_engagement' => 'datetime',
        'montant_brut' => 'decimal:2',
        'cnps' => 'decimal:2',
        'ir' => 'decimal:2',
        'autres_retenues' => 'decimal:2',
        'montant_net' => 'decimal:2',
        'montant_engage' => 'decimal:2',
        'engagee' => 'boolean',
    ];

    /**
     * Boot - Générer le numéro et calculer automatiquement
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($decision) {
            if (empty($decision->numero)) {
                $decision->numero = $decision->genererNumero();
            }
        });

        static::saving(function ($decision) {
            $decision->calculerMontants();
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
     * Relation : Personnel
     */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'personnel_id');
    }

    /**
     * Relation : Validée par
     */
    public function validateurUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }

    /**
     * Relation : Engagement (polymorphique)
     */
    public function engagement(): MorphOne
    {
        return $this->morphOne(Engagement::class, 'engageable');
    }

    /**
     * Scope : Par type
     */
    public function scopeType($query, $type)
    {
        return $query->where('type_decision', $type);
    }

    /**
     * Scope : Par statut
     */
    public function scopeStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Scope : Engagées
     */
    public function scopeEngagees($query)
    {
        return $query->where('engagee', true);
    }

    /**
     * Générer le numéro de décision
     * Format: DA-YYYY-XXXXX
     */
    public function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = self::where('numero', 'like', "DA-{$annee}-%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($dernier) {
            $dernierNumero = intval(substr($dernier->numero, -5));
            $nouveauNumero = $dernierNumero + 1;
        } else {
            $nouveauNumero = 1;
        }

        return sprintf('DA-%d-%05d', $annee, $nouveauNumero);
    }

    /**
     * Calculer CNPS, IR et montant net automatiquement
     */
    public function calculerMontants(): void
    {
        // CNPS (4.2% du brut)
        $this->cnps = $this->montant_brut * 0.042;

        // IR selon barème camerounais (simplifié)
        $this->ir = $this->calculerIR($this->montant_brut);

        // Montant net = Brut - CNPS - IR - Autres retenues
        $this->montant_net = $this->montant_brut - $this->cnps - $this->ir - $this->autres_retenues;
    }

    /**
     * Calculer l'IR selon le barème camerounais
     */
    protected function calculerIR(float $montantBrut): float
    {
        // Barème IR Cameroun (simplifié - à adapter selon besoins)
        if ($montantBrut <= 62000) {
            return 0; // Exonéré
        } elseif ($montantBrut <= 130000) {
            return ($montantBrut - 62000) * 0.10; // 10%
        } elseif ($montantBrut <= 200000) {
            return 6800 + ($montantBrut - 130000) * 0.15; // 15%
        } elseif ($montantBrut <= 333000) {
            return 17300 + ($montantBrut - 200000) * 0.25; // 25%
        } else {
            return 50550 + ($montantBrut - 333000) * 0.35; // 35%
        }
    }

    /**
     * Valider la décision
     */
    public function valider(User $user): void
    {
        $this->statut = 'validee';
        $this->validee_par = $user->id;
        $this->date_validation = now();
        $this->save();
    }

    /**
     * Engager le budget
     */
    public function engagerBudget(int $nomenclatureId): void
    {
        if ($this->statut !== 'validee') {
            throw new \Exception("La décision doit être validée avant d'engager le budget");
        }

        if ($this->engagee) {
            throw new \Exception("Le budget est déjà engagé pour cette décision");
        }

        \DB::beginTransaction();
        try {
            // Vérifier le crédit disponible
            $ligneBudgetaire = LigneBudgetaire::where('budget_id', $this->budget_id)
                ->where('nomenclature_id', $nomenclatureId)
                ->firstOrFail();

            if (!$ligneBudgetaire->peutEngager($this->montant_net)) {
                $nomenclature = $ligneBudgetaire->nomenclature;
                $manque = $this->montant_net - $ligneBudgetaire->disponible_engagement;

                throw new \Exception(
                    "❌ CRÉDIT INSUFFISANT\n\n" .
                        "Ligne budgétaire: {$nomenclature->code} - {$nomenclature->libelle}\n\n" .
                        "📊 DÉTAILS:\n" .
                        "• Provision totale: " . number_format($ligneBudgetaire->montant_vote, 0, ',', ' ') . " FCFA\n" .
                        "• Déjà engagé: " . number_format($ligneBudgetaire->engage, 0, ',', ' ') . " FCFA\n" .
                        "• Disponible: " . number_format($ligneBudgetaire->disponible_engagement, 0, ',', ' ') . " FCFA\n\n" .
                        "💰 ENGAGEMENT DEMANDÉ:\n" .
                        "• Type: {$this->type_decision}\n" .
                        "• Montant brut: " . number_format($this->montant_brut, 0, ',', ' ') . " FCFA\n" .
                        "• Montant net à engager: " . number_format($this->montant_net, 0, ',', ' ') . " FCFA\n" .
                        "• Manque: " . number_format($manque, 0, ',', ' ') . " FCFA\n\n" .
                        "✅ SOLUTIONS:\n" .
                        "1. Réduire le montant de la décision\n" .
                        "2. Demander un virement budgétaire vers cette ligne\n" .
                        "3. Utiliser une autre nomenclature budgétaire"
                );
            }

            // Créer l'engagement
            $engagement = Engagement::create([
                'budget_id' => $this->budget_id,
                'type_engagement' => $this->type_decision, // Type: prime, mission, formation, etc.
                'nomenclature_principale_id' => $nomenclatureId,
                'reference_document' => $this->numero,
                'engageable_type' => self::class,
                'engageable_id' => $this->id,
                'beneficiaire_type' => $this->personnel_id ? User::class : null,
                'beneficiaire_id' => $this->personnel_id,
                'date_engagement' => now(),
                'exercice' => now()->year,
                'objet' => $this->objet,
                'montant_engage' => $this->montant_net,
                'statut' => 'provisoire',
            ]);

            // Créer la ligne d'engagement
            LigneEngagement::create([
                'engagement_id' => $engagement->id,
                'nomenclature_id' => $nomenclatureId,
                'numero_ligne' => 1,
                'libelle' => $this->objet,
                'montant' => $this->montant_net,
            ]);

            // Engager la ligne budgétaire
            $ligneBudgetaire->enregistrerEngagement($this->montant_net);

            // Marquer la décision comme engagée
            $this->engagee = true;
            $this->montant_engage = $this->montant_net;
            $this->date_engagement = now();
            $this->statut = 'engagee';
            $this->save();

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /**
     * Désengager le budget
     */
    public function desengagerBudget(): void
    {
        if (!$this->engagee) {
            return;
        }

        \DB::beginTransaction();
        try {
            // Récupérer l'engagement
            $engagement = $this->engagement;

            if ($engagement) {
                // Désengager les lignes budgétaires
                foreach ($engagement->lignes as $ligne) {
                    $ligneBudgetaire = LigneBudgetaire::where('budget_id', $this->budget_id)
                        ->where('nomenclature_id', $ligne->nomenclature_id)
                        ->firstOrFail();

                    $ligneBudgetaire->annulerEngagement($ligne->montant);
                }

                // Annuler l'engagement
                $engagement->annuler();
            }

            // Marquer la décision comme non engagée
            $this->engagee = false;
            $this->montant_engage = 0;
            $this->save();

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /**
     * Annuler la décision
     */
    public function annuler(): void
    {
        if ($this->engagee) {
            $this->desengagerBudget();
        }

        $this->statut = 'annulee';
        $this->save();
    }

    /**
     * Vérifier si la décision est modifiable
     */
    public function estModifiable(): bool
    {
        return in_array($this->statut, ['brouillon', 'validee']);
    }

    /**
     * Obtenir le nom complet du personnel
     */
    public function getNomCompletPersonnel(): string
    {
        if ($this->personnel) {
            return $this->personnel->name;
        }

        return $this->nom_personnel ?? 'N/A';
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
