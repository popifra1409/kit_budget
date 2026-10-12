<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Compte administratif (classeur de travail) : synthèse, recettes, dépenses par titre, par sous-programme, par ligne. */
class CompteAdministratifExport implements WithMultipleSheets
{
    public function __construct(protected array $ca) {}

    public function sheets(): array
    {
        $R = $this->ca['recettes'];
        $D = $this->ca['depenses'];
        $res = $this->ca['resultat'];
        $colsD = ['initial', 'collectifs', 'virements', 'definitif', 'engage', 'liquide', 'ordonnance', 'paye', 'reste_a_payer', 'report', 'annule'];
        $entD = ['Initiaux', 'Collectifs', 'Virements', 'Définitifs', 'Engagés', 'Liquidés', 'Ordonnancés', 'Payés', 'Restes à payer', 'Reportés', 'Annulés'];
        $colsR = ['initial', 'collectifs', 'definitif', 'attendu', 'recouvre', 'rar'];
        $entR = ['Initiales', 'Collectifs', 'Définitives', 'Attendues', 'Recouvrées', 'Restes à recouvrer'];
        $val = fn(array $l, array $cols) => array_map(fn($c) => (float) ($l[$c] ?? 0), $cols);

        $synthese = [
            ['Exercice', $this->ca['exercice']->annee],
            ['Budget', $this->ca['budget']->code . ' — ' . $this->ca['budget']->libelle],
            ['', ''],
            ['Recettes : prévisions définitives', $R['totaux']['definitif']],
            ['Recettes recouvrées', $res['recettes_recouvrees']],
            ['Dépenses : crédits définitifs', $D['totaux']['definitif']],
            ['Dépenses payées', $res['depenses_payees']],
            ['Résultat de caisse', $res['resultat_caisse']],
            ['Restes à recouvrer', $res['restes_a_recouvrer']],
            ['Restes à payer', $res['restes_a_payer']],
            ['Résultat corrigé', $res['resultat_corrige']],
            ['', ''],
            ["Taux d'exécution des recettes (%)", $this->ca['taux']['execution_recettes']],
            ["Taux d'engagement (%)", $this->ca['taux']['engagement']],
            ['Taux de paiement (%)', $this->ca['taux']['paiement']],
        ];

        $recettes = $R['lignes']->map(fn($l) => array_merge([(string) $l['code'], $l['libelle'], $l['titre'] ? 'T' . $l['titre'] : ''], $val($l, $colsR)))->all();
        $recettes[] = array_merge(['', 'TOTAL', ''], $val($R['totaux'], $colsR));

        $depenses = $D['lignes']->map(fn($l) => array_merge([(string) $l['code'], $l['libelle'], $l['titre'] ? 'T' . $l['titre'] : '', $l['sous_programme'] ?? ''], $val($l, $colsD)))->all();
        $depenses[] = array_merge(['', 'TOTAL', '', ''], $val($D['totaux'], $colsD));

        $parTitre = $D['parTitre']->map(fn($t) => array_merge([$t['libelle']], $val($t, $colsD)))->all();
        $parTitre[] = array_merge(['TOTAL'], $val($D['totaux'], $colsD));

        $colsSp = ['definitif', 'engage', 'paye', 'reste_a_payer', 'report', 'annule'];
        $parSp = $D['parSousProgramme']->map(fn($s) => array_merge([$s['libelle']], $val($s, $colsSp)))->all();

        return [
            new ClotureFeuille('Synthèse', ['Élément', 'Valeur'], $synthese, [], 'B'),
            new ClotureFeuille('Recettes', array_merge(['Compte', 'Libellé', 'Titre'], $entR), $recettes, [count($recettes) + 1], 'D'),
            new ClotureFeuille('Dépenses par titre', array_merge(['Titre'], $entD), $parTitre, [count($parTitre) + 1], 'B'),
            new ClotureFeuille('Par sous-programme', ['Sous-programme', 'Définitifs', 'Engagés', 'Payés', 'Restes à payer', 'Reportés', 'Annulés'], $parSp, [], 'B'),
            new ClotureFeuille('Dépenses par ligne', array_merge(['Compte', 'Libellé', 'Titre', 'Sous-programme'], $entD), $depenses, [count($depenses) + 1], 'E'),
        ];
    }
}
