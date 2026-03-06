<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasExercice;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Traits\HasWorkflow;
use App\Traits\GereTransmissions;
use Illuminate\Support\Facades\DB;

class BonCommande extends Model
{
    use HasFactory, SoftDeletes, HasExercice, HasWorkflow, LogsActivity, GereTransmissions;

    protected $table = 'bons_commande';

    protected $fillable = [
        'exercice_id',
        'numero',
        'budget_id',
        'fournisseur_id',
        'service_demandeur_id',
        'service_beneficiaire_id',
        'date_emission',
        'date_livraison_prevue',
        'delai_livraison',
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
        'type_engagement_id',
        'reference',
        'montant_tsr',
        'montant_cnps',
        'montant_irnc',
        'montant_autres_taxes',
        'produit_importe',
        'created_by',
        'updated_by',
        'net_a_payer',
        'net_a_percevoir',
        'exonere_tva',
        'exonere_ir',
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
        'type_engagement_id' => 'integer',
        'montant_tsr' => 'decimal:2',
        'montant_cnps' => 'decimal:2',
        'montant_irnc' => 'decimal:2',
        'montant_autres_taxes' => 'decimal:2',
        'produit_importe' => 'boolean',
        'net_a_payer' => 'decimal:2',
        'net_a_percevoir' => 'decimal:2',
        'exonere_tva' => 'boolean',
        'exonere_ir' => 'boolean',
    ];

    /**
     * Relation : Type d'engagement
     */
    public function typeEngagement(): BelongsTo
    {
        return $this->belongsTo(TypeEngagement::class);
    }


    // Dans App\Models\BonCommande.php

    /**
     * Vérifier la disponibilité budgétaire avant engagement
     */
    public function verifierDisponibiliteBudgetaire(): array
    {
        $lignes = $this->lignes()->with('nomenclature')->get();

        if ($lignes->isEmpty()) {
            return [
                'peut_engager' => false,
                'montant_total' => 0,
                'lignes_budgetaires' => [],
                'message' => 'Aucune ligne de commande',
            ];
        }

        // Grouper par nomenclature
        $lignesParNomenclature = [];
        foreach ($lignes as $ligne) {
            $nomenclatureId = $ligne->nomenclature_id;

            if (!isset($lignesParNomenclature[$nomenclatureId])) {
                $lignesParNomenclature[$nomenclatureId] = [
                    'nomenclature' => $ligne->nomenclature,
                    'montant_a_engager' => 0,
                    'lignes' => [],
                ];
            }

            $lignesParNomenclature[$nomenclatureId]['montant_a_engager'] += $ligne->net_a_payer;
            $lignesParNomenclature[$nomenclatureId]['lignes'][] = $ligne->designation;
        }

        // Vérifier chaque ligne budgétaire
        $verifications = [];
        $peutEngager = true;
        $montantTotal = 0;

        foreach ($lignesParNomenclature as $nomenclatureId => $data) {
            $nomenclature = $data['nomenclature'];
            $montantAEngager = $data['montant_a_engager'];
            $montantTotal += $montantAEngager;

            // Récupérer la ligne budgétaire
            $ligneBudgetaire = \App\Models\LigneBudgetaire::where('budget_id', $this->budget_id)
                ->where('nomenclature_id', $nomenclatureId)
                ->first();

            if (!$ligneBudgetaire) {
                $verifications[] = [
                    'nomenclature' => $nomenclature,
                    'montant_a_engager' => $montantAEngager,
                    'disponible_avant' => 0,
                    'disponible_apres' => 0,
                    'suffisant' => false,
                    'manque' => $montantAEngager,
                    'taux_utilisation' => 100,
                    'taux_utilisation_avant' => 0,
                    'lignes_designation' => $data['lignes'],
                    'provision_totale' => 0,
                    'deja_engage' => 0,
                    'message' => 'Ligne budgétaire introuvable',
                ];
                $peutEngager = false;
                continue;
            }

            $disponibleAvant = $ligneBudgetaire->disponible_engagement;
            $disponibleApres = $disponibleAvant - $montantAEngager;
            $suffisant = $disponibleAvant >= $montantAEngager;
            $manque = $suffisant ? 0 : ($montantAEngager - $disponibleAvant);

            // Calcul du taux d'utilisation
            $tauxUtilisationAvant = $ligneBudgetaire->montant_vote > 0
                ? (($ligneBudgetaire->engage) / $ligneBudgetaire->montant_vote) * 100
                : 0;

            $tauxUtilisationApres = $ligneBudgetaire->montant_vote > 0
                ? (($ligneBudgetaire->engage + $montantAEngager) / $ligneBudgetaire->montant_vote) * 100
                : 0;

            $verifications[] = [
                'nomenclature' => $nomenclature,
                'ligne_budgetaire' => $ligneBudgetaire,
                'montant_a_engager' => $montantAEngager,
                'disponible_avant' => $disponibleAvant,
                'disponible_apres' => $disponibleApres,
                'suffisant' => $suffisant,
                'manque' => $manque,
                'taux_utilisation' => round($tauxUtilisationApres, 2),
                'taux_utilisation_avant' => round($tauxUtilisationAvant, 2),
                'lignes_designation' => $data['lignes'],
                'provision_totale' => $ligneBudgetaire->montant_vote,
                'deja_engage' => $ligneBudgetaire->engage,
            ];

            if (!$suffisant) {
                $peutEngager = false;
            }
        }

        return [
            'peut_engager' => $peutEngager,
            'montant_total' => $montantTotal,
            'nombre_nomenclatures' => count($verifications),
            'lignes_budgetaires' => $verifications,
            'message' => $peutEngager
                ? 'Toutes les lignes budgétaires ont un crédit suffisant'
                : 'Crédit insuffisant sur une ou plusieurs lignes budgétaires',
        ];
    }

    /**
     * Calculer le montant total des impôts et taxes
     */
    public function calculerMontantTotalImpots(): float
    {
        return $this->montant_tva
            + $this->montant_ir
            + $this->montant_tsr
            + $this->montant_cnps
            + $this->montant_irnc
            + $this->montant_autres_taxes;
    }

    /**
     * Obtenir le montant net à percevoir
     */
    public function getMontantNetPercevoir(): float
    {
        return $this->montant_ttc - $this->calculerMontantTotalImpots();
    }

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
     * Déterminer automatiquement le type d'engagement selon le montant
     */
    public function determinerTypeEngagement(): void
    {
        $type = TypeEngagement::determinerParMontant($this->montant_ttc);

        if ($type) {
            $this->type_engagement_id = $type->id;
            $this->save();
        }
    }

    /**
     * Vérifier si le BC est en cours de transmission
     */
    public function estEnCoursDeTransmission(): bool
    {
        return $this->transmissions()
            ->where('statut', 'en_attente')
            ->exists();
    }

    /**
     * Vérifier si l'utilisateur actuel est le destinataire de la transmission en cours
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
     * Vérifier si l'utilisateur actuel est l'auteur/propriétaire du BC
     */
    public function estAuteur(): bool
    {
        return $this->created_by === auth()->id();
    }

    /**
     * Vérifier si l'utilisateur actuel peut voir ce BC
     */
    public function peutEtreVuPar(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();

        if (auth()->user()?->hasRole('super_admin')) {
            return true;
        }

        if (!$this->estEnCoursDeTransmission()) {
            return true;
        }

        return $this->estDestinataireActuel();
    }

    /**
     * Vérifier si l'utilisateur actuel peut modifier ce BC
     */

    public function peutEtreModifiePar(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();
        $user = \App\Models\User::find($userId);

        if (!$user) {
            return false;
        }

        // 1. Super admin peut TOUT modifier
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // 2. Permission force_update bypass toutes les règles
        // ✅ ADAPTER selon le modèle :
        // Pour BonCommande : 'force_update_bon_commande'
        // Pour DecisionAdministrative : 'force_update_decision_administrative'
        if ($user->can('force_update_bon_commande')) { // ← CHANGER ICI
            return true;
        }

        // 3. Si transmis à quelqu'un d'autre, on ne peut PAS modifier
        if ($this->estEnCoursDeTransmissionPourAutrui($userId)) {
            return false;
        }

        // 4. Si statut brouillon, le créateur peut modifier
        if ($this->statut === 'brouillon' && $this->created_by === $userId) {
            return true;
        }

        // 5. Si document retourné pour correction, le créateur peut modifier
        if ($this->estRetournePourCorrection() && $this->created_by === $userId) {
            return true;
        }

        // 6. Si validé/engagé, PERSONNE ne peut modifier (sauf force_update)
        // ✅ ADAPTER selon le modèle :
        // BonCommande : ['valide', 'engage']
        // DecisionAdministrative : ['validee', 'engagee']
        if (in_array($this->statut, ['valide', 'engage'])) { // ← CHANGER ICI
            return false;
        }

        // Par défaut : non modifiable
        return false;
    }


    /**
     * Vérifier si le document est transmis à quelqu'un d'autre
     * (pas à moi, mais à une autre personne)
     * 
     * @param int|null $userId ID de l'utilisateur (null = utilisateur connecté)
     * @return bool
     */
    public function estEnCoursDeTransmissionPourAutrui(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();

        $transmission = $this->transmissions()
            ->where('statut', 'en_attente')
            ->latest()
            ->first();

        if (!$transmission) {
            return false;
        }

        // Si JE suis le destinataire, ce n'est PAS pour autrui
        if ($transmission->destinataire_id === $userId) {
            return false;
        }

        // Si JE suis l'expéditeur OU une autre personne, c'est pour autrui
        return true;
    }

    /**
     * Vérifier si le document est retourné pour correction
     * 
     * @return bool
     */
    public function estRetournePourCorrection(): bool
    {
        $derniereTransmission = $this->transmissions()
            ->latest()
            ->first();

        return $derniereTransmission && $derniereTransmission->statut === 'retourne';
    }

    /**
     * Vérifier si je suis l'émetteur de la transmission en cours
     * 
     * @return bool
     */
    public function suisEmetteurTransmissionEnCours(): bool
    {
        $transmission = $this->transmissions()
            ->where('statut', 'en_attente')
            ->latest()
            ->first();

        return $transmission && $transmission->expediteur_id === auth()->id();
    }

    protected static function booted(): void
    {
        /**
         * AVANT SAUVEGARDE
         */
        static::saving(function ($bonCommande) {
            // Forcer exonération TVA
            if ($bonCommande->exonere_tva) {
                $bonCommande->montant_tva = 0;
            }
            // Forcer exonération IF
            if ($bonCommande->exonere_ir) {
                $bonCommande->montant_ir = 0;
            }
        });

        /**
         * AVANT CRÉATION
         */
        static::creating(function ($bonCommande) {
            if (!$bonCommande->numero) {
                $bonCommande->numero = $bonCommande->genererNumero();
            }

            if (!$bonCommande->type_engagement_id && $bonCommande->montant_ttc > 0) {
                $type = \App\Models\TypeEngagement::determinerParMontant($bonCommande->montant_ttc);
                if ($type) {
                    $bonCommande->type_engagement_id = $type->id;
                }
            }
        });

        /**
         * AVANT MISE À JOUR
         */
        static::updating(function ($bonCommande) {
            // ✅ CORRECTION: Champs autorisés à être modifiés même si le BC n'est pas en brouillon
            // Ces champs font partie du workflow normal (engagement, transitions de statut)
            $champsAutorisesSansRestriction = [
                'engagement_id',
                'engage',
                'date_engagement',
                'statut',
                'updated_by',
                'updated_at',
            ];

            // Vérifier si SEULEMENT des champs autorisés ont été modifiés
            $champsDirty = array_keys($bonCommande->getDirty());
            $modificationAutorisee = empty(array_diff($champsDirty, $champsAutorisesSansRestriction));

            // ✅ Si seuls les champs autorisés sont modifiés, autoriser la mise à jour
            if ($modificationAutorisee) {
                return; // Sortir de l'observer sans lever d'exception
            }

            // Vérifier les permissions pour les autres modifications
            if (
                $bonCommande->isDirty() &&
                $bonCommande->getOriginal('statut') !== 'brouillon' &&
                !auth()->user()?->hasRole('super_admin')
            ) {
                throw new \Exception(
                    'Modification interdite : bon de commande non brouillon. Seul le super administrateur peut modifier un BC validé.'
                );
            }

            // Mise à jour automatique du type d'engagement si le montant change
            if ($bonCommande->isDirty('montant_ttc') && !$bonCommande->isDirty('type_engagement_id')) {
                if ($bonCommande->montant_ttc > 0) {
                    $type = \App\Models\TypeEngagement::determinerParMontant($bonCommande->montant_ttc);
                    if ($type) {
                        $bonCommande->type_engagement_id = $type->id;
                    }
                }
            }
        });

        /**
         * AVANT SUPPRESSION
         */
        static::deleting(function ($bonCommande) {
            if (!auth()->user()?->hasRole('super_admin')) {
                throw new \Exception(
                    'Suppression interdite : réservé au super administrateur.'
                );
            }

            if ($bonCommande->engage) {
                throw new \Exception(
                    'Suppression interdite : ce bon de commande est déjà engagé. Annulez-le d\'abord.'
                );
            }
        });

        /**
         * APRÈS CHARGEMENT
         */
        static::retrieved(function ($bonCommande) {
            if ($bonCommande->exonere_tva) {
                foreach ($bonCommande->lignes as $ligne) {
                    if ((float) $ligne->taux_tva !== 0.0) {
                        $ligne->taux_tva = 0;
                        $ligne->recalculerMontants();
                        $ligne->saveQuietly();
                    }
                }
            }
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
     * Service bénéficiaire
     */
    public function serviceBeneficiaire(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_beneficiaire_id');
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

    // public function engagement(): BelongsTo
    // {
    //     return $this->belongsTo(Engagement::class, 'engagement_id');
    // }

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
     * Format : BC26-00001 (au lieu de BC-2026-00001)
     */
    public function genererNumero(): string
    {
        // ✅ Utiliser l'exercice du BC, ou l'exercice actif
        $exercice = $this->exercice ?? \App\Models\Exercice::getActif();

        if (!$exercice) {
            throw new \Exception("Aucun exercice disponible pour générer le numéro");
        }

        // ✅ Prendre les 2 derniers chiffres de l'année
        $annee = substr($exercice->annee, -2); // 2026 → 26

        // Chercher le dernier BC de cet exercice
        $dernier = self::where('exercice_id', $exercice->id)
            ->where('numero', 'like', "BC{$annee}-%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($dernier) {
            // Extraire le numéro séquentiel (les 5 derniers chiffres)
            $dernierNumero = intval(substr($dernier->numero, -5));
            $nouveauNumero = $dernierNumero + 1;
        } else {
            $nouveauNumero = 1;
        }

        // ✅ Format : BC26-00001
        return sprintf('BC%s-%05d', $annee, $nouveauNumero);
    }

    /**
     * Générer le numéro d'engagement
     * Format : BE26-00001 (adapté du BC26-00001)
     */
    protected function genererNumeroEngagement(): string
    {
        if (!$this->numero) {
            throw new \Exception('Le bon de commande n\'a pas de numéro');
        }

        return 'BE-' . $this->numero;
    }

    /**
     * Calculer les montants
     */
    public function calculerMontants(): void
    {
        $totalHT = 0;
        $totalTVA = 0;
        $totalIR = 0;
        $totalTTC = 0;
        $totalNetAPayer = 0;

        if (!$this->relationLoaded('lignes')) {
            $this->load('lignes');
        }

        foreach ($this->lignes as $ligne) {
            $totalHT += $ligne->montant_ht ?? 0;
            $totalTVA += $ligne->montant_tva ?? 0;
            $totalIR += $ligne->montant_ir ?? 0;
            $totalTTC += $ligne->montant_ttc ?? 0;
            $totalNetAPayer += $ligne->net_a_payer ?? 0;
        }

        $this->montant_ht = round($totalHT, 2);
        $this->montant_tva = round($totalTVA, 2);
        $this->montant_ir = round($totalIR, 2);
        $this->montant_ttc = round($totalTTC, 2);
        $this->net_a_payer = round($totalNetAPayer, 2);
        $this->net_a_percevoir = round($totalNetAPayer, 2); // ✅ AJOUTÉ
    }

    /**
     * ✅ RECALCULER TOUS LES MONTANTS - VERSION CORRIGÉE
     */
    public function recalculerTousLesMontants(): void
    {
        $totalHT = 0;
        $totalTVA = 0;
        $totalIR = 0;
        $totalTTC = 0;
        $totalNetAPayer = 0;

        if (!$this->relationLoaded('lignes')) {
            $this->load('lignes');
        }

        foreach ($this->lignes as $ligne) {
            if ($this->exonere_tva && $ligne->taux_tva != 0) {
                $ligne->appliquerExonerationTVA();
                $ligne->saveQuietly();
            } else if (!$this->exonere_tva && $ligne->taux_tva == 0 && !$this->isDirty('exonere_tva')) {
                $ligne->restaurerTVA(19.25);
                $ligne->saveQuietly();
            } else {
                $ligne->recalculerMontants();
                $ligne->saveQuietly();
            }

            $totalHT += $ligne->montant_ht;
            $totalTVA += $ligne->montant_tva;
            $totalIR += $ligne->montant_ir;
            $totalTTC += $ligne->montant_ttc;
            $totalNetAPayer += $ligne->net_a_payer;
        }

        $this->montant_ht = round($totalHT, 2);
        $this->montant_tva = round($totalTVA, 2);
        $this->montant_ir = round($totalIR, 2);
        $this->montant_ttc = round($totalTTC, 2);
        $this->net_a_payer = round($totalNetAPayer, 2);
        $this->net_a_percevoir = round($totalNetAPayer, 2); // ✅ AJOUTÉ

        $this->saveQuietly();
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
     * Engager le bon de commande sur le budget
     */
    public function engagerBudget(?array $verifications = null): Engagement
    {
        // ✅ Vérifications préalables
        if ($this->statut !== 'valide') {
            throw new \Exception("Le bon de commande doit être validé avant d'être engagé.");
        }

        if ($this->engagement) {
            throw new \Exception("Ce bon de commande est déjà engagé (Engagement n°{$this->engagement->numero}).");
        }

        if (!$this->budget_id) {
            throw new \Exception("Aucun budget associé à ce bon de commande.");
        }

        try {
            \DB::beginTransaction();

            // ✅ Obtenir les vérifications si non fournies
            if (!$verifications) {
                $verifications = $this->verifierDisponibiliteBudgetaire();
            }

            if (!$verifications['peut_engager']) {
                $details = [];
                foreach ($verifications['lignes_budgetaires'] as $ligne) {
                    if (!$ligne['suffisant']) {
                        $details[] = "{$ligne['nomenclature']->code} : manque " .
                            number_format($ligne['manque'], 0, ',', ' ') . " FCFA";
                    }
                }

                throw new \Exception(
                    "Crédit budgétaire insuffisant :\n\n" .
                        implode("\n", $details) .
                        "\n\nVeuillez augmenter le crédit ou réduire le montant du bon de commande."
                );
            }

            // ✅ Préparer les données de l'engagement
            $engagementData = [
                'numero' => $this->genererNumeroEngagement(),
                'exercice_id' => $this->exercice_id,
                'budget_id' => $this->budget_id,
                'nomenclature_principale_id' => $this->lignes->first()->nomenclature_id ?? null,
                'type_engagement' => 'BC',
                'engageable_type' => get_class($this),
                'engageable_id' => $this->id,
                'date_engagement' => now(),
                'montant_engage' => $this->montant_ttc,
                'objet' => $this->objet,
                'reference_document' => $this->numero,
                'statut' => 'provisoire',
                'engage_par' => auth()->id(),
            ];

            // ✅ DÉFINIR LE BÉNÉFICIAIRE AVANT LA CRÉATION
            if ($this->fournisseur_id) {
                $engagementData['beneficiaire_type'] = \App\Models\Fournisseur::class;
                $engagementData['beneficiaire_id'] = $this->fournisseur_id;
            } else {
                // Si pas de fournisseur, utiliser un bénéficiaire par défaut
                $engagementData['beneficiaire_type'] = 'autre';
            }

            // ✅ Créer l'engagement avec toutes les données
            $engagement = \App\Models\Engagement::create($engagementData);

            // ✅ Engager les crédits sur chaque ligne budgétaire
            foreach ($verifications['lignes_budgetaires'] as $verification) {
                $ligneBudgetaire = \App\Models\LigneBudgetaire::where('budget_id', $this->budget_id)
                    ->where('nomenclature_id', $verification['nomenclature']->id)
                    ->first();

                if ($ligneBudgetaire) {
                    $ligneBudgetaire->engage += $verification['montant_a_engager'];
                    $ligneBudgetaire->save();

                    \Log::info("Crédit engagé sur {$verification['nomenclature']->code}", [
                        'montant' => $verification['montant_a_engager'],
                        'nouveau_engage' => $ligneBudgetaire->engage,
                    ]);
                }
            }

            // ✅ Lier l'engagement au BC
            $this->engagement_id = $engagement->id;
            $this->save();

            \DB::commit();

            \Log::info("BC {$this->numero} engagé → Engagement {$engagement->numero} créé");

            return $engagement;
        } catch (\Exception $e) {
            \DB::rollBack();

            \Log::error("Erreur engagement BC {$this->numero} : " . $e->getMessage());

            throw $e;
        }
    }

    /**
     * ✅ CRÉER OU METTRE À JOUR LE DOSSIER - VERSION CORRIGÉE
     * - Ne crée pas de dossier si le BC est en brouillon
     * - Format numéro : DF26-00001
     */
    public function creerOuMettreAJourDossier(): ?DossierFournisseur
    {
        // ✅ NE PAS CRÉER DE DOSSIER POUR UN BROUILLON
        if ($this->statut === 'brouillon') {
            \Log::info("BC {$this->numero} : Pas de dossier pour un brouillon");
            return null;
        }

        // ✅ VÉRIFICATIONS PRÉALABLES
        if (!$this->fournisseur_id) {
            \Log::warning("BC {$this->numero} : Impossible de créer le dossier sans fournisseur");
            return null;
        }

        if (!$this->exercice_id) {
            \Log::warning("BC {$this->numero} : Impossible de créer le dossier sans exercice");
            return null;
        }

        try {
            // Chercher un dossier existant
            $dossier = DossierFournisseur::where('document_principal_type', get_class($this))
                ->where('document_principal_id', $this->id)
                ->first();

            if (!$dossier) {
                // Créer un nouveau dossier
                $dossier = DossierFournisseur::create([
                    'numero_dossier' => DossierFournisseur::genererNumeroDossier($this->exercice_id),
                    'fournisseur_id' => $this->fournisseur_id,
                    'exercice_id' => $this->exercice_id,
                    'type_dossier' => 'bon_commande',
                    'document_principal_type' => get_class($this),
                    'document_principal_id' => $this->id,
                    'reference_principale' => $this->numero,
                    'objet' => $this->objet ?? 'Bon de commande ' . $this->numero,
                    'montant_total' => $this->montant_ttc,
                    'montant_engage' => $this->engagement ? $this->montant_ttc : 0,
                    'date_ouverture' => $this->date_emission ?? now(),
                    'date_limite_livraison' => $this->date_livraison_prevue,
                    'responsable_id' => $this->created_by ?? auth()->id(),
                    'createur_id' => $this->created_by ?? auth()->id(),
                    'statut' => 'ouvert',
                ]);

                \Log::info("Dossier {$dossier->numero_dossier} créé pour BC {$this->numero}");

                // Ajouter automatiquement le BC comme pièce
                $dossier->ajouterPiece([
                    'type_piece' => 'bon_commande',
                    'document_type' => get_class($this),
                    'document_id' => $this->id,
                    'nom_fichier' => "BC-{$this->numero}.pdf",
                    'chemin_fichier' => '',
                    'valide' => true,
                    'valide_par' => auth()->id(),
                    'date_validation' => now(),
                ]);
            } else {
                // Mettre à jour le dossier existant
                $dossier->update([
                    'montant_total' => $this->montant_ttc,
                    'montant_engage' => $this->engagement ? $this->montant_ttc : 0,
                    'date_limite_livraison' => $this->date_livraison_prevue,
                ]);

                \Log::info("Dossier {$dossier->numero_dossier} mis à jour pour BC {$this->numero}");
            }

            return $dossier;
        } catch (\Exception $e) {
            \Log::error("Erreur création/MAJ dossier pour BC {$this->numero} : " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Vérifier si le BC peut être engagé
     */
    public function peutEtreEngage(): bool
    {
        return $this->statut === 'valide'
            && !$this->engagement_id;
    }

    /**
     * Désengager le budget
     */
    public function desengagerBudget(): void
    {
        if (!$this->engage) {
            return;
        }

        \DB::beginTransaction();
        try {
            $engagement = $this->engagement;

            if ($engagement) {
                $engagement->annuler();
            }

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
        return trim(strtolower($this->statut)) === 'brouillon';
    }

    /**
     * Obtenir le nombre de lignes
     */
    public function getNombreLignesAttribute(): int
    {
        if ($this->relationLoaded('lignes')) {
            return $this->lignes->count();
        }

        return $this->lignes()->count();
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

    /**
     * Accesseur : Net à percevoir
     */
    public function getNetAPercevoirAttribute(): float
    {
        return $this->net_a_percevoir ?? $this->getMontantNetPercevoir();
    }

    /**
     * Formater le net à percevoir
     */
    public function getNetAPercevoirFormatteAttribute(): string
    {
        return number_format($this->net_a_percevoir ?? 0, 0, ',', ' ') . ' FCFA';
    }

    /**
     * Formater l'IR
     */
    public function getMontantIrFormatteAttribute(): string
    {
        return number_format($this->montant_ir, 0, ',', ' ') . ' FCFA';
    }

    /**
     * Formater le montant HT
     */
    public function getMontantHtFormatteAttribute(): string
    {
        return number_format($this->montant_ht, 0, ',', ' ') . ' FCFA';
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
