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
use App\Exceptions\CreditBudgetaireInsuffisantException;

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
        'taux_cnps',
        'taux_irnc',
        'montant_cnps',
        'montant_irnc',
        'autres_retenues',
        'total_taxes',
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
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_decision' => 'date',
        'date_effet' => 'date',
        'date_fin' => 'date',
        'date_validation' => 'datetime',
        'date_engagement' => 'datetime',
        'montant_brut' => 'decimal:2',
        'taux_cnps' => 'decimal:2',
        'taux_irnc' => 'decimal:2',
        'montant_cnps' => 'decimal:2',
        'montant_irnc' => 'decimal:2',
        'autres_retenues' => 'decimal:2',
        'total_taxes' => 'decimal:2',
        'montant_net' => 'decimal:2',
        'engagee' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($decision) {
            if (!$decision->numero) {
                $decision->numero = $decision->genererNumero();
            }

            // Assigner automatiquement le créateur
            if (!$decision->created_by) {
                $decision->created_by = auth()->id();
            }
        });

        static::updating(function ($decision) {
            // Assigner automatiquement le modificateur
            $decision->updated_by = auth()->id();

            // Vérifier les permissions
            if (
                $decision->isDirty() &&
                $decision->getOriginal('statut') !== 'brouillon' &&
                !auth()->user()?->hasRole('super_admin')
            ) {
                throw new \Exception('Modification interdite : décision non brouillon.');
            }

            // Bloquer si en cours de transmission
            if ($decision->estEnCoursDeTransmission() && !auth()->user()?->can('force_update_decision_administrative')) {
                throw new \Exception('Modification interdite : décision en cours de transmission.');
            }
        });

        static::deleting(function ($decision) {
            if (!auth()->user()?->hasRole('super_admin')) {
                throw new \Exception('Suppression interdite : réservé au super administrateur.');
            }

            if ($decision->engage) {
                throw new \Exception('Suppression interdite : décision déjà engagée. Annulez-la d\'abord.');
            }
        });
    }

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
     * Calculer les montants (CNPS, IRNC, autres retenues, net)
     */
    public function calculerMontants(): void
    {
        $brut = $this->montant_brut ?? 0;
        $tauxCnps = $this->taux_cnps ?? 4.2;
        $tauxIrnc = $this->taux_irnc ?? 11;
        $autresRetenues = $this->autres_retenues ?? 0;

        $this->montant_cnps = $brut * ($tauxCnps / 100);
        $this->montant_irnc = $brut * ($tauxIrnc / 100);
        $this->total_taxes = $this->montant_cnps + $this->montant_irnc + $autresRetenues;
        $this->montant_net = $brut - $this->total_taxes;
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

                throw new CreditBudgetaireInsuffisantException(
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
        if ($this->personnel && !empty($this->personnel->nom_complet)) {
            return $this->personnel->nom_complet;
        }

        if (!empty($this->personnel_id_ancien)) {
            $user = User::find($this->personnel_id_ancien);
            if ($user && !empty($user->name)) {
                return $user->name;
            }
        }

        return ''; // valeur par défaut obligatoire
    }


    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['numero', 'budget_id', 'exercice_id', 'statut', 'date_bordereau', 'montant_total'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Bordereau {$eventName}");
    }

    /**
     * Relation polymorphique : Transmissions
     */
    public function transmissions()
    {
        return $this->morphMany(Transmission::class, 'document');
    }

    /**
     * Vérifier si la décision est en cours de transmission
     */
    public function estEnCoursDeTransmission(): bool
    {
        return $this->transmissions()
            ->where('statut', 'en_attente')
            ->exists();
    }

    /**
     * Vérifier si l'utilisateur actuel est le destinataire
     */
    public function estDestinataireActuel(): bool
    {
        $transmissionEnCours = $this->transmissions()
            ->where('statut', 'en_attente')
            ->latest()
            ->first();

        return $transmissionEnCours
            && $transmissionEnCours->destinataire_id === auth()->id();
    }

    /**
     * Vérifier si peut être vu par l'utilisateur
     */
    public function peutEtreVuPar(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();

        // Super admin peut tout voir
        if (auth()->user()?->hasRole('super_admin')) {
            return true;
        }

        // Si pas de transmission en cours, tout le monde peut voir
        if (!$this->estEnCoursDeTransmission()) {
            return true;
        }

        // Si en cours de transmission, seul le destinataire actuel peut voir
        return $this->estDestinataireActuel();
    }

    /**
     * Méthodes de transmission (trait Transmissible)
     */
    public function transmettreA(
        User $destinataire,
        string $actionAttendue,
        ?string $commentaire = null,
        array $metadata = []
    ): Transmission {
        if ($this->estEnCoursDeTransmission()) {
            throw new \Exception('Cette décision est déjà en cours de transmission.');
        }

        $transmission = new Transmission([
            'document_type' => static::class,
            'document_id' => $this->id,
            'expediteur_id' => auth()->id(),
            'destinataire_id' => $destinataire->id,
            'action_attendue' => $actionAttendue,
            'commentaire' => $commentaire,
            'statut' => 'en_attente',
            'priorite' => $metadata['priorite'] ?? 'normale',
            'date_limite' => $metadata['date_limite'] ?? null,
            'date_transmission' => now(), // ✅ OBLIGATOIRE
            'metadata' => $metadata,
        ]);

        $transmission->save();

        activity()
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties(['destinataire' => $destinataire->name])
            ->log('Décision transmise');

        return $transmission;
    }

    public function retournerPourCorrection(string $motif): void
    {
        $transmission = $this->transmissions()
            ->where('statut', 'en_attente')
            ->latest()
            ->first();

        if (!$transmission || $transmission->destinataire_id !== auth()->id()) {
            throw new \Exception('Vous n\'êtes pas le destinataire de cette transmission.');
        }

        $transmission->statut = 'retourne';
        $transmission->date_traitement = now();
        $transmission->reponse = $motif;
        $transmission->save();

        activity()
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties(['motif' => $motif])
            ->log('Décision retournée pour correction');
    }

    public function cloturerTransmission(?string $reponse = null): void
    {
        $transmission = $this->transmissions()
            ->where('statut', 'en_attente')
            ->latest()
            ->first();

        if (!$transmission || $transmission->destinataire_id !== auth()->id()) {
            throw new \Exception('Vous n\'êtes pas le destinataire de cette transmission.');
        }

        $transmission->statut = 'traite';
        $transmission->date_traitement = now();
        $transmission->reponse = $reponse;
        $transmission->save();

        activity()
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->log('Transmission clôturée');
    }

    public function peutEtreTransmis(): bool
    {
        // Ne peut pas transmettre si déjà en cours de transmission
        if ($this->estEnCoursDeTransmission()) {
            return false;
        }

        // Peut transmettre si brouillon ou validé
        return in_array($this->statut, ['brouillon', 'valide']);
    }

    public function transmissionEnCours(): ?Transmission
    {
        return $this->transmissions()
            ->where('statut', 'en_attente')
            ->latest()
            ->first();
    }

    public function aEteTransmis(): bool
    {
        return $this->transmissions()->exists();
    }

    public function historiqueTransmissions()
    {
        return $this->transmissions()
            ->with(['expediteur', 'destinataire'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
