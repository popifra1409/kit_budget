<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class SousProgrammeEp extends Model
{
    use SoftDeletes, HasWorkflow;

    protected $table = 'sous_programmes_ep';

    protected $fillable = [
        'numero',
        'plan_strategique_ep_id',
        'code',
        'libelle',
        'description',
        'responsable_personnel_id',
        // ✅ AJOUT — sans lui, le « Programme de rattachement » choisi dans le formulaire
        //    était ignoré à l'enregistrement (affectation de masse silencieusement filtrée)
        'programme_budgetaire_id',
        'code_programme_ep',
        'type',
        'statut',
        'created_by',
        'objectif',
        'strategie',
        'cadre_institutionnel',
    ];

    public const MAX_SOUS_PROGRAMMES = 4;
    public const MAX_SUPPORT = 1;

    /** Cycle du statut, du brouillon à la clôture. Sert aux messages de refus. */
    public const STATUTS = [
        'brouillon'       => 'Brouillon',
        'en_transmission' => 'En transmission',
        'valide'          => 'Validé',
        'en_vigueur'      => 'En vigueur',
        'cloture'         => 'Clôturé',
    ];

    public function statutLabel(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    /**
     * La validation se commande-t-elle sans faire statuer deux personnes à la fois ?
     *
     * Elle ne dépend plus d'une transmission : un brouillon se valide sans avoir jamais
     * circulé. Une transmission OUVERTE, en revanche, désigne celle ou celui qui statue —
     * tant qu'elle n'est pas close, la validation appartient à ce destinataire.
     */
    public function peutEtreValide(): bool
    {
        return ! in_array($this->statut, ['valide', 'en_vigueur', 'cloture'], true)
            && $this->transmissionBloquante() === null;
    }

    /**
     * La transmission en cours qui interdit à la session de statuer, `null` si elle est bien
     * adressée à elle — ou s'il n'y en a pas.
     *
     * `HasWorkflow::estEnCoursDeTransmissionPourAutrui()` ne rend qu'un booléen, et le refus
     * doit nommer le destinataire : la ligne elle-même est donc nécessaire ici. Vérification
     * faite, les deux méthodes répondent pareil hors session — le trait compare en SQL, où
     * Laravel compile `destinataire_id != null` en `… is not null`, ce qui laisse passer la
     * transmission au lieu de la masquer.
     */
    public function transmissionBloquante(): ?Transmission
    {
        $enCours = $this->transmissionEnCours();

        return $enCours !== null && (int) $enCours->destinataire_id !== (int) auth()->id()
            ? $enCours
            : null;
    }

    /**
     * La seule écriture de « valide » du sous-programme.
     *
     * Avant elle, valider exigeait d'être le destinataire d'une transmission : le chargé de
     * programmation devait transmettre à lui-même pour faire apparaître le bouton. Depuis,
     * la permission suffit ; la transmission reste le chemin tracé, elle n'est plus la
     * condition d'accès.
     *
     * @throws \RuntimeException quand la transition n'est pas légale
     */
    public function valider(?string $reponse = null): void
    {
        if (in_array($this->statut, ['valide', 'en_vigueur'], true)) {
            throw new \RuntimeException(
                "« {$this->code} » est déjà « {$this->statutLabel()} » : la mise en vigueur est l'étape suivante."
            );
        }

        if ($this->statut === 'cloture') {
            throw new \RuntimeException(
                "« {$this->code} » est clôturé : on ne valide pas un sous-programme clôturé."
            );
        }

        if ($bloquante = $this->transmissionBloquante()) {
            throw new \RuntimeException(
                'Le sous-programme est en cours de transmission chez '
                    . ($bloquante->destinataire?->name ?? 'un destinataire indéfini')
                    . " ({$bloquante->getActionLabel()}) : seul ce destinataire peut statuer."
                    . ' Rappelez-la, ou faites-la clôturer, avant de valider.'
            );
        }

        // Une transmission adressée à la session se clôt sur la décision, pour qu'aucune
        // corbeille « en attente » ne survive au document qu'elle a fait statuer.
        if ($this->transmissionEnCours() !== null) {
            $this->cloturerTransmission($reponse ?? 'Validé');
        }

        $this->update(['statut' => 'valide']);
    }

    /**
     * Dernière marche du cycle : venir après la validation, et une
     * transmission en cours ferait statuer deux personnes à la fois.
     */
    public function peutEtreMisEnVigueur(): bool
    {
        return $this->statut === 'valide' && !$this->estEnCoursDeTransmission();
    }

    /**
     * Le seul écriture de « en_vigueur » du logiciel. Avant elle, ce badge
     * ne sortait que des seeders : un état que personne ne pouvait atteindre.
     *
     * @throws \RuntimeException quand la transition n'est pas légale
     */
    public function mettreEnVigueur(): void
    {
        if ($this->statut !== 'valide') {
            throw new \RuntimeException(
                "Seul un sous-programme validé peut être mis en vigueur — statut actuel : « {$this->statutLabel()} »."
                    . ($this->statut === 'brouillon' ? " Validez-le d'abord (action « Valider »), la transmission n'est pas requise." : '')
            );
        }

        if ($this->estEnCoursDeTransmission()) {
            throw new \RuntimeException(
                "Le sous-programme est en cours de transmission chez "
                    . ($this->getDestinataireActuel()?->name ?? 'un destinataire indéfini')
                    . ' : attendez sa réponse.'
            );
        }

        $this->update(['statut' => 'en_vigueur']);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by ??= auth()->id();
            $model->numero ??= static::genererNumero();

            static::validerLimiteSousProgrammes($model);
        });

        static::saving(function (self $model) {
            // 1. Rattachement ministériel : uniquement un programme de niveau 'programme'
            //    ✅ CORRIGÉ — sans le filtre d'exercice (sinon contrôle ignoré hors exercice actif)
            if ($model->programme_budgetaire_id) {
                $programme = Programme::withoutGlobalScope('exercice')->find($model->programme_budgetaire_id);
                if ($programme && $programme->niveau !== 'programme') {
                    throw new \InvalidArgumentException(
                        "Le programme de rattachement doit être un programme de niveau 'programme' "
                            . "(ex : P-410, 412), pas « {$programme->code} » de niveau '{$programme->niveau}'."
                    );
                }
            }

            // 2. ✅ AJOUT — Programme EP : le code doit exister dans la classification budgétaire
            if (
                filled($model->code_programme_ep)
                && !Programme::withoutGlobalScope('exercice')->where('code', $model->code_programme_ep)->exists()
            ) {
                throw new \InvalidArgumentException(
                    "Le programme « {$model->code_programme_ep} » n'existe pas dans la classification budgétaire."
                );
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('type')) {
                static::validerLimiteSousProgrammes($model);
            }
        });
    }

    public static function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = static::withTrashed()->whereYear('created_at', $annee)->count();

        return sprintf('SPE-%d-%05d', $annee, $dernier + 1);
    }

    public function planStrategiqueEp(): BelongsTo
    {
        return $this->belongsTo(PlanStrategiqueEp::class, 'plan_strategique_ep_id');
    }

    /**
     * L'agent responsable de la mise en œuvre du sous-programme.
     *
     * Il ne sert pas qu'à l'impression : c'est sur lui que se cloisonnent la revue des
     * libellés et la matrice d'arrimage, via la fiche agent reliée au compte de session
     * (`User::personnel()`). Un agent sans compte reste désignable — son nom s'imprime —,
     * mais il ne peut pas ouvrir ces deux écrans sur ses seuls sous-programmes tant que sa
     * fiche n'est reliée à aucun login.
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'responsable_personnel_id')->withTrashed();
    }

    /**
     * Relation conservée pour l'onglet « Actions » et le chargement groupé.
     * ⚠️ Elle ne suit que le code du programme EP, SANS descendre dans les subdivisions.
     *    Pour les rapports, utiliser actionsPourExercice() (règle générique complète).
     */
    public function actions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Action::class,
            Programme::class,
            'code',               // colonne de Programme comparee au sous-programme
            'programme_id',       // cle d'Action vers Programme
            'code_programme_ep',  // colonne du sous-programme
            'id'                  // cle de Programme
        );
    }

    public function indicateurs(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Indicateur::class, 'indicateurable');
    }

    /** Objectif affiché dans le cadre logique (Tableau 1 et matrices). */
    public function objectifPourCadreLogique(): ?string
    {
        return $this->objectif;
    }

    /**
     * Programme MINISTERIEL de rattachement (ex: P-410 Prevention de la maladie).
     * Sans scope d'exercice : le rattachement reste lisible quel que soit l'exercice actif.
     */
    public function programmeBudgetaire(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_budgetaire_id')
            ->withoutGlobalScope('exercice');
    }

    /** Alias explicite, plus lisible dans les nouvelles vues. */
    public function programmeRattachement(): BelongsTo
    {
        return $this->programmeBudgetaire();
    }

    /**
     * Programme budgetaire de l'EP (ex: SP-1) pour un exercice donne.
     */
    public function programmeEp(?int $exerciceId = null): ?Programme
    {
        if (blank($this->code_programme_ep)) {
            return null;
        }

        return Programme::withoutGlobalScope('exercice')
            ->where('code', $this->code_programme_ep)
            ->where('exercice_id', $exerciceId ?? Exercice::getActif()?->id)
            ->first();
    }

    // ════════════════════════════════════════════════════════
    // ARBORESCENCE BUDGÉTAIRE — RÈGLE GÉNÉRIQUE
    // Utilisée par : tableau des libellés, matrice d'arrimage, Tableaux 14/15, RAP, PPA
    // ════════════════════════════════════════════════════════

    /**
     * Programmes budgétaires qui portent les actions de ce sous-programme pour un exercice.
     *
     * GÉNÉRIQUE — fonctionne quelle que soit l'organisation de la base :
     *  1. point de départ : le programme EP (code_programme_ep) s'il est renseigné,
     *     SINON le programme de rattachement ;
     *  2. + toutes ses subdivisions (sous-programmes de gestion interne, via parent_id),
     *     sur plusieurs niveaux.
     *  → base où le programme porte directement les actions (412) : 412 seul ;
     *  → base où des sous-programmes de gestion les portent (P-410 → SP-1) : P-410 + SP-1.
     */
    public function programmesPourExercice(?int $exerciceId = null): \Illuminate\Support\Collection
    {
        $exerciceId ??= Exercice::getActif()?->id;
        $code = $this->code_programme_ep ?: $this->programmeBudgetaire?->code;

        if (blank($code) || !$exerciceId) {
            return collect();
        }

        $ids = Programme::withoutGlobalScope('exercice')
            ->where('code', $code)
            ->where('exercice_id', $exerciceId)
            ->pluck('id');

        if ($ids->isNotEmpty() && static::programmesOntUneHierarchie()) {
            $niveau = $ids;

            // Descente dans les subdivisions (5 niveaux maximum : protection contre une boucle)
            for ($i = 0; $i < 5 && $niveau->isNotEmpty(); $i++) {
                $niveau = Programme::withoutGlobalScope('exercice')
                    ->whereIn('parent_id', $niveau)
                    ->pluck('id')
                    ->diff($ids);

                $ids = $ids->merge($niveau);
            }
        }

        return $ids->unique()->values();
    }

    /** La table des programmes a-t-elle une hiérarchie (parent_id) ? Vérifié une fois par requête. */
    protected static function programmesOntUneHierarchie(): bool
    {
        static $hierarchie = null;

        return $hierarchie ??= \Illuminate\Support\Facades\Schema::hasColumn((new Programme)->getTable(), 'parent_id');
    }

    /**
     * Actions du sous-programme pour un exercice.
     * Les programmes étant propres à l'exercice, leurs actions le sont aussi.
     */
    public function actionsPourExercice(?int $exerciceId = null): Builder
    {
        return Action::withoutGlobalScope('exercice')
            ->whereIn('programme_id', $this->programmesPourExercice($exerciceId))
            ->orderBy('code');
    }

    /** Activités du sous-programme : exercice demandé, ou exercice non renseigné. */
    public function activitesPourExercice(?int $exerciceId = null): Builder
    {
        $exerciceId ??= Exercice::getActif()?->id;

        return Activite::withoutGlobalScope('exercice')
            ->whereIn('action_id', $this->actionsPourExercice($exerciceId)->select('id'))
            ->where(fn($q) => $q->where('exercice_id', $exerciceId)->orWhereNull('exercice_id'))
            ->orderBy('code');
    }

    /**
     * Instruction du 22 janvier 2026 : un EP ne peut avoir plus de 4
     * sous-programmes (3 operationnels + 1 support maximum).
     */
    protected static function validerLimiteSousProgrammes(self $model): void
    {
        $query = static::where('plan_strategique_ep_id', $model->plan_strategique_ep_id)
            ->when($model->exists, fn($q) => $q->where('id', '!=', $model->id));

        $totalExistant = $query->count();

        if ($totalExistant >= self::MAX_SOUS_PROGRAMMES) {
            throw new \Exception(
                "Ce Plan Stratégique compte déjà " . self::MAX_SOUS_PROGRAMMES
                    . " sous-programmes. L'Instruction du 22 janvier 2026 limite ce nombre à "
                    . self::MAX_SOUS_PROGRAMMES . " maximum (3 opérationnels + 1 support)."
            );
        }

        if (($model->type ?? 'operationnel') === 'support') {
            $nbSupport = (clone $query)->where('type', 'support')->count();
            if ($nbSupport >= self::MAX_SUPPORT) {
                throw new \Exception(
                    "Ce Plan Stratégique a déjà un sous-programme de type 'support'. "
                        . "Un seul sous-programme support est autorisé par établissement."
                );
            }
        }
    }
}
