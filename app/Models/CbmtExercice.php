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
     * Bandeau de la fiche : le chemin de crédits (collectifs appliqués, virements exécutés) a-t-il bougé
     * après la dernière écriture des lignes ? Comparaison d'horodatages, volontairement bon marché : elle
     * invite au rattrapage, elle n'affirme aucun montant. Elle n'est jamais muette non plus — le refus
     * hors brouillon, l'absence d'exercice ou de lignes sont dits avec le même vocabulaire.
     *
     * @return array{niveau: string, message: string} niveau : success | warning | danger
     */
    public function alerteCheminCredits(): array
    {
        // C'est la liaison qui est testée, pas la colonne : elle est NOT NULL, donc l'état réellement
        // atteignable est l'exercice passé à la corbeille — et le bandeau n'a pas le droit de dire
        // « à jour » alors que plus aucune source n'est lisible.
        $exercice = $this->exerciceReference;

        if (!$exercice) {
            return ['niveau' => 'danger', 'message' => app(\App\Services\Programmation\GenerationCbmtService::class)
                ->messageExercicePerdu($this)];
        }

        $n = (int) $exercice->id;
        $annee = (int) $exercice->annee;

        if ($this->lignes()->count() === 0) {
            return ['niveau' => 'warning', 'message' => "Aucune ligne au document : lancez « Générer à partir de {$annee} »"
                . " pour les écrire depuis le budget et la prévision de recettes de l'exercice."];
        }

        $ecrite_apres = $this->lignes()->max('updated_at');

        // Les faits qui portent de l'argent : les mouvements des collectifs adoptés ou fusionnés, et les
        // virements sortis de l'instruction. Un rattrapage réécrit les lignes touchées après eux, donc ce
        // bandeau s'efface de lui-même — il ne juge pas, il propose.
        $collectifs = CollectifBudgetaire::where('exercice_id', $n)
            ->whereIn('statut', ['adopte', 'fusionne'])
            ->whereHas('mouvements', fn($q) => $q->where('updated_at', '>', $ecrite_apres))
            ->pluck('numero')->all();

        $virements = VirementBudgetaire::withoutGlobalScope('exercice')
            ->where('exercice_id', $n)
            ->whereIn('statut', ['approuve', 'execute', 'rejete', 'annule'])
            ->where('updated_at', '>', $ecrite_apres)
            ->pluck('numero')->all();

        $noms = collect(array_merge($collectifs, $virements));
        $signaler = $noms->count();

        if ($this->statut !== 'brouillon') {
            return ['niveau' => 'danger', 'message' => app(\App\Services\Programmation\GenerationCbmtService::class)
                ->messageRefusActualisation($this)
                . ($signaler ? "\n{$signaler} document(s) de l'exercice {$annee} ont bougé depuis : "
                    . $noms->take(6)->implode(', ') . ($signaler > 6 ? '…' : '') : '')];
        }

        if ($signaler === 0) {
            return ['niveau' => 'success', 'message' => 'À jour des collectifs appliqués et des virements exécutés'
                . " de l'exercice {$annee} à la dernière écriture de ses lignes."];
        }

        return ['niveau' => 'warning', 'message' => "{$signaler} document(s) de l'exercice {$annee} ont fait bouger"
            . ' des enveloppes après la dernière écriture de ce CBMT : ' . $noms->take(6)->implode(', ')
            . ($signaler > 6 ? '…' : '') . '. Lancez « Rattraper les prévisions actualisées » pour reprendre'
            . ' la colonne N — les projections et la prévision votée ne bougent pas.'];
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
