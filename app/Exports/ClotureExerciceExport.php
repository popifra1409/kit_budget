<?php

namespace App\Exports;

use App\Models\CbmtLigne;
use App\Services\Budget\EtatsClotureService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Classeur de la clôture d'exercice : Synthèse, États et taux, Lignes, Reports, Annulations.
 * Mêmes données que le PDF (ClotureExerciceExportController::donnees()).
 */
class ClotureExerciceExport implements WithMultipleSheets
{
    public function __construct(protected array $d) {}

    public function sheets(): array
    {
        $c = $this->d['cloture'];
        $t = $c->totaux ?? [];
        $etats = $this->d['etats'];

        // ── Synthèse ──
        $synthese = [
            ['Exercice', $c->exercice->annee],
            ['Budget', $c->budget->code . ' — ' . $c->budget->libelle],
            ['Statut de la clôture', $c->statut_label],
            ['Phase d\'exécution', $this->d['periode']['libelle'] . ' (fin de la période complémentaire : ' . $this->d['periode']['fin_complementaire']->format('d/m/Y') . ')'],
            ['', ''],
            ['Dotation actualisée', (float) ($t['dotation'] ?? 0)],
            ['Engagé', (float) ($t['engage'] ?? 0)],
            ['Payé', (float) ($t['paye'] ?? 0)],
            ['Engagé non payé', (float) ($t['engage_non_paye'] ?? 0)],
            ['Reports retenus', (float) ($t['report_retenu'] ?? 0)],
            ['Annulations', (float) ($t['annule'] ?? 0)],
            ['Taux d\'annulation (%)', (float) ($t['taux_annulation'] ?? 0)],
            ['Engagé non payé NON reporté', (float) ($t['non_reporte_non_paye'] ?? 0)],
            ['', ''],
            ['Arrêté de report', trim(($c->reference_arrete ?? '') . ' ' . ($c->date_arrete?->format('d/m/Y') ?? ''))],
            ['Avis du CA', $c->avis_ca ? (($c->avis_ca === 'conforme' ? 'Conforme' : 'Défavorable') . ' — ' . $c->reference_avis_ca) : ''],
            ['Collectif de reports (N+1)', $c->collectifReports?->numero ?? ''],
        ];

        // ── États et taux ──
        $etatsTaux = [['ÉTATS DE FIN DE GESTION', '', '']];
        foreach (EtatsClotureService::LIBELLES_ETATS as $cle => $libelle) {
            $e = $etats['etats'][$cle];
            $etatsTaux[] = [$libelle, (float) $e['montant'], match ($cle) {
                'rar'           => $e['note'] ?? ($e['nombre'] . ' recette(s)'),
                'reste_a_payer' => $e['nombre'] . ' OP',
                'arrieres'      => $e['nombre'] . ' OP',
                'dette'         => 'dont engagé sans service fait : ' . number_format((float) $e['engage_sans_service_fait'], 0, ',', ' '),
                default         => '',
            }];
        }
        $etatsTaux[] = ['', '', ''];
        $etatsTaux[] = ['TAUX DE FIN DE GESTION', 'Valeur (%)', 'Base'];
        foreach (EtatsClotureService::LIBELLES_TAUX as $cle => $libelle) {
            $x = $etats['taux'][$cle];
            $etatsTaux[] = [$libelle, $x['valeur'], $x['libelle_base'] . ' : ' . number_format((float) $x['base'], 0, ',', ' ')];
        }

        // ── Lignes ──
        $colonnes = ['dotation', 'engage', 'liquide', 'ordonnance', 'paye', 'engage_non_paye', 'report_propose', 'report_retenu', 'annule'];
        $lignes = [];
        $gras = [];
        $rang = 2;
        foreach ($this->d['parTitre'] as $titre => $groupe) {
            $lignes[] = array_merge(
                ['', $titre === 'sans' ? 'Sans titre' : "Titre {$titre} — " . (CbmtLigne::TITRES_DEPENSES[$titre] ?? ''), '', ''],
                array_map(fn($col) => (float) $groupe->sum($col), $colonnes)
            );
            $gras[] = $rang++;
            foreach ($groupe as $l) {
                $lignes[] = array_merge(
                    [(string) $l->code, $l->libelle, $l->titre ? 'T' . $l->titre : '', $l->sousProgramme?->code ?? ''],
                    array_map(fn($col) => (float) $l->{$col}, $colonnes)
                );
                $rang++;
            }
        }
        $lignes[] = array_merge(['', 'TOTAL', '', ''], array_map(fn($col) => (float) $this->d['lignes']->sum($col), $colonnes));
        $gras[] = $rang;

        $enTetesLignes = ['Compte', 'Libellé', 'Titre', 'Sous-programme', 'Dotation', 'Engagé', 'Liquidé', 'Ordonnancé', 'Payé', 'Engagé non payé', 'Report proposé', 'Report retenu', 'Annulé'];

        // ── Reports et annulations ──
        $reports = $this->d['reports']->map(fn($l) => [
            (string) $l->code,
            $l->libelle,
            $l->titre ? 'T' . $l->titre : '',
            $l->sousProgramme?->code ?? '',
            (float) $l->engage_non_paye,
            (float) $l->report_propose,
            (float) $l->report_retenu,
            $l->motif_ecart ?? ''
        ])->all();
        $reports[] = ['', 'TOTAL', '', '', (float) $this->d['reports']->sum('engage_non_paye'), (float) $this->d['reports']->sum('report_propose'), (float) $this->d['reports']->sum('report_retenu'), ''];

        $annules = $this->d['lignes']->where('annule', '>', 0)->values();
        $annulations = $annules->map(fn($l) => [
            (string) $l->code,
            $l->libelle,
            $l->titre ? 'T' . $l->titre : '',
            (float) $l->dotation,
            (float) $l->paye,
            (float) $l->report_retenu,
            (float) $l->annule
        ])->all();
        $annulations[] = ['', 'TOTAL', '', (float) $annules->sum('dotation'), (float) $annules->sum('paye'), (float) $annules->sum('report_retenu'), (float) $annules->sum('annule')];

        return [
            new ClotureFeuille('Synthèse', ['Élément', 'Valeur'], $synthese, [], 'B'),
            new ClotureFeuille('États et taux', ['Élément', 'Montant / valeur', 'Détail'], $etatsTaux, [2, 9], 'B'),
            new ClotureFeuille('Lignes', $enTetesLignes, $lignes, $gras, 'E'),
            new ClotureFeuille('Reports', ['Compte', 'Libellé', 'Titre', 'Sous-programme', 'Engagé non payé', 'Report proposé', 'Report retenu', 'Motif d\'écart'], $reports, [count($reports) + 1], 'E'),
            new ClotureFeuille('Annulations', ['Compte', 'Libellé', 'Titre', 'Dotation', 'Payé', 'Report retenu', 'Annulé'], $annulations, [count($annulations) + 1], 'D'),
        ];
    }
}
