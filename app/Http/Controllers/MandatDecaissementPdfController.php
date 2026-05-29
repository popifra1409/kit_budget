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

        $params       = \App\Models\ParametresStructure::where('actif', true)->first();
        $daSource     = $regie->decisionAdministrative;
        $engagement   = $daSource?->engagement;
        $nomenclature = $engagement?->nomenclaturePrincipale;

        // ✅ Matricule + Nom depuis Personnel lié via user_id
        $matricule    = '—';
        $nomRegisseur = $regie->responsable?->name ?? '—';

        if ($regie->responsable_id) {
            $user = $regie->responsable;

            // Approche 1 : user_id (confirmé dans fillable)
            $personnel = \App\Models\Personnel::where('user_id', $regie->responsable_id)
                ->first();

            // Approche 2 : email si user_id ne donne rien
            if (!$personnel && $user?->email) {
                $personnel = \App\Models\Personnel::where('email', $user->email)
                    ->first();
            }

            if ($personnel) {
                $matricule = $personnel->matricule ?? '—';
                $nomPrenom = trim(
                    ($personnel->nom     ?? '')
                        . ' '
                        . ($personnel->prenoms ?? $personnel->prenom ?? '')
                );
                $nomRegisseur = $nomPrenom ?: ($user?->name ?? '—');
            }
        }

        // ✅ Code article via getCodeArticle()
        $codeArticle = $nomenclature && method_exists($nomenclature, 'getCodeArticle')
            ? $nomenclature->getCodeArticle()
            : ($nomenclature ? substr($nomenclature->code, 0, 4) : '——');
        $codeNomenclature = $nomenclature?->code ?? '——';

        // ✅ Imputation : {exercice}-{mois}-{article}-{ligne nomenclature}
        $annee = $regie->exercice?->annee ?? date('Y');
        $mois  = $daSource?->date_decision
            ? \Carbon\Carbon::parse($daSource->date_decision)->format('m')
            : str_pad(now()->month, 2, '0', STR_PAD_LEFT);
        $imputation = "{$annee}-{$mois}-{$codeArticle}-{$codeNomenclature}";

        // ✅ Taux depuis la DA source
        $tauxIr  = (float) ($daSource?->taux_ir  ?? 0);
        $tauxTva = (float) ($daSource?->taux_tva  ?? 0);

        // ✅ Encaisse annuelle depuis champ dédié RAV
        $encaisseAnnuelle = (float) ($regie->encaisse_annuelle ?? 0);

        // ✅ Montant décaissé = montant_accorde du décaissement
        $montantDecaisse = (float) ($decaissement->montant_accorde
            ?? $decaissement->montant ?? 0);

        // ✅ Numéro CE = numéro de l'ENGAGEMENT (BE-DA26-xxxx)
        $numeroCE = $engagement?->numero     // ✅ BE-DA26-xxxx
            ?? $daSource?->numero            // fallback numéro DA
            ?? '—';

        $dateCE = $daSource?->date_decision
            ? \Carbon\Carbon::parse($daSource->date_decision)->format('d/m/Y')
            : ($engagement?->date_engagement
                ? \Carbon\Carbon::parse($engagement->date_engagement)->format('d/m/Y')
                : '—');

        // ── Lignes de détail ───────────────────────────────
        $lignes = $regie->lignes->map(function ($ligne) use (
            $tauxTva,
            $tauxIr,
            $codeNomenclature,
            $regie
        ) {
            $ttc = (float) ($ligne->montant_alloue ?? $ligne->montant ?? 0);
            $ht  = $tauxTva > 0
                ? round($ttc / (1 + $tauxTva / 100), 2)
                : $ttc;
            $tva = round($ht * $tauxTva / 100, 2);
            $ir  = round($ht * $tauxIr  / 100, 2);
            $nap = $ttc - $ir;

            return [
                'code'        => $ligne->nomenclature?->code ?? $codeNomenclature,
                'libelle'     => $ligne->libelle
                    ?? $ligne->nomenclature?->libelle
                    ?? ($regie->objet ?? $regie->libelle),
                'montant_ttc' => $ttc,
                'montant_ht'  => $ht,
                'montant_tva' => $tva,
                'montant_ir'  => $ir,
                'nap'         => $nap,
            ];
        })->toArray();

        // ── Créateur / Initiales ───────────────────────────
        $createur    = \App\Models\User::find(
            $decaissement->created_by ?? $regie->created_by
        );
        $nomCreateur = $createur?->username ?? $createur?->name ?? '—';
        $initiales   = collect(explode(' ', $nomCreateur))
            ->filter()
            ->map(fn($mot) => strtoupper(substr($mot, 0, 1)))
            ->implode('.');

        // ── Décision de déblocage ──────────────────────────
        $numDecision  = $decaissement->numero_decision ?? '—';
        $dateDecision = $decaissement->date_decision
            ? \Carbon\Carbon::parse($decaissement->date_decision)->format('d/m/Y')
            : '—';

        return Pdf::loadView('pdf.templates.mandat-decaissement', [
            'donnees' => [
                'regie'             => $regie,
                'decaissement'      => $decaissement,
                'parametres'        => $params,
                'da_source'         => $daSource,
                'lignes'            => $lignes,
                'imputation'        => $imputation,

                // ✅ Numéro engagement (BE-DA26-xxxx)
                'numero_ce'         => $numeroCE,
                'date_ce'           => $dateCE,

                // ✅ Taux DA — string affichage + float calculs
                'taux_ir'           => number_format($tauxIr,  2, ',', ''),
                'taux_tva'          => number_format($tauxTva, 2, ',', ''),
                'taux_ir_float'     => $tauxIr,
                'taux_tva_float'    => $tauxTva,

                // ✅ Encaisse depuis RAV
                'encaisse_annuelle' => $encaisseAnnuelle,

                // ✅ Montant depuis montant_accorde
                'montant_decaisse'  => $montantDecaisse,

                'num_decision'      => $numDecision,
                'date_decision'     => $dateDecision,

                // ✅ Régisseur depuis Personnel
                'matricule'         => $matricule,
                'nom_regisseur'     => strtoupper($nomRegisseur),

                // ✅ Créateur
                'nom_createur'      => $nomCreateur,
                'initiales'         => $initiales,
            ],
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('margin-top',    '10mm')
            ->setOption('margin-bottom', '20mm')
            ->setOption('margin-left',   '12mm')
            ->setOption('margin-right',  '12mm');
    }
}
