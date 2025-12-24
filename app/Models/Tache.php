<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tache extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'taches';

    protected $fillable = [
        'activite_id',
        'nomenclature_id',
        'code',
        'libelle',
        'description',
        'delai',
        'guichet',
        'service_responsable',
        'ae',
        'cp',
        'resultat_attendu',
        'indicateur_resultat',
        'ordre',
        'actif',
    ];

    protected $casts = [
        'ae' => 'decimal:2',
        'cp' => 'decimal:2',
        'ordre' => 'integer',
        'actif' => 'boolean',
    ];

    /**
     * Relation : Activité parent
     */
    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    /**
     * Relation : Nomenclature budgétaire liée
     */
    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class);
    }

    /**
     * Obtenir le chemin complet du cadre logique
     */
    public function getCheminComplet(): array
    {
        $activite = $this->activite;
        $action = $activite->action;
        $programme = $action->programme;

        return [
            'programme' => $programme->code . ' - ' . $programme->libelle,
            'objectif_principal' => $programme->objectifsPrincipaux->first()?->libelle ?? '',
            'action' => $action->code . ' - ' . $action->libelle,
            'objectif_specifique' => $action->objectifsSpecifiques->first()?->libelle ?? '',
            'activite' => $activite->code . ' - ' . $activite->libelle,
            'tache' => $this->code . ' - ' . $this->libelle,
        ];
    }

    /**
     * Obtenir le programme
     */
    public function getProgramme(): Programme
    {
        return $this->activite->action->programme;
    }

    /**
     * Obtenir l'action
     */
    public function getAction(): Action
    {
        return $this->activite->action;
    }
}
