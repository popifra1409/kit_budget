<?php
// app/Services/Budget/ControlePlafondCbmt.php

namespace App\Services\Budget;

use App\Models\CbmtExercice;
use App\Models\CbmtLigne;
use App\Models\NomenclatureBudgetaire;
use App\Models\PrevisionBudgetProgramme;
use App\Services\Programmation\TitreNomenclatureService;

/**
 * Le plafond CBMT opposable à la saisie du budget programme.
 *
 * Le rapport CDMT (`CdmtExercice::getControleParTitre()`) affichait déjà plafond / programme / écart
 * par titre, sans jamais refuser quoi que ce soit. Ici vit la même formule, en version décideuse :
 * une seule implémentation du plafond, sinon l'écran et le rapport finiront par se contredire.
 *
 * Trois règles, mesurées sur le parc avant d'être écrites :
 *  - le titre d'une ligne vient de `TitreNomenclatureService::titreDe()`, PAS de
 *    `nomenclature_budgetaire.titre` : cette colonne est NULL sur les 367 lignes actives, et `titreDe()`
 *    rend le même titre que la ligne du CBMT sur 228/228 cas comparables ;
 *  - le plafond se lit par DÉCALAGE D'ANNÉE (`montant_n_plus_d`, d = année visée − année de référence du
 *    CBMT), comme le CDMT : un seul document CBMT couvre N..N+3, donc 2027 et 2028 sont plafonnées par
 *    le CBMT de 2026, sans attendre le suivant ;
 *  - la décision est prise par TITRE et par ANNÉE, sur le TOTAL du titre : la somme d'un titre peut être
 *    dépassée par trente petites lignes. Refuser une ligne et laisser ses voisines passer rendrait un
 *    total déjà hors plafond, à moitié écrit ;
 *  - l'enveloppe se compte à l'intérieur d'un exercice : la clé unique de `previsions_budget_programme`
 *    contient `exercice_id`, donc l'année 2028 peut être planifiée depuis l'exercice 2026 ET depuis
 *    l'exercice 2027. Additionner les deux doubles un même programme dans la balance.
 */
class ControlePlafondCbmt
{
    /** Nature d'écran ('depense'|'recette') → nature des lignes du CBMT. */
    public const NATURES_CBMT = ['depense' => 'depense', 'recette' => 'ressource'];

    public function __construct(private readonly TitreNomenclatureService $titres)
    {
    }

    /**
     * Plafond opposé à un titre pour une année visée.
     *
     * @return array{montant: ?float, cbmt: ?CbmtExercice, decalage: ?int, motif: ?string}
     */
    public function plafond(string $nature, int $titre, int $annee): array
    {
        $natureCbmt = self::NATURES_CBMT[$nature] ?? $nature;

        $cbmt = CbmtExercice::query()->whereNull('deleted_at')
            ->whereHas('exerciceReference')
            ->with('exerciceReference')
            ->get()
            ->filter(fn ($c) => $c->anneeReference() <= $annee && $annee <= $c->anneeReference() + 3)
            ->sortByDesc(fn ($c) => $c->anneeReference())
            ->first();

        if ($cbmt === null) {
            return ['montant' => null, 'cbmt' => null, 'decalage' => null,
                'motif' => "Aucun CBMT ne couvre l'année {$annee} : le plafond n'a pas été produit."];
        }

        $decalage = $annee - $cbmt->anneeReference();
        // `colonnesMontants()` est l'ordre d'affichage du rapport, pas un index par décalage : la
        // colonne de projection N+d se lit dans cette table, comme dans `getControleParTitre()`.
        $colonne = ['montant_n', 'montant_n_plus_1', 'montant_n_plus_2', 'montant_n_plus_3'][$decalage] ?? null;
        if ($colonne === null) {
            return ['montant' => null, 'cbmt' => $cbmt, 'decalage' => $decalage,
                'motif' => "Le CBMT de {$cbmt->anneeReference()} ne projette pas l'année {$annee}."];
        }

        $lignes = CbmtLigne::query()->where('cbmt_exercice_id', $cbmt->id)
            ->where('nature', $natureCbmt)->where('titre', $titre)
            ->whereNull('deleted_at')->get();

        if ($lignes->isEmpty()) {
            return ['montant' => null, 'cbmt' => $cbmt, 'decalage' => $decalage,
                'motif' => "Le CBMT de {$cbmt->anneeReference()} ne budgète aucun plafond pour le titre {$titre}"
                    . ' (' . $this->libelleTitre($nature, $titre) . ")."];
        }

        return ['montant' => round((float) $lignes->sum($colonne), 2),
            'cbmt' => $cbmt, 'decalage' => $decalage, 'motif' => null];
    }

    /**
     * Décider un lot de lignes pour une année : ce qui peut être écrit, ce qui est refusé et pourquoi.
     *
     * @param array<int, array{nomenclature_id: int, montant: float}> $lot les lignes QUE l'on veut écrire
     * @return array{acceptees: array<int, array>, refus: array<int, array>}
     */
    public function decider(string $nature, int $annee, array $lot, int $exerciceId): array
    {
        if ($lot === []) {
            return ['acceptees' => [], 'refus' => []];
        }

        $parTitre = [];
        foreach ($lot as $ligne) {
            $nomenclature = NomenclatureBudgetaire::find($ligne['nomenclature_id']);
            if ($nomenclature === null) {
                $parTitre['introuvable'][] = $ligne + ['libelle' => '#' . $ligne['nomenclature_id']];
                continue;
            }

            // Une ligne de l'autre nature (bouton Dépenses/Recettes falsifié, ou poste qui change de
            // type entre le chargement et l'envoi) ne serait pas plafonnée avec les bons chiffres.
            if ($nomenclature->type !== $nature) {
                $parTitre['mauvais_type'][] = $ligne + [
                    'code' => (string) $nomenclature->code,
                    'libelle' => (string) $nomenclature->libelle,
                ];
                continue;
            }

            $titre = $this->titres->titreDe($nomenclature);
            $cle = $titre === null ? 'sans-titre' : (string) $titre;
            $parTitre[$cle][] = $ligne + [
                'code' => (string) $nomenclature->code,
                'libelle' => (string) $nomenclature->libelle,
            ];
        }

        $acceptees = [];
        $refus = [];

        foreach ($parTitre as $cle => $lignes) {
            $demande = round((float) array_sum(array_column($lignes, 'montant')), 2);

            if ($cle === 'introuvable') {
                $refus[] = ['titre' => null, 'libelle' => 'Nomenclature introuvable',
                    'motif' => "La ligne de nomenclature pointée n'existe pas : aucun titre, donc aucun plafond.",
                    'plafond' => null, 'demande' => $demande, 'depassement' => null, 'nb_lignes' => count($lignes),
                    'lignes' => $this->resume($lignes), 'annee' => $annee];
                continue;
            }

            if ($cle === 'mauvais_type') {
                $refus[] = ['titre' => null, 'libelle' => 'Nature différente',
                    'motif' => 'Ces comptes ne sont pas des ' . ($nature === 'recette' ? 'recettes' : 'dépenses')
                        . ' : la saisie a changé de nature entre l’affichage et l’envoi.',
                    'plafond' => null, 'demande' => $demande, 'depassement' => null, 'nb_lignes' => count($lignes),
                    'lignes' => $this->resume($lignes), 'annee' => $annee];
                continue;
            }

            if ($cle === 'sans-titre') {
                $refus[] = ['titre' => null, 'libelle' => 'Sans titre',
                    'motif' => 'Ces comptes ne se rattachent à aucun titre du CBMT : impossible de les plafonner.',
                    'plafond' => null, 'demande' => $demande, 'depassement' => null, 'nb_lignes' => count($lignes),
                    'lignes' => $this->resume($lignes), 'annee' => $annee];
                continue;
            }

            $titre = (int) $cle;
            $p = $this->plafond($nature, $titre, $annee);

            if ($p['motif'] !== null) {
                $refus[] = ['titre' => $titre, 'libelle' => $this->libelleTitre($nature, $titre),
                    'motif' => $p['motif'], 'plafond' => null, 'demande' => $demande, 'depassement' => null,
                    'nb_lignes' => count($lignes), 'lignes' => $this->resume($lignes), 'annee' => $annee,
                    'cbmt_annee_reference' => $p['cbmt']?->anneeReference()];
                continue;
            }

            // Le total du titre ne se joue pas du seul lot soumis : les lignes déjà en base qui ne sont
            // pas redemandées ici restent acquises et comptent dans l'enveloppe.
            $dej = $this->totalDejaEnBase($nature, $titre, $annee, $exerciceId,
                array_column($lignes, 'nomenclature_id'));
            $total = round($dej + $demande, 2);

            if ($total > $p['montant']) {
                $refus[] = ['titre' => $titre, 'libelle' => $this->libelleTitre($nature, $titre),
                    'motif' => null, 'plafond' => $p['montant'], 'demande' => $total,
                    'total_deja_en_base' => $dej,
                    'depassement' => round($total - $p['montant'], 2),
                    'nb_lignes' => count($lignes), 'lignes' => $this->resume($lignes), 'annee' => $annee,
                    'cbmt_annee_reference' => $p['cbmt']->anneeReference()];
                continue;
            }

            foreach ($lignes as $l) {
                $acceptees[] = $l;
            }
        }

        return ['acceptees' => $acceptees, 'refus' => $refus];
    }

    /** Total déjà stocké pour ce titre et cette année, dans le même exercice, hors les lignes que le lot va réécrire. */
    private function totalDejaEnBase(string $nature, int $titre, int $annee, int $exerciceId, array $idsDuLot): float
    {
        return (float) PrevisionBudgetProgramme::query()
            ->where('type', 'prevision')
            ->where('exercice_id', $exerciceId)
            ->where('annee', $annee)
            ->whereNotIn('nomenclature_id', $idsDuLot)
            ->whereHas('nomenclature', fn ($q) => $q->where('type', $nature))
            ->with('nomenclature')
            ->get()
            ->filter(function ($p) use ($titre) {
                $n = $p->nomenclature ?? NomenclatureBudgetaire::find($p->nomenclature_id);

                return $n !== null && (int) $this->titres->titreDe($n) === $titre;
            })
            ->sum('montant');
    }

    /**
     * Phrase de refus, identique sur tous les écrans : la saisie du budget programme et la
     * reconduction doivent opposer le même plafond avec les mêmes chiffres.
     */
    public function messageRefus(array $refus): string
    {
        $annee = (int) $refus['annee'];

        if ($refus['motif'] !== null) {
            return $refus['motif'] . ' ' . $refus['nb_lignes']
                . ' ligne(s) non enregistrée(s) : ' . $refus['lignes'] . '.';
        }

        $dej = (float) ($refus['total_deja_en_base'] ?? 0);

        return 'CBMT de ' . $refus['cbmt_annee_reference']
            . ' : plafond ' . $this->franc($refus['plafond']) . ' F, programme demandé '
            . $this->franc($refus['demande']) . ' F'
            . ($dej > 0 ? ' (dont ' . $this->franc($dej) . ' F déjà enregistrés)' : '')
            . '. Dépassement ' . $this->franc($refus['depassement']) . ' F sur ' . $annee
            . '. ' . $refus['nb_lignes'] . ' ligne(s) non enregistrée(s) : ' . $refus['lignes'] . '.';
    }

    private function franc(?float $montant): string
    {
        return number_format((float) $montant, 0, ',', ' ');
    }

    private function libelleTitre(string $nature, int $titre): string
    {
        $titres = $nature === 'recette' ? CbmtLigne::TITRES_RESSOURCES : CbmtLigne::TITRES_DEPENSES;

        return $titres[$titre] ?? 'titre non nommé';
    }

    private function resume(array $lignes): string
    {
        $codes = array_values(array_unique(array_map(fn ($l) => $l['code'] ?? $l['libelle'] ?? '?', $lignes)));

        return implode(', ', array_slice($codes, 0, 6)) . (count($codes) > 6 ? '… (' . count($codes) . ' lignes)' : '');
    }
}
