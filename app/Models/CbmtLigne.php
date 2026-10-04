<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbmtLigne extends Model
{
    protected $table = 'cbmt_lignes';

    protected $fillable = [
        'cbmt_exercice_id',
        'nature',
        'nomenclature_id',
        'code',
        'libelle',
        'type_ligne',
        'titre',
        'libelle_titre',
        'source',
        'montant_n_moins_1',        // réalisation N-1 (saisie)
        'prevision_n_initiale',     // prévision N votée
        'montant_n',                // prévision N actualisée (collectifs, virements)
        'realisation_n',            // recouvré (ressources) / engagé (dépenses)
        'realisation_n_ordonnance', // ordonnancé (dépenses)
        'montant_n_plus_1',
        'montant_n_plus_2',
        'montant_n_plus_3',
        'ordre',
    ];

    /** LR : ligne de référence reconduite de l'exercice N ; MN : mesure nouvelle. */
    public const TYPES_LIGNE = [
        'LR' => 'Ligne de référence (reconduite)',
        'MN' => 'Mesure nouvelle',
    ];

    protected $casts = [
        'montant_n_moins_1' => 'decimal:2',
        'prevision_n_initiale' => 'decimal:2',
        'montant_n' => 'decimal:2',
        'realisation_n' => 'decimal:2',
        'realisation_n_ordonnance' => 'decimal:2',
        'titre' => 'integer',
        'montant_n_plus_1' => 'decimal:2',
        'montant_n_plus_2' => 'decimal:2',
        'montant_n_plus_3' => 'decimal:2',
    ];

    public function cbmtExercice(): BelongsTo
    {
        return $this->belongsTo(CbmtExercice::class);
    }

    public function nomenclature(): BelongsTo
    {
        return $this->belongsTo(NomenclatureBudgetaire::class, 'nomenclature_id');
    }

    /** Libellé complet du titre de la ligne (« Titre 3 — Dépenses de biens et services »). */
    public function getLibelleTitreCompletAttribute(): string
    {
        if ($this->titre === null) {
            return 'Sans titre (nomenclature à classer)';
        }

        $titres = $this->nature === 'ressource' ? self::TITRES_RESSOURCES : self::TITRES_DEPENSES;

        return "Titre {$this->titre} — " . ($titres[$this->titre] ?? $this->libelle_titre ?? '');
    }

    /** Libellés standards des titres de ressources (prévision à moyen terme des ressources) */
    public const TITRES_RESSOURCES = [
        1 => 'Recettes fiscales affectées',
        2 => "Produits de l'exploitation du domaine et des services",
        3 => 'Dotations et subventions',
        4 => 'Autres recettes',
    ];

    /** Libellés standards des titres de dépenses (prévision à moyen terme des dépenses) */
    public const TITRES_DEPENSES = [
        1 => 'Charges financières de la dette',
        2 => 'Dépenses de personnel',
        3 => 'Dépenses de biens et services',
        4 => 'Dépenses de subvention et de transfert',
        5 => "Dépenses d'investissement",
        6 => 'Autres dépenses de fonctionnement',
    ];
}
