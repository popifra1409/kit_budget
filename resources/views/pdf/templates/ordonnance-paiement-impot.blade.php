@extends('pdf.layouts.master', ['typeHeader' => 'op', 'typeFooter' => 'op', 'orientation' => 'landscape'])

@php
    $ordonnance = $donnees['_raw'];

    if (!$ordonnance->relationLoaded('engagement')) {
        $ordonnance->load('engagement.engageable');
    }

    $engagement = $ordonnance->engagement;
    $documentSource = $engagement?->engageable;

// ==========================================
// ✅ RÉCUPÉRATION DE LA NOMENCLATURE
// ==========================================
$nomenclature = null;
if ($engagement) {
    $engagement->load('nomenclaturePrincipale');
    $nomenclature = $engagement->nomenclaturePrincipale;
}

// ==========================================
// ✅ RÉCUPÉRATION DE LA HIÉRARCHIE BUDGÉTAIRE
// ==========================================
$tache = null;
$activite = null;
$action = null;
$programme = null;
$sousProgramme = null;
$objectif = null;

// Codes budgétaires
$codeProgramme = '';
$codeSousProgramme = '';
$codeAction = '';
$codeActivite = '';
$codeTache = '';
$codeArticle = '';
$codeParagraphe = '';
$codeChapitre = '';

if ($nomenclature) {
    // Tâche
    $tache = $nomenclature->tache ?? $nomenclature->taches()->first();

    if ($tache) {
        // Charger toute la hiérarchie
        $tache->load('activite.action.programme.parent');

        $activite = $tache->activite;
        $action = $activite?->action;
        $programme = $action?->programme;

        // ✅ Détection du sous-programme
        if ($programme) {
            if ($programme->estSousProgramme()) {
                // C'est un sous-programme
                    $sousProgramme = $programme;
                    $programme = $programme->parent;
                } else {
                    // C'est un programme principal
                $sousProgramme = null;
            }

            // Récupérer l'objectif
                try {
                    if (method_exists($programme, 'objectifPrincipal')) {
                        $objectif = $programme->objectifPrincipal;
                    } elseif (method_exists($programme, 'objectifsPrincipaux')) {
                        $objectifs = $programme->objectifsPrincipaux;
                        if ($objectifs instanceof \Illuminate\Support\Collection) {
                            $objectif = $objectifs->first();
                        } else {
                            $objectif = $objectifs;
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Erreur récupération objectif', [
                        'programme_id' => $programme->id,
                        'error' => $e->getMessage(),
                    ]);
                    $objectif = null;
                }
            }

            // ✅ EXTRAIRE LES CODES
            $codeTache = $tache->code ?? '';
        }

        if ($activite) {
            $codeActivite = $activite->code ?? '';
        }

        if ($action) {
            $codeAction = $action->code ?? '';
        }

        if ($sousProgramme) {
            $codeSousProgramme = $sousProgramme->code ?? '';
        }

        if ($programme) {
            $codeProgramme = $programme->code ?? '';
        }

        // ✅ CODES DEPUIS LA NOMENCLATURE
        // Chapitre : 2 premiers caractères du code
        $codeChapitre = substr($nomenclature->code, 0, 2);

        // Paragraphe/Compte : code complet
        $codeParagraphe = $nomenclature->code;

        // Article : méthode getCodeArticle() si elle existe
        if (method_exists($nomenclature, 'getCodeArticle')) {
            $codeArticle = $nomenclature->getCodeArticle();
        } else {
            // Sinon, extraire depuis le code (exemple: 4 premiers caractères)
            $codeArticle = substr($nomenclature->code, 0, 4);
        }
    }

    // Reverseur
    $reverseur = null;
    if ($engagement && $documentSource) {
        if ($engagement->estBonCommande()) {
            $reverseur = $documentSource->fournisseur;
        } elseif ($engagement->estDecision()) {
            $reverseur = $documentSource->personnel;
        }
    }
    if (!$reverseur && $ordonnance->beneficiaire) {
        $reverseur = $ordonnance->beneficiaire;
    }

    $nomReverseur = $reverseur->raison_sociale ?? ($reverseur->nom_complet ?? ($reverseur->name ?? 'N/A'));
    $nomBeneficiaire = 'LE DIRECTEUR DES IMPOTS';

    // Montants
    $detailImpots = $ordonnance->getDetailImpots();
    $montantTotalImpots = $detailImpots['total'];

    $parametres = \App\Models\ParametresStructure::where('actif', true)->first();
@endphp

@section('title', 'Ordonnance de Paiement - Impot')

@section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($montantTotalImpots) }}
@endsection

@section('additional_styles')
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.05;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .border-all {
            border: 1px solid #000;
        }

        .border-right {
            border-right: 1px solid #000;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: bold;
        }

        .font-tiny {
            font-size: 7pt;
        }

        .padding-3 {
            padding: 3px;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            color: #999;
            font-size: 16px;
        }
    </style>
@endsection

@section('content')

    <table>
        <tr>
            {{-- ================= LEFT ================= --}}
            <td style="width:30%; vertical-align:top; padding:5px;" class="border-right">
<div class="font-bold" style="font-size: 8pt; text-align:center;">IMPUTATION BUDGETAIRE</div>
                <div class="font-tiny" style="font-style: italic;  text-align:center;">BUDGETARY CHARGE</div>
                <div style="margin-top: 5px; font-size: 8pt;">
                    @if ($codeProgramme)
                        <div>• <span class="font-bold" style="font-size: 8pt;">PROGRAMME : </span>{{ $codeProgramme }} -
                            {{ $programme->libelle ?? '' }}</div>
                    @endif

                    @if ($codeSousProgramme)
                        <div>• <span class="font-bold" style="font-size: 8pt;">SOUS-PROGRAMME :
                            </span>{{ $codeSousProgramme }} - {{ $sousProgramme->libelle ?? '' }}</div>
                    @endif
                    @if ($codeAction)
                        <div>• <span class="font-bold" style="font-size: 7.5pt;">ACTION : </span>{{ $codeAction }} -
                            {{ $action->libelle ?? '' }}</div>
                    @endif
                    @if ($codeActivite)
                        <div>• <span class="font-bold" style="font-size: 7.5pt;">ACTIVITE : </span>{{ $codeActivite }} -
                            {{ $activite->libelle ?? '' }}</div>
                    @endif
                    @if ($codeArticle)
                        <div>• <span class="font-bold" style="font-size: 7.5pt;">ARTICLE : </span>{{ $codeArticle }}</div>
                    @endif
                    @if ($codeParagraphe)
                        <div>• <span class="font-bold" style="font-size: 7.5pt;">PARAGRAPHE : </span>{{ $codeParagraphe }}
                        </div>
                    @endif
                </div>
                {{-- Objet de la dépense --}}
                    <div style="border-bottom: 2px solid #000; margin: 5px 0;"></div>
                <div class="font-bold">OBJET DE LA DEPENSE</div>
                <div class="font-tiny">SUBJECT OF EXPENDITURE</div>

                <div style="margin-top:3px;">
                    {{ $ordonnance->objet ?? 'Reversement des impots et taxes' }}
                </div>
            </td>
            <td style="width:40%; vertical-align:top; padding:5px;" class="border-right">
                <div style="margin-top:6px; text-align:center; font-weight:bold;">
                    Reversement Impots et Taxes
                </div>

                <div style="text-align:center; font-weight:bold;">
                    {{ $nomReverseur }}
                </div>
                {{-- DETAIL IMPOTS --}}
                <div style="margin-top:8px;">
                    <div class="font-bold">DETAIL IMPOTS</div>

                    <table class="border-all" style="margin-top:3px;">
                        @foreach ($detailImpots as $key => $val)
                            @if ($key !== 'total' && $val > 0)
                                <tr>
                                    <td class="border-right padding-3">{{ strtoupper($key) }}</td>
                                    <td class="padding-3 text-center">{{ number_format($val, 0, ',', ' ') }}</td>
                                </tr>
                            @endif
                        @endforeach

                        <tr style="background:#f0f0f0; font-weight:bold;">
                            <td class="border-right padding-3">TOTAL</td>
                            <td class="padding-3 text-center">{{ number_format($montantTotalImpots, 0, ',', ' ') }}</td>
                        </tr>
                    </table>
                </div>
                {{-- VISA --}}
                <div style="border-bottom:2px solid #000; margin:10px 0 0;"></div>

                <div class="border-all text-center" style="margin-top:3px;">
                    <div class="font-bold">Visa Contrôle Financier</div>
                    <div class="font-tiny">Financial Control Stamp</div>
                </div>

                <table>
                    <tr>
                        <td style="height:190px; width:40%; position:relative;">
                            <div class="watermark">Visa engagement</div>
                        </td>
                        <td style="height:190px; width:60%; position:relative;">
                            <div class="watermark">Visa budgétaire</div>
                        </td>
                    </tr>
                </table>
            </td>

            {{-- ================= RIGHT ================= --}}
            <td style="width:30%; vertical-align:top; padding:5px;">

                {{-- MONTANTS --}}
                <table style="margin-bottom:8px;">
                    <tr>
                        <td>
                            <div style="font-size: 8pt; line-height: 1.1; text-align:left; margin-bottom: 35px;">
                                <div style="font-weight: bold;">
                                    Vu, bon à payer
                                </div>
                                <div style="font-style: italic;">
                                    Votes available
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width:60%; padding:0;">
                            <div class="font-bold">Montant brut</div>
                            <div class="font-tiny">Gross amount</div>
                        </td>
                        <td style="padding:0;">
                            <div class="border-all text-center padding-3">0</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0;">
                            <div class="font-bold">A PRECOMPTER</div>
                            <div class="font-tiny">TO BE DEDUCTED</div>
                        </td>
                        <td style="padding:0;">
                            <div class="border-all text-center padding-3">
                                {{ number_format($montantTotalImpots, 0, ',', ' ') }}
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0;">
                            <div class="font-bold">Net à payer</div>
                            <div class="font-tiny">Net amount</div>
                        </td>
                        <td style="padding:0;">
                            <div class="border-all text-center padding-3" style="background:#f0f0f0;">
                                {{ number_format($montantTotalImpots, 0, ',', ' ') }}
                            </div>
                        </td>
                    </tr>
                </table>

                {{-- LETTRES --}}
                <div class="border-all text-center padding-3" style="margin-top:5px;">
                    <strong>@yield('montant_lettres')</strong>
                </div>


                {{-- SIGNATURE --}}
                <div style="border-bottom: 2px solid #000; margin: 5px 0;"></div>
                <div style="margin-top: 3px;">
                    <div style="font-size: 8pt;">En vertu des crédits ouverts au titre de l'imputation
                        budgétaire désignée, l'<strong>Ordonnateur</strong> soussigné ordonne sur la caisse de
                        {{ strtoupper($parametres->sigle) }}, le paiment de la créance de détailée ci-dessus.</div>
                    <div class="font-tiny" style="font-style: italic;">Pursuant to the appropriations opened under the
                        designated budgetary allocation, the undersigned <strong>Authorizing Officer</strong> orders, from
                        {{ strtoupper($parametres->sigle) }} treasury, the payment of the above-detailed debt.
                    </div>
                </div>
                {{-- Date et signature --}}
                <div style="margin-top: 20px;">
                    <div class="font-bold" style="font-size: 8pt;">Yaoundé, le _____________</div>
                    <div class="font-tiny" style="font-style: italic;">Yaounde, the</div>
                    <div style="margin-top: 15px; text-align: right;">
                        <div class="font-bold" style="font-size: 7.5pt;">(Signature et timbre de l'ordonnateur)</div>
                        <div class="font-tiny" style="font-style: italic;">(Signature and stamp)</div>
                    </div>
                </div>
            </td>
        </tr>
    </table>
@endsection
