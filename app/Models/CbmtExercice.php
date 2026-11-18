<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasWorkflow;
use App\Traits\HasCorbeille;

class CbmtExercice extends Model
{
    use SoftDeletes, HasWorkflow, HasCorbeille;

    /** Relations dont les lignes partent avec ce document dans la corbeille. */
    public static function corbeilleEnfants(): array
    {
        return ['lignes'];
    }

    protected $table = 'cbmt_exercices';

    protected $fillable = [
        'numero',
        'plan_strategique_ep_id',
        'exercice_reference_id',
        'date_lettre_cadrage',
        'hypotheses_ressources',
        'commentaire_soutenabilite',
        'statut',
        'created_by',
    ];

    protected $casts = [
        'date_lettre_cadrage' => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->created_by ??= auth()->id();
            $model->numero ??= static::genererNumero();
        });
    }

    public static function genererNumero(): string
    {
        $annee = now()->year;
        $dernier = static::withTrashed()->whereYear('created_at', $annee)->count();

        return sprintf('CBMT-%d-%05d', $annee, $dernier + 1);
    }

    public function planStrategiqueEp(): BelongsTo
    {
        return $this->belongsTo(PlanStrategiqueEp::class, 'plan_strategique_ep_id');
    }

    public function exerciceReference(): BelongsTo
    {
        return $this->belongsTo(Exercice::class, 'exercice_reference_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(CbmtLigne::class);
    }

    public function lignesRessources(): HasMany
    {
        return $this->lignes()->where('nature', 'ressource');
    }

    public function lignesDepenses(): HasMany
    {
        return $this->lignes()->where('nature', 'depense');
    }

    /** Année de l'exercice de référence (N). */
    public function anneeReference(): int
    {
        return (int) ($this->exerciceReference?->annee ?? now()->year);
    }

    /**
     * Prévision à moyen terme PAR TITRES, avec le détail des lignes de chaque titre.
     * Source unique de l'écran de rapport, du PDF et de l'export Excel.
     *
     * @param string $nature 'ressource' ou 'depense'
     */
    public function syntheseParTitres(string $nature): \Illuminate\Support\Collection
    {
        $colonnes = self::colonnesMontants();
        $libelles = $nature === 'ressource' ? CbmtLigne::TITRES_RESSOURCES : CbmtLigne::TITRES_DEPENSES;

        return $this->lignes()
            ->where('nature', $nature)
            ->orderBy('ordre')->orderBy('code')
            ->get()
            ->groupBy(fn ($l) => $l->titre ?? 'sans')
            ->sortKeysUsing(fn ($a, $b) => ($a === 'sans') <=> ($b === 'sans') ?: $a <=> $b)
            ->map(fn ($lignes, $titre) => [
                'titre'   => $titre === 'sans' ? null : (int) $titre,
                'libelle' => $titre === 'sans'
                    ? 'Sans titre (comptes à classer dans la nomenclature)'
                    : "Titre {$titre} — " . ($libelles[$titre] ?? $lignes->first()->libelle_titre),
                'lignes'  => $lignes,
                'totaux'  => collect($colonnes)->mapWithKeys(fn ($c) => [$c => (float) $lignes->sum($c)])->all(),
            ])
            ->values();
    }

    /** Colonnes de montants d'une ligne du CBMT, dans l'ordre d'affichage. */
    public static function colonnesMontants(): array
    {
        return [
            'montant_n_moins_1', 'prevision_n_initiale', 'montant_n',
            'realisation_n', 'realisation_n_ordonnance',
            'montant_n_plus_1', 'montant_n_plus_2', 'montant_n_plus_3',
        ];
    }

    /**
     * Test de soutenabilite : ecart entre ressources et depenses projetees,
     * pour chaque annee du cadrage (I.3 du guide).
     */
    public function getTestSoutenabilite(): array
    {
        $colonnes = ['montant_n_plus_1', 'montant_n_plus_2', 'montant_n_plus_3'];
        $resultat = [];

        foreach ($colonnes as $col) {
            $ressources = $this->lignesRessources()->sum($col);
            $depenses = $this->lignesDepenses()->sum($col);
            $resultat[$col] = [
                'ressources' => $ressources,
                'depenses' => $depenses,
                'ecart' => $ressources - $depenses,
            ];
        }

        return $resultat;
    }
}
