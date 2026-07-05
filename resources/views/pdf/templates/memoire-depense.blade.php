@php
$disableFooter = true;

$memoire = $donnees['_raw'] ?? $memoire ?? null;
if (!$memoire) abort(404);

$parametres = \App\Models\ParametresStructure::where('actif', true)->first();

$memoire->load([
'lignes',
'decisionAdministrative.typeDecision',
'decisionAdministrative.engagement',
]);

$lignes = $memoire->lignes->sortBy('numero_ligne');

$da = $memoire->decisionAdministrative;
$engagement = $da?->engagement;

// ── Pagination ────────────────────────────────────────────
$lignesPage1 = 12;
$lignesPagesSuivantes = 20;

$totalLignes = $lignes->count();
$lignesChunked = collect();
$lignesRest = $lignes;

if ($totalLignes > 0) {
$lignesChunked->push($lignesRest->take($lignesPage1));
$lignesRest = $lignesRest->skip($lignesPage1);
while ($lignesRest->count() > 0) {
$lignesChunked->push($lignesRest->take($lignesPagesSuivantes));
$lignesRest = $lignesRest->skip($lignesPagesSuivantes);
}
} else {
$lignesChunked->push(collect());
}

$nombrePages = $lignesChunked->count();

$montantLettres = $donnees['montant_lettres']
?? $memoire->montant_lettres
?? \App\Helpers\NombreEnLettres::montantCFA($memoire->montant_ttc ?? 0);

// ── Taux depuis les lignes ────────────────────────────────
$premiereLigne = $lignes->first();

$totalNap = 0;
$totalHt  = 0;
$totalTva = 0;
$totalIr  = 0;
$totalTtc = 0;

foreach ($lignes as $l) {
    $qte = max(1, (float)($l->quantite ?? 1));

    // ✅ NAP total = montant_net stocké (calculé exact à la sauvegarde)
    //    fallback : MHT stocké - IR stocké (anciens enregistrements)
    $napTotal = (float)($l->montant_net ?? $l->net_a_payer ?? 0);
    if ($napTotal <= 0 && ($l->montant_ht ?? 0) > 0) {
        $napTotal = (float)$l->montant_ht - (float)($l->montant_ir ?? 0);
    }

    // ✅ Totaux = somme des colonnes stockées en base
$totalNap = (int) round($lignes->sum('montant_net'),   0);
$totalHt  = (int) round($lignes->sum('montant_ht'),    0);
$totalIr  = (int) round($lignes->sum('montant_ir'),    0);
$totalTva = (int) round($lignes->sum('montant_tva'),   0);
$totalTtc = (int) round($lignes->sum('montant_ttc'),   0);
}

$tauxTvaVal = (float) ($premiereLigne?->taux_tva ?? 19.25);
$tauxIrVal = (float) ($premiereLigne?->taux_ir ?? 5.5);

$tauxTvaLabel = rtrim(rtrim(number_format($tauxTvaVal, 2, ',', ''), '0'), ',') . '%';
$tauxIrLabel = rtrim(rtrim(number_format($tauxIrVal, 2, ',', ''), '0'), ',') . '%';

if ($memoire->montant_ht > 0) {
if ($memoire->montant_tva > 0 && $tauxTvaLabel === '19,25%') {
$tauxTvaCalc = round(($memoire->montant_tva / $memoire->montant_ht) * 100, 2);
$tauxTvaLabel = rtrim(rtrim(number_format($tauxTvaCalc, 2, ',', ''), '0'), ',') . '%';
}
if ($memoire->montant_ir > 0 && $tauxIrLabel === '5,5%') {
$tauxIrCalc = round(($memoire->montant_ir / $memoire->montant_ht) * 100, 2);
$tauxIrLabel = rtrim(rtrim(number_format($tauxIrCalc, 2, ',', ''), '0'), ',') . '%';
}
}

// ── Pied de page : créateur et dates ─────────────────────
$dateImpression = now()->format('d/m/Y à H:i');
$dateCreation = '—';
$nomCreateur = '—';
$sigle = $parametres->sigle ?? 'CHUY';

// ✅ Créateur du mémoire — PAS l'utilisateur connecté
$createurId = $memoire->created_by ?? null;

if ($createurId) {
$createur = \App\Models\User::find($createurId);
if ($createur) {
$nomCreateur = $createur->username
?? $createur->login
?? $createur->name
?? '—';
}
}

if ($memoire->created_at) {
$dateCreation = \Carbon\Carbon::parse($memoire->created_at)
->format('d/m/Y à H:i');
}
@endphp

@extends('pdf.layouts.master', ['orientation' => 'landscape'])

@section('title', 'Mémoire de Dépense N° ' . $memoire->numero)

@section('montant_lettres')
{{ $montantLettres }}
@endsection

@push('styles')
<style>
    @page {
        size: A4 landscape;
        margin-top: 2cm;
        margin-bottom: 2cm;
        /* ✅ Espace pour le pied de page */
        margin-left: 1.5cm;
        margin-right: 1.5cm;
    }

    .content-wrapper {
        padding-top: 1cm;
    }

    .md-header-row {
        display: table;
        width: 100%;
        margin-bottom: 6px;
    }

    .md-header-cell {
        display: table-cell;
        vertical-align: middle;
        font-size: 9pt;
    }

    .md-header-cell.left {
        width: 50%;
        text-align: left;
    }

    .md-header-cell.right {
        width: 50%;
        text-align: right;
    }

    .md-numero-box {
        display: inline-block;
        border: 2px solid #000;
        padding: 5px 14px;
        font-weight: bold;
        font-size: 11pt;
    }

    .md-titre {
        text-align: center;
        font-weight: bold;
        font-size: 11pt;
        text-decoration: underline;
        text-transform: uppercase;
        margin: 6px 0 3px;
    }

    .md-objet {
        text-align: center;
        font-size: 9pt;
        font-style: italic;
        margin-bottom: 4px;
    }

    .md-refs {
        display: table;
        width: 100%;
        font-size: 8.5pt;
        margin-bottom: 4px;
    }

    .md-refs td {
        padding: 2px 4px;
    }

    .md-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.5pt;
        margin: 6px 0;
    }

    .md-table th {
        background: #e8e8e8;
        border: 1px solid #000;
        padding: 5px 6px;
        text-align: center;
        font-weight: bold;
        font-size: 8pt;
        text-transform: uppercase;
    }

    .md-table td {
        border: 1px solid #000;
        padding: 4px 6px;
        vertical-align: middle;
        font-size: 8.5pt;
        line-height: 1.2;
    }

    .md-table td.num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .md-table td.center {
        text-align: center;
    }

    .md-table tr.total-row td {
        background: #d8d8d8;
        font-weight: bold;
        border-top: 2px solid #000;
    }

    .md-lettres {
        border: 1px solid #000;
        padding: 5px 10px;
        font-size: 8.5pt;
        text-align: center;
        margin: 8px 0;
        page-break-inside: avoid;
    }

    .md-signature {
        margin-top: 20px;
        page-break-inside: avoid;
    }

    .md-signature-right {
        float: right;
        width: 30%;
        text-align: center;
        font-size: 8.5pt;
    }

    .page-break {
        page-break-after: always;
        break-after: page;
    }

    .page-number {
        text-align: right;
        font-size: 8pt;
        color: #666;
        margin-top: 4px;
    }

    .clearfix::after {
        content: "";
        display: table;
        clear: both;
    }

    /* ✅ Pied de page fixe — affiché sur toutes les pages */
    .pdf-footer-md {
        position: fixed;
        bottom: 0;
        left: 1.5cm;
        right: 1.5cm;
        height: 1.5cm;
        border-top: 1px solid #ccc;
        padding-top: 4px;
        font-size: 7pt;
        color: #000;
        background: #fff;
    }

    .pdf-footer-md table {
        width: 100%;
        border-collapse: collapse;
    }

    .pdf-footer-md td {
        border: none;
        padding: 0 4px;
        vertical-align: top;
        font-size: 7pt;
        color: #000;
    }
</style>
@endpush

@section('content')

{{-- ✅ Pied de page fixe — Imprimé le | N° | Créé le | Par --}}
<div class="pdf-footer-md">
    <table>
        <tr>
            <td style="width:30%; text-align:left;">
                <strong>Imprimé le :</strong> {{ $dateImpression }}
            </td>
            <td style="width:35%; text-align:center; font-weight:bold;">
                {{ $sigle }} — N° {{ $memoire->numero }}
            </td>
            <td style="width:35%; text-align:right;">
                <strong>Créé le :</strong> {{ $dateCreation }}
                @if($nomCreateur !== '—')
                &nbsp;|&nbsp; <strong>Par :</strong> {{ $nomCreateur }}
                @endif
            </td>
        </tr>
    </table>
</div>

@foreach($lignesChunked as $pageIndex => $lignesPage)

@if($pageIndex === 0)

{{-- ── Numéro + Exercice ──────────────────────── --}}
<div class="md-header-row">
    <div class="md-header-cell left" style="font-size:8pt;">
        Exercice : <strong>{{ $memoire->exercice }}</strong>
    </div>
    <div class="md-header-cell right">
        <div class="md-numero-box">N° {{ $memoire->numero }}</div>
    </div>
</div>

<div class="md-titre">MÉMOIRE DE DÉPENSE</div>
<div class="md-objet">{{ strtoupper($memoire->objet ?? '') }}</div>

{{-- ── Références ──────────────────────────────── --}}
<table class="md-refs">
    <tr>
        @if($memoire->numero_decision)
        <td>
            <strong>Décision N° :</strong> {{ $memoire->numero_decision }}
            @if($memoire->date_decision)
            du {{ $memoire->date_decision->format('d/m/Y') }}
            @endif
        </td>
        @endif

        @if($memoire->numero_ce)
        <td>
            <strong>CE N° :</strong> {{ $memoire->numero_ce }}
            @if($memoire->date_ce)
            du {{ $memoire->date_ce->format('d/m/Y') }}
            @endif
        </td>
        @endif

        <td style="text-align:right;">
            <strong>Date :</strong>
            {{ $memoire->date_memoire?->format('d/m/Y') ?? now()->format('d/m/Y') }}
        </td>
    </tr>
</table>

@else
{{-- ── En-tête pages suivantes ─────────────────── --}}
<div style="margin-bottom:8px; display:table; width:100%;">
    <span style="display:table-cell; font-size:9pt; font-weight:bold; vertical-align:middle;">
        Suite — Page {{ $pageIndex + 1 }}
    </span>
    <span style="display:table-cell; text-align:right; vertical-align:middle;">
        <div class="md-numero-box" style="font-size:10pt;">N° {{ $memoire->numero }}</div>
    </span>
</div>
@endif

{{-- ── Tableau des lignes ─────────────────────────── --}}
<table class="md-table">
    <thead>
        <tr>
            <th style="width:34%;">NATURE DE LA DÉPENSE</th>
            <th style="width:5%;">QTÉ</th>
            <th style="width:9%;">
                P.U NET
                <span style="font-size:6.5pt; font-weight:400; display:block;">
                    (NAP / unité)
                </span>
            </th>
            <th style="width:9%;">NAP</th>
            <th style="width:9%;">MHT</th>
            <th style="width:9%;">TVA ({{ $tauxTvaLabel }})</th>
            <th style="width:7%;">IR ({{ $tauxIrLabel }})</th>
            <th style="width:9%;">MONTANT TTC</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lignesPage as $ligne)
      @php
    $qte = max(1, (float)($ligne->quantite ?? 1));

    // ✅ NAP total = montant_net stocké
    //    fallback : MHT - IR si montant_net absent
    $napTotal = (float)($ligne->montant_net ?? $ligne->net_a_payer ?? 0);
    if ($napTotal <= 0 && ($ligne->montant_ht ?? 0) > 0) {
        $napTotal = (float)$ligne->montant_ht - (float)($ligne->montant_ir ?? 0);
    }

    // ✅ NAP/unité — précision monétaire (évite 349.9997)
    $brut        = $qte > 0 ? $napTotal / $qte : 0;
    $entier      = round($brut);
    $napUnitaire = abs($brut - $entier) < 0.005 ? (float)$entier : round($brut, 2);

    // ✅ Montants depuis la base (pas recalculés)
    $mht = (float)($ligne->montant_ht  ?? 0);
    $ir  = (float)($ligne->montant_ir  ?? 0);
    $tva = (float)($ligne->montant_tva ?? 0);
   $ttc = (float)($ligne->montant_ttc ?? 0);
@endphp
        <tr>
            <td>{{ $ligne->nature_depense }}</td>
            <td class="center">
                {{ number_format($ligne->quantite, 0, ',', ' ') }}
            </td>
            <td class="num" style="font-weight:600;">
                {{ number_format($napUnitaire, 0, ',', ' ') }}
            </td>
            <td class="num" style="font-weight:600;">
                {{ number_format($napTotal, 0, ',', ' ') }}
            </td>
            <td class="num">
                {{ number_format($ligne->montant_ht,  0, ',', ' ') }}
            </td>
            <td class="num">
                {{ number_format($ligne->montant_tva, 0, ',', ' ') }}
            </td>
            <td class="num">
                {{ number_format($ligne->montant_ir,  0, ',', ' ') }}
            </td>
            <td class="num" style="font-weight:600;">
                {{ number_format($ligne->montant_ttc, 0, ',', ' ') }}
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="8" style="text-align:center; font-style:italic;
                                color:#666; height:8mm;">
                Aucune ligne enregistrée
            </td>
        </tr>
        @endforelse
    </tbody>

    @if($loop->last)
<tfoot>
    <tr class="total-row">
        <td colspan="2" style="text-align:right; font-size:9pt;
                               text-transform:uppercase;">
            TOTAL
        </td>
        <td></td>
        <td class="num">{{ number_format($totalNap, 0, ',', ' ') }}</td>
        <td class="num">{{ number_format($totalHt,  0, ',', ' ') }}</td>
        <td class="num">{{ number_format($totalTva, 0, ',', ' ') }}</td>
        <td class="num">{{ number_format($totalIr,  0, ',', ' ') }}</td>
        <td class="num">{{ number_format($totalTtc, 0, ',', ' ') }}</td>
    </tr>
</tfoot>
@endif
</table>

@if($loop->last)

<div class="md-lettres" style="page-break-inside:avoid;">
    Arrêté le présent mémoire de dépense à la somme TTC de :
    <strong style="text-transform:uppercase;">
        @yield('montant_lettres')
    </strong>
</div>

<div class="md-signature clearfix" style="page-break-inside:avoid;">
    <div class="md-signature-right">
        <div style="margin-bottom:4px; font-size:8pt;">
            {{ $memoire->lieu_signature ?? 'Yaoundé' }},
            le ________________________________
        </div>
        <div style="font-weight:bold; font-size:9pt; text-transform:uppercase;">
            {{ $memoire->signataire_fonction
                            ?? ($parametres?->fonction_ordonnateur ?? 'LE DIRECTEUR GÉNÉRAL') }}
        </div>
        <div style="margin-top:18mm; font-size:8.5pt;">
            @if($memoire->signataire_nom)
            <span style="text-decoration:underline; font-weight:bold;">
                {{ strtoupper($memoire->signataire_nom) }}
            </span>
            @else
            <span style="color:#aaa;">____________________</span>
            @endif
        </div>
    </div>
</div>

@endif

<div class="page-number">
    Page {{ $pageIndex + 1 }} / {{ $nombrePages }}
</div>

@if(!$loop->last)
<div class="page-break"></div>
@endif

@endforeach

@endsection