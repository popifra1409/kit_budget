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

class BonCommande extends Model
{
    use HasFactory, SoftDeletes, HasExercice, HasWorkflow, LogsActivity;

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
        'net_a_percevoir', // ✅ AJOUTÉ
        'exonere_tva', // ✅ AJOUTÉ - TRÈS IMPORTANT
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

        if (auth()->user()?->hasRole('super_admin')) {
            return true;
        }

        if ($this->estEnCoursDeTransmission()) {
            return false;
        }

        return $this->estModifiable();
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
            if (
                $bonCommande->isDirty() &&
                $bonCommande->getOriginal('statut') !== 'brouillon' &&
                !auth()->user()?->hasRole('super_admin')
            ) {
                throw new \Exception(
                    'Modification interdite : bon de commande non brouillon. Seul le super administrateur peut modifier un BC validé.'
                );
            }

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
     * Générer le numéro d'engagement
     */
    protected function genererNumeroEngagement(): string
    {
        if (!$this->numero) {
            throw new \Exception('Le bon de commande n\'a pas de numéro');
        }

        $numeroEngagement = str_replace('BC-', 'BE-', $this->numero);

        if ($numeroEngagement === $this->numero) {
            $numeroEngagement = preg_replace('/^BC([\/\-_])/', 'BE$1', $this->numero);
        }

        if ($numeroEngagement === $this->numero) {
            $numeroEngagement = 'BE-' . $this->numero;
        }

        $count = 1;
        $numeroBase = $numeroEngagement;
        while (Engagement::where('reference_document', $numeroEngagement)->exists()) {
            $numeroEngagement = $numeroBase . '-' . $count;
            $count++;
        }

        return $numeroEngagement;
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

        if ($this->lignes()->count() === 0) {
            throw new \Exception("Le BC doit avoir au moins une ligne");
        }

        $lignesCalculees = collect();
        foreach ($this->lignes()->get() as $ligne) {
            if ($ligne->taux_tva === null || $ligne->taux_tva === '') {
                $ligne->setAttribute('taux_tva', 19.25);
            }

            $ligne->calculerMontants();
            $ligne->save();
            $ligne->refresh();
            $lignesCalculees->push($ligne);
        }

        $this->refresh();

        \DB::beginTransaction();
        try {
            $numeroEngagement = $this->genererNumeroEngagement();
            $netAPayer = $this->montant_ht - $this->montant_ir;

            if ($netAPayer <= 0) {
                throw new \Exception(
                    "❌ MONTANT INVALIDE\n\n" .
                        "Le montant net à payer du BC est invalide.\n\n" .
                        "Montant HT: " . number_format($this->montant_ht, 0, ',', ' ') . " FCFA\n" .
                        "Montant IR: " . number_format($this->montant_ir, 0, ',', ' ') . " FCFA\n" .
                        "Net à percevoir: " . number_format($netAPayer, 0, ',', ' ') . " FCFA"
                );
            }

            $premiereLigne = $lignesCalculees->first();
            $nomenclaturePrincipaleId = $premiereLigne ? $premiereLigne->nomenclature_id : null;

            if (!$nomenclaturePrincipaleId) {
                throw new \Exception("Impossible de déterminer la nomenclature principale");
            }

            $engagement = Engagement::create([
                'exercice_id' => $this->exercice_id, // ✅ AJOUTÉ
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
                'montant_engage' => $this->montant_ttc,
                'statut' => 'provisoire',
            ]);

            $lignesParNomenclature = [];
            foreach ($lignesCalculees as $ligne) {
                $nomenclatureId = $ligne->nomenclature_id;

                if ($ligne->net_a_payer <= 0) {
                    throw new \Exception(
                        "❌ MONTANT INVALIDE\n\n" .
                            "Ligne: {$ligne->designation}\n" .
                            "Net à payer: " . number_format($ligne->net_a_payer, 0, ',', ' ') . " FCFA"
                    );
                }

                if (!isset($lignesParNomenclature[$nomenclatureId])) {
                    $lignesParNomenclature[$nomenclatureId] = [
                        'montant' => 0,
                        'libelles' => []
                    ];
                }

                $lignesParNomenclature[$nomenclatureId]['montant'] += $ligne->net_a_payer;
                $lignesParNomenclature[$nomenclatureId]['libelles'][] = $ligne->designation;
            }

            $numeroLigne = 1;
            foreach ($lignesParNomenclature as $nomenclatureId => $data) {
                $ligneBudgetaire = LigneBudgetaire::where('budget_id', $this->budget_id)
                    ->where('nomenclature_id', $nomenclatureId)
                    ->firstOrFail();

                if (!$ligneBudgetaire->peutEngager($data['montant'])) {
                    $nomenclature = $ligneBudgetaire->nomenclature;
                    $manque = $data['montant'] - $ligneBudgetaire->disponible_engagement;

                    throw new \Exception(
                        "❌ CRÉDIT INSUFFISANT\n\n" .
                            "Ligne budgétaire: {$nomenclature->code} - {$nomenclature->libelle}\n" .
                            "Disponible: " . number_format($ligneBudgetaire->disponible_engagement, 0, ',', ' ') . " FCFA\n" .
                            "Demandé: " . number_format($data['montant'], 0, ',', ' ') . " FCFA\n" .
                            "Manque: " . number_format($manque, 0, ',', ' ') . " FCFA"
                    );
                }

                LigneEngagement::create([
                    'engagement_id' => $engagement->id,
                    'nomenclature_id' => $nomenclatureId,
                    'numero_ligne' => $numeroLigne++,
                    'libelle' => implode(', ', $data['libelles']),
                    'montant' => $data['montant'],
                ]);

                $ligneBudgetaire->enregistrerEngagement($data['montant']);
            }

            $this->engage = true;
            $this->montant_engage = $netAPayer;
            $this->date_engagement = now();
            $this->save();

            \DB::commit();

            \Log::info("Engagement créé", [
                'bc_numero' => $this->numero,
                'engagement_numero' => $numeroEngagement,
                'engagement_id' => $engagement->id,
                'montant' => $netAPayer,
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /**
     * ✅ CRÉER OU METTRE À JOUR LE DOSSIER - VERSION CORRIGÉE AVEC VÉRIFICATIONS
     */
    public function creerOuMettreAJourDossier(): ?DossierFournisseur
    {
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
                    'numero_dossier' => DossierFournisseur::genererNumeroDossier('bon_commande'),
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
            \Log::error("Erreur création/MAJ dossier pour BC {$this->numero} : " . $e->getMessage());
            return null;
        }
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
