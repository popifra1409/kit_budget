<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Étape d'évaluation du DAO (paramétrage) : une ligne, rattachée à un volet
 * — pièce administrative, pièce financière ou description technique.
 */
class ElementEvaluationDao extends Model
{
    protected $table = 'elements_evaluation_dao';

    protected $fillable = [
        'famille', 'code', 'libelle', 'description', 'bareme_max', 'obligatoire', 'ordre', 'actif',
    ];

    protected $casts = [
        'actif'       => 'boolean',
        'obligatoire' => 'boolean',
        'bareme_max'  => 'decimal:2',
    ];

    public function scopeActives($query)
    {
        return $query->where('actif', true)->orderBy('ordre');
    }

    /**
     * La grille en vigueur pour la notation (étape D) : toutes les lignes actives,
     * dans l'ordre du schéma. Une ligne retirée du jeu ne se note plus, mais les
     * notes déjà portées sur elle restent au dossier et se lisent.
     */
    public static function grille(): Collection
    {
        return static::actives()->get();
    }

    /** Les notes portées sur cette étape — la table de l'étape D. */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'element_evaluation_dao_id');
    }

    protected static function booted(): void
    {
        /*
         * Une étape notée ne se supprime pas : le rapport d'évaluation s'appuie sur
         * elle, et la clé étrangère est `restrictOnDelete` pour la même raison. Ce
         * garde-fu ne fait que dire en français ce que SQL refuserait en anglais,
         * et par le désactiver plutôt que le supprimer : une étape qui n'exige plus
         * rien du DAO sort du jeu, elle n'efface pas les notes des offres.
         */
        static::deleting(function (ElementEvaluationDao $element) {
            if ($element->evaluations()->exists()) {
                throw new \Exception(sprintf(
                    '« %s » a déjà reçu des notes : désactivez-la plutôt que de la supprimer, '
                        . 'le rapport d\'évaluation s\'appuie sur cette ligne.',
                    $element->libelle,
                ));
            }
        });
    }
}
