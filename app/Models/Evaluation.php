<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Note d'évaluation — étape D du module /marches.
 *
 * Une ligne = une offre notée sur une étape du DAO. C'est tout ce que cette table
 * contient : pas de statut, pas de soft delete. Une note n'est pas un document du
 * circuit de transmission, elle se corrige à la main de son auteur et l'audit relit
 * l'ancien et le nouveau ; lui donner un cycle propre voudrait dire deux décisions
 * sur le même fait.
 *
 * La fenêtre de notation est celle du marché : ouverte à l'ouverture des plis,
 * fermée à l'attribution. C'est la règle qui protège le rapport — après la décision,
 * la discussion porte sur l'attribution (elle se lève), jamais sur une colonne de
 * cette table.
 *
 * Le barème n'est jamais recopié : `elements_evaluation_dao` en tient la valeur, et
 * le plafond de la note se contrôle ici, sur tous les chemins d'écriture, parce
 * qu'un CHECK PostgreSQL ne lit pas une autre table.
 */
class Evaluation extends Model
{
    protected $table = 'evaluations';

    protected $fillable = [
        'offre_id',
        'element_evaluation_dao_id',
        'note',
        'commentaire',
        'evalue_par_id',
        'date_evaluation',
    ];

    protected $casts = [
        'note'            => 'decimal:2',
        'date_evaluation' => 'datetime',
    ];

    // ====================================
    // RELATIONS
    // ====================================

    /**
     * L'offre notée, puis son marché : la fenêtre de notation se juge sur le marché,
     * et Offre::marche() retire la portée exercice pour que cette lecture survive à
     * la bascule d'année (un DAO ouvert en janvier se note encore en février).
     */
    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class);
    }

    public function element(): BelongsTo
    {
        return $this->belongsTo(ElementEvaluationDao::class, 'element_evaluation_dao_id');
    }

    public function evaluateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evalue_par_id');
    }

    // ====================================
    // SCOPES
    // ====================================

    public function scopeSurMarche(Builder $query, int $marcheId): Builder
    {
        return $query->whereHas('offre', fn (Builder $q) => $q->where('marche_id', $marcheId));
    }

    /** Les notes d'un volet du schéma (administrative | financiere | technique). */
    public function scopeParFamille(Builder $query, string $famille): Builder
    {
        return $query->whereHas('element', fn (Builder $q) => $q->where('famille', $famille));
    }

    // ====================================
    // LECTURES
    // ====================================

    /** Le barème en vigueur de l'étape notée — pas celui qui était noté à l'époque. */
    public function bareme(): float
    {
        return (float) ($this->element?->bareme_max ?? 0);
    }

    public function horsBareme(): bool
    {
        return $this->bareme() > 0 && (float) $this->note > $this->bareme();
    }

    /** Le volet du DAO, pour les badges de l'écran. */
    public function famille(): ?string
    {
        return $this->element?->famille;
    }

    public function getFamilleLibelle(): ?string
    {
        return $this->famille() === null
            ? null
            : config('marches.familles_evaluation')[$this->famille()] ?? $this->famille();
    }

    /**
     * La note est-elle encore saisissable ? Le marché est-il plis ouverts ?
     * Faux après l'attribution, faux avant l'ouverture : c'est la règle unique de
     * cette table, et l'écran la lit pour ouvrir ou fermer ses actions.
     */
    public function fenetreOuverte(): bool
    {
        return (bool) $this->offre?->marche?->peutNoterDesOffres();
    }

    // ====================================
    // CONTRÔLES
    // ====================================

    protected function verifierFenetre(): void
    {
        $marche = $this->offre?->marche;

        if (!$marche) {
            throw new \Exception('Une note se porte sur une offre déposée sur un marché.');
        }
        if (!$marche->peutNoterDesOffres()) {
            throw new \Exception(sprintf(
                'Le marché %s est « %s » : les notes ne se portent qu\'après l\'ouverture des plis, '
                    . 'et se figent à l\'attribution — levez-la d\'abord.',
                $marche->numero,
                Marche::statutLibelle($marche->statut),
            ));
        }
    }

    protected function verifierNote(): void
    {
        $element = $this->element;

        if (!$element) {
            throw new \Exception('Choisissez l\'étape d\'évaluation du DAO qui est notée.');
        }
        if (!is_numeric($this->note)) {
            throw new \Exception('Une note d\'évaluation est un nombre.');
        }
        if ((float) $this->note < 0) {
            throw new \Exception('Une note ne descend pas sous zéro : le barème commence là.');
        }
        if ((float) $this->note > (float) $element->bareme_max) {
            throw new \Exception(sprintf(
                '« %s » se note au plus %s : %s est hors barème. Le barème se corrige au '
                    . 'paramétrage des éléments d\'évaluation, jamais à la main sur une note.',
                $element->libelle,
                (float) $element->bareme_max,
                (float) $this->note,
            ));
        }
    }

    // ====================================
    // BOOT
    // ====================================

    protected static function booted(): void
    {
        /*
         * La main et la date ne se saisissent pas, elles se constatent. Deux règles,
         * dans les deux sens de la correction :
         * — une note nouvelle prend son évaluateur ;
         * — une note corrigée prend la main qui l'a corrigée, parce que c'est elle
         *   que l'audit cherche quand deux évaluateurs ne sont pas d'accord.
         * Rien d'autre n'est réécrit ici : la note vient de l'écran, telle quelle,
         * et c'est le refus qui borne la saisie, jamais un écrasement.
         */
        static::saving(function (Evaluation $evaluation) {
            $evaluation->verifierFenetre();
            $evaluation->verifierNote();

            if (!$evaluation->element?->actif) {
                throw new \Exception(sprintf(
                    'L\'étape « %s » est inactive au paramétrage : elle ne se note plus.',
                    $evaluation->element?->libelle ?? '—',
                ));
            }

            if (!$evaluation->exists) {
                $evaluation->evalue_par_id   = $evaluation->evalue_par_id ?? auth()->id();
                $evaluation->date_evaluation = $evaluation->date_evaluation ?? now();

                if (!$evaluation->evalue_par_id) {
                    throw new \Exception(
                        'Une note d\'évaluation exige un évaluateur identifié : rien n\'est noté sans main.'
                    );
                }

                return;
            }

            if ($evaluation->isDirty('note')) {
                $evaluation->evalue_par_id   = auth()->id() ?? $evaluation->evalue_par_id;
                $evaluation->date_evaluation = now();
            }
        });

        /*
         * Supprimer une note efface une preuve du rapport : la même fenêtre que la
         * saisie la protège, et après l'attribution il n'y a plus de suppression
         * possible — il y a une attribution à lever.
         */
        static::deleting(function (Evaluation $evaluation) {
            $evaluation->verifierFenetre();
        });

        static::saved(function (Evaluation $evaluation) {
            $aNoté = $evaluation->wasRecentlyCreated || $evaluation->wasChanged('note');
            if (!$aNoté) {
                return;
            }

            ActivityLog::logAction($evaluation, 'evaluer', [
                'offre'         => $evaluation->offre?->numero,
                'soumissionnaire' => $evaluation->offre?->fournisseur?->raison_sociale,
                'marche'        => $evaluation->offre?->marche?->numero,
                'element'       => $evaluation->element?->libelle,
                'volet'         => $evaluation->getFamilleLibelle(),
                'note'          => (float) $evaluation->note,
                'bareme'        => (float) $evaluation->bareme(),
                'par'           => $evaluation->evaluateur?->name,
            ]);
        });
    }
}
