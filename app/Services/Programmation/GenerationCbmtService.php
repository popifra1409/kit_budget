<?php

namespace App\Services\Programmation;

use App\Models\Budget;
use App\Models\CbmtExercice;
use App\Models\CbmtLigne;
use App\Models\Exercice;
use App\Models\LignePrevisionRecette;
use App\Models\LigneBudgetaire;
use App\Models\MouvementCollectif;
use App\Models\NomenclatureBudgetaire;
use App\Models\PrevisionRecette;
use Illuminate\Support\Facades\DB;

/**
 * Construit le CBMT LIGNE PAR LIGNE à partir de l'exercice de référence (N) :
 *  - ressources : lignes de la prévision de recettes adoptée de N ;
 *  - dépenses   : lignes du budget actif de N ;
 * chacune rangée sous son titre (TitreNomenclatureService).
 *
 * Valeurs de N (toujours rafraîchies) :
 *  - prévision initiale, prévision actualisée (collectifs et virements),
 *  - réalisation : recouvré (ressources) ; engagé ET ordonnancé (dépenses).
 * Projections N+1..N+3 : reconduction de la prévision N actualisée (lignes de référence, LR),
 * écrites à la création seulement — les projections déjà saisies ne sont jamais écrasées,
 * sauf demande explicite ($reinitialiserProjections).
 *
 * Relançable : ajoute les nouvelles lignes de N et met à jour les valeurs de N.
 *
 * `actualiser()` est la version déclenchée par les faits : un collectif appliqué ou annulé, un virement
 * dont le statut change, et la seule colonne `montant_n` (la prévision N actualisée) est reprise, avec
 * les mesures nouvelles que le document ne connaît pas encore. Les projections et la prévision votée
 * ne bougent pas — elles sont la décision de l'agent.
 */
class GenerationCbmtService
{
    public function __construct(protected TitreNomenclatureService $titres) {}

    /** @return array{creees: int, mises_a_jour: int, sans_titre: int, sans_compte: int, classes_mn: int, ecartees: int, budget: ?string, prevision: ?string} */
    public function generer(CbmtExercice $cbmt, bool $reinitialiserProjections = false): array
    {
        ['exercice' => $exercice, 'budget' => $budget, 'prevision' => $prevision] = $this->sources($cbmt);

        if (!$exercice) {
            throw new \DomainException("Le CBMT n'a pas d'exercice de référence (N).");
        }

        if (!$budget && !$prevision) {
            throw new \DomainException("Aucun budget actif ni prévision de recettes pour l'exercice {$exercice->annee}.");
        }

        $bilan = [
            'creees' => 0,
            'mises_a_jour' => 0,
            'sans_titre' => 0,
            'sans_compte' => 0,
            'classes_mn' => 0,
            'ecartees' => 0,
            'comptes_perdus' => [],
            'lignes_jetees' => [],
            'budget' => $budget?->code ?? $budget?->libelle,
            'prevision' => $prevision?->code
        ];

        DB::transaction(function () use ($cbmt, $budget, $prevision, $reinitialiserProjections, &$bilan) {
            // ── Ressources ─────────────────────────────────────────
            if ($prevision) {
                $lignes = LignePrevisionRecette::where('prevision_recette_id', $prevision->id)
                    ->with('nomenclature')
                    ->get();

                foreach ($lignes as $l) {
                    $this->enregistrer($cbmt, 'ressource', $l->nomenclature, [
                        'prevision_n_initiale'     => (float) $l->montant_prevu_initial,
                        'montant_n'                => $l->getMontantRectifieReel(),
                        'realisation_n'            => (float) $l->montant_recouvre,
                        'realisation_n_ordonnance' => 0,
                    ], $reinitialiserProjections, $bilan, $l->code_nomenclature, $l->libelle_nomenclature, $l);
                }
            }

            // ── Dépenses ───────────────────────────────────────────
            if ($budget) {
                $lignes = LigneBudgetaire::withoutGlobalScope('exercice')
                    ->where('budget_id', $budget->id)
                    ->with('nomenclature')
                    ->get();

                foreach ($lignes as $l) {
                    $this->enregistrer($cbmt, 'depense', $l->nomenclature, [
                        'prevision_n_initiale'     => (float) $l->budget_initial,
                        'montant_n'                => (float) $l->getBudgetRectifieReel(),
                        'realisation_n'            => (float) $l->engage,
                        'realisation_n_ordonnance' => (float) $l->ordonne,
                    ], $reinitialiserProjections, $bilan, null, null, $l);
                }
            }
        });

        return $bilan;
    }

    /**
     * Documents sources du CBMT : le budget ACTIF de N et la prévision de recettes retenue pour N.
     *
     * @return array{exercice: ?\App\Models\Exercice, budget: ?Budget, prevision: ?PrevisionRecette}
     */
    private function sources(CbmtExercice $cbmt): array
    {
        $exercice = $cbmt->exerciceReference;

        if (!$exercice) {
            return ['exercice' => null, 'budget' => null, 'prevision' => null];
        }

        $budget = Budget::withoutGlobalScope('exercice')
            ->where('exercice', $exercice->annee)
            ->where('actif', true)
            ->first();

        $prevision = PrevisionRecette::withoutGlobalScope('exercice')
            ->where('exercice_id', $exercice->id)
            ->where('statut', '!=', 'elaboration')
            ->orderByRaw("CASE WHEN statut = 'adopte' THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();

        return ['exercice' => $exercice, 'budget' => $budget, 'prevision' => $prevision];
    }

    /** Crée ou met à jour la ligne du CBMT pour un compte. */
    protected function enregistrer(
        CbmtExercice $cbmt,
        string $nature,
        $nomenclature,
        array $valeursN,
        bool $reinitialiserProjections,
        array &$bilan,
        ?string $codeRepli = null,
        ?string $libelleRepli = null,
        ?object $ligneSource = null
    ): void {
        if (!$nomenclature) {
            $this->comptePerdu($nature, $ligneSource, $codeRepli, $bilan);

            return;
        }

        $titre = $this->titres->titreDe($nomenclature);
        $titres = $nature === 'ressource' ? CbmtLigne::TITRES_RESSOURCES : CbmtLigne::TITRES_DEPENSES;

        $ligne = CbmtLigne::firstOrNew([
            'cbmt_exercice_id' => $cbmt->id,
            'nature'           => $nature,
            'nomenclature_id'  => $nomenclature->id,
        ]);

        $nouvelle = !$ligne->exists;

        if ($nouvelle && $this->compteAJeter($cbmt, $nature, $nomenclature, $bilan)) {
            return;
        }

        // Une ligne née d'un collectif est une MESURE NOUVELLE. Le `?: 'LR'` que le service écrivait
        // plus bas étiquetait tout en ligne de référence : le filtre LR/MN, le test de soutenabilité
        // et les plafonds traitaient alors un MN comme une reconduction.
        //
        // La promotion s'applique aussi aux lignes DÉJÀ au document (elles ont pu être créées avant
        // cette règle, ou par une saisie antérieure) — mais jamais dans l'autre sens : un MN désigné
        // par l'agent reste MN, la qualification LR→MN lui rend au passage la suppression.
        if ($nouvelle) {
            $ligne->type_ligne = $this->typeLigneSource($ligneSource, $nature);
        } elseif ($ligne->type_ligne === 'LR' && $this->typeLigneSource($ligneSource, $nature) === 'MN') {
            $ligne->type_ligne = 'MN';
            $bilan['classes_mn']++;
        }

        $ligne->fill(array_merge($valeursN, [
            'code'          => $nomenclature->code ?? $codeRepli,
            'libelle'       => $nomenclature->libelle ?? $libelleRepli,
            'titre'         => $titre,
            'libelle_titre' => $titre !== null ? ($titres[$titre] ?? null) : null,
            'ordre'         => (int) preg_replace('/\D/', '', (string) ($nomenclature->code ?? '0')),
        ]));

        // Projections : reconduction de la prévision N actualisée (lignes de référence uniquement)
        if ($nouvelle || ($reinitialiserProjections && $ligne->type_ligne !== 'MN')) {
            $ligne->montant_n_plus_1 = $valeursN['montant_n'];
            $ligne->montant_n_plus_2 = $valeursN['montant_n'];
            $ligne->montant_n_plus_3 = $valeursN['montant_n'];
        }

        $ligne->save();

        $nouvelle ? $bilan['creees']++ : $bilan['mises_a_jour']++;
        if ($titre === null) {
            $bilan['sans_titre']++;
        }
    }

    /**
     * Réactualise la prévision N du CBMT après un fait qui change les enveloppes : collectif appliqué
     * ou annulé, virement exécuté / rejeté / annulé.
     *
     * Seule `montant_n` est reprise sur les comptes déjà au document ; les comptes qui y manquent sont
     * créés — c'est ainsi qu'une mesure nouvelle née d'un collectif rejoint le CBMT sans attendre
     * l'opérateur, avec les comptes déjà présents promus `MN` en passant. `prevision_n_initiale`
     * (la prévision votée) et les projections N+1..N+3 ne bougent
     * pas : elles restent la décision de l'agent, et seule la case « réinitialiser » de `generer()`
     * les reprend.
     *
     * Un CBMT sorti du brouillon n'est pas retouché : le refus est remonté pour être affiché, pas tu.
     *
     * @param  int[]  $nomenclatureIds  comptes visés ; tableau vide = tout N
     * @return array{creees: int, mises_a_jour: int, sans_titre: int, sans_compte: int, classes_mn: int, ecartees: int, comptes_perdus: array, lignes_jetees: array, refuses: array<int, array>}
     */
    public function actualiser(int $exerciceId, array $nomenclatureIds = []): array
    {
        $bilan = ['creees' => 0, 'mises_a_jour' => 0, 'sans_titre' => 0, 'sans_compte' => 0,
                  'classes_mn' => 0, 'ecartees' => 0, 'comptes_perdus' => [], 'lignes_jetees' => [],
                  'refuses' => []];

        if ($exerciceId <= 0) {
            return $bilan;
        }

        foreach (CbmtExercice::where('exercice_reference_id', $exerciceId)->get() as $cbmt) {
            if ($cbmt->statut !== 'brouillon') {
                $bilan['refuses'][] = [
                    'cbmt_exercice_id' => $cbmt->id,
                    'numero'           => $cbmt->numero,
                    'statut'           => $cbmt->statut,
                    'message'          => $this->messageRefusActualisation($cbmt),
                ];
                continue;
            }

            ['budget' => $budget, 'prevision' => $prevision, 'exercice' => $exercice] = $this->sources($cbmt);

            // Sans exercice joignable, il n'y a RIEN à lire : un bilan vide rendu tel quel dirait « déjà
            // conforme » à quelque chose qui n'a jamais été vérifié. Le refus passe donc par la même
            // accroche que le hors-brouillon, et il est dit.
            if (!$exercice) {
                $bilan['refuses'][] = [
                    'cbmt_exercice_id' => $cbmt->id,
                    'numero'           => $cbmt->numero,
                    'statut'           => $cbmt->statut,
                    'message'          => $this->messageExercicePerdu($cbmt),
                ];
                continue;
            }

            DB::transaction(function () use ($cbmt, $budget, $prevision, $nomenclatureIds, &$bilan) {
                if ($prevision) {
                    $lignes = LignePrevisionRecette::where('prevision_recette_id', $prevision->id)
                        ->when($nomenclatureIds !== [], fn($q) => $q->whereIn('nomenclature_id', $nomenclatureIds))
                        ->with('nomenclature')->get();

                    foreach ($lignes as $l) {
                        $this->reactualiserLigne($cbmt, 'ressource', $l, [
                            'prevision_n_initiale'     => (float) $l->montant_prevu_initial,
                            'montant_n'                => $l->getMontantRectifieReel(),
                            'realisation_n'            => (float) $l->montant_recouvre,
                            'realisation_n_ordonnance' => 0,
                        ], $l->code_nomenclature, $l->libelle_nomenclature, $bilan);
                    }
                }

                if ($budget) {
                    $lignes = LigneBudgetaire::withoutGlobalScope('exercice')
                        ->where('budget_id', $budget->id)
                        ->when($nomenclatureIds !== [], fn($q) => $q->whereIn('nomenclature_id', $nomenclatureIds))
                        ->with('nomenclature')->get();

                    foreach ($lignes as $l) {
                        $this->reactualiserLigne($cbmt, 'depense', $l, [
                            'prevision_n_initiale'     => (float) $l->budget_initial,
                            'montant_n'                => (float) $l->getBudgetRectifieReel(),
                            'realisation_n'            => (float) $l->engage,
                            'realisation_n_ordonnance' => (float) $l->ordonne,
                        ], null, null, $bilan);
                    }
                }
            });
        }

        return $bilan;
    }

    /** Un compte déjà au document ne voit que sa prévision actualisée changer ; les autres sont créés. */
    private function reactualiserLigne(
        CbmtExercice $cbmt,
        string $nature,
        object $ligneSource,
        array $valeursN,
        ?string $codeRepli,
        ?string $libelleRepli,
        array &$bilan
    ): void {
        $nomenclature = $ligneSource->nomenclature;

        if (!$nomenclature) {
            $this->comptePerdu($nature, $ligneSource, $codeRepli, $bilan);

            return;
        }

        $existante = CbmtLigne::where('cbmt_exercice_id', $cbmt->id)
            ->where('nature', $nature)
            ->where('nomenclature_id', $nomenclature->id)
            ->first();

        if (!$existante) {
            $this->enregistrer($cbmt, $nature, $nomenclature, $valeursN, false, $bilan,
                $codeRepli, $libelleRepli, $ligneSource);
            return;
        }

        $montant = round((float) $valeursN['montant_n'], 2);

        $champs = [];

        if (abs(round((float) $existante->montant_n, 2) - $montant) > 0.005) {
            $champs['montant_n'] = $montant;
            $bilan['mises_a_jour']++;
        }

        // Le compte était déjà au document sous une étiquette de reconduction : il porte une mesure
        // nouvelle. Promotion LR→MN uniquement — un MN choisi par l'agent ne sera jamais requalifié.
        if ($existante->type_ligne === 'LR' && $this->typeLigneSource($ligneSource, $nature) === 'MN') {
            $champs['type_ligne'] = 'MN';
            $bilan['classes_mn']++;
        }

        if ($champs !== []) {
            $existante->update($champs);
        } else {
            // Même sans montant à reprendre, la ligne a été recontrôlée à l'heure du passage : c'est
            // l'horodatage que `CbmtExercice::alerteCheminCredits()` compare aux documents, et sans ce
            // témoin le bandeau se plaindrait pour toujours d'un écart déjà vérifié.
            $existante->touch();
        }
    }

    /**
     * LR (ligne de référence reconduite) ou MN (mesure née d'un collectif).
     *
     * Même règle que EtatMouvementsCreditsService::lrMn(), mais contrôlée PAR TABLE : lrMn() ne connaît
     * que les lignes de dépense, et les identifiants des deux tables de lignes se recouvrent — un id de
     * recette pourrait être pris pour une dépense créée par un mouvement.
     */
    protected function typeLigneSource(?object $ligne, string $nature): string
    {
        if ($ligne === null) {
            return 'LR';
        }

        if ($ligne->est_issue_collectif ?? false) {
            return 'MN';
        }

        $colonne = $nature === 'ressource' ? 'nouvelle_ligne_recette_id' : 'nouvelle_ligne_depense_id';

        return MouvementCollectif::where($colonne, $ligne->id)->exists() ? 'MN' : 'LR';
    }

    /** Phrase unique du refus d'actualisation : la fiche du CBMT et les accroches doivent dire la même chose. */
    public function messageRefusActualisation(CbmtExercice $cbmt): string
    {
        return "Le CBMT {$cbmt->numero} n'est plus en brouillon (statut « {$cbmt->statut} ») : ses prévisions"
            . ' actualisées ne sont plus retouchées toutes seules. La mise à jour reste possible avec'
            . ' « Générer à partir de ' . ($cbmt->exerciceReference?->annee ?? 'N') . ' », à votre main.';
    }

    /**
     * Phrase unique du refus faute d'exercice de référence joignable. La colonne est NOT NULL : ce qui
     * arrive réellement, c'est l'exercice mis à la corbeille (#58) — la liaison devient pendante et le
     * document ne peut plus être alimenté du tout.
     */
    public function messageExercicePerdu(CbmtExercice $cbmt): string
    {
        $id = (int) $cbmt->exercice_reference_id;

        if ($id <= 0) {
            return "Aucun exercice de référence (N) : le CBMT ne peut être alimenté des enveloppes réelles."
                . ' Renseignez-le, puis générez les lignes.';
        }

        $exercice = Exercice::withTrashed()->find($id);

        if (!$exercice) {
            return "L'exercice de référence (N) du CBMT {$cbmt->numero} est introuvable (id {$id}) : aucune"
                . ' enveloppe ne peut être reprise. Renseignez un exercice existant, puis relancez le rattrapage.';
        }

        return "L'exercice de référence (N) « {$exercice->annee} » du CBMT {$cbmt->numero} est à la corbeille :"
            . ' ses enveloppes ne sont plus lisibles, donc rien n\'a été vérifié. Ressortez-le depuis la'
            . ' corbeille, puis relancez le rattrapage.';
    }

    /**
     * Le compte est-il déjà porté par une ligne mise à la corbeille du document ?
     *
     * La clef unique `(cbmt_exercice_id, nature, nomenclature_id)` reste prise par la ligne jetée :
     * en recréer une seconde fait éclater l'écriture — et dans le rattrapage déclenché par un collectif,
     * c'est l'adoption elle-même qui échouerait. La ressusciter écraserait le choix de l'agent. Le compte
     * est donc tenu à l'écart et NOMMÉ, jamais écrit en double ni réveillé en silence.
     */
    private function compteAJeter(CbmtExercice $cbmt, string $nature, $nomenclature, array &$bilan): bool
    {
        $jetee = CbmtLigne::onlyTrashed()
            ->where('cbmt_exercice_id', $cbmt->id)
            ->where('nature', $nature)
            ->where('nomenclature_id', $nomenclature->id)
            ->first();

        if (!$jetee) {
            return false;
        }

        $bilan['ecartees']++;
        $bilan['lignes_jetees'][] = [
            'nature'  => $nature,
            'code'    => $jetee->code,
            'libelle' => $jetee->libelle,
        ];

        return true;
    }

    /** Le compte d'une ligne source a disparu de la nomenclature : à nommer, jamais à passer sous silence. */
    private function comptePerdu(string $nature, ?object $ligneSource, ?string $codeRepli, array &$bilan): void
    {
        $id = $ligneSource?->nomenclature_id;
        $compte = $id ? NomenclatureBudgetaire::withTrashed()->find($id) : null;

        $bilan['sans_compte']++;
        $bilan['comptes_perdus'][] = [
            'nature'          => $nature,
            'ligne_source_id' => $ligneSource?->id,
            'nomenclature_id' => $id,
            'code'            => $codeRepli ?? $compte?->code,
            'libelle'         => $compte?->libelle,
            'a_la_corbeille'  => $compte?->deleted_at !== null,
        ];
    }

    /**
     * Corps de la notification, commun à « Générer à partir de N » et au rattrapage automatique :
     * un bilan partiel est une erreur silencieuse, donc tout ce qui n'a pas pu être écrit est dit.
     */
    public function resumeBilan(array $bilan): string
    {
        $parties = [];

        if (($bilan['creees'] ?? 0) + ($bilan['mises_a_jour'] ?? 0) > 0) {
            $parties[] = $bilan['creees'] . ' ligne(s) créée(s), ' . $bilan['mises_a_jour'] . ' mise(s) à jour.';
        }

        if (!empty($bilan['classes_mn'])) {
            $parties[] = $bilan['classes_mn'] . ' ligne(s) déjà présente(s) promue(s) MESURE NOUVELLE.';
        }

        if (!empty($bilan['sans_titre'])) {
            $parties[] = '⚠️ ' . $bilan['sans_titre']
                . ' ligne(s) sans titre : classez leur compte dans la nomenclature.';
        }

        if (!empty($bilan['ecartees'])) {
            $noms = collect($bilan['lignes_jetees'] ?? [])->map(fn($c) => ($c['code'] ?? 'compte inconnu'))->take(6)->implode(', ');

            $parties[] = "⚠️ {$bilan['ecartees']} ligne(s) tenue(s) à l'écart : le compte est déjà porté par une"
                . " ligne que vous avez mise à la corbeille de ce document, et il ne peut y figurer deux fois."
                . " Ressortez-la pour la reprendre : {$noms}.";
        }

        if (!empty($bilan['sans_compte'])) {
            $noms = collect($bilan['comptes_perdus'])->map(fn($c) => ($c['code'] ?? 'compte inconnu')
                . ($c['a_la_corbeille'] ? ' (corbeille)' : ''))->take(6)->implode(', ');

            $parties[] = '⚠️ ' . $bilan['sans_compte'] . ' ligne(s) du chemin de crédits n'
                . "'ont pas rejoint le document, leur compte n'étant plus dans la nomenclature : {$noms}. "
                . 'Rattachez-les à un compte actif ou retirez-les du budget.';
        }

        foreach ($bilan['refuses'] ?? [] as $refus) {
            $parties[] = '⛔ ' . $refus['message'];
        }

        if ($parties === []) {
            $parties[] = 'Aucune ligne à reprendre : les enveloppes visées étaient déjà conformes.';
        }

        return implode("\n", $parties);
    }
}
