@extends('pdf.layouts.master', ['typeHeader' => 'op', 'typeFooter' => 'op', 'orientation' => 'landscape'])

@php
$ordonnance = $donnees['_raw'];

if (!$ordonnance->relationLoaded('engagement')) {
$ordonnance->load('engagement.engageable');
}

$engagement = $ordonnance->engagement;
$documentSource = $engagement?->engageable;

// ── Nomenclature ──────────────────────────────────────────
$nomenclature = null;
if ($engagement) {
$engagement->load('nomenclaturePrincipale');
$nomenclature = $engagement->nomenclaturePrincipale;
}

// ── Hiérarchie budgétaire ─────────────────────────────────
$tache = $activite = $action = $programme = $sousProgramme = $objectif = null;
$codeProgramme = $codeSousProgramme = $codeAction = $codeActivite = '';
$codeTache = $codeArticle = $codeParagraphe = $codeChapitre = '';

if ($nomenclature) {
$tache = $nomenclature->tache ?? $nomenclature->taches()->first();
if ($tache) {
$tache->load('activite.action.programme.parent');
$activite = $tache->activite;
$action = $activite?->action;
$programme = $action?->programme;

if ($programme) {
if ($programme->estSousProgramme()) {
$sousProgramme = $programme;
$programme = $programme->parent;
} else {
$sousProgramme = null;
}

if ($sousProgramme) {
$codeSousProgrammeBrut = $sousProgramme->code ?? '';
$chiffres = preg_replace('/[^0-9]/', '', $codeSousProgrammeBrut);
$codeSousProgramme = $chiffres !== ''
? '(' . (int) $chiffres . ')'
: $codeSousProgrammeBrut;
}

if ($programme) {
$codeProgrammeBrut = $programme->code ?? '';
$chiffresProg = preg_replace('/[^0-9]/', '', $codeProgrammeBrut);
$codeProgramme = $chiffresProg !== ''
? '(' . (int) $chiffresProg . ')'
: $codeProgrammeBrut;
}

try {
if (method_exists($programme, 'objectifPrincipal')) {
$objectif = $programme->objectifPrincipal;
} elseif (method_exists($programme, 'objectifsPrincipaux')) {
$objectifs = $programme->objectifsPrincipaux;
$objectif = $objectifs instanceof \Illuminate\Support\Collection
? $objectifs->first() : $objectifs;
}
} catch (\Exception $e) {
\Log::warning('Erreur objectif', [
'programme_id' => $programme->id,
'error' => $e->getMessage(),
]);
$objectif = null;
}
}

$codeTache = $tache->code ?? '';
$codeActivite = $activite->code ?? '';
$codeAction = $action->code ?? '';

if ($sousProgramme) $codeSousProgramme = $sousProgramme->code ?? '';
if ($programme) $codeProgramme = $programme->code ?? '';
}

$codeChapitre = substr($nomenclature->code, 0, 2);
$codeParagraphe = $nomenclature->code;
$codeArticle = method_exists($nomenclature, 'getCodeArticle')
? $nomenclature->getCodeArticle()
: substr($nomenclature->code, 0, 4);
}

// ── Réverseur ─────────────────────────────────────────────
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

$nomReverseur = $reverseur->raison_sociale
?? ($reverseur->nom_complet ?? ($reverseur->name ?? 'N/A'));
$nomBeneficiaire = 'LE RECEVEUR';

// ── Détail impôts ─────────────────────────────────────────
$detailImpots = $ordonnance->getDetailImpots();
$montantTotalImpots = $detailImpots['total'];

// ✅ Séparer IR et IRNC explicitement depuis le document source (DA)
$montantIr = 0;
$montantIrnc = 0;
$tauxIr = 0;
$tauxIrnc = 0;

if ($engagement->estDecision() && $documentSource) {
$montantIr = (float) ($documentSource->montant_ir ?? 0);
$montantIrnc = (float) ($documentSource->montant_irnc ?? 0);
$tauxIr = (float) ($documentSource->taux_ir ?? 0);
$tauxIrnc = (float) ($documentSource->taux_irnc ?? 0);
}

// ✅ Labels avec taux
$labelIr = $tauxIr > 0
? 'IR (' . rtrim(rtrim(number_format($tauxIr, 2, ',', ''), '0'), ',') . '%)'
: 'IR';
$labelIrnc = $tauxIrnc > 0
? 'IR(NC) (' . rtrim(rtrim(number_format($tauxIrnc, 2, ',', ''), '0'), ',') . '%)'
: 'IR(NC)';

$parametres = \App\Models\ParametresStructure::where('actif', true)->first();

// ── Pied de page ──────────────────────────────────────────
$dateImpression = now()->format('d/m/Y à H:i');
$dateCreation = '—';
$nomCreateur = '—';
$sigle = $parametres->sigle ?? 'CHUY';
$numeroDocument = $documentSource?->numero ?? $engagement->numero ?? '—';

$createurId = $documentSource?->created_by ?? $ordonnance->created_by ?? null;

if ($createurId) {
$createur = \App\Models\User::find($createurId);
if ($createur) {
$nomCreateur = $createur->username
?? $createur->login
?? $createur->name
?? '—';
}
}

$createdAt = $documentSource?->created_at ?? $ordonnance->created_at;
if ($createdAt) {
$dateCreation = \Carbon\Carbon::parse($createdAt)->format('d/m/Y à H:i');
}
@endphp

@section('title', 'Ordonnance de Paiement - Impôt')

@section('montant_lettres')
{{ \App\Helpers\NombreEnLettres::montantCFA($montantTotalImpots) }}
@endsection

@section('additional_styles')
<style>
    @page {
        size: A4 landscape;
        margin: 8mm 8mm 16mm 8mm;
        /* ✅ Marge basse pour le pied de page */
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

    /* ✅ Ligne IR — distinct de IRNC */
    .row-ir {
        background: #fff8e1;
    }

    .row-irnc {
        background: #fce4ec;
    }

    /* ✅ Pied de page fixe */
    .pdf-footer-impot {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: 0.9cm;
        border-top: 1px solid #ccc;
        padding-top: 2px;
        font-size: 6.5pt;
        color: #000;
        background: #fff;
    }

    .pdf-footer-impot table {
        width: 100%;
        border-collapse: collapse;
    }

    .pdf-footer-impot td {
        border: none;
        padding: 0 3px;
        vertical-align: middle;
        font-size: 6.5pt;
        color: #000;
    }
</style>
@endsection

@section('content')

{{-- ✅ Pied de page fixe --}}
<div class="pdf-footer-impot">
    <table>
        <tr>
            <td style="width:30%; text-align:left;">
                <strong>Imprimé le :</strong> {{ $dateImpression }}
            </td>
            <td style="width:35%; text-align:center; font-weight:bold;">
                {{ $sigle }} — N° {{ $numeroDocument }}
            </td>
            <td style="width:35%; text-align:right;">
                <strong>Créé le :</strong> {{ $dateCreation }}
                &nbsp;|&nbsp;
                <strong>Par :</strong> {{ $nomCreateur }}
            </td>
        </tr>
    </table>
</div>

<table>
    <tr>

        {{-- ══ COLONNE GAUCHE : Imputation budgétaire ════════════ --}}
        <td style="width:30%; vertical-align:top; padding:5px;" class="border-right">
            <div class="font-bold" style="font-size:8pt; text-align:center;">
                IMPUTATION BUDGETAIRE
            </div>
            <div class="font-tiny" style="font-style:italic; text-align:center;">
                BUDGETARY CHARGE
            </div>

            <div style="margin-top:5px; font-size:8pt;">
                @if ($codeSousProgramme)
                <div>• <span class="font-bold" style="font-size:8pt;">SOUS-PROGRAMME :</span>
                    {{ $codeSousProgramme }} - {{ $sousProgramme->libelle ?? '' }}
                </div>
                @elseif ($codeProgramme)
                <div>• <span class="font-bold" style="font-size:8pt;">PROGRAMME :</span>
                    {{ $codeProgramme }} - {{ $programme->libelle ?? '' }}
                </div>
                @endif

                @if ($codeAction)
                <div>• <span class="font-bold" style="font-size:7.5pt;">ACTION :</span>
                    {{ $codeAction }} - {{ $action->libelle ?? '' }}
                </div>
                @endif

                @if ($codeActivite)
                <div>• <span class="font-bold" style="font-size:7.5pt;">ACTIVITE :</span>
                    {{ $codeActivite }} - {{ $activite->libelle ?? '' }}
                </div>
                @endif

                @if ($codeArticle)
                <div>• <span class="font-bold" style="font-size:7.5pt;">ARTICLE :</span>
                    {{ $codeArticle }}
                </div>
                @endif

                @if ($codeParagraphe)
                <div>• <span class="font-bold" style="font-size:7.5pt;">PARAGRAPHE :</span>
                    {{ $codeParagraphe }}
                </div>
                @endif
            </div>

            <div style="border-bottom:2px solid #000; margin:5px 0;"></div>
            <div class="font-bold">OBJET DE LA DEPENSE</div>
            <div class="font-tiny" style="font-style:italic;">SUBJECT OF EXPENDITURE</div>
            <div style="margin-top:3px; font-size:8pt;">
                {{ $ordonnance->objet ?? 'Reversement des impots et taxes' }}
            </div>
        </td>

        {{-- ══ COLONNE CENTRALE ═══════════════════════════════════ --}}
        <td style="width:40%; vertical-align:top; padding:5px;" class="border-right">

            <div style="margin-top:6px; text-align:center; font-weight:bold; font-size:9pt;">
                Reversement Impôts et Taxes
            </div>

            {{-- ✅ DETAIL IMPOTS — IR et IRNC séparés --}}
            <div style="margin-top:8px;">
                <div class="font-bold" style="font-size:8pt;">DETAIL IMPÔTS ET TAXES</div>

                <table class="border-all" style="margin-top:3px;">

                    {{-- ✅ IR standard — affiché séparément si > 0 --}}
                    @if ($montantIr > 0)
                    <tr class="row-ir">
                        <td class="border-right padding-3" style="font-size:8pt;">
                            {{ $labelIr }}
                            <span style="font-size:6.5pt; font-style:italic; color:#555;">
                                — Impôt sur le Revenu
                            </span>
                        </td>
                        <td class="padding-3 text-center" style="font-weight:bold;">
                            {{ number_format($montantIr, 0, ',', ' ') }}
                        </td>
                    </tr>
                    @endif

                    {{-- ✅ IRNC — affiché séparément si > 0 --}}
                    @if ($montantIrnc > 0)
                    <tr class="row-irnc">
                        <td class="border-right padding-3" style="font-size:8pt;">
                            {{ $labelIrnc }}
                            <span style="font-size:6.5pt; font-style:italic; color:#555;">
                                — IR Non Commercial
                            </span>
                        </td>
                        <td class="padding-3 text-center" style="font-weight:bold;">
                            {{ number_format($montantIrnc, 0, ',', ' ') }}
                        </td>
                    </tr>
                    @endif

                    {{-- Autres impôts depuis getDetailImpots() --}}
                    @foreach ($detailImpots as $key => $val)
                    @if (
                    $key !== 'total'
                    && $val > 0
                    && !in_array(strtolower($key), ['ir', 'irnc', 'ir(nc)'])
                    )
                    <tr>
                        <td class="border-right padding-3" style="font-size:8pt;">
                            {{ strtoupper($key) }}
                        </td>
                        <td class="padding-3 text-center" style="font-weight:bold;">
                            {{ number_format($val, 0, ',', ' ') }}
                        </td>
                    </tr>
                    @endif
                    @endforeach

                    {{-- Total --}}
                    <tr style="background:#f0f0f0; font-weight:bold;
                                border-top:2px solid #000;">
                        <td class="border-right padding-3" style="font-size:8.5pt;">
                            TOTAL
                        </td>
                        <td class="padding-3 text-center" style="font-size:8.5pt;">
                            {{ number_format($montantTotalImpots, 0, ',', ' ') }}
                        </td>
                    </tr>
                </table>
            </div>

            <div style="border-bottom:2px solid #000; margin:10px 0;"></div>

            <div style="font-weight:bold; margin-bottom:2px; line-height:1.1;">
                DESIGNATION DU CREANCIER:
                <div style="font-weight:normal; font-style:italic;
                            font-size:8pt; line-height:1.1;">
                    DESIGNATION OF THE CREDITOR:
                </div>
            </div>
            <div style="margin-top:8px; font-size:10pt; font-weight:bold;
                        min-height:30px; line-height:1.1;">
                {{ $nomBeneficiaire }}
            </div>

            {{-- Visa --}}
            <div style="border-bottom:2px solid #000; margin:10px 0 0;"></div>
            <div class="border-all text-center" style="margin-top:3px;">
                <div class="font-bold">Visa Contrôle Financier</div>
                <div class="font-tiny" style="font-style:italic;">Financial Control Stamp</div>
            </div>

            <table>
                <tr>
                    <td style="height:140px; width:40%; position:relative;">
                        <div class="watermark">Visa engagement <br>comptable</div>
                    </td>
                    <td style="height:140px; width:60%; position:relative;">
                        <div class="watermark">Validation de la<br>dépense</div>
                    </td>
                </tr>
            </table>
        </td>

        {{-- ══ COLONNE DROITE ═════════════════════════════════════ --}}
        <td style="width:30%; vertical-align:top; padding:5px;">

            <div style="font-size:8pt; line-height:1.1; text-align:left;
                        margin-bottom:35px;">
                <div style="font-weight:bold;">Vu, bon à payer</div>
                <div style="font-style:italic;">Votes available</div>
            </div>

            <table style="margin-bottom:8px;">
                <tr>
                    <td style="width:60%; padding:0;">
                        <div class="font-bold" style="font-size:8pt; margin:0;">
                            Montant brut
                        </div>
                        <div class="font-tiny" style="font-style:italic; margin:0;">
                            Gross amount
                        </div>
                    </td>
                    <td style="padding:0;">
                        <div class="border-all text-center padding-3">0</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0;">
                        <div class="font-bold" style="font-size:8pt; margin:0;">A PRECOMPTER</div>
                        <div class="font-tiny" style="font-style:italic; margin:0;">TO BE DEDUCTED</div>
                    </td>
                    <td style="padding:0;">
                        <div class="border-all text-center padding-3">
                            {{ number_format($montantTotalImpots, 0, ',', ' ') }}
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0;">
                        <div class="font-bold" style="font-size:8pt; margin:0;">Net à payer</div>
                        <div class="font-tiny" style="font-style:italic; margin:0;">Net amount</div>
                    </td>
                    <td style="padding:0;">
                        <div class="border-all text-center padding-3"
                            style="background:#f0f0f0;">
                            {{ number_format($montantTotalImpots, 0, ',', ' ') }}
                        </div>
                    </td>
                </tr>
            </table>

            <div class="border-all text-center padding-3" style="margin-top:5px;">
                <strong style="font-size:7.5pt; text-transform:uppercase;">
                    @yield('montant_lettres')
                </strong>
            </div>

            <div style="border-bottom:2px solid #000; margin:5px 0;"></div>

            <div style="margin-top:3px;">
                <div style="font-size:8pt;">
                    En vertu des crédits ouverts au titre de l'imputation budgétaire désignée,
                    l'<strong>Ordonnateur</strong> soussigné ordonne sur la caisse de
                    {{ strtoupper($parametres->sigle) }},
                    le paiement de la créance détaillée ci-dessus.
                </div>
                <div class="font-tiny" style="font-style:italic;">
                    Pursuant to the appropriations opened under the designated budgetary
                    allocation, the undersigned <strong>Authorizing Officer</strong> orders,
                    from {{ strtoupper($parametres->sigle) }} treasury,
                    the payment of the above-detailed debt.
                </div>
            </div>

            <div style="margin-top:60px;">
                <div class="font-bold" style="font-size:8pt;">Yaoundé, le _____________</div>
                <div class="font-tiny" style="font-style:italic;">Yaounde, the</div>
                <div style="margin-top:15px; text-align:right;">
                    <div class="font-bold" style="font-size:7.5pt;">
                        (Signature et timbre de l'ordonnateur)
                    </div>
                    <div class="font-tiny" style="font-style:italic;">
                        (Signature and stamp)
                    </div>
                </div>
            </div>
        </td>

    </tr>
</table>

@endsection