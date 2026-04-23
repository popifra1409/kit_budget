<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasExercice;
use App\Traits\HasRecentValues;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Traits\HasWorkflow;
use App\Traits\GereTransmissions;
use Illuminate\Support\Facades\DB;

class BonCommande extends Model
{
    use HasFactory, SoftDeletes, HasExercice, HasWorkflow, LogsActivity, GereTransmissions, HasRecentValues;

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
        'nomenclature_commune_id',
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

    /**
     * Relation polymorphique : Les engagements
     */
    public function engagements()
    {
        return $this->morphMany(\App\Models\Engagement::class, 'engageable');
    }

    public function ligneBudgetaire()
    {
        return $this->belongsTo(LigneBudgetaire::class, 'budgetaire_ligne_id'); // Vrai nom
    }

    /**
     * Vérifier la disponibilité budgétaire avant engagement
     */
    public function verifierDisponibiliteBudgetaire(): array
    {
        // ✅ Utiliser nomenclature_commune_id — pas les lignes individuelles
        if (!$this->nomenclature_commune_id) {
            return [
                'peut_engager'     => false,
                'montant_total'    => 0,
                'lignes_budgetaires' => [],
                'message'          => 'Aucune nomenclature commune définie sur ce bon de commande',
            ];
        }

        $nomenclature = \App\Models\NomenclatureBudgetaire::find($this->nomenclature_commune_id);
        $montantTotal = (float) $this->montant_ttc;

        $ligneBudgetaire = \App\Models\LigneBudgetaire::where('budget_id', $this->budget_id)
            ->where('nomenclature_id', $this->nomenclature_commune_id)
            ->first();

        if (!$ligneBudgetaire) {
            return [
                'peut_engager'     => false,
                'montant_total'    => $montantTotal,
                'nombre_nomenclatures' => 1,
                'lignes_budgetaires' => [[
                    'nomenclature'      => $nomenclature,
                    'montant_a_engager' => $montantTotal,
                    'disponible_avant'  => 0,
                    'disponible_apres'  => 0,
                    'suffisant'         => false,
                    'manque'            => $montantTotal,
                    'taux_utilisation'  => 100,
                    'taux_utilisation_avant' => 0,
                    'lignes_designation' => [],
                    'provision_totale'  => 0,
                    'deja_engage'       => 0,
                    'message'           => 'Ligne budgétaire introuvable',
                ]],
                'message' => 'Ligne budgétaire introuvable pour cette nomenclature',
            ];
        }

        $disponibleAvant = $ligneBudgetaire->disponible_engagement;
        $disponibleApres = $disponibleAvant - $montantTotal;
        $suffisant       = $disponibleAvant >= $montantTotal;
        $manque          = $suffisant ? 0 : ($montantTotal - $disponibleAvant);

        $tauxAvant = $ligneBudgetaire->montant_vote > 0
            ? ($ligneBudgetaire->engage / $ligneBudgetaire->montant_vote) * 100
            : 0;
        $tauxApres = $ligneBudgetaire->montant_vote > 0
            ? (($ligneBudgetaire->engage + $montantTotal) / $ligneBudgetaire->montant_vote) * 100
            : 0;

        return [
            'peut_engager'         => $suffisant,
            'montant_total'        => $montantTotal,
            'nombre_nomenclatures' => 1,
            'lignes_budgetaires'   => [[
                'nomenclature'           => $nomenclature,
                'ligne_budgetaire'       => $ligneBudgetaire,
                'montant_a_engager'      => $montantTotal,
                'disponible_avant'       => $disponibleAvant,
                'disponible_apres'       => $disponibleApres,
                'suffisant'              => $suffisant,
                'manque'                 => $manque,
                'taux_utilisation'       => round($tauxApres, 2),
                'taux_utilisation_avant' => round($tauxAvant, 2),
                'lignes_designation'     => $this->lignes()->pluck('designation')->toArray(),
                'provision_totale'       => $ligneBudgetaire->montant_vote,
                'deja_engage'            => $ligneBudgetaire->engage,
            ]],
            'message' => $suffisant
                ? 'Crédit suffisant pour l\'engagement'
                : 'Crédit insuffisant sur la ligne budgétaire',
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
     * ✅ Vérifier si le BC peut être récupéré
     */
    public function peutEtreRecupere(): bool
    {
        // Doit être annulé
        if ($this->statut !== 'annule') {
            return false;
        }

        // Si l'engagement existe et n'est pas annulé, on ne peut pas récupérer
        if ($this->engagement && $this->engagement->statut !== 'annule') {
            return false;
        }

        return true;
    }

    /**
     * ✅ Récupérer un BC annulé pour le réutiliser
     * Passe le statut de "annulé" à "brouillon"
     */
    public function recuperer(?string $motif = null): void
    {
        if (!auth()->check()) {
            throw new \Exception("Vous devez être connecté pour récupérer ce bon de commande.");
        }

        if ($this->statut !== 'annule') {
            throw new \Exception("Seul un bon de commande annulé peut être récupéré.");
        }

        \DB::beginTransaction();
        try {
            // ✅ Vérifier s'il reste un engagement résiduel
            $engagement = \App\Models\Engagement::where('engageable_id', $this->id)
                ->where(function ($q) {
                    $q->where('engageable_type', static::class)
                        ->orWhere('engageable_type', 'bon_commande');
                })->first();

            if ($engagement) {
                if ($engagement->ordonnancesPaiement()->count() > 0) {
                    throw new \Exception(
                        "Impossible de récupérer : des ordonnances de paiement existent sur l'engagement lié."
                    );
                }

                // Libérer les crédits résiduels
                $engagement->load('lignes');
                foreach ($engagement->lignes as $ligne) {
                    $lb = \App\Models\LigneBudgetaire::where('budget_id', $this->budget_id)
                        ->where('nomenclature_id', $ligne->nomenclature_id)
                        ->first();
                    if ($lb && $lb->engage > 0) {
                        $lb->engage = max(0, $lb->engage - $ligne->montant);
                        $lb->save();
                    }
                }
                $engagement->lignes()->delete();
                $engagement->forceDelete();

                \Log::info("Engagement résiduel supprimé lors de la récupération du BC", [
                    'bc_numero' => $this->numero,
                    'engagement_numero' => $engagement->numero,
                ]);
            }

            $observationsAjout = "\n\n--- RÉCUPÉRÉ LE " . now()->format('d/m/Y H:i') . " ---\n" .
                "Motif : " . ($motif ?? 'Document récupéré pour modification') . "\n" .
                "Par : " . auth()->user()->name;

            // ✅ Toujours retourner en brouillon — modifiable + réengageable
            $this->update([
                'statut' => 'brouillon',
                'statut_avant_annulation' => null,
                'engage' => false,
                'montant_engage' => 0,
                'date_engagement' => null,
                'valide_par' => null,
                'date_validation' => null,
                'observations' => ($this->observations ?? '') . $observationsAjout,
            ]);

            \DB::commit();

            \Log::info("BC {$this->numero} récupéré — remis en brouillon");
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Erreur récupération BC {$this->numero} : " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ✅ Libérer les crédits d'un engagement annulé
     */
    // protected function libererCreditsEngagement(): void
    // {
    //     $lignesBudgetaires = \App\Models\LigneBudgetaire::where('budget_id', $this->budget_id)
    //         ->whereIn('nomenclature_id', $this->lignes->pluck('nomenclature_id'))
    //         ->get();

    //     foreach ($lignesBudgetaires as $ligneBudgetaire) {
    //         // Calculer le montant engagé pour cette nomenclature
    //         $montantEngagePourNomenclature = $this->lignes()
    //             ->where('nomenclature_id', $ligneBudgetaire->nomenclature_id)
    //             ->sum('montant_ttc');

    //         if ($montantEngagePourNomenclature > 0) {
    //             // Libérer le crédit
    //             $ligneBudgetaire->engage -= $montantEngagePourNomenclature;

    //             // Sécurité : ne pas avoir de montant négatif
    //             if ($ligneBudgetaire->engage < 0) {
    //                 $ligneBudgetaire->engage = 0;
    //             }

    //             $ligneBudgetaire->save();

    //             \Log::info("Crédit libéré sur {$ligneBudgetaire->nomenclature->code}", [
    //                 'montant_libere' => $montantEngagePourNomenclature,
    //                 'nouveau_engage' => $ligneBudgetaire->engage,
    //             ]);
    //         }
    //     }
    // }

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
            if ($bonCommande->statut === 'brouillon') {
                return;
            }
            // ✅ CORRECTION: Champs autorisés à être modifiés même si le BC n'est pas en brouillon
            // Ces champs font partie du workflow normal (engagement, transitions de statut)
            $champsAutorisesSansRestriction = [
                'engagement_id',
                'engage',
                'montant_engage',
                'date_engagement',
                'statut',
                'observations',
                'valide_par',
                'date_validation',
                'updated_by',
                'updated_at',
                'created_at'
            ];

            // Vérifier si SEULEMENT des champs autorisés ont été modifiés
            $champsDirty = array_keys($bonCommande->getDirty());
            $modificationAutorisee = empty(array_diff($champsDirty, $champsAutorisesSansRestriction));

            // ✅ Si seuls les champs autorisés sont modifiés, autoriser la mise à jour
            if ($modificationAutorisee) {
                \Log::info("BC {$bonCommande->numero} : Modification autorisée", [
                    'champs' => $champsDirty,
                    'statut' => $bonCommande->statut,
                ]);
                return;
            }

            // Vérifier les permissions pour les autres modifications
            if (
                $bonCommande->isDirty() &&
                $bonCommande->getOriginal('statut') !== 'brouillon' &&
                !auth()->user()?->hasRole('super_admin')
            ) {
                \Log::error("BC {$bonCommande->numero} : Modification interdite", [
                    'champs_tentes' => $champsDirty,
                    'user' => auth()->user()?->name,
                    'statut_original' => $bonCommande->getOriginal('statut'),
                ]);

                throw new \Exception(
                    'Modification interdite : bon de commande non brouillon. ' .
                        'Seul le super administrateur peut modifier un BC validé. ' .
                        'Champs tentés : ' . implode(', ', $champsDirty)
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


    public function nomenclatureCommune(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_commune_id');
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
        $exercice = $this->exercice
            ?? ($this->exercice_id ? \App\Models\Exercice::find($this->exercice_id) : null)
            ?? \App\Models\Exercice::getActif();

        if (!$exercice) {
            throw new \Exception("Aucun exercice disponible pour générer le numéro");
        }

        $annee   = substr($exercice->annee, -2);
        $prefixe = $this->determinerPrefixeNumero();

        return \DB::transaction(function () use ($annee, $exercice, $prefixe) {
            // ✅ withoutGlobalScope — sinon HasExercice filtre sur l'exercice actif
            // et ne trouve pas les BC des exercices clôturés (ex: BC25-XXXXX)
            $dernier = self::withoutGlobalScope('exercice')
                ->withTrashed()
                ->where('exercice_id', $exercice->id)
                ->where('numero', 'like', "{$prefixe}{$annee}-%")
                ->lockForUpdate()
                ->orderByRaw("CAST(SPLIT_PART(numero, '-', 2) AS INTEGER) DESC")
                ->first();

            $sequence = $dernier
                ? intval(explode('-', $dernier->numero)[1]) + 1
                : 1;

            return sprintf('%s%s-%05d', $prefixe, $annee, $sequence);
        });
    }

    protected function determinerPrefixeNumero(): string
    {
        if ($this->type_engagement_id && !$this->relationLoaded('typeEngagement')) {
            $this->load('typeEngagement');
        }

        return match ($this->typeEngagement?->code ?? 'BC') {
            'LC'   => 'LC',
            'MA'   => 'MA',
            'DL'   => 'DL',
            'DM'   => 'DM',
            default => 'BC',
        };
    }

    /**
     * Générer le numéro d'engagement
     * Format : BE26-00001 (adapté du BC26-00001)
     */
    protected function genererNumeroEngagement(): string
    {
        if (!$this->numero) {
            $this->numero = $this->genererNumero();
            $this->saveQuietly();
        }

        // Détecter le format
        if (preg_match('/^BC-\d{4}-/', $this->numero)) {
            // Format 1 : BC-2026-00001 → BE-2026-00001
            return str_replace('BC-', 'BE-', $this->numero);
        } elseif (preg_match('/^BC\d{2}-/', $this->numero)) {
            // Format 2 : BC26-00001 → BE-BC26-00001
            return 'BE-' . $this->numero;
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
        $this->net_a_percevoir = round($totalNetAPayer, 2);

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
                'nomenclature_principale_id' => $this->nomenclature_commune_id,
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

                    $ligneEngagement = \App\Models\LigneEngagement::create([
                        'engagement_id' => $engagement->id,
                        'nomenclature_id' => $verification['nomenclature']->id,
                        'numero_ligne' => 1,
                        'libelle' => $this->objet,
                        'montant' => $verification['montant_a_engager'],
                    ]);

                    if (!$ligneEngagement || !$ligneEngagement->id) {
                        throw new \Exception("Erreur création ligne d'engagement");
                    }

                    \Log::info("Crédit engagé + ligne créée sur {$verification['nomenclature']->code}", [
                        'montant' => $verification['montant_a_engager'],
                        'nouveau_engage' => $ligneBudgetaire->engage,
                        'ligne_engagement_id' => $ligneEngagement->id,
                    ]);
                }
            }

            // ✅ MODIFICATION ICI : Lier l'engagement au BC + mettre à jour les champs
            $this->engagement_id = $engagement->id;
            $this->engage = true;
            $this->date_engagement = now();
            $this->montant_engage = $this->montant_ttc;
            $this->statut = 'engage';
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

    public function peutEtreDesengage(): bool
    {
        if (!$this->engage || !$this->engagement_id) {
            return false;
        }

        // ✅ Vérifier via l'engagement
        if ($this->engagement && $this->engagement->ordonnancesPaiement()->count() > 0) {
            return false;
        }

        if (in_array($this->statut, ['annule', 'livre'])) {
            return false;
        }

        return true;
    }

    /**
     * Désengager le budget
     */
    public function desengagerBudget(): void
    {
        if (!$this->peutEtreDesengage()) {
            throw new \Exception("Ce bon de commande ne peut pas être désengagé.");
        }

        // ✅ Requête directe — contourne morphMap
        $engagement = \App\Models\Engagement::where('engageable_id', $this->id)
            ->where(function ($q) {
                $q->where('engageable_type', static::class)
                    ->orWhere('engageable_type', 'bon_commande');
            })->first();

        if (!$engagement) {
            $this->updateQuietly([
                'engage'          => false,
                'montant_engage'  => 0,
                'date_engagement' => null,
                'statut'          => 'valide',
            ]);
            return;
        }

        \DB::beginTransaction();
        try {
            $engagement->load('lignes');

            // ✅ Libérer depuis les LIGNES D'ENGAGEMENT (pas les lignes du BC)
            // Car les lignes du BC peuvent avoir changé de nomenclature
            foreach ($engagement->lignes as $ligne) {
                $lb = \App\Models\LigneBudgetaire::where('budget_id', $this->budget_id)
                    ->where('nomenclature_id', $ligne->nomenclature_id) // ← nomenclature de l'engagement
                    ->first();

                if ($lb && $lb->engage > 0) {
                    $lb->engage = max(0, $lb->engage - $ligne->montant);
                    $lb->save();

                    \Log::info("Crédit libéré sur nomenclature {$ligne->nomenclature_id}", [
                        'montant'       => $ligne->montant,
                        'nouveau_engage' => $lb->engage,
                    ]);
                }
            }

            // ✅ Fallback — si pas de lignes d'engagement
            if ($engagement->lignes->isEmpty() && $engagement->nomenclature_principale_id) {
                $lb = \App\Models\LigneBudgetaire::where('budget_id', $this->budget_id)
                    ->where('nomenclature_id', $engagement->nomenclature_principale_id)
                    ->first();
                if ($lb && $lb->engage > 0) {
                    $lb->engage = max(0, $lb->engage - $engagement->montant_engage);
                    $lb->save();
                }
            }

            // ✅ Supprimer définitivement — libère le numéro pour réengagement
            $engagement->lignes()->delete();
            $engagement->forceDelete();

            \Log::info("Engagement {$engagement->numero} supprimé — crédits libérés");

            $this->updateQuietly([
                'engage'          => false,
                'montant_engage'  => 0,
                'date_engagement' => null,
                'statut'          => 'valide',
            ]);

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Erreur désengagement BC", ['erreur' => $e->getMessage()]);
            throw $e;
        }
    }


    public function peutEtreAnnule(): bool
    {
        // ✅ Brouillon = pas besoin d'annuler, on supprime
        if (in_array($this->statut, ['annule', 'brouillon'])) return false;

        // Si engagé avec OP → impossible
        if ($this->engage) {
            $engagement = \App\Models\Engagement::where('engageable_id', $this->id)
                ->where(function ($q) {
                    $q->where('engageable_type', static::class)
                        ->orWhere('engageable_type', 'bon_commande');
                })->first();

            if ($engagement && $engagement->ordonnancesPaiement()->count() > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Annuler le BC
     */
    public function annuler(?string $motif = null): void
    {
        if ($this->statut === 'annule') {
            throw new \Exception("Ce bon de commande est déjà annulé.");
        }

        // ✅ Bloquer si engagé — l'utilisateur doit désengager d'abord
        if ($this->engage) {
            $engagement = \App\Models\Engagement::where('engageable_id', $this->id)
                ->where(function ($q) {
                    $q->where('engageable_type', static::class)
                        ->orWhere('engageable_type', 'bon_commande');
                })->first();

            $numEngagement = $engagement?->numero ?? '—';

            throw new \Exception(
                "❌ Annulation impossible : ce bon de commande est engagé.\n\n" .
                    "Veuillez d'abord annuler l'engagement N° {$numEngagement}, " .
                    "puis revenez annuler le bon de commande."
            );
        }

        \DB::beginTransaction();
        try {
            $statutAvant = $this->statut;

            $observationsAjout = "\n\n--- ANNULÉ LE " . now()->format('d/m/Y H:i') . " ---\n" .
                "Statut avant : {$statutAvant}\n" .
                "Motif : " . ($motif ?? 'Non précisé') . "\n" .
                "Par : " . auth()->user()->name;

            $this->update([
                'statut' => 'annule',
                'statut_avant_annulation' => $statutAvant,
                'observations' => ($this->observations ?? '') . $observationsAjout,
            ]);

            \DB::commit();

            \Log::info("BC {$this->numero} annulé", [
                'statut_avant' => $statutAvant,
                'user' => auth()->id(),
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Erreur annulation BC {$this->numero} : " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Vérifier si le BC est modifiable
     */
    public function estModifiable(): bool
    {
        return trim(strtolower($this->statut)) === 'brouillon';
    }

    public function getNomenclaturePrincipale()
    {
        if ($this->nomenclature_commune_id) {
            return $this->nomenclatureCommune;
        }
        return $this->lignes()->first()?->nomenclature;
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
