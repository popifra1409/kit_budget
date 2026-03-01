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
use Illuminate\Support\Facades\DB;

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
        'type_decision_id',
        'date_decision',
        'date_effet',
        'date_fin',
        'objet',
        'montant_brut',

        // Taxes CNPS et IRNC
        'taux_cnps',
        'taux_irnc',
        'montant_cnps',
        'montant_irnc',

        // Nouvelles taxes
        'type_tva',
        'taux_tva',
        'montant_tva',
        'type_redevance_audiovisuelle',
        'taux_redevance_audiovisuelle',
        'montant_redevance_audiovisuelle',
        'type_feicom',
        'taux_feicom',
        'montant_feicom',

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
        'service_emetteur_id',
    ];

    protected $casts = [
        'date_decision' => 'date',
        'date_effet' => 'date',
        'date_fin' => 'date',
        'date_validation' => 'datetime',
        'date_engagement' => 'datetime',

        // Montants de base
        'montant_brut' => 'decimal:2',
        'montant_cnps' => 'decimal:2',
        'montant_irnc' => 'decimal:2',
        'autres_retenues' => 'decimal:2',
        'total_taxes' => 'decimal:2',
        'montant_net' => 'decimal:2',
        'montant_engage' => 'decimal:2',

        // Taux CNPS et IRNC
        'taux_cnps' => 'decimal:2',
        'taux_irnc' => 'decimal:2',

        // TVA
        'type_tva' => 'string',
        'taux_tva' => 'decimal:2',
        'montant_tva' => 'decimal:2',

        // Redevance audiovisuelle
        'type_redevance_audiovisuelle' => 'string',
        'taux_redevance_audiovisuelle' => 'decimal:2',
        'montant_redevance_audiovisuelle' => 'decimal:2',

        // FEICOM
        'type_feicom' => 'string',
        'taux_feicom' => 'decimal:2',
        'montant_feicom' => 'decimal:2',

        'engagee' => 'boolean',
    ];

    // ========================================
    // BOOT ET ÉVÉNEMENTS
    // ========================================

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
            $decision->updated_by = auth()->id();

            // ✅ Champs autorisés même si non brouillon
            $champsAutorisesSansRestriction = [
                'engagement_id',
                'engage',
                'date_engagement',
                'statut',
                'updated_by',
                'updated_at',
            ];

            // Vérifier si SEULEMENT des champs autorisés ont été modifiés
            $champsDirty = array_keys($decision->getDirty());
            $modificationAutorisee = empty(array_diff($champsDirty, $champsAutorisesSansRestriction));

            // Si seuls les champs autorisés sont modifiés, autoriser
            if ($modificationAutorisee) {
                return;
            }

            // Vérifications normales...
            if (
                $decision->isDirty() &&
                $decision->getOriginal('statut') !== 'brouillon' &&
                !auth()->user()?->hasRole('super_admin')
            ) {
                throw new \Exception('Modification interdite : décision non brouillon.');
            }

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

    // ========================================
    // RELATIONS
    // ========================================

    /**
     * Relation : Budget
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * Relation : Type de décision
     */
    public function typeDecision()
    {
        return $this->belongsTo(TypeDecision::class, 'type_decision_id');
    }

    /**
     * Relation : Personnel
     */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    /**
     * Relation : Service émetteur
     */
    public function serviceEmetteur(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_emetteur_id');
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
     * Relation polymorphique : Transmissions
     */
    public function transmissions()
    {
        return $this->morphMany(Transmission::class, 'document');
    }

    // ========================================
    // SCOPES
    // ========================================

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

    // ========================================
    // ACCESSEURS (GETTERS) POUR LES MONTANTS
    // ========================================

    /**
     * ✅ Calculer le montant CNPS
     */
    public function getMontantCnpsCalculeAttribute(): float
    {
        $brut = (float) ($this->montant_brut ?? 0);
        $taux = (float) ($this->taux_cnps ?? 0);

        return $brut * ($taux / 100);
    }

    /**
     * ✅ Calculer le montant IRNC
     */
    public function getMontantIrncCalculeAttribute(): float
    {
        $brut = (float) ($this->montant_brut ?? 0);
        $taux = (float) ($this->taux_irnc ?? 0);

        return $brut * ($taux / 100);
    }

    /**
     * ✅ Calculer le montant de la TVA selon le type
     */
    public function getMontantTvaCalculeAttribute(): float
    {
        if ($this->type_tva === 'forfait') {
            return (float) ($this->attributes['montant_tva'] ?? 0);
        }

        // Type = taux
        $brut = (float) ($this->montant_brut ?? 0);
        $taux = (float) ($this->taux_tva ?? 0);

        return $brut * ($taux / 100);
    }

    /**
     * ✅ Calculer le montant de la Redevance audiovisuelle selon le type
     */
    public function getMontantRedevanceAudiovisuelleCalculeAttribute(): float
    {
        if ($this->type_redevance_audiovisuelle === 'forfait') {
            return (float) ($this->attributes['montant_redevance_audiovisuelle'] ?? 0);
        }

        // Type = taux
        $brut = (float) ($this->montant_brut ?? 0);
        $taux = (float) ($this->taux_redevance_audiovisuelle ?? 0);

        return $brut * ($taux / 100);
    }

    /**
     * ✅ Calculer le montant du FEICOM selon le type
     */
    public function getMontantFeicomCalculeAttribute(): float
    {
        if ($this->type_feicom === 'forfait') {
            return (float) ($this->attributes['montant_feicom'] ?? 0);
        }

        // Type = taux
        $brut = (float) ($this->montant_brut ?? 0);
        $taux = (float) ($this->taux_feicom ?? 0);

        return $brut * ($taux / 100);
    }

    /**
     * ✅ Calculer le total de toutes les retenues et taxes
     */
    public function getTotalRetenuesCalculeAttribute(): float
    {
        return $this->montant_cnps_calcule +
            $this->montant_irnc_calcule +
            $this->montant_tva_calcule +
            $this->montant_redevance_audiovisuelle_calcule +
            $this->montant_feicom_calcule +
            ((float) ($this->autres_retenues ?? 0));
    }

    /**
     * ✅ Calculer le montant net à payer
     */
    public function getMontantNetCalculeAttribute(): float
    {
        $brut = (float) ($this->montant_brut ?? 0);

        return $brut - $this->total_retenues_calcule;
    }

    // ========================================
    // MÉTHODES MÉTIER
    // ========================================

    /**
     * Générer le numéro de décision
     * Format: DA-YYYY-XXXXX
     */
    public static function genererNumero(): string
    {
        $annee = now()->year;
        $anneeCourte = substr($annee, -2);

        $prefixe = "DA{$anneeCourte}-";

        // Trouver le dernier numéro de l'année
        $dernier = static::where('numero', 'like', "{$prefixe}%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($dernier && preg_match('/DA\d{2}-(\d+)/', $dernier->numero, $matches)) {
            $sequence = intval($matches[1]) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('DA%s-%05d', $anneeCourte, $sequence);
    }

    /**
     * ✅ Calculer tous les montants (CNPS, IRNC, TVA, Redevance, FEICOM, net)
     */
    public function calculerMontants(): void
    {
        $brut = (float) ($this->montant_brut ?? 0);

        // CNPS
        $tauxCnps = (float) ($this->taux_cnps ?? 4.2);
        $this->montant_cnps = $brut * ($tauxCnps / 100);

        // IRNC  
        $tauxIrnc = (float) ($this->taux_irnc ?? 11);
        $this->montant_irnc = $brut * ($tauxIrnc / 100);

        // TVA
        if ($this->type_tva === 'taux') {
            $tauxTva = (float) ($this->taux_tva ?? 0);
            $montantTva = $brut * ($tauxTva / 100);
            // On stocke le montant calculé
            $this->attributes['montant_tva'] = $montantTva;
        }
        // Si type = forfait, on garde la valeur saisie manuellement

        // Redevance audiovisuelle
        if ($this->type_redevance_audiovisuelle === 'taux') {
            $tauxRedevance = (float) ($this->taux_redevance_audiovisuelle ?? 0);
            $montantRedevance = $brut * ($tauxRedevance / 100);
            $this->attributes['montant_redevance_audiovisuelle'] = $montantRedevance;
        }

        // FEICOM
        if ($this->type_feicom === 'taux') {
            $tauxFeicom = (float) ($this->taux_feicom ?? 0);
            $montantFeicom = $brut * ($tauxFeicom / 100);
            $this->attributes['montant_feicom'] = $montantFeicom;
        }

        // Autres retenues
        $autresRetenues = (float) ($this->autres_retenues ?? 0);

        // Total taxes
        $this->total_taxes =
            $this->montant_cnps +
            $this->montant_irnc +
            ((float) ($this->attributes['montant_tva'] ?? 0)) +
            ((float) ($this->attributes['montant_redevance_audiovisuelle'] ?? 0)) +
            ((float) ($this->attributes['montant_feicom'] ?? 0)) +
            $autresRetenues;

        // Montant net
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

            if (!$ligneBudgetaire->peutEngager($this->montant_brut)) {
                $nomenclature = $ligneBudgetaire->nomenclature;
                $manque = $this->montant_brut - $ligneBudgetaire->disponible_engagement;

                throw new CreditBudgetaireInsuffisantException(
                    "❌ CRÉDIT INSUFFISANT\n\n" .
                        "Ligne budgétaire: {$nomenclature->code} - {$nomenclature->libelle}\n\n" .
                        "📊 DÉTAILS:\n" .
                        "• Provision totale: " . number_format($ligneBudgetaire->montant_vote, 0, ',', ' ') . " FCFA\n" .
                        "• Déjà engagé: " . number_format($ligneBudgetaire->engage, 0, ',', ' ') . " FCFA\n" .
                        "• Disponible: " . number_format($ligneBudgetaire->disponible_engagement, 0, ',', ' ') . " FCFA\n\n" .
                        "💰 ENGAGEMENT DEMANDÉ:\n" .
                        "• Type: Décision Administrative\n" .
                        "• Montant brut: " . number_format($this->montant_brut, 0, ',', ' ') . " FCFA\n" .
                        "• Montant net à engager: " . number_format($this->montant_brut, 0, ',', ' ') . " FCFA\n" .
                        "• Manque: " . number_format($manque, 0, ',', ' ') . " FCFA\n\n" .
                        "✅ SOLUTIONS:\n" .
                        "1. Réduire le montant de la décision\n" .
                        "2. Demander un virement budgétaire vers cette ligne\n" .
                        "3. Utiliser une autre nomenclature budgétaire"
                );
            }

            // ✅ Générer le numéro d'abord
            $numeroEngagement = Engagement::genererNumero();

            \Log::info("Création engagement", [
                'da_numero' => $this->numero,
                'numero_engagement' => $numeroEngagement,
                // 'montant' => $this->montant_net,
                'montant' => $this->montant_brut,
            ]);

            // Créer l'engagement
            $engagement = Engagement::create([
                'numero' => $numeroEngagement,
                'exercice_id' => $this->exercice_id,
                'budget_id' => $this->budget_id,
                'type_engagement' => 'decision_administrative',
                'nomenclature_principale_id' => $nomenclatureId,
                'reference_document' => $this->numero,
                'engageable_type' => 'decision_administrative',
                'engageable_id' => $this->id,
                'beneficiaire_type' => 'App\Models\Personnel',
                'beneficiaire_id' => $this->personnel_id,
                'date_engagement' => now(),
                'exercice' => $this->exercice?->annee ?? now()->year,
                'objet' => $this->objet,
                // 'montant_engage' => $this->montant_net,
                'montant_engage' => $this->montant_brut,
                'statut' => 'provisoire',
                'created_by' => auth()->id(),
            ]);

            // ✅ Vérifier que l'engagement a bien été créé
            if (!$engagement || !$engagement->id) {
                throw new \Exception("Erreur lors de la création de l'engagement");
            }

            // ✅ Rafraîchir pour avoir toutes les données
            $engagement->refresh();

            \Log::info("Engagement créé avec succès", [
                'id' => $engagement->id,
                'numero' => $engagement->numero,
                'montant' => $engagement->montant_brut,
            ]);

            // Créer la ligne d'engagement
            $ligneEngagement = LigneEngagement::create([
                'engagement_id' => $engagement->id,
                'nomenclature_id' => $nomenclatureId,
                'numero_ligne' => 1,
                'libelle' => $this->objet,
                'montant' => $this->montant_brut,
            ]);

            if (!$ligneEngagement || !$ligneEngagement->id) {
                throw new \Exception("Erreur lors de la création de la ligne d'engagement");
            }

            // Engager la ligne budgétaire
            $ligneBudgetaire->enregistrerEngagement($this->montant_brut);

            // Marquer la décision comme engagée
            $this->engagee = true;
            $this->montant_engage = $this->montant_brut;
            $this->date_engagement = now();
            $this->statut = 'engagee';
            $this->save();

            \DB::commit();

            \Log::info("Engagement créé depuis DA", [
                'da_numero' => $this->numero,
                'engagement_numero' => $engagement->numero,
                'montant' => $this->montant_brut,
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();

            \Log::error("Erreur lors de l'engagement", [
                'da_id' => $this->id,
                'da_numero' => $this->numero,
                'erreur' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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

    // ========================================
    // MÉTHODES DE TRANSMISSION
    // ========================================

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
     * Transmettre la décision à un destinataire
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
            'date_transmission' => now(),
        ]);

        $transmission->save();

        activity()
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->withProperties(['destinataire' => $destinataire->name])
            ->log('Décision transmise');

        return $transmission;
    }

    /**
     * Retourner pour correction
     */
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

    /**
     * Clôturer la transmission
     */
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

    /**
     * Vérifier si peut être transmis
     */
    public function peutEtreTransmis(): bool
    {
        // Ne peut pas transmettre si déjà en cours de transmission
        if ($this->estEnCoursDeTransmission()) {
            return false;
        }

        // Peut transmettre si brouillon ou validé
        return in_array($this->statut, ['brouillon', 'valide']);
    }

    /**
     * Obtenir la transmission en cours
     */
    public function transmissionEnCours(): ?Transmission
    {
        return $this->transmissions()
            ->where('statut', 'en_attente')
            ->latest()
            ->first();
    }

    /**
     * Vérifier si a été transmis
     */
    public function aEteTransmis(): bool
    {
        return $this->transmissions()->exists();
    }

    /**
     * Obtenir l'historique des transmissions
     */
    public function historiqueTransmissions()
    {
        return $this->transmissions()
            ->with(['expediteur', 'destinataire'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ========================================
    // ACTIVITY LOG
    // ========================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['numero', 'budget_id', 'exercice_id', 'statut', 'montant_brut', 'montant_net'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Décision administrative {$eventName}");
    }
}
