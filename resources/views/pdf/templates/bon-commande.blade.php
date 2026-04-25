@php
$disableFooter = true;

$bonCommande = $donnees['_raw'];

$service = $donnees['service'] ?? ($bonCommande->serviceDemandeur->nom ?? 'DIRECTION GENERALE');
$numeroBca = $donnees['numero_bca'] ?? ($bonCommande->numero ?? '.........');
$dateImpression = $donnees['date_impression'] ?? date('d/m/Y');
$prestataireNom = $donnees['prestataire_nom'] ?? ($bonCommande->fournisseur->raison_sociale ?? '');
$prestataireAdresse = $donnees['prestataire_adresse'] ?? ($bonCommande->fournisseur->adresse ?? '...............');
$prestataireTel = $donnees['prestataire_tel'] ?? ($bonCommande->fournisseur->telephone ?? '......................');
$prestataireContribuable = $donnees['prestataire_contribuable'] ?? ($bonCommande->fournisseur->nif ?? '........................');

// ── Taux dynamiques ───────────────────────────────────────
// Calculer le taux TVA réel depuis les montants
$montantHt = (float) ($bonCommande->montant_ht ?? 0);
$montantTva = (float) ($bonCommande->montant_tva ?? 0);
$montantIr = (float) ($bonCommande->montant_ir ?? 0);
$montantTtc = (float) ($bonCommande->montant_ttc ?? 0);

// ✅ Taux TVA — depuis le champ du modèle ou calculé
$tauxTva = 0;
if (isset($bonCommande->taux_tva) && $bonCommande->taux_tva > 0) {
$tauxTva = (float) $bonCommande->taux_tva;
} elseif ($montantHt > 0 && $montantTva > 0) {
$tauxTva = round(($montantTva / $montantHt) * 100, 2);
}

// ✅ Taux IR — depuis le champ du modèle ou calculé
$tauxIr = 0;
if (isset($bonCommande->taux_ir) && $bonCommande->taux_ir > 0) {
$tauxIr = (float) $bonCommande->taux_ir;
} elseif ($montantHt > 0 && $montantIr > 0) {
$tauxIr = round(($montantIr / $montantHt) * 100, 2);
}

// ✅ Labels dynamiques
$labelTva = $tauxTva > 0
? 'MONTANT TVA (' . rtrim(rtrim(number_format($tauxTva, 2, ',', ''), '0'), ',') . '%)'
: 'MONTANT TVA';

$labelIr = $tauxIr > 0
? 'MONTANT IR (' . rtrim(rtrim(number_format($tauxIr, 2, ',', ''), '0'), ',') . '%)'
: 'MONTANT IR';

// ── Pagination ────────────────────────────────────────────
$lignesPage1 = 10;
$lignesPagesSuivantes = 25;
$seuilSautTotaux = 15;

$totalLignes = $bonCommande->lignes->count();
$lignesChunked = collect();
$lignesRestantes = $bonCommande->lignes;

if ($totalLignes > 0) {
$lignesChunked->push($lignesRestantes->take($lignesPage1));
$lignesRestantes = $lignesRestantes->skip($lignesPage1);
while ($lignesRestantes->count() > 0) {
$lignesChunked->push($lignesRestantes->take($lignesPagesSuivantes));
$lignesRestantes = $lignesRestantes->skip($lignesPagesSuivantes);
}
}

$derniereLigneCount = $lignesChunked->last()?->count() ?? 0;
$totauxVontSauter = $derniereLigneCount >= $seuilSautTotaux;
$nombrePages = $lignesChunked->count() + ($totauxVontSauter ? 1 : 0);

$parametres = \App\Models\ParametresStructure::where('actif', true)->first();
@endphp

@extends('pdf.layouts.master', ['orientation' => 'landscape'])

@section('title', 'BCA N° ' . $numeroBca)

@section('montant_lettres')
{{ $donnees['montant_lettres'] ?? \App\Helpers\NombreEnLettres::montantCFA($bonCommande->montant_ttc ?? 0) }}
@endsection

@push('styles')
<style>
    @page {
        size: A4 portrait !important;
        margin-top: 2cm;
        margin-bottom: 2cm;
        margin-left: 1.5cm;
        margin-right: 1.5cm;
    }

    .content-wrapper {
        padding-top: 1.5cm;
    }

    .service-info {
        margin-bottom: 8px;
        font-weight: bold;
        font-size: 10pt;
    }

    .bca-numero {
        text-align: right;
        font-weight: bold;
        margin-bottom: 8px;
        font-size: 10pt;
    }

    .date-impression {
        text-align: right;
        font-size: 8pt;
        margin-bottom: 10px;
    }

    .text-center {
        text-align: center;
    }

    .mb-10 {
        margin-bottom: 10px;
    }

    .mb-15 {
        margin-bottom: 15px;
    }

    .font-bold {
        font-weight: bold;
    }

    .font-normal {
        font-weight: 400;
    }

    table.simple {
        width: 100%;
        border-collapse: collapse;
    }

    table.simple td {
        border: none;
        padding: 4px;
        font-size: 9pt;
    }

    table.simple td:first-child {
        width: 30%;
    }

    /* ✅ Colonnes FIXES — text-wrap au lieu de stretching */
    .articles-table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
        font-size: 9pt;
        table-layout: fixed;
        /* ← clé : colonnes fixes */
    }

    .articles-table th {
        background-color: #f0f0f0;
        font-weight: bold;
        text-align: center;
        font-size: 9pt;
        border: 1px solid #000;
        padding: 6px 4px;
        overflow: hidden;
        word-wrap: break-word;
        /* ← retour à la ligne si trop long */
    }

    .articles-table td {
        text-align: left;
        font-size: 9pt;
        border: 1px solid #000;
        padding: 5px 4px;
        overflow: hidden;
        word-wrap: break-word;
        /* ← retour à la ligne */
        white-space: normal;
        /* ← autorise le wrap */
        vertical-align: top;
    }

    .articles-table td.nombre {
        text-align: right;
        white-space: nowrap;
        /* montants sur une ligne */
    }

    /* ✅ Largeurs fixes des colonnes */
    .col-reference {
        width: 18%;
    }

    .col-designation {
        width: 44%;
    }

    .col-qte {
        width: 8%;
    }

    .col-pu {
        width: 15%;
    }

    .col-total {
        width: 15%;
    }

    .montant-lettres-box {
        margin-top: 20px;
        text-align: center;
        font-style: italic;
        font-size: 8.5pt;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .page-break {
        page-break-after: always;
        break-after: page;
    }

    .page-header-continue {
        text-align: right;
        margin-bottom: 20px;
        font-size: 10pt;
    }

    .bca-box-continue {
        display: inline-block;
        border: 2px solid #000;
        padding: 8px 15px;
        font-weight: bold;
        font-size: 11pt;
        margin-bottom: 10px;
    }

    .page-number-inline {
        text-align: right;
        font-size: 9pt;
        color: #666;
        margin-top: 6px;
        padding-right: 2px;
    }

    .signature-container {
        margin-top: 50px;
        page-break-inside: avoid;
    }

    .clearfix::after {
        content: "";
        display: table;
        clear: both;
    }

    .bloc-recapitulatif {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .totaux {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .signature-container {
        page-break-inside: avoid;
        break-inside: avoid;
        page-break-before: avoid;
        break-before: avoid;
    }
</style>
@endpush

@section('content')
@foreach ($lignesChunked as $pageIndex => $lignesPage)

{{-- ════ EN-TÊTE DE PAGE ════ --}}
@if ($pageIndex === 0)
<div class="service-info">
    DEMANDEUR: <span class="font-normal">{{ strtoupper($service) }}</span>
</div>
<div class="bca-numero">BCA N°: {{ $numeroBca }}</div>
<div class="date-impression">Imprimé le {{ $dateImpression }}</div>
<div class="text-center font-bold mb-10">BON DE COMMANDE ADMINISTRATIF</div>
<div class="text-center font-bold mb-15">Pour les objets et matières ci-après:</div>

<div class="mb-15">
    <table class="simple">
        <tr>
            <td><strong>Objet du bon de commande: </strong></td>
            <td class="font-normal">
                {{ $bonCommande->engagement?->objet ?? ($bonCommande->objet ?? '') }}
            </td>
        </tr>
        <tr>
            <td><strong>Nom ou raison du Prestataire</strong></td>
            <td class="font-bold">{{ $prestataireNom }}</td>
        </tr>
    </table>
</div>
@else
<div class="page-header-continue">
    <div class="bca-box-continue">BCA N°: {{ $numeroBca }}</div>
    <div style="font-size: 9pt; margin-top: 3px;">
        <strong>Suite — Page {{ $pageIndex + 1 }}</strong>
    </div>
</div>
@endif

{{-- ════ TABLEAU DES LIGNES ════ --}}
<table class="articles-table">
    <colgroup>
        <col class="col-reference">
        <col class="col-designation">
        <col class="col-qte">
        <col class="col-pu">
        <col class="col-total">
    </colgroup>
    <thead>
        <tr>
            <th class="col-reference">REFERENCE</th>
            <th class="col-designation">DESIGNATION</th>
            <th class="col-qte">QTES</th>
            <th class="col-pu">P.U (FCFA)</th>
            <th class="col-total">TOTAL (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lignesPage as $ligne)
        <tr>
            <td>{{ $ligne->reference ?? '-' }}</td>
            <td>{{ $ligne->designation }}</td>
            <td class="nombre">{{ number_format($ligne->quantite, 0, ',', ' ') }}</td>
            <td class="nombre">{{ number_format($ligne->prix_unitaire_ht, 0, ',', ' ') }}</td>
            <td class="nombre">{{ number_format($ligne->montant_ht, 0, ',', ' ') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

@if ($loop->last)
{{-- ════ DERNIÈRE PAGE : totaux + signatures ════ --}}

@if ($totauxVontSauter)
<div class="page-number-inline">
    Page {{ $pageIndex + 1 }} sur {{ $nombrePages }}
</div>
<div class="page-break"></div>
<div class="page-header-continue">
    <div class="bca-box-continue">BCA N°: {{ $numeroBca }}</div>
    <div style="font-size: 9pt; margin-top: 3px;">
        <strong>Récapitulatif — Page {{ $nombrePages }}</strong>
    </div>
</div>
@endif

<div class="bloc-recapitulatif">

    <div class="totaux">
        <table style="width:auto; min-width:280px; margin-left:auto; border-collapse:collapse;">
            <tr>
                <td style="padding:3px 8px; font-size:9pt;">MONTANT HT</td>
                <td style="padding:3px 8px; font-size:9pt; text-align:right; font-weight:bold;">
                    {{ number_format($bonCommande->montant_ht, 0, ',', ' ') }} F
                </td>
            </tr>
            @if ($montantTva > 0)
            <tr>
                {{-- ✅ Label TVA dynamique --}}
                <td style="padding:3px 8px; font-size:9pt;">{{ $labelTva }}</td>
                <td style="padding:3px 8px; font-size:9pt; text-align:right; font-weight:bold;">
                    {{ number_format($montantTva, 0, ',', ' ') }} F
                </td>
            </tr>
            @endif
            @if ($montantIr > 0)
            <tr>
                {{-- ✅ Label IR dynamique --}}
                <td style="padding:3px 8px; font-size:9pt;">{{ $labelIr }}</td>
                <td style="padding:3px 8px; font-size:9pt; text-align:right; font-weight:bold;">
                    {{ number_format($montantIr, 0, ',', ' ') }} F
                </td>
            </tr>
            @endif
            <tr style="border-top:1px solid #000;">
                <td style="padding:3px 8px; font-size:9pt; font-weight:bold;">NET A PAYER</td>
                <td style="padding:3px 8px; font-size:9pt; text-align:right; font-weight:bold;">
                    {{ number_format($bonCommande->net_a_percevoir, 0, ',', ' ') }} F
                </td>
            </tr>
            <tr style="border-top:2px solid #000; background:#f0f0f0;">
                <td style="padding:4px 8px; font-size:9.5pt; font-weight:bold;">MONTANT TOTAL TTC</td>
                <td style="padding:4px 8px; font-size:9.5pt; text-align:right; font-weight:bold;">
                    {{ number_format($montantTtc, 0, ',', ' ') }} F
                </td>
            </tr>
        </table>
    </div>

    <div class="montant-lettres-box">
        Arrêté le présent bon de commande administratif à la somme TTC de
        <strong style="text-transform: uppercase;">@yield('montant_lettres')</strong>
    </div>

    <div class="signature-container clearfix">
        <div style="text-align: right; margin-bottom: 20px; font-size: 8pt;">
            Yaoundé Le__________________________
        </div>
        <div style="width: 100%;">
            <div style="width: 33%; float: left; text-align: center;">
                <div class="font-bold">Le Prestataire</div>
            </div>
            <div style="width: 33%; float: left;"></div>
            <div style="width: 33%; float: left; text-align: center;">
                <div class="font-bold" style="margin-top: 10px;">
                    {{ $parametres->fonction_ordonnateur ?? 'LE DIRECTEUR GENERAL' }}
                </div>
            </div>
        </div>
    </div>

</div>{{-- fin .bloc-recapitulatif --}}

<div class="page-number-inline">
    Page {{ $nombrePages }} sur {{ $nombrePages }}
</div>

@else
{{-- ════ PAGES INTERMÉDIAIRES ════ --}}
<div class="page-number-inline">
    Page {{ $pageIndex + 1 }} sur {{ $nombrePages }}
</div>
<div class="page-break"></div>
@endif

@endforeach
@endsection