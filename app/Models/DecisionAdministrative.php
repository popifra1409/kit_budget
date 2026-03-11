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
        'type_beneficiaire',
        'personnel_id',
        'fournisseur_id',
        'nom_personnel',
        'matricule',
        'fonction',
        'type_decision_id',
        'date_decision',
        'date_effet',
        'date_fin',
        'objet',
        'montant_brut',
        'montant_ht',

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
        'montant_ht' => 'decimal:2',
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

    // protected static function boot()
    // {
    //     parent::boot();

    //     static::creating(function ($decision) {
    //         if (empty($decision->numero)) {
    //             $decision->numero = $decision->genererNumero();
    //         }
    //     });

    //     static::saving(function ($decision) {
    //         $decision->calculerMontants();
    //     });
    // }

    protected static function booted(): void
    {
        static::creating(function ($decision) {
            if (!$decision->numero) {
                $decision->numero = $decision->genererNumero();
            }
            if (!$decision->created_by) {
                $decision->created_by = auth()->id();
            }
        });

        static::saving(function ($decision) {
            $decision->calculerMontants();
        });

        static::updating(function ($decision) {
            // Assigner automatiquement le modificateur
            $decision->updated_by = auth()->id();

            // ✅ CORRECTION: Champs autorisés même si la décision n'est pas en brouillon
            // ATTENTION: Le champ s'appelle "engagee" (avec "e") dans DecisionAdministrative
            $champsAutorisesSansRestriction = [
                'engagement_id',
                'engagee',
                'montant_engage',
                'date_engagement',
                'statut',
                'validee_par',
                'date_validation',
                'observations',
                'updated_by',
                'updated_at',
            ];
            // Vérifier si SEULEMENT des champs autorisés ont été modifiés
            $champsDirty = array_keys($decision->getDirty());
            $modificationAutorisee = empty(array_diff($champsDirty, $champsAutorisesSansRestriction));

            // ✅ Si seuls les champs autorisés sont modifiés, autoriser la mise à jour
            if ($modificationAutorisee) {
                return; // Sortir de l'observer sans lever d'exception
            }

            // Vérifier les permissions pour les autres modifications
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

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id');
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
        // $brut = (float) ($this->montant_brut ?? 0);
        $ht = (float) ($this->montant_ht ?? 0);
        $taux = (float) ($this->taux_cnps ?? 0);

        return $ht * ($taux / 100);
    }

    /**
     * ✅ Calculer le montant IRNC
     */
    public function getMontantIrncCalculeAttribute(): float
    {
        // $brut = (float) ($this->montant_brut ?? 0);
        $taux = (float) ($this->taux_irnc ?? 0);
        $ht = (float) ($this->montant_ht ?? 0);

        return $ht * ($taux / 100);
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
        $ht = (float) ($this->montant_ht ?? 0);

        return $brut - $ht;
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
        // $brut = (float) ($this->montant_brut ?? 0);
        $ht = (float) ($this->montant_ht ?? 0);
        $taux = (float) ($this->taux_redevance_audiovisuelle ?? 0);

        return $ht * ($taux / 100);
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
        // $brut = (float) ($this->montant_brut ?? 0);
        $ht = (float) ($this->montant_ht ?? 0);
        $taux = (float) ($this->taux_feicom ?? 0);

        return $ht * ($taux / 100);
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

    protected function genererNumeroEngagement(): string
    {
        if (!$this->numero) {
            $this->numero = static::genererNumero();
            $this->saveQuietly();
        }

        return 'BE-' . $this->numero;
    }
    /**
     * ✅ Calculer tous les montants (CNPS, IRNC, TVA, Redevance, FEICOM, net)
     */
    public function calculerMontants(): void
    {
        $brut = (float) ($this->montant_brut ?? 0);

        if ($brut <= 0) {
            $this->montant_ht = 0;
            $this->montant_cnps = 0;
            $this->montant_irnc = 0;
            $this->total_taxes = 0;
            $this->montant_net = 0;
            return;
        }

        // ========================================
        // ÉTAPE 1 : CALCULER LE MONTANT HT
        // ========================================
        // Le montant brut est le TTC (incluant la TVA)
        // Formule : HT = Brut / (1 + TVA/100)

        $tauxTva = 0;
        if ($this->type_tva === 'taux') {
            $tauxTva = (float) ($this->taux_tva ?? 0);
        }

        // Calculer HT
        if ($tauxTva > 0) {
            $this->montant_ht = $brut / (1 + ($tauxTva / 100));
        } else {
            // Si pas de TVA, HT = Brut
            $this->montant_ht = $brut;
        }

        // Arrondir à 2 décimales
        $montantHT = round($this->montant_ht, 2);
        $this->montant_ht = $montantHT;

        // ========================================
        // ÉTAPE 2 : CALCULER LES TAXES SUR HT
        // ========================================

        // CNPS (calculée sur HT)
        $tauxCnps = (float) ($this->taux_cnps ?? 0);
        $this->montant_cnps = round($montantHT * ($tauxCnps / 100), 2);

        // IRNC (calculée sur HT)
        $tauxIrnc = (float) ($this->taux_irnc ?? 0);
        $this->montant_irnc = round($montantHT * ($tauxIrnc / 100), 2);

        // TVA (montant de la TVA elle-même)
        if ($this->type_tva === 'taux') {
            // TVA = HT × (taux/100)
            $montantTva = round($montantHT * ($tauxTva / 100), 2);
            $this->attributes['montant_tva'] = $montantTva;
        }
        // Si type = forfait, on garde la valeur saisie manuellement

        // Redevance audiovisuelle (calculée sur HT)
        if ($this->type_redevance_audiovisuelle === 'taux') {
            $tauxRedevance = (float) ($this->taux_redevance_audiovisuelle ?? 0);
            $montantRedevance = round($montantHT * ($tauxRedevance / 100), 2);
            $this->attributes['montant_redevance_audiovisuelle'] = $montantRedevance;
        }

        // FEICOM (calculé sur HT)
        if ($this->type_feicom === 'taux') {
            $tauxFeicom = (float) ($this->taux_feicom ?? 0);
            $montantFeicom = round($montantHT * ($tauxFeicom / 100), 2);
            $this->attributes['montant_feicom'] = $montantFeicom;
        }

        // Autres retenues
        $autresRetenues = (float) ($this->autres_retenues ?? 0);

        // ========================================
        // ÉTAPE 3 : CALCULER LE TOTAL DES RETENUES ET LE NET
        // ========================================

        // Total des retenues (SANS la TVA car elle est déjà dans le brut)
        $this->total_taxes =
            $this->montant_cnps +
            $this->montant_irnc +
            ((float) ($this->attributes['montant_redevance_audiovisuelle'] ?? 0)) +
            ((float) ($this->attributes['montant_feicom'] ?? 0)) +
            $autresRetenues;

        // Montant net = HT - Retenues
        // (on ne soustrait PAS la TVA car elle est déjà déduite dans le calcul du HT)
        $this->montant_net = round($montantHT - $this->total_taxes, 2);
    }

    /**
     * Valider la décision
     */
    public function valider(User $user): void
    {
        // ✅ VÉRIFICATION PERMISSION
        if (!auth()->check() || !auth()->user()->can('valider_decision_administrative')) {
            throw new \Exception("Vous n'avez pas la permission de valider cette décision.");
        }

        $this->statut = 'validee';
        $this->validee_par = $user->id;
        $this->date_validation = now();
        $this->save();
    }

    public function peutEtreDesengagee(): bool
    {
        if (!$this->engagee || !$this->engagement_id) {
            return false;
        }

        if ($this->engagement) {
            if ($this->engagement->ordonnancesPaiement()->count() > 0) {
                return false;
            }
        }

        if ($this->statut === 'annulee') {
            return false;
        }

        return true;
    }

    /**
     * Engager le budget
     */
    public function engagerBudget(int $nomenclatureId): void
    {
        if (!auth()->check() || !auth()->user()->can('engager_decision_administrative')) {
            throw new \Exception("Vous n'avez pas la permission d'engager cette décision.");
        }

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
            //$numeroEngagement = Engagement::genererNumero();

            $numeroEngagement = $this->genererNumeroEngagement();

            \Log::info("Création engagement", [
                'da_numero' => $this->numero,
                'numero_engagement' => $numeroEngagement,
                'montant' => $this->montant_brut,
            ]);

            // Créer l'engagement
            $engagement = Engagement::create([
                'numero' => $numeroEngagement,
                'exercice_id' => $this->exercice_id,
                'budget_id' => $this->budget_id,
                'type_engagement' => 'Décision',
                'nomenclature_principale_id' => $nomenclatureId,
                'reference_document' => $this->numero,
                'engageable_type' => get_class($this),
                'engageable_id' => $this->id,
                // 'beneficiaire_type' => 'App\Models\Personnel',
                'beneficiaire_type' => $this->type_beneficiaire === 'fournisseur'
                    ? 'App\Models\Fournisseur'
                    : 'App\Models\Personnel',
                'beneficiaire_id' => $this->type_beneficiaire === 'fournisseur'
                    ? $this->fournisseur_id
                    : $this->personnel_id,
                // 'beneficiaire_id' => $this->personnel_id,
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
        // ✅ VÉRIFICATION PERMISSION
        // On utilise annuler_decision_administrative car désengager fait partie du processus d'annulation
        if (!auth()->check() || !auth()->user()->can('annuler_decision_administrative')) {
            throw new \Exception("Vous n'avez pas la permission de désengager cette décision.");
        }

        if (!$this->engagee) {
            return;
        }

        DB::beginTransaction();
        try {
            $engagement = $this->engagement;

            if ($engagement) {
                // ✅ Désengager chaque ligne budgétaire
                foreach ($engagement->lignes as $ligne) {
                    $ligneBudgetaire = LigneBudgetaire::where('budget_id', $this->budget_id)
                        ->where('nomenclature_id', $ligne->nomenclature_id)
                        ->firstOrFail();

                    // Utilise la méthode propre de LigneBudgetaire
                    $ligneBudgetaire->annulerEngagement($ligne->montant);
                }

                // ✅ SUPPRIMER l'engagement (au lieu de annuler())
                // Évite les engagements orphelins
                $engagement->delete();
            }

            // ✅ Remettre la DA en statut 'validee' (retour en arrière)
            $this->engagee = false;
            $this->montant_engage = 0;
            $this->statut = 'validee'; // ← CORRECTION ICI
            $this->save();

            DB::commit();

            \Log::info("DA désengagée avec succès", [
                'da_numero' => $this->numero,
                'montant' => $this->montant_brut,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error("Erreur désengagement DA", [
                'da_id' => $this->id,
                'erreur' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Annuler la décision
     */
    public function annuler(?string $motif = null): void
    {
        // ✅ VÉRIFICATION PERMISSION
        if (!auth()->check() || !auth()->user()->can('annuler_decision_administrative')) {
            throw new \Exception("Vous n'avez pas la permission d'annuler cette décision.");
        }
        try {
            DB::beginTransaction();

            // Si la DA est engagée, désengager d'abord
            if ($this->engagee && $this->engagement_id) {
                \Log::info("DA {$this->numero} engagée, désengagement automatique avant annulation");

                $this->desengagerBudget();
                $this->refresh();
            }

            // Annuler la DA
            $this->statut = 'annulee';

            if ($motif) {
                $this->observations = ($this->observations ? $this->observations . "\n\n" : '') .
                    "--- ANNULÉE LE " . now()->format('d/m/Y H:i') . " ---\n" .
                    "Motif : " . $motif . "\n" .
                    "Par : " . auth()->user()->name;
            }

            $this->save();

            DB::commit();

            \Log::info("DA {$this->numero} annulée avec succès", [
                'user' => auth()->id(),
                'motif' => $motif,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error("Erreur annulation DA {$this->numero} : " . $e->getMessage());

            throw $e;
        }
    }

    public function peutEtreRecuperee(): bool
    {
        if ($this->statut !== 'annulee') {
            return false;
        }

        if ($this->engagement && $this->engagement->statut !== 'annule') {
            return false;
        }

        return true;
    }

    public function recuperer(?string $motif = null): void
    {
        // ✅ VÉRIFICATION PERMISSION
        if (!auth()->check() || !auth()->user()->can('recuperer_decision_administrative')) {
            throw new \Exception("Vous n'avez pas la permission de récupérer cette décision.");
        }

        if (!$this->peutEtreRecuperee()) {
            throw new \Exception("Seule une DA annulée peut être récupérée.");
        }

        try {
            DB::beginTransaction();

            // Si un engagement existe encore (engagement orphelin)
            if ($this->engagement) {
                \Log::warning("DA {$this->numero} : Engagement {$this->engagement->id} existe encore, nettoyage...");

                if ($this->engagement) {
                    // Vérifier qu'il n'y a pas d'OP
                    if ($this->engagement->ordonnancesPaiement()->count() > 0) {
                        throw new \Exception("Impossible de récupérer : des ordonnances de paiement existent sur l'engagement.");
                    }

                    // ✅ CORRECTION : Libérer les crédits via les lignes d'engagement
                    // Au lieu de libererCreditsEngagement() qui est obsolète
                    foreach ($this->engagement->lignes as $ligne) {
                        $ligneBudgetaire = LigneBudgetaire::where('budget_id', $this->budget_id)
                            ->where('nomenclature_id', $ligne->nomenclature_id)
                            ->first();

                        if ($ligneBudgetaire) {
                            $ligneBudgetaire->annulerEngagement($ligne->montant);
                        }
                    }

                    // Supprimer l'engagement
                    $this->engagement->delete();

                    \Log::info("Engagement nettoyé lors de la récupération de la DA {$this->numero}");
                }
            }

            // Réinitialiser tous les champs
            // $this->engagement_id = null;
            $this->engagee = false;
            $this->date_engagement = null;
            $this->montant_engage = 0;
            $this->validee_par = null;
            $this->date_validation = null;
            $this->statut = 'brouillon';

            $this->observations = ($this->observations ? $this->observations . "\n\n" : '') .
                "--- RÉCUPÉRÉE LE " . now()->format('d/m/Y H:i') . " ---\n" .
                "Motif : " . ($motif ?? 'Document récupéré pour modification') . "\n" .
                "Par : " . auth()->user()->name;

            $this->save();

            DB::commit();

            \Log::info("DA {$this->numero} récupérée et remise en brouillon", [
                'user' => auth()->id(),
                'motif' => $motif,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error("Erreur récupération DA {$this->numero} : " . $e->getMessage());

            throw $e;
        }
    }

    // protected function libererCreditsEngagement(): void
    // {
    //     if (!$this->engagement) {
    //         return;
    //     }

    //     $ligneBudgetaire = \App\Models\LigneBudgetaire::where('budget_id', $this->budget_id)
    //         ->where('nomenclature_id', $this->engagement->nomenclature_principale_id)
    //         ->first();

    //     if ($ligneBudgetaire && $this->montant_net > 0) {
    //         $ligneBudgetaire->engage -= $this->montant_net;

    //         if ($ligneBudgetaire->engage < 0) {
    //             $ligneBudgetaire->engage = 0;
    //         }

    //         $ligneBudgetaire->save();

    //         \Log::info("Crédit libéré lors du désengagement", [
    //             'da' => $this->numero,
    //             'nomenclature' => $ligneBudgetaire->nomenclature->code,
    //             'montant_libere' => $this->montant_net,
    //             'nouveau_engage' => $ligneBudgetaire->engage,
    //         ]);
    //     }
    // }

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
        // Si c'est un fournisseur
        if ($this->type_beneficiaire === 'fournisseur' && $this->fournisseur) {
            return $this->fournisseur->raison_sociale;
        }

        // Si c'est un personnel
        if ($this->personnel && !empty($this->personnel->nom_complet)) {
            return $this->personnel->nom_complet;
        }

        // Ancien système (compatibilité)
        if (!empty($this->personnel_id_ancien)) {
            $user = User::find($this->personnel_id_ancien);
            if ($user && !empty($user->name)) {
                return $user->name;
            }
        }

        return '';
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
