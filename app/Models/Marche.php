<?php

namespace App\Models;

use App\Traits\GereTransmissions;
use App\Traits\HasExercice;
use App\Traits\HasWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Marché public — cœur du module /marches (étape B : table + cycle de vie).
 *
 * Le cycle est une machine à états simple, dont les transitions sont dans
 * config/marches.php : un marché avance par les méthodes publiees ici (valider,
 * retirerValidation, publierDao, ouvrirPlis, attribuer, leverAttribution, homologuer,
 * leverHomologation, demarrerExecution, emettreOrdreService, annuler), jamais par une
 * écriture directe de statut. `termine` et `resilie` restent déclarés dans le
 * vocabulaire sans méthode correspondante : l'exécution elle-même, ses réceptions et
 * ses décomptes appartiennent à l'étape F.
 *
 * L'attribution ne s'arrête plus au provisoire : l'homologation (étape E) est la
 * décision qui la suit, et elle ne se confond pas avec elle. Elle n'a ni offre à
 * retenir ni montant à figer — elle approuve ce que l'attribution a écrit.
 *
 * Le circuit de validation hiérarchique vient de HasWorkflow + GereTransmissions,
 * les mêmes traits que BonCommande : un marché transmis est verrouillé chez
 * l'émetteur et n'est modifiable que par son destinataire.
 */
class Marche extends Model
{
    use HasExercice, HasWorkflow, GereTransmissions, SoftDeletes;

    protected $table = 'marches';

    protected $fillable = [
        'numero',
        'titre',
        'objet',
        'exercice_id',
        'expression_besoin_id',
        'service_demandeur_id',
        'responsable_id',
        'categorie_id',
        'type_procedure_id',
        'type_contrat_id',
        'classement_manuel',
        'classement_justificatif',
        'classement_par_id',
        'classement_le',
        'nomenclature_id',
        'montant_estime',
        'duree_execution_mois',
        'date_publication_dao',
        'date_limite_depot',
        'date_ouverture_plis',
        'date_validation',
        'date_annulation',
        'statut',
        'valide_par_id',
        'annule_par_id',
        'motif_annulation',
        'commission_id',
        'offre_attribuee_id',
        'montant_attribue',
        'attribue_par_id',
        'date_attribution',
        'attribution_manuelle',
        'attribution_justificatif',
        'homologue_par_id',
        'date_homologation',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'montant_estime'       => 'decimal:2',
        'montant_attribue'     => 'decimal:2',
        'duree_execution_mois' => 'integer',
        'classement_manuel'    => 'boolean',
        'attribution_manuelle' => 'boolean',
        'date_publication_dao' => 'date',
        'date_limite_depot'    => 'date',
        'date_ouverture_plis'  => 'date',
        'date_validation'      => 'datetime',
        'date_attribution'     => 'datetime',
        'date_homologation'    => 'datetime',
        'date_annulation'      => 'datetime',
        'classement_le'        => 'datetime',
    ];

    // ====================================
    // VOCABULAIRE DU CYCLE
    // ====================================

    public static function statuts(): array
    {
        return config('marches.statuts_marche');
    }

    public static function statutLibelle(?string $statut): string
    {
        return config('marches.statuts_marche')[$statut] ?? (string) $statut;
    }

    public static function transitions(): array
    {
        return config('marches.transitions_marche');
    }

    public function statutSuivants(): array
    {
        return static::transitions()[$this->statut] ?? [];
    }

    public function peutAtteindre(string $statutCible): bool
    {
        return in_array($statutCible, $this->statutSuivants(), true);
    }

    /**
     * Refuse une transition interdite plutôt que de la laisser écrire un statut
     * que la machine ne prévoit pas.
     */
    protected function verifierTransition(string $statutCible): void
    {
        if (!$this->peutAtteindre($statutCible)) {
            throw new \Exception(sprintf(
                'Transition interdite : %s → %s. Un marché « %s » ne peut pas devenir « %s ».',
                static::statutLibelle($this->statut),
                static::statutLibelle($statutCible),
                static::statutLibelle($this->statut),
                static::statutLibelle($statutCible),
            ));
        }
    }

    /**
     * Ce qui manque au marché pour quitter le brouillon. Le formulaire filtre déjà,
     * cette méthode rend le message lisible au lieu d'un refus de la contrainte CHECK.
     */
    public function elementsManquants(): array
    {
        $manquants = [];
        if (!$this->categorie_id)        { $manquants[] = 'la catégorie (pluriannuel, annuel, mensuel)'; }
        if (!$this->type_procedure_id)   { $manquants[] = 'le type de procédure'; }
        if (!$this->type_contrat_id)     { $manquants[] = 'le type de contrat (marché ou lettre de commande)'; }
        if (!$this->responsable_id)      { $manquants[] = 'le responsable du marché'; }
        if (!$this->exercice_id)         { $manquants[] = "l'exercice budgétaire"; }
        if ((float) $this->montant_estime <= 0) { $manquants[] = 'un montant estimatif supérieur à zéro'; }

        return $manquants;
    }

    public function estComplet(): bool
    {
        return $this->elementsManquants() === [];
    }

    protected function verifierComplete(): void
    {
        if (!$this->estComplet()) {
            throw new \Exception(
                'Le marché ne peut pas quitter le brouillon sans : '
                    . implode(', ', $this->elementsManquants()) . '.'
            );
        }
    }

    // ====================================
    // RÈGLE DE SEUIL
    // ====================================

    /**
     * Le classement que la règle de seuil impose pour le montant estimatif.
     */
    public function typeContratCalcule(): ?TypeContrat
    {
        return TypeContrat::pourMontant((float) $this->montant_estime);
    }

    /**
     * Le classement enregistré contredit-il le seuil ? Vrai même pour un classement
     * manuel : c'est ce qui fait apparaître l'avertissement à l'écran tant que le
     * choix contraire n'est pas justifié et enregistré.
     */
    public function classementContredit(): bool
    {
        $calcule = $this->typeContratCalcule();

        return $calcule !== null && (int) $calcule->id !== (int) $this->type_contrat_id;
    }

    // ====================================
    // RELATIONS
    // ====================================

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieMarche::class, 'categorie_id');
    }

    public function typeProcedure(): BelongsTo
    {
        return $this->belongsTo(TypeProcedure::class, 'type_procedure_id');
    }

    public function typeContrat(): BelongsTo
    {
        return $this->belongsTo(TypeContrat::class, 'type_contrat_id');
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class, 'commission_id');
    }

    public function expressionBesoin(): BelongsTo
    {
        return $this->belongsTo(ExpressionBesoin::class, 'expression_besoin_id');
    }

    public function serviceDemandeur(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_demandeur_id');
    }

    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function valideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par_id');
    }

    public function annuleur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annule_par_id');
    }

    public function classementPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classement_par_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Les offres déposées sur ce marché (étape C). */
    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class);
    }

    /**
     * L'offre retenue à l'attribution (étape D). L'attributaire n'a pas de colonne :
     * il est le soumissionnaire de cette offre, et une seconde colonne « attributaire »
     * serait une seconde main sur le même fait, capable de la contredire.
     */
    public function offreAttribuee(): BelongsTo
    {
        return $this->belongsTo(Offre::class, 'offre_attribuee_id');
    }

    public function attribueur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attribue_par_id');
    }

    /** Qui a homologué l'attribution (étape E). */
    public function homologueur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homologue_par_id');
    }

    /**
     * Les ordres de service émis sur ce marché (étape E), dans l'ordre où ils ont
     * été notifiés : l'arrêt et la reprise se lisent comme une chronologie, pas
     * comme une liste. Les lignes retirées du dossier en sont absentes — un retrait
     * est un acte du service, pas une pièce de la chronologie.
     */
    public function ordresService(): HasMany
    {
        return $this->hasMany(OrdreService::class)->orderBy('date_emission')->orderBy('id');
    }

    /**
     * Les fiches de présence dressées pour ce marché (étape E), dans l'ordre des
     * séances. Une ligne par séance tenue : le marché peut en compter plusieurs
     * entre l'ouverture des plis et l'attribution, et chacune a son papier signé.
     */
    public function fichesPresence(): HasMany
    {
        return $this->hasMany(FichePresence::class)->orderBy('date_seance')->orderBy('id');
    }

    // ====================================
    // SCOPES
    // ====================================

    public function scopeParStatut(Builder $query, string $statut): Builder
    {
        return $query->where('statut', $statut);
    }

    /** Tout ce qui n'est pas clos : le marché reste vivant pour le suivi. */
    public function scopeEnCours(Builder $query): Builder
    {
        return $query->whereNotIn('statut', ['termine', 'resilie', 'annule']);
    }

    // ====================================
    // PRÉDICATS D'ÉCRAN
    // ====================================

    /**
     * Rédigeable : brouillon, et pas entre les mains d'un destinataire
     * (HasWorkflow définit la même méthode sans notion de statut).
     */
    public function estModifiable(): bool
    {
        return $this->statut === 'brouillon' && !$this->estEnCoursDeTransmission();
    }

    public function peutEtreValide(): bool
    {
        return $this->statut === 'brouillon' && $this->estComplet();
    }

    /**
     * Même règle que MarcheResource::canEdit(), côté modèle : l'action « Modifier »
     * du circuit partagé (WorkflowActions) appelle cette méthode pour s'afficher,
     * elle doit donc dire exactement ce que la page d'édition autoriserait. Un
     * bouton visible qui mène à un 403 serait un mensonge à l'écran.
     */
    public function peutEtreModifiePar(?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return false;
        }

        return $user->can('force_update_marche')
            || ($user->can('update_marche') && $this->statut === 'brouillon');
    }

    public function peutEtrePublie(): bool
    {
        return $this->statut === 'valide';
    }

    public function peutEtreEvalue(): bool
    {
        return $this->statut === 'publie';
    }

    /**
     * Fenêtre de dépôt des offres : de l'avis de lancement jusqu'à l'ouverture des
     * plis, et pendant l'évaluation elle-même — le registre des offres se remplit
     * au fur et à mesure que les plis arrivent (scan 17.29). Passé l'attribution,
     * une offre ne s'enregistre plus : la procédure est jugée.
     */
    public function peutRecevoirDesOffres(): bool
    {
        return in_array($this->statut, ['publie', 'en_evaluation'], true);
    }

    /**
     * Fenêtre de notation (étape D) : ouverte à l'ouverture des plis, fermée à
     * l'attribution. Avant l'ouverture, rien n'est jugé ; après la décision, la
     * discussion porte sur l'attribution — qui se lève — et non sur une note.
     */
    public function peutNoterDesOffres(): bool
    {
        return $this->statut === 'en_evaluation';
    }

    /**
     * Attribuable au sens de la machine et du travail réellement fait : des offres
     * recevables, toutes notées jusqu'au bout. Le bouton, lui, se montre sur le seul
     * statut — c'est à l'écran que le service voit ce qui manque, pas à un bouton
     * qui a disparu.
     */
    public function peutEtreAttribue(): bool
    {
        $recevables = $this->classementOffres();

        return $this->statut === 'en_evaluation'
            && $recevables->isNotEmpty()
            && $recevables->every(fn (Offre $o) => $o->estEvaluee());
    }

    public function estAttribue(): bool
    {
        return $this->statut === 'attribue';
    }

    /**
     * Fenêtre d'émission des ordres de service (étape E) : de l'homologation
     * jusqu'à la clôture. Avant l'homologation, personne n'est titulaire — un ordre
     * de service commandé à un attributaire provisoire ne vaut pas. Passé « terminé
     * », « résilié » ou « annulé », le marché n'a plus de service à commander.
     */
    public function peutRecevoirDesOrdres(): bool
    {
        return in_array($this->statut, ['homologue', 'en_execution'], true);
    }

    /** Homologable : la machine n'admet ce pas qu'à partir de l'attribution provisoire. */
    public function peutEtreHomologue(): bool
    {
        return $this->statut === 'attribue';
    }

    /**
     * Fenêtre de la fiche de présence (étape E) : de l'ouverture des plis jusqu'à
     * l'exécution en cours. Avant l'ouverture, la commission n'a rien eu à examiner —
     * sa séance daterait d'avant la matière qu'elle juge. Passé la clôture, la fiche
     * a servi : elle reste au dossier telle qu'elle a été signée.
     *
     * La règle est la même que celle de `FichePresence::STATUTS_SEANCE`, écrite là-bas
     * et reprise ici : c'est le modèle de la fiche qui garde la fenêtre, le marché ne
     * fait que la montrer à l'écran avant de refuser l'action.
     */
    public function peutRecevoirUneFichePresence(): bool
    {
        return in_array($this->statut, FichePresence::STATUTS_SEANCE, true);
    }

    /**
     * Le marché a-t-il reçu son ordre de service de démarrage ? C'est la condition
     * de `demarrerExecution()` : le service n'est pas commandé par un clic, il est
     * commandé par un acte écrit et versé au dossier.
     *
     * La requête est directe et ne passe pas par `ordresService()` : une ligne
     * retirée du dossier n'ouvre plus l'exécution, mais elle n'a pas à être triée
     * pour le savoir.
     */
    public function aUnOrdreDeDemarrage(): bool
    {
        return OrdreService::query()
            ->where('marche_id', $this->id)
            ->whereHas('type', fn (Builder $q) => $q->where('demarre_lexecution', true))
            ->exists();
    }

    // ====================================
    // CLASSEMENT DE L'ÉVALUATION (étape D)
    // ====================================

    /**
     * Les offres recevables, dans l'ordre où les notes les placent : la mieux notée
     * d'abord, puis — à note égale, ce qui arrive — la moins disante, puis la
     * référence, pour que deux lectures du même dossier donnent le même classement.
     *
     * Les offres non recevables en sont absentes par définition : elles ont été
     * écartées avant l'examen du fond, leur note ne se compare pas.
     */
    public function classementOffres(): Collection
    {
        return $this->offres()
            ->where('statut', 'validee')
            ->with(['fournisseur', 'evaluations'])
            ->get()
            ->sort(fn (Offre $a, Offre $b) => [
                -$a->totalEvalue(),                     // note décroissante
                (float) $a->montant_offert,             // puis montant croissant
                $a->numero,                             // puis référence
            ] <=> [
                -$b->totalEvalue(),
                (float) $b->montant_offert,
                $b->numero,
            ])
            ->values();
    }

    /** La tête du classement, celle que l'attribution suit par défaut. */
    public function offreMieuxNotee(): ?Offre
    {
        return $this->classementOffres()->first();
    }

    /**
     * Le classement tel que le service le lit avant de décider : une ligne par offre
     * recevable, avec sa note sur le barème. Les offres incomplètes y figurent —
     * c'est l'écran qui doit dire laquelle attend encore ses notes.
     */
    public function offresNonEvaluees(): array
    {
        return $this->classementOffres()
            ->reject(fn (Offre $o) => $o->estEvaluee())
            ->map(fn (Offre $o) => sprintf(
                '%s — %s : il manque %s',
                $o->numero,
                $o->fournisseur?->raison_sociale ?? 'soumissionnaire inconnu',
                implode(', ', $o->etapesManquantes()),
            ))
            ->values()
            ->all();
    }

    /**
     * L'offre retenue contredit-elle les notes ? Vrai même après l'attribution :
     * c'est ce qui fait apparaître le rappel à l'écran tant que le justificatif
     * enregistré n'a pas été repris — la même asymétrie que classementContredit().
     */
    public function attributionContredite(): bool
    {
        $premier = $this->offreMieuxNotee();

        return $this->offre_attribuee_id !== null
            && $premier !== null
            && (int) $premier->id !== (int) $this->offre_attribuee_id;
    }

    public function estClo(): bool
    {
        return in_array($this->statut, ['termine', 'resilie', 'annule'], true);
    }

    // ====================================
    // TRANSITIONS DU CYCLE
    // ====================================

    /**
     * Brouillon → validé. Le marché est alors figé côté saisie : ses
     * caractéristiques ne se corrigent plus qu'avec force_update_marche.
     */
    public function valider(User $user): void
    {
        $this->verifierPasEnTransmission('valider');
        $this->verifierTransition('valide');
        $this->verifierComplete();

        $ancien = $this->statut;
        $this->statut         = 'valide';
        $this->valide_par_id  = $user->id;
        $this->date_validation = now();
        $this->save();

        ActivityLog::logAction($this, 'valider', [
            'ancien_statut'  => $ancien,
            'nouveau_statut' => 'valide',
            'valide_par'     => $user->name,
        ]);
    }

    /**
     * Validé → brouillon : le service des marchés défait sa propre validation tant
     * que rien n'a été publié. Passé la publication, la seule correction passe par
     * force_update_marche. Le retrait est journalisé comme la validation : l'audit
     * doit voir qui a validé, et qui a remis en cause cette validation.
     *
     * L'événement enregistré est « devalider », déjà traduit et coloré dans le
     * journal d'activité — un retrait de validation n'est pas un nouvel événement à
     * inventer, c'est le symétrique de la validation.
     */
    public function retirerValidation(User $user): void
    {
        $this->verifierPasEnTransmission('retrait_validation');
        $this->verifierTransition('brouillon');

        if ($this->date_publication_dao) {
            throw new \Exception(sprintf(
                "Le DAO du marché %s est publié depuis le %s : la validation ne se reprend pas, "
                    . 'une procédure lancée se solde par une annulation motivée ou un sans suite.',
                $this->numero,
                $this->date_publication_dao->format('d/m/Y'),
            ));
        }

        $ancien = $this->statut;
        $this->statut          = 'brouillon';
        $this->valide_par_id   = null;
        $this->date_validation = null;
        $this->save();

        ActivityLog::logAction($this, 'devalider', [
            'ancien_statut'  => $ancien,
            'nouveau_statut' => 'brouillon',
            'par'            => $user->name,
        ]);
    }

    /**
     * Validé → publié : l'avis de lancement part, la date limite de dépôt des
     * offres est fixée à ce moment-là (scan 17.29 : la réception des offres a une
     * échéance, sinon l'ouverture des plis n'a pas de sens).
     */
    public function publierDao(User $user, ?string $dateLimiteDepot = null): void
    {
        $this->verifierPasEnTransmission('publier');
        $this->verifierTransition('publie');

        if ($dateLimiteDepot) {
            $this->date_limite_depot = $dateLimiteDepot;
        }
        if (!$this->date_limite_depot) {
            throw new \Exception("La publication exige une date limite de dépôt des offres.");
        }
        if ($this->date_limite_depot->isPast()) {
            throw new \Exception("La date limite de dépôt est déjà passée : corrigez-la avant de publier.");
        }

        $ancien = $this->statut;
        $this->statut = 'publie';
        if (!$this->date_publication_dao) {
            $this->date_publication_dao = now()->toDateString();
        }
        $this->save();

        ActivityLog::logAction($this, 'publier_dao', [
            'ancien_statut'  => $ancien,
            'nouveau_statut' => 'publie',
            'date_limite_depot' => $this->date_limite_depot?->format('d/m/Y'),
            'par'            => $user->name,
        ]);
    }

    /**
     * Publié → en évaluation : ouverture des plis. Elle n'est possible qu'après la
     * date limite de dépôt, sinon des offres restent à recevoir.
     */
    public function ouvrirPlis(User $user): void
    {
        $this->verifierPasEnTransmission('ouvrir_plis');
        $this->verifierTransition('en_evaluation');

        if (!$this->date_limite_depot) {
            throw new \Exception("Aucune date limite de dépôt : le DAO n'est pas publiable en l'état.");
        }
        if ($this->date_limite_depot->isFuture()) {
            throw new \Exception(sprintf(
                'Les plis ne s\'ouvrent qu\'à l\'échéance : date limite de dépôt le %s.',
                $this->date_limite_depot->format('d/m/Y'),
            ));
        }

        $ancien = $this->statut;
        $this->statut             = 'en_evaluation';
        $this->date_ouverture_plis = now()->toDateString();
        $this->save();

        ActivityLog::logAction($this, 'ouvrir_plis', [
            'ancien_statut'  => $ancien,
            'nouveau_statut' => 'en_evaluation',
            'par'            => $user->name,
        ]);
    }

    /**
     * En évaluation → attribué : l'attribution provisoire (étape D). Elle retient une
     * offre recevable parmi les offres notées, fige le montant au jour de la décision
     * et rend la note de l'écran.
     *
     * Six refus, dans cet ordre : la machine à états, l'offre qui n'appartient pas au
     * marché, l'offre non recevable, le montant absent, le classement inachevé, et le
     * choix contre le classement sans justificatif. Le dernier est celui qui protège
     * le rapport : une attribution qui contredit ses propres notes sans les motiver
     * ne se relit plus devant un recours.
     */
    public function attribuer(User $user, Offre $offre, ?string $justificatif = null): void
    {
        $this->verifierPasEnTransmission('attribuer');
        $this->verifierTransition('attribue');

        if ((int) $offre->marche_id !== (int) $this->id) {
            throw new \Exception(sprintf(
                "L'offre %s est déposée sur un autre marché que %s : un DAO ne s'attribue pas "
                    . 'au soumissionnaire d\'à côté.',
                $offre->numero,
                $this->numero,
            ));
        }

        if ($offre->statut !== 'validee') {
            throw new \Exception(sprintf(
                "L'offre %s est « %s » : seule une offre déclarée recevable peut emporter le marché.",
                $offre->numero,
                Offre::statutLibelle($offre->statut),
            ));
        }

        if ((float) $offre->montant_offert <= 0) {
            throw new \Exception(sprintf(
                "L'offre %s n'a pas de montant. L'attribution fige le montant retenu : "
                    . 'elle ne peut pas le poser à zéro.',
                $offre->numero,
            ));
        }

        $incomplete = $this->offresNonEvaluees();
        if ($incomplete !== []) {
            throw new \Exception(
                'Le classement doit être complet avant toute attribution — offres recevables encore '
                    . 'non notées : ' . implode(' ; ', $incomplete) . '.'
            );
        }

        $mieuxNotee     = $this->offreMieuxNotee();
        $contreClassement = $mieuxNotee !== null && (int) $mieuxNotee->id !== (int) $offre->id;

        if ($contreClassement && blank($justificatif)) {
            throw new \Exception(sprintf(
                'Retenir %s alors que %s est mieux notée (%s/%s contre %s/%s) se justifie : '
                    . 'sans motif enregistré, le rapport contredit ses propres notes.',
                $offre->numero,
                $mieuxNotee->numero,
                (float) $offre->totalEvalue(),
                (float) $offre->baremeTotal(),
                (float) $mieuxNotee->totalEvalue(),
                (float) $mieuxNotee->baremeTotal(),
            ));
        }

        $ancien = $this->statut;
        $this->statut                   = 'attribue';
        $this->offre_attribuee_id       = $offre->id;
        $this->montant_attribue         = (float) $offre->montant_offert;
        $this->attribue_par_id          = $user->id;
        $this->date_attribution         = now();
        $this->attribution_manuelle     = $contreClassement;
        $this->attribution_justificatif = $contreClassement ? trim((string) $justificatif) : null;
        $this->save();

        ActivityLog::logAction($this, 'attribuer', [
            'ancien_statut'     => $ancien,
            'nouveau_statut'    => 'attribue',
            'offre'             => $offre->numero,
            'attributaire'      => $offre->fournisseur?->raison_sociale,
            'montant_attribue'  => (float) $offre->montant_offert,
            'note_obtenue'      => (float) $offre->totalEvalue(),
            'bareme'            => (float) $offre->baremeTotal(),
            'rang'              => $this->classementOffres()->search(fn (Offre $o) => (int) $o->id === (int) $offre->id) + 1,
            'hors_classement'   => $contreClassement,
            'justificatif'      => $this->attribution_justificatif,
            'par'               => $user->name,
        ]);
    }

    /**
     * Attribué → en évaluation : le service défait sa propre attribution tant que
     * rien n'est homologué (étape E). Les six colonnes se vident — un dossier ne doit
     * pas porter deux attributions, pas plus qu'il ne portait deux décisions
     * contradictoires à l'étape C.
     */
    public function leverAttribution(User $user): void
    {
        $this->verifierPasEnTransmission('lever_attribution');

        // Avant la machine à états : un marché qui ne retient rien n'a pas d'action
        // à défaire, et la transition répondrait « Plis ouverts → Plis ouverts »,
        // qui n'apprend rien. Rien n'est masqué — la contrainte CHECK de la migration
        // 000009 interdit un statut « attribué » sans offre retenue, donc ce refus ne
        // coupe jamais le chemin d'un véritable attribué.
        if (!$this->offre_attribuee_id) {
            throw new \Exception(sprintf(
                'Aucune attribution à lever sur le marché %s : rien n\'a été retenu dans ce dossier.',
                $this->numero,
            ));
        }

        $this->verifierTransition('en_evaluation');

        $offre          = $this->offreAttribuee;
        $montant        = (float) $this->montant_attribue;
        $horsClassement = (bool) $this->attribution_manuelle;
        $justificatif   = $this->attribution_justificatif;

        $this->statut                    = 'en_evaluation';
        $this->offre_attribuee_id        = null;
        $this->montant_attribue          = null;
        $this->attribue_par_id           = null;
        $this->date_attribution          = null;
        $this->attribution_manuelle      = false;
        $this->attribution_justificatif  = null;
        $this->save();

        ActivityLog::logAction($this, 'lever_attribution', [
            'ancien_statut'   => 'attribue',
            'nouveau_statut'  => 'en_evaluation',
            'offre_levee'     => $offre?->numero,
            'attributaire'    => $offre?->fournisseur?->raison_sociale,
            'montant'         => $montant,
            'hors_classement' => $horsClassement,
            'justificatif'    => $justificatif,
            'par'             => $user->name,
        ]);
    }

    /**
     * Attribué → homologué (étape E). L'homologation approuve l'attribution
     * provisoire : elle n'ajoute rien au dossier, elle le rend définitif. D'où deux
     * colonnes seulement — la main et l'heure — et aucun montant recopié.
     */
    public function homologuer(User $user): void
    {
        $this->verifierPasEnTransmission('homologuer');
        $this->verifierTransition('homologue');

        $offre = $this->offreAttribuee;

        $ancien            = $this->statut;
        $this->statut           = 'homologue';
        $this->homologue_par_id = $user->id;
        $this->date_homologation = now();
        $this->save();

        ActivityLog::logAction($this, 'homologuer', [
            'ancien_statut'  => $ancien,
            'nouveau_statut' => 'homologue',
            'attributaire'   => $offre?->fournisseur?->raison_sociale,
            'offre'          => $offre?->numero,
            'montant_attribue' => (float) $this->montant_attribue,
            'par'            => $user->name,
        ]);
    }

    /**
     * Homologué → attribué : la main qui a homologué défait sa décision, tant
     * qu'aucun ordre de service n'a été émis. Passé l'émission, le service est
     * commandé chez le titulaire : ce n'est plus une annotation du dossier que l'on
     * efface, c'est une exécution engagée.
     */
    public function leverHomologation(User $user): void
    {
        $this->verifierPasEnTransmission('lever_homologation');

        // Comme pour la levée de l'attribution : la machine répondrait « Attribué →
        // Attribué » sur un marché qui n'a jamais été homologué. La trace, pas le
        // statut, fait la condition — un marché revenu en deçà par le circuit de
        // transmission a déjà eu ses colonnes vidées.
        if (!$this->homologue_par_id && !$this->date_homologation) {
            throw new \Exception(sprintf(
                'Aucune homologation à lever sur le marché %s : rien n\'a été homologué dans ce dossier.',
                $this->numero,
            ));
        }

        $emis = $this->ordresService()->count();
        if ($emis > 0) {
            throw new \Exception(sprintf(
                'Le marché %s a reçu %s ordre(s) de service : le service est commandé, l\'homologation '
                    . 'ne se lève plus. Ce qui se discute alors, ce sont les ordres de service eux-mêmes.',
                $this->numero,
                $emis,
            ));
        }

        $this->verifierTransition('attribue');

        $homologue = $this->homologueur;

        $ancien             = $this->statut;
        $this->statut            = 'attribue';
        $this->homologue_par_id  = null;
        $this->date_homologation = null;
        $this->save();

        ActivityLog::logAction($this, 'lever_homologation', [
            'ancien_statut'   => $ancien,
            'nouveau_statut'  => 'attribue',
            'homologation_levee' => $homologue?->name,
            'par'             => $user->name,
        ]);
    }

    /**
     * Homologué → en cours d'exécution : le service est commandé. Cette étape n'est
     * pas un clic, c'est la conséquence d'un acte — l'ordre de service de démarrage
     * (étape E). Sans lui, le marché avancerait sans que personne ait rien notifié
     * au titulaire, et le délai d'exécution n'aurait plus de point de départ.
     */
    public function demarrerExecution(User $user): void
    {
        $this->verifierPasEnTransmission('demarrer_execution');

        if ($this->statut === 'en_execution') {
            throw new \Exception(sprintf(
                'Le marché %s est déjà en cours d\'exécution : le service a déjà été commandé.',
                $this->numero,
            ));
        }

        $this->verifierTransition('en_execution');

        if (!$this->aUnOrdreDeDemarrage()) {
            throw new \Exception(sprintf(
                'Aucun ordre de service de démarrage au dossier du marché %s : le service ne se met '
                    . 'pas en exécution par une case du cycle, il se commande par écrit.',
                $this->numero,
            ));
        }

        $demarrage = OrdreService::query()
            ->where('marche_id', $this->id)
            ->whereHas('type', fn (Builder $q) => $q->where('demarre_lexecution', true))
            ->orderBy('date_emission')
            ->orderBy('id')
            ->first();

        $ancien = $this->statut;
        $this->statut = 'en_execution';
        $this->save();

        ActivityLog::logAction($this, 'demarrer_execution', [
            'ancien_statut'  => $ancien,
            'nouveau_statut' => 'en_execution',
            'ordre_service'  => $demarrage?->numero,
            'date_emission'  => $demarrage?->date_emission?->format('d/m/Y'),
            'par'            => $user->name,
        ]);
    }

    /**
     * Émettre un ordre de service (étape E) — la seule façon d'entrer une ligne dans
     * `ordres_service` depuis l'écran.
     *
     * Deux écritures, dans un seul transaction : la ligne, puis — si le type le
     * commande et que le marché est encore homologué — le marché qui passe en
     * exécution. L'ordre de service s'écrit d'abord parce que c'est la condition du
     * mouvement du marché (`demarrerExecution()` l'exige au dossier), et parce qu'en
     * cas d'échec la ligne se rollback avec le reste : un marché avancé sans acte
     * écrit au dossier serait le seul état que rien ne rattrape.
     *
     * Les fenêtres (statut du marché, type actif, démarrage unique, date) ne sont pas
     * re-vérifiées ici : elles le sont dans le hook `creating` d'OrdreService, qui
     * les impose aussi bien aux écritures directes qu'à cet écran.
     *
     * La date n'est pas suppléée non plus. Elle pourrait l'être — le guichet émet le
     * jour même — mais une date inventée par le code est une date qui ne se discute
     * plus : c'est elle qui ouvre le délai d'exécution, et son absence doit se lire
     * comme un refus, pas comme un aujourd'hui ajouté par la machine.
     */
    public function emettreOrdreService(User $user, TypeOrdreService $type, array $champs): OrdreService
    {
        $this->verifierPasEnTransmission('emettre_ordre_service');

        $os = DB::transaction(function () use ($user, $type, $champs) {
            $os = OrdreService::create([
                'marche_id'             => $this->id,
                'type_ordre_service_id' => $type->id,
                'date_emission'         => $champs['date_emission'] ?? null,
                'objet'                 => $champs['objet'] ?? null,
                'fichier_os'            => $champs['fichier_os'] ?? null,
                'observations'          => $champs['observations'] ?? null,
                'emis_par_id'           => $user->id,
            ]);

            if ($os->demarreLexecution() && $this->statut === 'homologue') {
                $this->demarrerExecution($user);
            }

            return $os;
        });

        ActivityLog::logAction($os, 'emettre_ordre_service', [
            'marche'         => $this->numero,
            'type'           => $type->libelle,
            'demarrage'      => $os->demarreLexecution(),
            'date_emission'  => $os->date_emission?->format('d/m/Y'),
            'objet'          => $os->objet,
            'par'            => $user->name,
        ]);

        return $os;
    }

    /**
     * Dresser la fiche de présence de la séance : écrire la constatation, avec ses
     * membres recopiés du référentiel.
     *
     * L'acte ne décide rien du cycle — ni attribution, ni homologation ne regardent
     * cette table. Il rend visible ce que la commission était venue faire, et il
     * ouvre les cases d'émargement et de visa que la fiche, une fois imprimée, porte
     * sur le papier.
     *
     * Les lignes viennent de `commission->membres`, dans l'ordre du paramétrage :
     * c'est le service qui dit qui siège, le dressage ne fait que le recopier. Une
     * commission sans membre, ou sans président, n'est pas opérante (c'est déjà ce
     * que dit `Commission::president()`), et la fiche le refuse : une page blanche
     * datée n'atteste personne.
     *
     * Le jour de la séance se lit ici, il n'est pas suppléé. `date_ouverture_plis`
     * pourrait servir de valeur par défaut à l'écran — c'est ce que fait le champ du
     * formulaire — mais l'absenter ici laisserait le code dater une séance que
     * personne n'a déclarée.
     */
    public function dresserFichePresence(User $user, array $champs): FichePresence
    {
        $this->verifierPasEnTransmission('dresser_fiche_presence');

        $commission = $this->commission;
        if (!$commission) {
            throw new \Exception(sprintf(
                'Le marché %s n\'a pas de commission épinglée : la fiche de présence se dresse sur celui '
                    . 'qui siège. Choisissez la commission sur la fiche du marché.',
                    $this->numero,
            ));
        }

        $membres = $commission->membres()->get();
        if ($membres->isEmpty()) {
            throw new \Exception(sprintf(
                'La commission « %s » n\'a aucun membre au paramétrage : la fiche serait dressée vide. '
                    . 'Complétez sa composition, puis recommencez.',
                $commission->libelle,
            ));
        }
        if (!$membres->contains('titre', 'president')) {
            throw new \Exception(sprintf(
                'La commission « %s » n\'a pas de président au paramétrage : elle n\'est pas opérante, et '
                    . 'sa fiche de présence n\'a donc personne à faire viser en tête.',
                $commission->libelle,
            ));
        }

        $fiche = DB::transaction(function () use ($user, $champs, $commission, $membres) {
            $fiche = FichePresence::create([
                'marche_id'     => $this->id,
                'commission_id' => $commission->id,
                'date_seance'   => $champs['date_seance'] ?? null,
                'lieu'          => $champs['lieu'] ?? null,
                'objet'         => $champs['objet'] ?? null,
                'observations'  => $champs['observations'] ?? null,
                'etablie_par_id' => $user->id,
            ]);

            // Recopie, pas lien vivant : la fiche doit se relire dans un an avec les
            // mêmes noms et les mêmes qualités qu'au jour de la séance.
            foreach ($membres as $membre) {
                $fiche->membres()->create([
                    'commission_membre_id' => $membre->id,
                    'user_id'              => $membre->user_id,
                    'nom'                  => $membre->nom,
                    'titre'                => $membre->titre,
                    'telephone'            => $membre->telephone,
                    'ordre'                => $membre->ordre,
                ]);
            }

            return $fiche;
        });

        ActivityLog::logAction($fiche, 'dresser_fiche_presence', [
            'marche'      => $this->numero,
            'commission'  => $commission->libelle,
            'date_seance' => $fiche->date_seance?->format('d/m/Y'),
            'membres'     => $fiche->membres()->count(),
            'objet'       => $fiche->objet,
            'par'         => $user->name,
        ]);

        return $fiche;
    }

    /**
     * Annulation motivée, à toute étape du cycle encore vivant.
     */
    public function annuler(?string $motif = null): void
    {
        $this->verifierPasEnTransmission('annuler');

        if ($this->estClo()) {
            throw new \Exception('Ce marché est déjà clos (' . static::statutLibelle($this->statut) . ').');
        }
        $this->verifierTransition('annule');

        if (!$motif) {
            throw new \Exception("L'annulation d'un marché exige un motif.");
        }

        $ancien = $this->statut;
        $this->statut           = 'annule';
        $this->motif_annulation = $motif;
        $this->annule_par_id    = auth()->id();
        $this->date_annulation  = now();
        $this->save();

        ActivityLog::logAction($this, 'annuler', [
            'ancien_statut'  => $ancien,
            'nouveau_statut' => 'annule',
            'motif'          => $motif,
            'par'            => auth()->user()?->name,
        ]);
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function booted(): void
    {
        static::creating(function (Marche $marche) {
            $marche->numero    ??= static::genererNumero();
            $marche->statut    ??= 'brouillon';
            $marche->created_by ??= auth()->id();
            static::classerSelonSeuil($marche);
        });

        /*
         * Le seuil est la règle : tant que personne n'a revendiqué un classement
         * manuel, le type de contrat suit le montant. Le Select du formulaire est
         * coupé tant que classement_manuel est faux, donc rien de saisi par
         * l'utilisateur n'est écrasé ici — c'est la seule écriture de la colonne.
         *
         * La règle vaut à tous les statuts, sinon une correction de montant sur un
         * marché déjà publié laisserait un contrat qui ne correspond plus à son
         * seuil, sans justification nulle part : « pas manuel » doit toujours dire
         * « c'est le seuil qui a classé ».
         */
        static::updating(function (Marche $marche) {
            if (!$marche->classement_manuel) {
                static::classerSelonSeuil($marche);
            }
        });

        /*
         * Les trois colonnes de la contradiction appartiennent au classement manuel :
         * elles ont un auteur et une date dès qu'il est revendiqué, et elles se
         * vident dès qu'il ne l'est plus, quel que soit le chemin d'écriture (fiche,
         * semence, reprise en direct). C'est ce que l'auditeur lit en premier.
         */
        static::saving(function (Marche $marche) {
            if ($marche->classement_manuel) {
                if (!$marche->classement_par_id) {
                    $marche->classement_par_id = auth()->id();
                    $marche->classement_le     = now();
                }
                return;
            }

            $marche->classement_justificatif = null;
            $marche->classement_par_id       = null;
            $marche->classement_le           = null;
        });

        /*
         * L'attribution n'existe qu'avec la décision : sans offre retenue, ou dès que
         * le marché est revenu en deçà (brouillon, en évaluation), les six colonnes
         * sont vides. leverAttribution() les écrit nulles lui-même ; ce garde-fu
         * couvre les autres chemins, en premier lieu le retour pour correction du
         * circuit de transmission, qui laisserait sinon un marché « brouillon » avec
         * un attributaire, sa date et son montant.
         *
         * Aucune de ces colonnes ne se saisit à l'écran : rien de ce qu'un humain a
         * tapé ne peut disparaître ici.
         */
        static::saving(function (Marche $marche) {
            if ($marche->offre_attribuee_id
                && !in_array($marche->statut, ['brouillon', 'en_evaluation'], true)) {
                return;
            }

            $marche->offre_attribuee_id      = null;
            $marche->montant_attribue        = null;
            $marche->attribue_par_id         = null;
            $marche->date_attribution        = null;
            $marche->attribution_manuelle    = false;
            $marche->attribution_justificatif = null;
        });

        /*
         * L'homologation n'existe qu'avec un marché qui l'a reçue : à partir de
         * `homologue` et pour toute la suite de l'exécution, la trace reste au
         * dossier ; en deçà, elle se vide. `leverHomologation()` écrit ses nullités
         * lui-même ; ce garde-fu couvre les autres chemins, en premier lieu le retour
         * pour correction du circuit de transmission, qui laisserait sinon un marché
         * « brouillon » avec son homologation, sa date et son auteur.
         *
         * Le marché annulé, terminé ou résilié garde la sienne : ce sont des fins de
         * procédure, pas des retours en arrière — la décision a été prise, elle se
         * lit encore.
         *
         * Aucune de ces colonnes ne se saisit à l'écran : rien de ce qu'un humain a
         * tapé ne peut disparaître ici.
         */
        static::saving(function (Marche $marche) {
            if ($marche->date_homologation
                && !in_array($marche->statut, ['brouillon', 'valide', 'publie', 'en_evaluation', 'attribue'], true)) {
                return;
            }

            $marche->homologue_par_id  = null;
            $marche->date_homologation = null;
        });

        /*
         * La bascule entre « classé par le seuil » et « classé à la main » est un
         * événement d'audit, pas une correction de colonne : elle a sa ligne dans le
         * journal, dans les deux sens.
         */
        static::saved(function (Marche $marche) {
            $bascule = $marche->wasRecentlyCreated
                ? (bool) $marche->classement_manuel
                : $marche->wasChanged('classement_manuel');

            if (!$bascule) {
                return;
            }

            ActivityLog::logAction($marche, $marche->classement_manuel ? 'classer_manuellement' : 'revenir_au_seuil', [
                'type_contrat'      => $marche->typeContrat?->libelle,
                'montant_estime'    => (float) $marche->montant_estime,
                'justificatif'      => $marche->classement_justificatif
                    ?: $marche->getPrevious('classement_justificatif'),
                'par'               => $marche->classement_par_id
                    ? (User::find($marche->classement_par_id)?->name ?? '#'. $marche->classement_par_id)
                    : auth()->user()?->name,
            ]);
        });
    }

    protected static function classerSelonSeuil(Marche $marche): void
    {
        if ($marche->classement_manuel) {
            return;
        }
        $calcule = $marche->typeContratCalcule();
        if ($calcule) {
            $marche->type_contrat_id = $calcule->id;
        }
    }

    public static function genererNumero(): string
    {
        return DB::transaction(function () {
            $annee   = now()->year;
            $prefixe = "MR-{$annee}-";
            $dernier = static::withTrashed()
                ->where('numero', 'like', "{$prefixe}%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();
            $seq = $dernier ? intval(substr($dernier->numero, -4)) + 1 : 1;

            return $prefixe . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        });
    }
}
