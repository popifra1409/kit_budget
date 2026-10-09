<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasExercice;

class VirementBudgetaire extends Model
{
    use HasFactory, LogsActivity, SoftDeletes, HasExercice;

    protected $table = 'virements_budgetaires';

    protected $fillable = [
        'exercice_id',
        'numero',
        'budget_id',
        'ligne_source_id',
        'ligne_destination_id',
        'montant',
        'date_virement',
        'motif',
        'reference_decision',
        'statut',
        'valide_par',
        'date_validation',

        // ✅ AJOUT — mouvement de crédits typé, cycle avant / pendant / après
        'type_mouvement',
        'origine',
        'sous_programme_source_id',
        'sous_programme_destination_id',
        'categorie_motif',
        'analyse_ecart',
        'impact_performance_verifie',
        'impact_commentaire',
        'decideur',
        'date_acte',
        'piece_acte',
        'controle_plafond',
        'integrer_collectif',

        // ✅ AJOUT — workflow : rejet, annulation
        'motif_rejet',
        'annule_par',
        'date_annulation',
        'motif_annulation',
    ];

    public const STATUTS = [
        'en_attente' => 'En attente',
        'approuve'   => 'Approuvé',
        'execute'    => 'Exécuté',
        'rejete'     => 'Rejeté',
        'annule'     => 'Annulé',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_virement' => 'date',
        'date_validation' => 'datetime',
        'date_acte' => 'date',
        'date_annulation' => 'datetime',
        'controle_plafond' => 'array',
        'impact_performance_verifie' => 'boolean',
        'integrer_collectif' => 'boolean',
    ];

    /**
     * Boot - Générer le numéro automatiquement
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($virement) {
            if (empty($virement->numero)) {
                $virement->numero = $virement->genererNumero();
            }
        });

        // ✅ AJOUT — type (fongibilité / virement) et sous-programmes déduits des lignes.
        //    Un transfert saisi explicitement n'est jamais requalifié.
        static::saving(function ($virement) {
            if ($virement->type_mouvement === 'transfert') {
                return;
            }

            if ($virement->isDirty(['ligne_source_id', 'ligne_destination_id']) || blank($virement->type_mouvement)) {
                $q = app(\App\Services\Budget\MouvementCreditService::class)
                    ->qualifier($virement->ligneSource()->first(), $virement->ligneDestination()->first());

                $virement->type_mouvement = $q['type'];
                $virement->sous_programme_source_id = $q['sp_source']?->id;
                $virement->sous_programme_destination_id = $q['sp_destination']?->id;
            }
        });
    }

    public function sousProgrammeSource(): BelongsTo
    {
        return $this->belongsTo(SousProgrammeEp::class, 'sous_programme_source_id');
    }

    public function sousProgrammeDestination(): BelongsTo
    {
        return $this->belongsTo(SousProgrammeEp::class, 'sous_programme_destination_id');
    }

    /**
     * Relation : Budget parent
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * Relation : Ligne source (qui perd du budget)
     */
    public function ligneSource(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class, 'ligne_source_id');
    }

    /**
     * Relation : Ligne destination (qui reçoit du budget)
     */
    public function ligneDestination(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class, 'ligne_destination_id');
    }

    /**
     * Relation : Validateur
     */
    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    /**
     * Scope : Par statut
     */
    public function scopeStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Scope : En attente
     */
    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    /**
     * Scope : Approuvés
     */
    public function scopeApprouves($query)
    {
        return $query->where('statut', 'approuve');
    }

    /**
     * Scope : Exécutés
     */
    public function scopeExecutes($query)
    {
        return $query->where('statut', 'execute');
    }

    /**
     * Générer le numéro de virement
     */
    public function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = self::where('numero', 'like', "VIR-{$annee}-%")
            ->orderBy('numero', 'desc')
            ->first();

        if ($dernier) {
            $dernierNumero = intval(substr($dernier->numero, -5));
            $nouveauNumero = $dernierNumero + 1;
        } else {
            $nouveauNumero = 1;
        }

        return sprintf('VIR-%d-%05d', $annee, $nouveauNumero);
    }

    /**
     * Approuver le virement
     */
    public function approuver(User $user): void
    {
        // ✅ AJOUT — acte formel et plafond des virements (paramètres d'exécution),
        //    contrôle figé sur le mouvement. Lève une DomainException si non conforme.
        $this->controle_plafond = app(\App\Services\Budget\MouvementCreditService::class)
            ->verifierAvantApprobation($this);

        $this->statut = 'approuve';
        $this->valide_par = $user->id;
        $this->date_validation = now();
        $this->save();

        \App\Models\ActivityLog::logAction($this, 'valider', [
            'ancien_statut'  => 'en_attente',
            'nouveau_statut' => 'approuve',
            'approuve_par'   => $user->name,
            'montant'        => $this->montant,
        ]);
    }

    /**
     * Exécuter le virement
     */
    public function executer(): void
    {
        if ($this->statut !== 'approuve') {
            throw new \Exception("Le virement doit d'abord être approuvé");
        }

        // Vérifier que la ligne source a assez de disponible
        $ligneSource = $this->ligneSource;
        if ($ligneSource->disponible_engagement < $this->montant) {
            throw new \Exception("Crédit insuffisant sur la ligne source. Disponible: {$ligneSource->disponible_engagement} FCFA");
        }

        // Mettre à jour la ligne source
        $ligneSource->virements_sortants += $this->montant;
        $ligneSource->save();

        // Mettre à jour la ligne destination
        $ligneDestination = $this->ligneDestination;
        $ligneDestination->virements_entrants += $this->montant;
        $ligneDestination->save();

        // Marquer le virement comme exécuté
        $this->statut = 'execute';
        $this->save();

        $this->repercuterSurLignes();
    }

    /**
     * budget_rectifie est une colonne stockée, recalculée par getBudgetRectifieReel()
     * à chaque sauvegarde de ligne — donc uniquement APRÈS la bascule de notre propre
     * statut. Sans ce recalcul final, la ligne garde le solde d'avant le mouvement
     * (crédits restitués comptés deux fois, ou jamais retirés).
     */
    private function repercuterSurLignes(): void
    {
        $this->ligneSource?->recalculerDepuisEngagements();
        $this->ligneDestination?->recalculerDepuisEngagements();

        \App\Models\Budget::find($this->budget_id)?->recalculerTotaux();

        // Le tableau de bord multi-exercices lit un instantané persisté des
        // statistiques : sans ce recalcul, le mouvement n'y apparaîtrait pas.
        \App\Models\Exercice::find($this->exercice_id)?->mettreAJourStatistiques();
    }

    // ════════════════════════════════════════════════════════
    // WORKFLOW (tout est réversible tant que le mouvement n'est pas exécuté)
    // ════════════════════════════════════════════════════════

    /** Mouvement piloté par un collectif budgétaire : aucune action manuelle. */
    public function estPiloteParCollectif(): bool
    {
        return $this->origine === 'collectif';
    }

    /** Seul l'état « en attente » d'un mouvement de gestion est modifiable. */
    public function estModifiableWorkflow(): bool
    {
        return $this->statut === 'en_attente' && !$this->estPiloteParCollectif();
    }

    protected function exigerStatuts(array $statuts, string $action): void
    {
        if ($this->estPiloteParCollectif()) {
            throw new \DomainException("Ce mouvement est issu d'un collectif budgétaire : il se gère depuis le collectif.");
        }

        if (!in_array($this->statut, $statuts, true)) {
            throw new \DomainException("Impossible de {$action} un mouvement « " . (self::STATUTS[$this->statut] ?? $this->statut) . ' ».');
        }
    }

    /** Rejeter (depuis en attente ou approuvé), avec motif. */
    public function rejeter(User $user, ?string $motif = null): void
    {
        $this->exigerStatuts(['en_attente', 'approuve'], 'rejeter');
        $ancien = $this->statut;

        $this->statut = 'rejete';
        $this->valide_par = $user->id;
        $this->date_validation = now();
        $this->motif_rejet = $motif;
        $this->controle_plafond = null;
        $this->save();

        \App\Models\ActivityLog::logAction($this, 'rejeter', [
            'ancien_statut' => $ancien,
            'nouveau_statut' => 'rejete',
            'rejete_par' => $user->name,
            'motif' => $motif,
        ]);
    }

    /**
     * Retour en attente : retrait d'une approbation, récupération d'un mouvement rejeté,
     * ou réactivation d'un mouvement annulé. L'approbation et le contrôle de plafond sont
     * effacés : ils seront refaits. L'historique (rejet, annulation) reste au journal d'audit.
     */
    public function remettreEnAttente(User $user, string $motif): void
    {
        $this->exigerStatuts(['approuve', 'rejete', 'annule'], 'remettre en attente');
        $ancien = $this->statut;

        $this->statut = 'en_attente';
        $this->valide_par = null;
        $this->date_validation = null;
        $this->controle_plafond = null;

        if ($ancien === 'annule') {
            $this->annule_par = null;
            $this->date_annulation = null;
            $this->motif_annulation = null;
        }

        $this->save();

        $evenement = match ($ancien) {
            'rejete' => 'recuperer',
            'annule' => 'reactiver',
            default  => 'retirer_approbation',
        };

        \App\Models\ActivityLog::logAction($this, $evenement, [
            'ancien_statut' => $ancien,
            'nouveau_statut' => 'en_attente',
            'par' => $user->name,
            'motif' => $motif,
        ]);
    }

    /** Annulation d'un mouvement non exécuté (conservé, réactivable par remettreEnAttente()). */
    public function annulerMouvement(User $user, string $motif): void
    {
        $this->exigerStatuts(['en_attente', 'approuve', 'rejete'], 'annuler');
        $ancien = $this->statut;

        $this->statut = 'annule';
        $this->annule_par = $user->id;
        $this->date_annulation = now();
        $this->motif_annulation = $motif;
        $this->controle_plafond = null;
        $this->save();

        \App\Models\ActivityLog::logAction($this, 'annuler', [
            'ancien_statut' => $ancien,
            'nouveau_statut' => 'annule',
            'par' => $user->name,
            'motif' => $motif,
        ]);
    }

    /**
     * Annulation de l'exécution (contre-passation) : les crédits reviennent sur la ligne source.
     * Refusée si la ligne destination a déjà consommé les crédits reçus.
     */
    public function annulerExecution(User $user, string $motif): void
    {
        $this->exigerStatuts(['execute'], "annuler l'exécution de");

        $destination = $this->ligneDestination;
        if ($destination && (float) $destination->disponible_engagement < (float) $this->montant - 0.01) {
            throw new \DomainException(
                "Annulation impossible : la ligne destination " . ($destination->nomenclature?->code ?? '') . " n'a plus que "
                    . number_format((float) $destination->disponible_engagement, 0, ',', ' ')
                    . " FCFA disponibles, les crédits reçus ont déjà été engagés."
            );
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $motif) {
            $this->annuler();   // remet les lignes et repasse « en attente »

            $this->valide_par = null;
            $this->date_validation = null;
            $this->controle_plafond = null;
            $this->save();

            \App\Models\ActivityLog::logAction($this, 'annuler_execution', [
                'nouveau_statut' => 'en_attente',
                'par' => $user->name,
                'motif' => $motif,
                'montant' => $this->montant,
            ]);
        });
    }

    /**
     * Annuler un virement exécuté
     */
    public function annuler(): void
    {
        if ($this->statut !== 'execute') {
            throw new \Exception("Seuls les virements exécutés peuvent être annulés");
        }

        // Annuler sur la ligne source
        $ligneSource = $this->ligneSource;
        $ligneSource->virements_sortants -= $this->montant;
        $ligneSource->save();

        // Annuler sur la ligne destination
        $ligneDestination = $this->ligneDestination;
        $ligneDestination->virements_entrants -= $this->montant;
        $ligneDestination->save();

        // Marquer comme en attente
        $this->statut = 'en_attente';
        $this->save();

        $this->repercuterSurLignes();
    }

    /**
     * Relation vers le mouvement collectif source
     */
    public function mouvementCollectif(): BelongsTo
    {
        return $this->belongsTo(\App\Models\MouvementCollectif::class, 'mouvement_collectif_id');
    }

    /**
     * ✅ Créer un virement depuis un mouvement de collectif budgétaire
     * Statut initial : en_attente (sera exécuté à l'adoption du collectif)
     */
    public static function creerDepuisCollectif(
        \App\Models\MouvementCollectif $mouvement,
        \App\Models\CollectifBudgetaire $collectif
    ): self {
        $ligneSource = \App\Models\LigneBudgetaire::find($mouvement->ligne_source_id);
        if (!$ligneSource) throw new \Exception('Ligne source introuvable.');

        return static::create([
            'exercice_id'          => $collectif->exercice_id,
            'budget_id'            => $ligneSource->budget_id,
            'ligne_source_id'      => $mouvement->ligne_source_id,
            'ligne_destination_id' => $mouvement->ligne_destination_id,
            'montant'              => $mouvement->montant_modification,
            'date_virement'        => now(),
            'motif'                => '[Collectif ' . $collectif->numero . '] ' . $mouvement->motif,
            'reference_decision'   => $collectif->numero,
            'statut'               => 'en_attente',
            'origine'              => 'collectif',   // ✅ hors plafond des virements de gestion
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'montant', 'valide_par', 'date_validation', 'motif'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('workflow')
            ->setDescriptionForEvent(fn(string $event) => 'Virement Budgétaire ' . ($this->numero ?? '') . ' — ' . $event);
    }
}
