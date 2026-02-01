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
        'type_engagement_id',
        'reference',
        'montant_tsr',
        'montant_cnps',
        'montant_irnc',
        'montant_autres_taxes',
        'produit_importe',
    ];

    /**
     * Relation : Type d'engagement
     */
    public function typeEngagement(): BelongsTo
    {
        return $this->belongsTo(TypeEngagement::class);
    }

    /**
     * Calculer le montant total des impôts et taxes
     * (TVA + IR + TSR + CNPS + IRNC + Autres)
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
     * Pour OP : Montant Brut (TTC) - Total Impôts
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
     * Vérifier si le BC est en cours de transmission (pas clôturé, pas retourné)
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
        // Vous pouvez ajuster cette logique selon votre modèle
        // Par exemple, si vous avez un champ created_by
        return $this->created_by === auth()->id();
    }

    /**
     * Vérifier si l'utilisateur actuel peut voir ce BC
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
     * Vérifier si l'utilisateur actuel peut modifier ce BC
     */
    public function peutEtreModifiePar(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();

        // Super admin peut tout modifier
        if (auth()->user()?->hasRole('super_admin')) {
            return true;
        }

        // Si en cours de transmission, seul le destinataire peut "agir" (pas modifier, mais traiter)
        if ($this->estEnCoursDeTransmission()) {
            return false; // Personne ne peut modifier pendant une transmission
        }

        // Si brouillon, vérifier les permissions normales
        return $this->estModifiable();
    }

    protected static function booted(): void
    {
        // ========================================
        // ÉVÉNEMENT : AVANT CRÉATION
        // ========================================
        static::creating(function ($bonCommande) {
            // 1. Générer le numéro si pas défini
            if (!$bonCommande->numero) {
                $bonCommande->numero = $bonCommande->genererNumero();
            }

            // 2. Déterminer le type d'engagement automatiquement si non défini
            if (!$bonCommande->type_engagement_id && $bonCommande->montant_ttc > 0) {
                $type = \App\Models\TypeEngagement::determinerParMontant($bonCommande->montant_ttc);
                if ($type) {
                    $bonCommande->type_engagement_id = $type->id;
                }
            }
        });

        // ========================================
        // ÉVÉNEMENT : AVANT MISE À JOUR
        // ========================================
        static::updating(function ($bonCommande) {
            // 1. CONTRÔLE DE SÉCURITÉ : Vérifier les permissions
            if (
                $bonCommande->isDirty() &&
                $bonCommande->getOriginal('statut') !== 'brouillon' &&
                !auth()->user()?->hasRole('super_admin')
            ) {
                throw new \Exception(
                    'Modification interdite : bon de commande non brouillon. Seul le super administrateur peut modifier un BC validé.'
                );
            }

            // 2. Recalculer le type d'engagement si le montant change
            // (mais uniquement si l'utilisateur n'a pas manuellement changé le type)
            if ($bonCommande->isDirty('montant_ttc') && !$bonCommande->isDirty('type_engagement_id')) {
                if ($bonCommande->montant_ttc > 0) {
                    $type = \App\Models\TypeEngagement::determinerParMontant($bonCommande->montant_ttc);
                    if ($type) {
                        $bonCommande->type_engagement_id = $type->id;
                    }
                }
            }
        });

        // ========================================
        // ÉVÉNEMENT : AVANT SUPPRESSION
        // ========================================
        static::deleting(function ($bonCommande) {
            // CONTRÔLE DE SÉCURITÉ : Seul le super admin peut supprimer
            if (!auth()->user()?->hasRole('super_admin')) {
                throw new \Exception(
                    'Suppression interdite : réservé au super administrateur.'
                );
            }

            // Note : Si le BC est engagé, vous pourriez ajouter un contrôle supplémentaire
            if ($bonCommande->engage) {
                throw new \Exception(
                    'Suppression interdite : ce bon de commande est déjà engagé. Annulez-le d\'abord.'
                );
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

        // Forcer le recalcul et sauvegarder les lignes
        $lignesCalculees = collect();
        foreach ($this->lignes()->get() as $ligne) {
            // IMPORTANT : Définir taux_tva AVANT tout calcul
            // Car le hook `saving` va aussi appeler calculerMontants()
            if ($ligne->taux_tva === null || $ligne->taux_tva === '') {
                $ligne->setAttribute('taux_tva', 19.25);
            }

            // Forcer le recalcul des montants
            $ligne->calculerMontants();

            // Vérifier que les montants sont bien calculés
            if ($ligne->net_a_payer <= 0 && $ligne->montant_ht > 0) {
                // Debug: afficher les valeurs avant save
                \Log::error("Ligne avant save", [
                    'designation' => $ligne->designation,
                    'montant_ht' => $ligne->montant_ht,
                    'taux_tva' => $ligne->taux_tva,
                    'montant_tva' => $ligne->montant_tva,
                    'montant_ttc' => $ligne->montant_ttc,
                    'taux_ir' => $ligne->taux_ir,
                    'montant_ir' => $ligne->montant_ir,
                    'net_a_payer' => $ligne->net_a_payer,
                ]);
            }

            // Sauvegarder AVEC les événements
            // Le hook `saving` va recalculer avec taux_tva = 19.25
            $ligne->save();

            // Recharger pour vérifier les valeurs en BD
            $ligne->refresh();

            // Garder en mémoire
            $lignesCalculees->push($ligne);
        }

        // Recharger le BC pour avoir les montants totaux à jour
        $this->refresh();

        \DB::beginTransaction();
        try {
            // Net à payer = TTC - IR
            // Net à payer = HT - IR (montant effectivement perçu par le fournisseur)
            $netAPayer = $this->montant_ht - $this->montant_ir;

            // Vérification finale du montant total
            if ($netAPayer <= 0) {
                throw new \Exception(
                    "❌ MONTANT INVALIDE\n\n" .
                        "Le montant net à payer du BC est invalide.\n\n" .
                        "Montant HT: " . number_format($this->montant_ht, 0, ',', ' ') . " FCFA\n" .
                        "Montant IR: " . number_format($this->montant_ir, 0, ',', ' ') . " FCFA\n" .
                        "Net à percevoir: " . number_format($netAPayer, 0, ',', ' ') . " FCFA\n" .
                        "TVA: " . number_format($this->montant_tva, 0, ',', ' ') . " FCFA\n" .
                        "TTC: " . number_format($this->montant_ttc, 0, ',', ' ') . " FCFA\n\n" .
                        "Vérifiez les montants des lignes du BC."
                );
            }

            // Récupérer la première nomenclature (principale)
            $premiereLigne = $lignesCalculees->first();
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
                'montant_engage' => $this->montant_ttc,
                'statut' => 'provisoire',
            ]);

            // Utiliser les lignes calculées en mémoire (PAS de rechargement BD)
            $lignesParNomenclature = [];
            foreach ($lignesCalculees as $ligne) {
                $nomenclatureId = $ligne->nomenclature_id;

                // Vérifier que le montant est valide
                if ($ligne->net_a_payer <= 0) {
                    throw new \Exception(
                        "❌ MONTANT INVALIDE\n\n" .
                            "Ligne: {$ligne->designation}\n\n" .
                            "📊 DÉTAILS:\n" .
                            "• Quantité: {$ligne->quantite}\n" .
                            "• Prix unitaire HT: " . number_format($ligne->prix_unitaire_ht, 0, ',', ' ') . " FCFA\n" .
                            "• Montant HT: " . number_format($ligne->montant_ht, 0, ',', ' ') . " FCFA\n" .
                            "• Taux TVA: {$ligne->taux_tva}%\n" .
                            "• Montant TVA: " . number_format($ligne->montant_tva, 0, ',', ' ') . " FCFA\n" .
                            "• TTC: " . number_format($ligne->montant_ttc, 0, ',', ' ') . " FCFA\n" .
                            "• Taux IR: {$ligne->taux_ir}%\n" .
                            "• Montant IR: " . number_format($ligne->montant_ir, 0, ',', ' ') . " FCFA\n" .
                            "• Net à payer: " . number_format($ligne->net_a_payer, 0, ',', ' ') . " FCFA\n\n" .
                            "✅ SOLUTION:\n" .
                            "Vérifiez que tous les montants sont corrects dans le formulaire."
                    );
                }

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
                    $nomenclature = $ligneBudgetaire->nomenclature;
                    $manque = $data['montant'] - $ligneBudgetaire->disponible_engagement;

                    throw new \Exception(
                        "❌ CRÉDIT INSUFFISANT\n\n" .
                            "Ligne budgétaire: {$nomenclature->code} - {$nomenclature->libelle}\n\n" .
                            "📊 DÉTAILS:\n" .
                            "• Provision totale: " . number_format($ligneBudgetaire->montant_vote, 0, ',', ' ') . " FCFA\n" .
                            "• Déjà engagé: " . number_format($ligneBudgetaire->engage, 0, ',', ' ') . " FCFA\n" .
                            "• Disponible: " . number_format($ligneBudgetaire->disponible_engagement, 0, ',', ' ') . " FCFA\n\n" .
                            "💰 ENGAGEMENT DEMANDÉ:\n" .
                            "• Montant à engager: " . number_format($data['montant'], 0, ',', ' ') . " FCFA\n" .
                            "• Manque: " . number_format($manque, 0, ',', ' ') . " FCFA\n\n" .
                            "✅ SOLUTIONS:\n" .
                            "1. Réduire le montant de la commande\n" .
                            "2. Demander un virement budgétaire vers cette ligne\n" .
                            "3. Utiliser une autre nomenclature budgétaire"
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
            $this->save();

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    /**
     * Créer ou mettre à jour le dossier fournisseur
     */
    public function creerOuMettreAJourDossier(): DossierFournisseur
    {
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

            // Ajouter automatiquement le BC comme pièce
            $dossier->ajouterPiece([
                'type_piece' => 'bon_commande',
                'document_type' => get_class($this),
                'document_id' => $this->id,
                'nom_fichier' => "BC-{$this->numero}.pdf",
                'chemin_fichier' => '', // Sera rempli lors de la génération PDF
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
        }

        return $dossier;
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
        return trim(strtolower($this->statut)) === 'brouillon';
    }

    /**
     * Obtenir le nombre de lignes
     */
    public function getNombreLignesAttribute(): int
    {
        // Si la relation est déjà chargée, utiliser la collection
        if ($this->relationLoaded('lignes')) {
            return $this->lignes->count();
        }

        // Sinon faire une requête
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

    // /**
    //  * Calcule le Net à Percevoir (HT - IR)
    //  */
    // public function getNetAPercevoirAttribute(): float
    // {
    //     return $this->montant_ht - $this->montant_ir;
    // }
    /**
     * Accesseur : Net à percevoir
     */
    public function getNetAPercevoirAttribute(): float
    {
        return $this->getMontantNetPercevoir();
    }

    /**
     * Formater le net à percevoir
     */
    public function getNetAPercevoirFormatteAttribute(): string
    {
        return number_format($this->net_a_percevoir, 0, ',', ' ') . ' FCFA';
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
