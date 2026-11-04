<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Offre reçue — étape C du module /marches (scan du 27/10 17.29).
 *
 * Une offre n'est pas un document du circuit de transmission : elle ne part pas
 * chez un destinataire, elle est enregistrée au service des marchés puis décidée
 * là. D'où le cycle court — reçue, puis recevable ou rejetée — et l'absence des
 * traits HasWorkflow / GereTransmissions, qui supposeraient une référence
 * `numero` transmissible et un statut « brouillon » chez l'émetteur.
 *
 * La décision reste réversible tant que le marché n'est pas attribué :
 * « lever la décision » remet l'offre en attente, avec sa trace. Passé
 * l'attribution, ce n'est plus l'offre qui se discute mais le rapport
 * d'évaluation (étape D), et ce modèle le refuse.
 *
 * Le marché garde la main sur la fenêtre de dépôt : c'est son avis de lancement
 * (date_limite_depot) qui rend une offre hors délai, jamais une date saisie sur
 * l'offre elle-même.
 */
class Offre extends Model
{
    use SoftDeletes;

    protected $table = 'offres';

    protected $fillable = [
        'numero',
        'marche_id',
        'fournisseur_id',
        'date_depot',
        'montant_offert',
        'delai_propose',
        'numero_quittance',
        'date_quittance',
        'montant_quittance',
        'fichier_quittance',
        'statut',
        'valide_par_id',
        'date_validation',
        'motif_rejet',
        'rejet_par_id',
        'date_rejet',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'date_depot'        => 'date',
        'montant_offert'    => 'decimal:2',
        'delai_propose'     => 'integer',
        'date_quittance'    => 'date',
        'montant_quittance' => 'decimal:2',
        'date_validation'   => 'datetime',
        'date_rejet'        => 'datetime',
    ];

    // ====================================
    // VOCABULAIRE DU CYCLE
    // ====================================

    public static function statuts(): array
    {
        return config('marches.statuts_offre');
    }

    public static function statutLibelle(?string $statut): string
    {
        return config('marches.statuts_offre')[$statut] ?? (string) $statut;
    }

    public static function transitions(): array
    {
        return config('marches.transitions_offre');
    }

    public function statutSuivants(): array
    {
        return static::transitions()[$this->statut] ?? [];
    }

    public function peutAtteindre(string $statutCible): bool
    {
        return in_array($statutCible, $this->statutSuivants(), true);
    }

    protected function verifierTransition(string $statutCible): void
    {
        if (!$this->peutAtteindre($statutCible)) {
            throw new \Exception(sprintf(
                'Transition interdite : %s → %s. Une offre « %s » ne peut pas devenir « %s ».',
                static::statutLibelle($this->statut),
                static::statutLibelle($statutCible),
                static::statutLibelle($this->statut),
                static::statutLibelle($statutCible),
            ));
        }
    }

    // ====================================
    // RELATIONS
    // ====================================

    /**
     * Le marché de l'offre est un fait du dossier : il ne cesse pas d'exister parce
     * que la session a changé d'exercice. HasExercice pose une portée globale sur
     * marches, qui rendrait $this->marche null à la bascule de l'année — et l'offre
     * hors délai alors décisionnable, l'obstacle disparaissant avec la relation.
     */
    public function marche(): BelongsTo
    {
        return $this->belongsTo(Marche::class)->withoutGlobalScope('exercice');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function valideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par_id');
    }

    public function rejeteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejet_par_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Les notes portées sur cette offre (étape D) — la grille du dossier. */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    // ====================================
    // GRILLE D'ÉVALUATION (étape D)
    // ====================================

    /*
     * La grille est celle du référentiel, telle qu'elle est aujourd'hui : toute
     * ligne active se note, dans l'ordre du schéma. Le drapeau `obligatoire` de
     * l'élément dit au rédacteur du DAO quelles pièces sont exigées du
     * soumissionnaire ; il ne change rien à la notation, parce qu'un classement
     * ne compare des offres que si toutes passent sous les mêmes lignes.
     *
     * Une ligne désactivée après coup continue d'exister dans `elements_evaluation_dao`
     * et ses notes restent lisibles ; elle ne compte plus dans le total.
     */

    /** Ce que l'offre a cumulé, sur les lignes actives uniquement. */
    public function totalEvalue(): float
    {
        $etapes = ElementEvaluationDao::grille();
        $ids    = $etapes->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $this->relationLoaded('evaluations')
            ? (float) $this->evaluations->whereIn('element_evaluation_dao_id', $ids)->sum('note')
            : (float) $this->evaluations()->whereIn('element_evaluation_dao_id', $ids)->sum('note');
    }

    /** Le maximum atteignable — le dénominateur que l'écran affiche avec la note. */
    public function baremeTotal(): float
    {
        return (float) ElementEvaluationDao::grille()->sum('bareme_max');
    }

    /** Les étapes qui manquent à l'offre, par leur libellé — le refus de l'attribution les nomme. */
    public function etapesManquantes(): array
    {
        $notees = $this->relationLoaded('evaluations')
            ? $this->evaluations->pluck('element_evaluation_dao_id')->map(fn ($i) => (int) $i)->all()
            : $this->evaluations()->pluck('element_evaluation_dao_id')->map(fn ($i) => (int) $i)->all();

        return ElementEvaluationDao::grille()
            ->reject(fn (ElementEvaluationDao $e) => in_array((int) $e->id, $notees, true))
            ->pluck('libelle')
            ->all();
    }

    /**
     * Notée jusqu'au bout. Une offre recevable incomplète empêchera l'attribution
     * du marché, la sienne comme celle d'une concurrente : le classement n'a de
     * valeur que comparé, et un rapport qui note trois offres sur quatre ne dit
     * rien de ce que la commission a réellement regardé.
     */
    public function estEvaluee(): bool
    {
        return $this->etapesManquantes() === [];
    }

    /** La note peut encore être portée ou corrigée : fenêtre du marché (étape D). */
    public function estNotable(): bool
    {
        return (bool) $this->marche?->peutNoterDesOffres();
    }

    // ====================================
    // SCOPES
    // ====================================

    public function scopeParStatut(Builder $query, string $statut): Builder
    {
        return $query->where('statut', $statut);
    }

    /** Ce qui reste à décider — le travail quotidien du service des marchés. */
    public function scopeEnAttenteDeDecision(Builder $query): Builder
    {
        return $query->where('statut', 'recue');
    }

    /** Déposées après l'échéance fixée par l'avis de lancement du marché. */
    public function scopeHorsDelai(Builder $query): Builder
    {
        return $query->whereHas('marche', function (Builder $q) {
            // Même échappée que la relation : le filtre et le prédicat estHorsDelai()
            // doivent décrire la même liste, exercice changé ou non.
            $q->withoutGlobalScope('exercice')
              ->whereNotNull('date_limite_depot')
              ->whereColumn('marches.date_limite_depot', '<', 'offres.date_depot');
        });
    }

    public function scopeSurMarche(Builder $query, int $marcheId): Builder
    {
        return $query->where('marche_id', $marcheId);
    }

    // ====================================
    // PRÉDICATS
    // ====================================

    /**
     * L'offre est arrivée après la date limite de l'avis de lancement. Elle reste
     * enregistrable — un dépôt tardif se constate, cela ne se cache pas — mais
     * elle n'est pas validable : c'est un rejet motivé, ou rien.
     */
    public function estHorsDelai(): bool
    {
        $limite = $this->marche?->date_limite_depot;

        return $limite !== null && $this->date_depot !== null && $this->date_depot->gt($limite);
    }

    public function fournisseurBlackliste(): bool
    {
        return (bool) $this->fournisseur?->blackliste;
    }

    /** Le numéro et la date de la quittance sont réunis — ce que la base exige. */
    public function aSaQuittance(): bool
    {
        return filled($this->numero_quittance) && $this->date_quittance !== null;
    }

    public function estDecisionnee(): bool
    {
        return $this->statut !== 'recue';
    }

    /**
     * Ce qui empêche de déclarer l'offre recevable, énoncé en français. Le
     * formulaire filtre déjà ; cette méthode rend le refus lisible au lieu de
     * laisser la contrainte CHECK parler en SQL.
     */
    public function obstaclesValidation(): array
    {
        $obstacles = [];

        if ($this->estHorsDelai()) {
            $obstacles[] = 'le dépôt est postérieur à la date limite ('
                . $this->marche->date_limite_depot->format('d/m/Y') . ') — à rejeter avec motif';
        }
        if ($this->fournisseurBlackliste()) {
            $obstacles[] = 'le soumissionnaire est blacklisté ('
                . $this->fournisseur->motif_blacklist . ') — à rejeter avec motif';
        }
        if ((float) $this->montant_offert <= 0) {
            $obstacles[] = 'le montant offert n\'est pas saisi';
        }
        if (!$this->aSaQuittance()) {
            $obstacles[] = 'la quittance de paiement n\'est pas renseignée (numéro et date)';
        }

        return $obstacles;
    }

    /**
     * La fenêtre de décision : le marché doit en être à ses offres. Après
     * l'attribution, la discussion porte sur le rapport d'évaluation, pas sur une
     * colonne de cette table.
     */
    protected function verifierMarcheDecisionnable(): void
    {
        if (!$this->marche) {
            throw new \Exception('Cette offre n\'est rattachée à aucun marché.');
        }
        if (!$this->marche->peutRecevoirDesOffres()) {
            throw new \Exception(sprintf(
                'Le marché %s est « %s » : les offres ne se décident plus à cette étape.',
                $this->marche->numero,
                Marche::statutLibelle($this->marche->statut),
            ));
        }
    }

    // ====================================
    // DÉCISIONS
    // ====================================

    /**
     * Reçue → recevable. Le scan appelle cela « validation » ; le mot du dépôt
     * (événement « valider ») est conservé pour que le journal reste lisible.
     */
    public function valider(User $user): void
    {
        $this->verifierTransition('validee');
        $this->verifierMarcheDecisionnable();

        $obstacles = $this->obstaclesValidation();
        if ($obstacles !== []) {
            throw new \Exception(
                "L'offre {$this->numero} ne peut pas être déclarée recevable : "
                    . implode(' ; ', $obstacles) . '.'
            );
        }

        $ancien = $this->statut;
        $this->statut          = 'validee';
        $this->valide_par_id   = $user->id;
        $this->date_validation = now();
        $this->motif_rejet     = null;
        $this->rejet_par_id    = null;
        $this->date_rejet      = null;
        $this->save();

        ActivityLog::logAction($this, 'valider', [
            'ancien_statut'   => $ancien,
            'nouveau_statut'  => 'validee',
            'montant_offert'  => (float) $this->montant_offert,
            'soumissionnaire' => $this->fournisseur?->raison_sociale,
            'par'             => $user->name,
        ]);
    }

    /**
     * Reçue → rejetée, avec le motif que lira une contestation.
     */
    public function rejeter(User $user, ?string $motif = null): void
    {
        $this->verifierTransition('rejetee');
        $this->verifierMarcheDecisionnable();

        // blank() et non un simple test de vérité : «   » est vrai en PHP, alors que
        // la contrainte CHECK de la table juge le motif sur sa valeur vidée. Sans
        // cela, l'écran afficherait l'erreur SQL au lieu de la motivation attendue.
        if (blank($motif)) {
            throw new \Exception(
                'Rejeter une offre exige un motif : c\'est ce que lit le soumissionnaire qui conteste.'
            );
        }

        $ancien = $this->statut;
        $this->statut       = 'rejetee';
        $this->motif_rejet  = $motif;
        $this->rejet_par_id = $user->id;
        $this->date_rejet   = now();
        $this->valide_par_id   = null;
        $this->date_validation = null;
        $this->save();

        ActivityLog::logAction($this, 'rejeter', [
            'ancien_statut'   => $ancien,
            'nouveau_statut'  => 'rejetee',
            'motif'           => $motif,
            'soumissionnaire' => $this->fournisseur?->raison_sociale,
            'par'             => $user->name,
        ]);
    }

    /**
     * Recevable ou rejetée → reçue : le service défait sa propre décision tant
     * que le marché n'a pas été attribué. Les colonnes de la décision se vident,
     * comme celles du classement contredit à l'étape B — un dossier ne doit pas
     * porter deux décisions contradictoires.
     */
    public function leverDecision(User $user): void
    {
        $this->verifierTransition('recue');
        $this->verifierMarcheDecisionnable();

        $ancien = $this->statut;
        $this->statut          = 'recue';
        $this->valide_par_id   = null;
        $this->date_validation = null;
        $this->motif_rejet     = null;
        $this->rejet_par_id    = null;
        $this->date_rejet      = null;
        $this->save();

        ActivityLog::logAction($this, 'lever_decision', [
            'ancien_statut'  => $ancien,
            'nouveau_statut' => 'recue',
            'decision_annulee' => static::statutLibelle($ancien),
            'par'            => $user->name,
        ]);
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function booted(): void
    {
        static::creating(function (Offre $offre) {
            $offre->numero     ??= static::genererNumero();
            $offre->statut     ??= 'recue';
            $offre->created_by ??= auth()->id();

            /*
             * Le marché décide de la fenêtre : une offre ne s'enregistre que tant
             * que l'avis de lancement est publié et les plis pas encore attribués.
             * Le formulaire refuse avant, avec son message de champ ; ce garde-fu
             * est là pour les autres chemins d'écriture (semence, reprise en
             * direct), et ne réécrit jamais une colonne saisie.
             */
            $marche = $offre->marche;
            if (!$marche) {
                throw new \Exception("L'offre doit être rattachée à un marché.");
            }
            if (!$marche->peutRecevoirDesOffres()) {
                throw new \Exception(sprintf(
                    'Le marché %s est « %s » : il n\'accueille plus les offres.',
                    $marche->numero,
                    Marche::statutLibelle($marche->statut),
                ));
            }
        });

        /*
         * L'offre retirée du dossier est un constat (étape C) ; l'offre retenue par
         * une attribution est une décision (étape D). L'effacer laisserait le marché
         * « attribué » sans attributaire lisible — la fiche, le PV et l'engagement
         * (étape F) ne retrouveraient plus qui a gagné. La clef étrangère est
         * `restrictOnDelete`, mais l'offre se retire en douceur : c'est ici que la
         * décision se défend. Le chemin de correction est la levée de l'attribution,
         * pas la suppression.
         */
        static::deleting(function (Offre $offre) {
            $retenu_par = Marche::withTrashed()
                ->where('offre_attribuee_id', $offre->id)
                ->first();

            if ($retenu_par) {
                throw new \Exception(sprintf(
                    '« %s » est l\'offre retenue du marché %s : levez l\'attribution avant de retirer cette offre du dossier.',
                    $offre->numero,
                    $retenu_par->numero,
                ));
            }
        });
    }

    public static function genererNumero(): string
    {
        return DB::transaction(function () {
            $annee   = now()->year;
            $prefixe = "OFF-{$annee}-";
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
