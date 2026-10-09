<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LigneBudgetaire extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lignes_budgetaires';

    protected $fillable = [
        'budget_id',
        'nomenclature_id',
        'budget_initial',
        'virements_entrants',
        'virements_sortants',
        'budget_rectifie',
        'engage',
        'ordonne',
        'liquide',
        'paye',
        'disponible_engagement',
        'disponible_ordonnancement',
        'observations',
        'montant_initial',
        'est_issue_collectif',
        'collectif_creation_id',
    ];

    protected $casts = [
        'budget_initial'            => 'decimal:2',
        'virements_entrants'        => 'decimal:2',
        'virements_sortants'        => 'decimal:2',
        'budget_rectifie'           => 'decimal:2',
        'engage'                    => 'decimal:2',
        'ordonne'                   => 'decimal:2',
        'liquide'                   => 'decimal:2',
        'paye'                      => 'decimal:2',
        'disponible_engagement'     => 'decimal:2',
        'disponible_ordonnancement' => 'decimal:2',
    ];

    protected $appends = [
        'total_engage',
        'nombre_engagements',
        'taux_consommation',
    ];

    // =========================================================
    // BOOT
    // =========================================================
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($ligne) {
            $ligne->calculerMontants();
        });
    }

    public function getLibelleAttribute()
    {
        return $this->nomenclature?->libelle ?? 'N/A';
    }
    
    // =========================================================
    // RELATIONS
    // =========================================================

    /**
     * Engagements via la table lignes_engagement (source de vérité)
     * Filtre les annulés et soft-deleted
     */

    public function collectifCreation(): BelongsTo
    {
        return $this->belongsTo(CollectifBudgetaire::class, 'collectif_creation_id');
    }

    // Accesseur pour l'écart
    public function getEcartAttribute(): float
    {
        return $this->budget_rectifie - $this->montant_initial;
    }

    public function engagements()
    {
        $engagementIds = \DB::table('lignes_engagement')
            ->where('nomenclature_id', $this->nomenclature_id)
            ->pluck('engagement_id')
            ->unique();

        return \App\Models\Engagement::query()
            ->whereIn('id', $engagementIds)
            ->where('budget_id', $this->budget_id)   // ✅ AJOUT : uniquement le budget de cette ligne (pas les autres exercices)
            ->whereNull('date_annulation')           // ✅ AJOUT : annulation reelle
            ->whereNotIn('statut', ['annule'])
            ->whereNull('deleted_at');
    }

    /**
     * ✅ CORRIGÉ — tous les engagements y compris soft-deleted
     *    pour les calculs historiques
     */
    public function engagementsAvecSupprimes()
    {
        $engagementIds = \DB::table('lignes_engagement')
            ->where('nomenclature_id', $this->nomenclature_id)
            ->pluck('engagement_id')
            ->unique();

        return \App\Models\Engagement::withTrashed()
            ->whereIn('id', $engagementIds);
    }

    public function lignesEngagement(): HasMany
    {
        return $this->hasMany(\App\Models\LigneEngagement::class, 'nomenclature_id', 'nomenclature_id');
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class);
    }

    /**
     * Resout le Programme (et Sous-Programme de gestion interne, si applicable)
     * de rattachement de cette ligne, en remontant la chaine :
     * Nomenclature -> Tache (sous_tache) -> Activite -> Action -> Programme.
     *
     * 'programme' = niveau national (codes 412/413/414...)
     * 'sous_programme' = subdivision de gestion interne (niveau='sous_programme'),
     *                     null si le Programme rattache est deja de niveau 'programme'.
     */
    public function getClassificationStrategique(): array
    {
        if (!$this->nomenclature_id) {
            return ['programme' => null, 'sous_programme' => null];
        }

        $tache = \App\Models\Tache::where('nomenclature_id', $this->nomenclature_id)
            ->where('niveau', 'sous_tache')
            ->where('exercice_id', $this->budget->exercice_id)
            ->with('activite.action.programme.parent')
            ->first();

        $programmeRattache = $tache?->activite?->action?->programme;

        if (!$programmeRattache) {
            return ['programme' => null, 'sous_programme' => null];
        }

        if ($programmeRattache->niveau === 'sous_programme') {
            return [
                'programme' => $programmeRattache->parent,
                'sous_programme' => $programmeRattache,
            ];
        }

        return [
            'programme' => $programmeRattache,
            'sous_programme' => null,
        ];
    }

    public function virementsSource(): HasMany
    {
        return $this->hasMany(VirementBudgetaire::class, 'ligne_source_id');
    }

    public function virementsDestination(): HasMany
    {
        return $this->hasMany(VirementBudgetaire::class, 'ligne_destination_id');
    }

    public function getLibelleWithDisponibleAttribute()
    {
        $nom = $this->nomenclature;
        $libelle = $nom ? "{$nom->code} - {$nom->libelle}" : 'N/A';
        $dispo = number_format($this->disponible_engagement, 0, ',', ' ');
        return "{$libelle} (Dispo: {$dispo} FCFA)";
    }

    // =========================================================
    // RECALCUL DEPUIS LES DONNÉES RÉELLES
    // =========================================================

    /**
     * ✅ SOURCE UNIQUE — montants de lignes_engagement des engagements ACTIFS imputes
     * sur la nomenclature de cette ligne, pour le MEME budget (donc le meme exercice).
     *
     * Utilisee par recalculerDepuisEngagements(), engageEntre(), total_engage :
     * ces calculs ne peuvent plus diverger.
     *
     * Avant : le filtre portait uniquement sur la nomenclature. Une meme nomenclature
     * presente dans deux budgets (ex. apres reconduction d'exercice) cumulait les
     * engagements des deux budgets.
     */
    public function requeteEngagementsActifs(): \Illuminate\Database\Query\Builder
    {
        return \DB::table('lignes_engagement as le')
            ->join('engagements as e', 'le.engagement_id', '=', 'e.id')
            ->where('le.nomenclature_id', $this->nomenclature_id)
            ->where('e.budget_id', $this->budget_id)
            ->whereNull('e.date_annulation')
            ->whereNotIn('e.statut', ['annule'])
            ->whereNull('e.deleted_at');
    }

    /**
     * Engage sur une periode (dates d'engagement incluses).
     * Utilise par le Suivi-Evaluation (Annexe 9 — rapport d'activite periodique).
     */
    public function engageEntre(\Carbon\CarbonInterface $debut, \Carbon\CarbonInterface $fin): float
    {
        return (float) $this->requeteEngagementsActifs()
            ->whereDate('e.date_engagement', '>=', $debut->toDateString())
            ->whereDate('e.date_engagement', '<=', $fin->toDateString())
            ->sum('le.montant');
    }

    /**
     * ✅ Recalcule et PERSISTE les colonnes stockées depuis les engagements réels
     *    À appeler après toute suppression ou création d'engagement
     *
     * Source : lignes_engagement → engagements actifs du MEME budget
     *          (non annulés, non supprimés)
     *
     * ✅ CORRIGÉ — le budget rectifié est calculé par getBudgetRectifieReel()
     *    (initial + virements exécutés + collectifs adoptés), la même formule que
     *    celle appliquée à chaque sauvegarde. L'ancienne version utilisait
     *    « initial + virements » et effaçait l'effet des collectifs budgétaires.
     */
    public function recalculerDepuisEngagements(): void
    {
        $totalEngage = (float) $this->requeteEngagementsActifs()->sum('le.montant');

        $budgetRectifie = $this->getBudgetRectifieReel();

        $this->updateQuietly([
            'engage'                    => $totalEngage,
            'budget_rectifie'           => $budgetRectifie,
            'disponible_engagement'     => $budgetRectifie - $totalEngage,
            'disponible_ordonnancement' => $totalEngage - (float) $this->ordonne,
        ]);

        \Illuminate\Support\Facades\Log::info("LigneBudgetaire recalculée", [
            'ligne_id'       => $this->id,
            'nomenclature'   => $this->nomenclature_id,
            'total_engage'   => $totalEngage,
            'disponible'     => $budgetRectifie - $totalEngage,
        ]);
    }

    /**
     * ✅ Données complètes pour la Fiche de Contrôle PDF
     *    Retourne le tableau $engagements attendu par le template Blade
     */
    public function getDonneesEngagementsPdf(): array
    {
        $this->loadMissing(['budget.exercice', 'nomenclature']);

        $engagementsActifs = $this->engagements()
            ->with(['ordonnancesPaiement', 'engageable'])
            ->orderBy('date_engagement')
            ->get();

        $budgetRectifie = (float) $this->budget_rectifie
            ?: ((float) $this->budget_initial
                + (float) $this->virements_entrants
                - (float) $this->virements_sortants);

        $disponibleCumulatif = $budgetRectifie;
        $totalEngage         = 0;
        $totalOP             = 0;
        $totalOPT            = 0;

        $lignesEngagement = [];

        foreach ($engagementsActifs as $eng) {
            $montantEngage = (float) $eng->montant_engage;

            // Calcul cumulatif du disponible après cet engagement
            $disponibleCumulatif -= $montantEngage;
            $totalEngage         += $montantEngage;

            // ✅ OP Standard liée à cet engagement (non annulée)
            $montantOP = (float) $eng->ordonnancesPaiement
                ->where('type_ordonnance', 'standard')
                ->whereNotIn('statut', ['annulee'])
                ->sum('montant_net');

            // ✅ OPT Impôt liée à cet engagement (non annulée)
            $montantOPT = (float) $eng->ordonnancesPaiement
                ->where('type_ordonnance', 'impot')
                ->whereNotIn('statut', ['annulee'])
                ->sum('montant_net');

            $totalOP  += $montantOP;
            $totalOPT += $montantOPT;

            // N° OP Standard
            $numOP = $eng->ordonnancesPaiement
                ->where('type_ordonnance', 'standard')
                ->whereNotIn('statut', ['annulee'])
                ->first()?->numero ?? '-';

            $lignesEngagement[] = [
                'numero_engagement' => $eng->numero,
                'beneficiaire'      => $eng->getNomBeneficiaire() ?? '-',
                'objet'             => $eng->objet,
                'reference'         => $eng->reference_document ?? $eng->engageable?->numero ?? '-',
                'date_engagement'   => $eng->date_engagement?->format('d/m/Y') ?? '-',
                'montant_engage'    => $montantEngage,
                'disponible_apres'  => $disponibleCumulatif,   // ← cumulatif correct
                'numero_op'         => $numOP,
                'montant_op'        => $montantOP,              // ← depuis OrdonnancePaiement
                'montant_opt'       => $montantOPT,             // ← depuis OrdonnancePaiement
                'observations'      => $eng->observations ?? '',
                'statut'            => $eng->statut,
            ];
        }

        $tauxConsommation = $budgetRectifie > 0
            ? ($totalEngage / $budgetRectifie) * 100
            : 0;

        return [
            'engagements'        => $lignesEngagement,
            'budget_rectifie'    => $budgetRectifie,
            'total_engage'       => $totalEngage,
            'disponible'         => $budgetRectifie - $totalEngage,
            'taux_consommation'  => $tauxConsommation,
            'total_op'           => $totalOP,
            'total_opt'          => $totalOPT,
        ];
    }

    // =========================================================
    // CALCULS
    // =========================================================

    /**
     * ✅ SOURCE DE VÉRITÉ UNIQUE pour le budget rectifié — utilisée à la fois
     * ici (à chaque sauvegarde du modèle) et par FicheControleEngagementsResource
     * (Aperçu, PDF, Recalculer). Avant cette harmonisation, ces deux endroits
     * utilisaient des formules différentes (l'une ignorait les collectifs,
     * l'autre ignorait les virements), ce qui pouvait écraser silencieusement
     * l'effet d'un virement dès qu'on générait la fiche de contrôle.
     *
     * budget_rectifie = budget_initial
     *                 + Σ virements EXÉCUTÉS (entrants - sortants)
     *                 + Σ mouvements de collectifs ADOPTÉS de type "dépense"
     *                   portant sur une ligne EXISTANTE (modification directe)
     *                   — jamais les virements, déjà comptés ci-dessus pour
     *                   éviter le double comptage, qu'ils soient manuels ou
     *                   issus d'un collectif : un virement de collectif devient
     *                   un VirementBudgetaire à statut 'execute' comme un autre.
     *
     * Une ligne créée par un collectif (nouvelle_ligne_depense_id) a pour
     * budget_initial le montant du mouvement qui l'a créée : ce mouvement n'est
     * donc PAS ajouté une seconde fois. Symétrique de la côté recette — cf.
     * LignePrevisionRecette::getMontantRectifieReel().
     */
    public function getBudgetRectifieReel(): float
    {
        $base = (float) $this->budget_initial;

        if (!$this->id) {
            // Ligne pas encore enregistrée — aucun virement/collectif possible
            return $base;
        }

        $virementsEntrants = \App\Models\VirementBudgetaire::where('ligne_destination_id', $this->id)
            ->where('statut', 'execute')
            ->sum('montant');

        $virementsSortants = \App\Models\VirementBudgetaire::where('ligne_source_id', $this->id)
            ->where('statut', 'execute')
            ->sum('montant');

        $mouvements = \App\Models\MouvementCollectif::where('ligne_depense_id', $this->id)
            ->where('type', 'depense')
            // Un mouvement annulé ne doit plus peser sur la ligne. Sans ce filtre,
            // « Annuler ce mouvement » était sans effet sur budget_rectifie côté
            // dépense (le côté recette, lui, filtrait déjà — cf.
            // LignePrevisionRecette::getMontantRectifieReel).
            ->where(fn($q) => $q->whereNull('statut')->orWhereNotIn('statut', ['annule', 'annulee']))
            ->whereNull('date_annulation')
            ->whereHas('collectif', fn($q) => $q->where('statut', 'adopte'))
            ->sum('montant_modification');

        return $base + (float) $virementsEntrants - (float) $virementsSortants + (float) $mouvements;
    }

    public function calculerMontants(): void
    {
        $this->budget_rectifie = $this->getBudgetRectifieReel();

        $this->disponible_engagement     = (float) $this->budget_rectifie - (float) $this->engage;
        $this->disponible_ordonnancement = (float) $this->engage - (float) $this->ordonne;
    }

    public function peutEngager(float $montant): bool
    {
        return (float) $this->disponible_engagement >= $montant;
    }

    public function peutOrdonner(float $montant): bool
    {
        return (float) $this->disponible_ordonnancement >= $montant;
    }

    public function enregistrerEngagement(float $montant): void
    {
        if (!$this->peutEngager($montant)) {
            throw new \Exception("Crédit insuffisant. Disponible: {$this->disponible_engagement} FCFA");
        }
        $this->engage += $montant;
        $this->save();
    }

    public function enregistrerOrdonnancement(float $montant): void
    {
        if (!$this->peutOrdonner($montant)) {
            throw new \Exception("Crédit insuffisant pour ordonnancement. Disponible: {$this->disponible_ordonnancement} FCFA");
        }
        $this->ordonne += $montant;
        $this->save();
    }

    public function enregistrerLiquidation(float $montant): void
    {
        $this->liquide += $montant;
        $this->save();
    }

    public function enregistrerPaiement(float $montant): void
    {
        $this->paye += $montant;
        $this->save();
    }

    public function annulerEngagement(float $montant): void
    {
        if ($this->engage <= 0) {
            \Illuminate\Support\Facades\Log::warning("annulerEngagement — engage déjà à 0", [
                'ligne_id' => $this->id,
                'montant'  => $montant,
            ]);
            return;
        }
        $this->engage = max(0, (float) $this->engage - $montant);
        $this->save();
    }

    public function getTauxEngagement(): float
    {
        if ($this->budget_rectifie == 0) return 0;
        return ((float) $this->engage / (float) $this->budget_rectifie) * 100;
    }

    public function getTauxExecution(): float
    {
        if ($this->budget_rectifie == 0) return 0;
        return ((float) $this->liquide / (float) $this->budget_rectifie) * 100;
    }

    // =========================================================
    // ACCESSEURS ($appends)
    // =========================================================

    /**
     * ✅ CORRIGÉ — calcul dynamique via la requête unique (même budget uniquement)
     */
    public function getTotalEngageAttribute(): float
    {
        return (float) $this->requeteEngagementsActifs()->sum('le.montant');
    }

    /**
     * ✅ CORRIGÉ — calcul dynamique via la requête unique (même budget uniquement)
     */
    public function getNombreEngagementsAttribute(): int
    {
        return (int) $this->requeteEngagementsActifs()
            ->distinct()
            ->count('le.engagement_id');
    }

    /**
     * ✅ CORRIGÉ — calcul dynamique
     */
    public function getTauxConsommationAttribute(): float
    {
        $budgetRectifie = (float) $this->budget_rectifie
            ?: ((float) $this->budget_initial
                + (float) $this->virements_entrants
                - (float) $this->virements_sortants);

        if ($budgetRectifie <= 0) return 0;

        return ($this->getTotalEngageAttribute() / $budgetRectifie) * 100;
    }

    public function getEngagementsCollectionAttribute()
    {
        return $this->engagements()->get();
    }

    public function getTotalEngageViaLignesAttribute(): float
    {
        return (float) \DB::table('lignes_engagement')
            ->where('nomenclature_id', $this->nomenclature_id)
            ->sum('montant');
    }

    public function bonCommandes()
    {
        $possibleColumns = ['ligne_budgetaire_id', 'lignebudgetaire_id', 'ligne_budget_id', 'budget_ligne_id'];
        $tableColumns    = \Schema::getColumnListing('bon_commandes');
        foreach ($possibleColumns as $col) {
            if (in_array($col, $tableColumns)) return $this->hasMany(\App\Models\BonCommande::class, $col);
        }
        return $this->hasMany(\App\Models\BonCommande::class, 'ligne_budgetaire_id');
    }

    public function decisionsAdministratives()
    {
        $possibleColumns = ['ligne_budgetaire_id', 'lignebudgetaire_id', 'ligne_budget_id', 'budget_ligne_id'];
        $tableColumns    = \Schema::getColumnListing('decisions_administratives');
        foreach ($possibleColumns as $col) {
            if (in_array($col, $tableColumns)) return $this->hasMany(\App\Models\DecisionAdministrative::class, $col);
        }
        return $this->hasMany(\App\Models\DecisionAdministrative::class, 'ligne_budgetaire_id');
    }
}
