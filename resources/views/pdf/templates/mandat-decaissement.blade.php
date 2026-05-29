{{-- resources/views/pdf/templates/mandat-decaissement.blade.php --}}
@extends('pdf.layouts.master', ['orientation' => 'portrait'])

@php
$regie = $donnees['regie'];
$decaissement = $donnees['decaissement'];
$params = $donnees['parametres'];
$lignes = $donnees['lignes'];
$daSource = $donnees['da_source'];

$responsable = $regie->responsable;
$exercice = $regie->exercice;

// ── Montants ──────────────────────────────────────────
// ✅ FIX 2 — encaisse_annuelle depuis le champ dédié de la RAV
$encaisseAnnuelle = (float) ($regie->encaisse_annuelle ?? 0);

// ✅ montant accordé = montant_accorde du décaissement
$montantDecaisse = (float) ($decaissement->montant_accorde
?? $decaissement->montant ?? 0);
$montantDecaisseTotal = (float) ($regie->montant_decaisse ?? 0);
$montantRestant = max(0, $encaisseAnnuelle - $montantDecaisseTotal);

$numeroEncaisse = $decaissement->libelle_tranche ?? $decaissement->numero ?? '01';
$dateDecaissement = $decaissement->date_decaissement
? \Carbon\Carbon::parse($decaissement->date_decaissement)->format('d/m/Y')
: now()->format('d/m/Y');

// ── DA Source ─────────────────────────────────────────
$imputation = $donnees['imputation'] ?? '—';
$numeroCE = $donnees['numero_ce'] ?? $daSource?->numero ?? '—';
$dateCE   = '____________________';

$numDecision = '_______________';
$dateDecision = '____________________';
$montantAE = (float) ($daSource?->montant_net ?? $encaisseAnnuelle);
$montantCredPaie = $montantDecaisse;

// ✅ FIX 4 — TVA et IR depuis la DA source
$tauxTva = (float) ($daSource?->taux_tva ?? 0);
$tauxIr = (float) ($daSource?->taux_ir ?? 0);

// Si le taux IR est dans donnees (calculé en controller)
if (isset($donnees['taux_ir_float'])) {
$tauxIr = (float) $donnees['taux_ir_float'];
}

// ── Régisseur ─────────────────────────────────────────
// ✅ FIX 3 — matricule et nom depuis Personnel (passés par le contrôleur)
$matricule = $donnees['matricule'] ?? '—';
$nomRegisseur = $donnees['nom_regisseur'] ?? strtoupper($responsable?->name ?? '—');

// ── Ordonnateur ───────────────────────────────────────
$nomOrdonnateur = $params?->nom_ordonnateur ?? '—';
$fonctionOrdo = $params?->fonction_ordonnateur ?? 'LE DIRECTEUR GENERAL';
$sigle = $params?->sigle ?? 'CHUY';
$nomStructure = $params?->nom_complet ?? 'CENTRE HOSPITALIER ET UNIVERSITAIRE DE YAOUNDE';
$nomStructureEn = $params?->nom_structure_en ?? 'YAOUNDE UNIVERSITY TEACHING HOSPITAL';
$ville = $params?->ville ?? 'Yaoundé';

// ── Montant en lettres ────────────────────────────────
$montantLettres = \App\Helpers\NombreEnLettres::montantCFA($montantDecaisse);

// ── Créateur / Initiales ──────────────────────────────
$nomCreateur = $donnees['nom_createur'] ?? '—';
$initiales = $donnees['initiales'] ?? '—';
$dateImpression = now()->format('d/m/Y à H:i');
@endphp

@section('title', 'Mandat de Décaissement — ' . $regie->numero)

@section('montant_lettres')
{{ $montantLettres }}
@endsection

@section('additional_styles')
<style>
    @page {
        size: A4 portrait;
        margin: 10mm 12mm 20mm 12mm;
    }

    * {
        box-sizing: border-box;
    }

    body {
        font-family: Arial, sans-serif;
        font-size: 8.5pt;
        color: #000;
    }

    .titre-mandat {
        text-align: center;
        font-size: 13pt;
        font-weight: bold;
        text-decoration: underline;
        margin: 10px 0 4px;
        text-transform: uppercase;
    }

    .sous-titre {
        border: 1px solid #000;
        display: inline-block;
        padding: 4px 10px;
        float: right;
        font-size: 9pt;
        font-weight: bold;
        margin-bottom: 6px;
    }

    .clearfix::after {
        content: "";
        display: table;
        clear: both;
    }

    .corps {
        text-align: justify;
        margin: 12px 0 8px;
        font-size: 8.5pt;
        line-height: 1.5;
    }

    .info-table {
        width: 100%;
        border-collapse: collapse;
        margin: 4px 0;
    }

    .info-table td {
        font-size: 8.5pt;
        padding: 2px 4px;
        vertical-align: top;
    }

    .info-table .label {
        font-weight: bold;
        width: 50%;
    }

    .table-regisseur {
        border-collapse: collapse;
        float: right;
        width: 40%;
        margin-left: 10px;
    }

    .table-regisseur th,
    .table-regisseur td {
        border: 1px solid #000;
        padding: 3px 6px;
        font-size: 8pt;
    }

    .table-regisseur th {
        background: #f0f0f0;
        font-weight: bold;
    }

    .table-detail {
        width: 100%;
        border-collapse: collapse;
        margin: 8px 0;
        font-size: 8pt;
    }

    .table-detail th {
        background: #e8e8e8;
        border: 1px solid #000;
        padding: 4px 3px;
        text-align: center;
        font-weight: bold;
    }

    .table-detail td {
        border: 1px solid #000;
        padding: 3px 4px;
        vertical-align: top;
    }

    .table-detail .nombre {
        text-align: right;
        white-space: nowrap;
    }

    .table-detail .centre {
        text-align: center;
    }

    .arrete {
        margin: 10px 0;
        font-size: 8.5pt;
        font-weight: bold;
        text-align: center;
    }

    .sig-table {
        width: 100%;
        border-collapse: collapse;
    }

    .sig-table td {
        text-align: center;
        vertical-align: top;
        font-size: 8pt;
        padding: 4px;
        width: 33%;
    }

    .sig-box {
        border: 1px solid #000;
        min-height: 65px;
        padding: 4px;
        margin-top: 4px;
    }

    /* ✅ Pied de page fixe */
    .pdf-footer-mandat {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: 14mm;
        border-top: 1px solid #ccc;
        padding-top: 2px;
        font-size: 6.5pt;
        background: #fff;
    }

    .pdf-footer-mandat table {
        width: 100%;
        border-collapse: collapse;
    }

    .pdf-footer-mandat td {
        border: none;
        padding: 0 4px;
        font-size: 6.5pt;
        vertical-align: middle;
    }

    .separator {
        border-top: 1px solid #000;
        margin: 6px 0;
    }

    .bold {
        font-weight: bold;
    }
</style>
@endsection

@section('content')

{{-- ✅ FIX 1 — EN-TÊTE SUPPRIMÉ (fourni par master) --}}

{{-- ✅ Pied de page fixe --}}
<div class="pdf-footer-mandat">
    <table>
        <tr>
            <td style="width:30%; text-align:left;">
                <strong>Imprimé le :</strong> {{ $dateImpression }}
            </td>
            <td style="width:40%; text-align:center; font-weight:bold;">
                {{ $sigle }} — Mandat N° {{ $decaissement->numero ?? $decaissement->id }}
                | Régie {{ $regie->numero }}
            </td>
            <td style="width:30%; text-align:right;">
                <strong>Créé par :</strong> {{ $nomCreateur }}
                &nbsp;|&nbsp; <strong>Initiales :</strong> {{ $initiales }}
            </td>
        </tr>
    </table>
</div>

<div class="separator"></div>

{{-- ══ TITRE ══ --}}
<div class="clearfix">
    <div class="sous-titre">
        REGIE D'AVANCE<br>N° : {{ $regie->numero }}
    </div>
</div>
<div class="titre-mandat">
    Mandat de Décaissement N° {{ $decaissement->numero ?? $decaissement->id }}
</div>
<div class="clearfix"></div>
<div class="separator"></div>

{{-- ══ CORPS ══ --}}
<div class="corps">
    Je soussigné <strong>{{ $nomOrdonnateur }}</strong>,
    Ordonnateur des crédits du {{ $nomStructure }},
    donne ordre au comptable assignataire de payer
    la présente encaisse relative à
    la Régie d'Avance N° <strong>{{ $regie->numero }}</strong>
    pour {{ strtolower($regie->objet ?? $regie->libelle) }} :
</div>

{{-- ══ INFOS + TABLEAU RÉGISSEUR ══ --}}
<div class="clearfix">
    <table class="table-regisseur">
        <tr>
            <th colspan="2" style="text-align:center;">Désignation Régisseur</th>
        </tr>
        <tr>
            <td class="bold">Matricule</td>
            {{-- ✅ FIX 3 — depuis Personnel --}}
            <td>{{ $matricule }}</td>
        </tr>
        <tr>
            <td class="bold">Nom(s) et prénoms</td>
            <td>{{ $nomRegisseur }}</td>
        </tr>
    </table>

    <table class="info-table" style="width:55%;">
        <tr>
            <td class="label">Service Emetteur :</td>
            <td>{{ $nomStructure }}</td>
        </tr>
        <tr>
            <td class="label">Exercice :</td>
            <td>{{ $exercice?->annee ?? date('Y') }}</td>
        </tr>
        <tr>
            <td class="label">Imputation Technique :</td>
            <td class="bold">{{ $imputation }}</td>
        </tr>
        <tr>
            <td class="label">Encaisse annuelle :</td>
            {{-- ✅ FIX 2 — depuis $regie->encaisse_annuelle --}}
            <td class="bold">{{ number_format($encaisseAnnuelle, 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr>
            <td class="label">Numéro encaisse :</td>
            <td>{{ $numeroEncaisse }}</td>
        </tr>
        <tr>
            <td class="label">Montant encaisse autorisée :</td>
            <td class="bold">{{ number_format($montantDecaisse, 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr>
            <td class="label">Montant Net à décaisser :</td>
            <td class="bold">{{ number_format($montantDecaisseTotal, 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr>
            <td class="label">Montant encaisse restant :</td>
            <td class="bold">{{ number_format($montantRestant, 0, ',', ' ') }} FCFA</td>
        </tr>
    </table>
</div>

<br style="clear:both;">

<table class="info-table">
    <tr>
        <td class="label">Objet de la Régie :</td>
        <td>{{ $regie->objet ?? $regie->libelle }}</td>
    </tr>
    <tr>
        <td class="label">Certificat d'Engagement :</td>
        <td>
            N° <strong>{{ $numeroCE }}</strong> du <strong>{{ $dateCE }}</strong>
            &nbsp; Décision de <br>déblocage N° <strong>{{ $numDecision }}</strong>
            du <strong>{{ $dateDecision }}</strong>
        </td>
    </tr>
    <tr>
        <td class="label">Montant autorisation d'engagement :</td>
        <td class="bold">{{ number_format($montantAE, 0, ',', ' ') }} FCFA</td>
    </tr>
    <tr>
        <td class="label">Montant Crédit de paiement :</td>
        <td class="bold">{{ number_format($montantCredPaie, 0, ',', ' ') }} FCFA</td>
    </tr>
</table>

{{-- ══ TABLEAU DÉTAIL ══ --}}
<div style="margin-top:8px; font-weight:bold; font-size:8.5pt;">
    Détail des dépenses avec taxes :
</div>

<table class="table-detail">
    <thead>
        <tr>
            <th style="width:5%;">N°</th>
            <th style="width:10%;">Code</th>
            <th style="width:33%;">Libellé Dépenses</th>
            <th style="width:12%;">Montant TTC</th>
            <th style="width:10%;">Montant HT</th>
            {{-- ✅ FIX 4 — taux depuis DA source --}}
            <th style="width:8%;">TVA ({{ number_format($tauxTva, 1) }}%)</th>
            <th style="width:10%;">AC/IR ({{ number_format($tauxIr, 1) }}%)</th>
            <th style="width:10%;">NAP</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lignes as $index => $ligne)
        @php
        $ttc = (float) ($ligne['montant_ttc'] ?? 0);
        $ht = $tauxTva > 0
        ? round($ttc / (1 + $tauxTva / 100), 2)
        : $ttc;
        // ✅ TVA et IR calculés depuis taux DA source
        $tva = round($ht * $tauxTva / 100, 2);
        $ir = round($ht * $tauxIr / 100, 2);
        $nap = $ttc - $ir;
        @endphp
        <tr>
            <td class="centre">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</td>
            <td class="centre">{{ $ligne['code'] ?? '—' }}</td>
            <td>{{ $ligne['libelle'] ?? '—' }}</td>
            <td class="nombre">{{ number_format($ttc, 0, ',', ' ') }}</td>
            <td class="nombre">{{ number_format($ht,  0, ',', ' ') }}</td>
            <td class="nombre">{{ number_format($tva, 0, ',', ' ') }}</td>
            <td class="nombre">{{ number_format($ir,  0, ',', ' ') }}</td>
            <td class="nombre bold">{{ number_format($nap, 0, ',', ' ') }}</td>
        </tr>
        @empty
        {{-- ✅ Ligne par défaut avec taux DA --}}
        @php
        $ht0 = $tauxTva > 0
        ? round($montantDecaisse / (1 + $tauxTva / 100), 2)
        : $montantDecaisse;
        $tva0 = round($ht0 * $tauxTva / 100, 2);
        $ir0 = round($ht0 * $tauxIr / 100, 2);
        $nap0 = $montantDecaisse - $ir0;
        @endphp
        <tr>
            <td class="centre">01</td>
            <td class="centre">{{ $codeNomenclature ?? substr($imputation, -6) }}</td>
            <td>{{ $regie->objet ?? $regie->libelle }}</td>
            <td class="nombre">{{ number_format($montantDecaisse, 0, ',', ' ') }}</td>
            <td class="nombre">{{ number_format($ht0,  0, ',', ' ') }}</td>
            <td class="nombre">{{ number_format($tva0, 0, ',', ' ') }}</td>
            <td class="nombre">{{ number_format($ir0,  0, ',', ' ') }}</td>
            <td class="nombre bold">{{ number_format($nap0, 0, ',', ' ') }}</td>
        </tr>
        @endforelse
    </tbody>
</table>

{{-- ══ ARRÊTÉ ══ --}}
<div class="arrete">
    Arrêté le présent mandat à la somme de FCFA :
    <span style="text-transform:uppercase;">@yield('montant_lettres')</span>.
</div>

{{-- ══ SIGNATURES ══ --}}
<table class="sig-table" style="margin-top:25px;">
    <tr>
        <td>
            <div class="bold">Visa du</div>
            <div class="bold">Contrôleur Financier</div>
            <div class="sig-box"></div>
        </td>
        <td>
            <div class="bold">Signature <br>de l'Ordonnateur</div>
            <div class="sig-box"></div>
            <!-- <div style="margin-top:4px; font-size:7.5pt; font-weight:bold;">
                {{ $fonctionOrdo }}<br>{{ $nomOrdonnateur }}
            </div> -->
        </td>
        <td>
            <div class="bold">Visa de <br>l'Agent Comptable</div>
            <div class="sig-box"></div>
        </td>
    </tr>
</table>

@endsection