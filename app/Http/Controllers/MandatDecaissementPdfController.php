<?php

namespace App\Http\Controllers;

use App\Models\RegieAvance;
use App\Models\DecaissementRegie;
use Barryvdh\DomPDF\Facade\Pdf;

class MandatDecaissementPdfController extends Controller
{
    public function apercu(RegieAvance $regie, DecaissementRegie $decaissement)
    {
        return $this->generer($regie, $decaissement)->stream(
            "Mandat_{$regie->numero}_{$decaissement->id}.pdf"
        );
    }

    public function telecharger(RegieAvance $regie, DecaissementRegie $decaissement)
    {
        return $this->generer($regie, $decaissement)->download(
            "Mandat_{$regie->numero}_{$decaissement->id}.pdf"
        );
    }

    protected function generer(RegieAvance $regie, DecaissementRegie $decaissement)
    {
        $regie->load([
            'responsable',
            'exercice',
            'budget',
            'lignes.nomenclature',
            'decisionAdministrative.engagement.nomenclaturePrincipale',
        ]);

        $params     = \App\Models\ParametresStructure::where('actif', true)->first();
        $daSource   = $regie->decisionAdministrative;
        $engagement = $daSource?->engagement;
        $nomenclature = $engagement?->nomenclaturePrincipale;

        // ✅ Matricule depuis Personnel lié au User responsable
        $matricule = '—';
        $nomRegisseur = $regie->responsable?->name ?? '—';
        if ($regie->responsable_id) {
            $personnel = \App\Models\Personnel::where('user_id', $regie->responsable_id)->first();
            if ($personnel) {
                $matricule    = $personnel->matricule ?? '—';
                $nomRegisseur = trim(($personnel->nom ?? '') . ' ' . ($personnel->prenoms ?? ''))
                    ?: $regie->responsable?->name ?? '—';
            }
        }

        // ✅ Code article via getCodeArticle() comme dans certificat-engagement
        $codeArticle      = $nomenclature && method_exists($nomenclature, 'getCodeArticle')
            ? $nomenclature->getCodeArticle()
            : ($nomenclature ? substr($nomenclature->code, 0, 4) : '——');
        $codeNomenclature = $nomenclature?->code ?? '——';

        // ✅ Imputation : {exercice}-{2chiffres mois}-{article}-{ligne nomenclature}
        $annee = $regie->exercice?->annee ?? date('Y');
        $mois  = $daSource?->date_decision
            ? \Carbon\Carbon::parse($daSource->date_decision)->format('m')
            : str_pad(now()->month, 2, '0', STR_PAD_LEFT);
        $imputation = "{$annee}-{$mois}-{$codeArticle}-{$codeNomenclature}";

        // ✅ Créateur du décaissement
        $createur    = \App\Models\User::find($decaissement->created_by ?? $regie->created_by);
        $nomCreateur = $createur?->username ?? $createur?->name ?? '—';
        // Initiales
        $initiales = collect(explode(' ', $nomCreateur))
            ->map(fn($mot) => strtoupper(substr($mot, 0, 1)))
            ->implode('.');

        // ── Lignes de détail ───────────────────────────────
        $lignes = $regie->lignes->map(fn($ligne) => [
            'code'        => $ligne->nomenclature?->code ?? $codeNomenclature,
            'libelle'     => $ligne->libelle ?? $ligne->nomenclature?->libelle
                ?? ($regie->objet ?? $regie->libelle),
            'montant_ttc' => (float) ($ligne->montant_alloue ?? $ligne->montant ?? 0),
            'montant_ht'  => (float) ($ligne->montant_alloue ?? $ligne->montant ?? 0),
            'montant_tva' => 0,
            'montant_ir'  => (float) ($ligne->montant_alloue ?? 0)
                * ($daSource?->taux_ir ?? 5.5) / 100,
        ])->toArray();

        $tauxIr       = (float) ($daSource?->taux_ir ?? 5.5);
        $numDecision  = $decaissement->numero_decision ?? '—';
        $dateDecision = $decaissement->date_decision
            ? \Carbon\Carbon::parse($decaissement->date_decision)->format('d/m/Y')
            : '—';

        return Pdf::loadView('pdf.templates.mandat-decaissement', [
            'donnees' => [
                'regie'         => $regie,
                'decaissement'  => $decaissement,
                'parametres'    => $params,
                'da_source'     => $daSource,
                'lignes'        => $lignes,
                'imputation'    => $imputation,
                'taux_ir'       => number_format($tauxIr, 1, ',', ''),
                'num_decision'  => $numDecision,
                'date_decision' => $dateDecision,
                'matricule'     => $matricule,
                'nom_regisseur' => strtoupper($nomRegisseur),
                'nom_createur'  => $nomCreateur,
                'initiales'     => $initiales,
            ],
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top',    '10mm')
            ->setOption('margin-bottom', '20mm')
            ->setOption('margin-left',   '12mm')
            ->setOption('margin-right',  '12mm');
    }
}
