@php
$disableFooter = true;

$bonCommande = $donnees['_raw'];
$parametres = \App\Models\ParametresStructure::where('actif', true)->first();

// ── Montants ──────────────────────────────────────────────
$montantHt = (float) ($bonCommande->montant_ht ?? 0);
$montantTva = (float) ($bonCommande->montant_tva ?? 0);
$montantIr = (float) ($bonCommande->montant_ir ?? 0);
$montantTtc = (float) ($bonCommande->montant_ttc ?? 0);

// ✅ Taux TVA
$tauxTva = 0;
if (isset($bonCommande->taux_tva) && $bonCommande->taux_tva > 0) {
$tauxTva = (float) $bonCommande->taux_tva;
} elseif ($montantHt > 0 && $montantTva > 0) {
$tauxTva = round(($montantTva / $montantHt) * 100, 2);
}

// ✅ Taux IR
$tauxIr = 0;
if (isset($bonCommande->taux_ir) && $bonCommande->taux_ir > 0) {
$tauxIr = (float) $bonCommande->taux_ir;
} elseif ($montantHt > 0 && $montantIr > 0) {
$tauxIr = round(($montantIr / $montantHt) * 100, 2);
}

// ✅ Labels dynamiques — toujours affichés même si taux = 0
$labelTva = 'MONTANT TVA ('
. rtrim(rtrim(number_format($tauxTva, 2, ',', ''), '0'), ',')
. '%)';

$labelIr = 'MONTANT IR ('
. rtrim(rtrim(number_format($tauxIr, 2, ',', ''), '0'), ',')
. '%)';

// ── Date d'impression ─────────────────────────────────────
$dateImpression = now()->format('d/m/Y à H:i');

// ── Créateur du document (pas l'imprimeur) ────────────────
$createur = null;
$nomCreateur = '—';
$dateCreation = '—';

if ($bonCommande->created_by ?? null) {
$createur = \App\Models\User::find($bonCommande->created_by);
}
if (!$createur && ($bonCommande->user_id ?? null)) {
$createur = \App\Models\User::find($bonCommande->user_id);
}
// ❌ Pas de fallback auth()->user() — on veut le créateur uniquement

if ($createur) {
// ✅ Username / login / name dans cet ordre de priorité
$nomCreateur = $createur->username
?? $createur->login
?? $createur->name
?? '—';
}

if ($bonCommande->created_at) {
$dateCreation = \Carbon\Carbon::parse($bonCommande->created_at)
->format('d/m/Y à H:i');
}

// ── Pagination ────────────────────────────────────────────
// Largeur utile A4 portrait (180mm) selon les colgroup :
// Désignation (46%) ≈ 83mm → ~40 chars/ligne à 9pt DejaVu Sans
// Référence   (16%) ≈ 29mm → ~13 chars/ligne

$charsDesignParLigne = 40;
$charsRefParLigne    = 13;

$calcPoids = function ($ligne) use ($charsDesignParLigne, $charsRefParLigne) {
    $pDesign = max(1, (int) ceil(mb_strlen($ligne->designation ?? '') / $charsDesignParLigne));
    $pRef    = max(1, (int) ceil(mb_strlen($ligne->reference   ?? '') / $charsRefParLigne));
    return max($pDesign, $pRef);
};

$budgetPage1    = 8;  // unités disponibles page 1 (inchangé)
$budgetSuivante = 25; // unités disponibles pages suivantes (inchangé)
$seuilSautTotaux = 15; // seuil pour page de totaux séparée (inchangé)

$lignesChunked    = collect();
$pageCourante     = collect();
$poidsPageCourant = 0;
$budgetCourant    = $budgetPage1;

foreach ($bonCommande->lignes as $ligne) {
    $poids = $calcPoids($ligne);

    if ($pageCourante->isNotEmpty() && ($poidsPageCourant + $poids) > $budgetCourant) {
        $lignesChunked->push($pageCourante);
        $pageCourante     = collect();
        $poidsPageCourant = 0;
        $budgetCourant    = $budgetSuivante;
    }

    $pageCourante->push($ligne);
    $poidsPageCourant += $poids;
}

if ($pageCourante->isNotEmpty()) {
    $lignesChunked->push($pageCourante);
}

$totauxVontSauter = $poidsPageCourant >= $seuilSautTotaux;
$nombrePages      = $lignesChunked->count() + ($totauxVontSauter ? 1 : 0);

@endphp

@extends('pdf.layouts.master')

@section('title', 'Bon de Commande ' . $bonCommande->numero)

@section('montant_lettres')
{{ \App\Helpers\NombreEnLettres::montantCFA($bonCommande->montant_ttc) }}
@endsection

@push('styles')
<style>
    @page {
        size: A4 portrait !important;
        margin-top: 2cm;
        margin-bottom: 2.5cm;
        /* ✅ Marge basse pour le pied de page */
        margin-left: 1.5cm;
        margin-right: 1.5cm;
    }

    .content-wrapper {
        padding-top: 1.5cm;
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

    .commande-box-continue {
        display: inline-block;
        border: 2px solid #000;
        padding: 8px 15px;
        font-weight: bold;
        font-size: 12pt;
        margin-bottom: 10px;
    }

    .page-number-inline {
        text-align: right;
        font-size: 9pt;
        color: #666;
        margin-top: 6px;
        padding-right: 2px;
    }

    .bloc-recapitulatif {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .totaux {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .montant-lettres {
        page-break-inside: avoid;
        break-inside: avoid;
        page-break-before: avoid;
        break-before: avoid;
    }

    .bas-page {
        page-break-inside: avoid;
        break-inside: avoid;
        page-break-before: avoid;
        break-before: avoid;
    }

    /* ✅ Pied de page fixe — date impression + numéro + créateur */
    .pdf-footer-custom {
        position: fixed;
        bottom: 0;
        left: 1.5cm;
        right: 1.5cm;
        height: 1.8cm;
        border-top: 1px solid #ccc;
        padding-top: 4px;
        font-size: 7pt;
        color: #000;
    }

    .pdf-footer-custom table {
        width: 100%;
        border-collapse: collapse;
    }

    .pdf-footer-custom td {
        border: none;
        padding: 0 4px;
        vertical-align: top;
        font-size: 7pt;
        color: #000;
    }
</style>
@endpush

@section('content')

{{-- ✅ Pied de page fixe sur toutes les pages --}}
{{-- Ordre : Imprimé le | CHUY — N°xxx | Créé le + Par --}}
<div class="pdf-footer-custom">
    <table>
        <tr>
            <td style="width:30%; text-align:left;">
                <strong>Imprimé le :</strong> {{ $dateImpression }}
            </td>
            <td style="width:35%; text-align:center; font-weight:bold;">
                {{ $parametres->sigle ?? '' }}
                &nbsp;—&nbsp;
                N° {{ $bonCommande->numero }}
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

@foreach ($lignesChunked as $pageIndex => $lignesPage)

{{-- ════════ EN-TÊTE ════════ --}}
@if ($pageIndex === 0)
<div style="text-align: right; margin: 15px 0; font-size: 10pt;">
    <div class="commande-box">
        COMMANDE {{ $parametres->sigle }} N° {{ $bonCommande->numero }}
    </div>
    <strong>Yaounde, le</strong>
    {{ \Carbon\Carbon::parse($bonCommande->date_emission)->format('d/m/Y') }}
</div>

<div class="info">
    <table class="info-table">
        <tr>
            <td class="label">Nom ou raison du Prestataire :</td>
            <td class="value">
                {{ $bonCommande->fournisseur->raison_sociale ?? '' }}
            </td>
        </tr>
        <tr>
            <td class="label">
                Livraison - Reception<br>
                de 7h30 a 12h du Lundi au Mercredi<br>
                (Sauf urgence)
            </td>
            <td class="value">
                {{ $bonCommande->serviceDemandeur->nom ?? '' }}
            </td>
        </tr>
        <tr>
            <td class="label">DELAI :</td>
            <td class="value">
                le plus court possible et a nous confirmer au plus tard le _____________
            </td>
        </tr>
        <tr>
            <td class="label">IMPUTATION :</td>
            <td class="value">
                @php
                $nomenclature = $bonCommande->getNomenclaturePrincipale();
                @endphp
                {{ $nomenclature?->code ?? 'N/A' }}
                - {{ $nomenclature?->libelle ?? 'Non définie' }}
            </td>
        </tr>
        <tr>
            <td class="label">OBJET :</td>
            <td class="value">
                {{ $bonCommande->engagement?->objet ?? ($bonCommande->objet ?? '') }}
            </td>
        </tr>
    </table>
</div>

@else
<div class="page-header-continue">
    <div class="commande-box-continue">
        COMMANDE {{ $parametres->sigle }} N° {{ $bonCommande->numero }}
    </div>
    <div style="font-size: 9pt; margin-top: 3px;">
        <strong>Suite — Page {{ $pageIndex + 1 }}</strong>
    </div>
</div>
@endif

{{-- ════════ TABLEAU DES LIGNES ════════ --}}
<table>
    <thead>
        <tr>
            <th style="width: 16%;">REFERENCE</th>
            <th style="width: 46%;">DESIGNATION</th>
            <th style="width: 10%;">QTES</th>
            <th style="width: 14%;">P.U</th>
            <th style="width: 14%;">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lignesPage as $ligne)
        <tr>
            <td class="ref">{{ $ligne->reference ?? '-' }}</td>
            <td class="designation">{{ $ligne->designation }}</td>
            <td class="num">
                {{ number_format($ligne->quantite, 0, ',', ' ') }}
            </td>
            <td class="money">
                {{ number_format($ligne->prix_unitaire_ht, 0, ',', ' ') }}
            </td>
            <td class="money">
                {{ number_format($ligne->montant_ht, 0, ',', ' ') }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

@if ($loop->last)
{{-- ════════ DERNIÈRE PAGE : totaux + signatures ════════ --}}

@if ($totauxVontSauter)
<div class="page-number-inline">
    Page {{ $pageIndex + 1 }} sur {{ $nombrePages }}
</div>
<div class="page-break"></div>
<div class="page-header-continue">
    <div class="commande-box-continue">
        COMMANDE {{ $parametres->sigle }} N° {{ $bonCommande->numero }}
    </div>
    <div style="font-size: 9pt; margin-top: 3px;">
        <strong>Récapitulatif — Page {{ $nombrePages }}</strong>
    </div>
</div>
@endif

{{-- ✅ Bloc totaux + lettres + signatures — indivisible --}}
<div class="bloc-recapitulatif">

    <div class="totaux">
        <table>
            {{-- Montant HT --}}
            <tr>
                <td>MONTANT HT</td>
                <td class="money">
                    {{ number_format($montantHt, 0, ',', ' ') }}
                </td>
            </tr>

            {{-- ✅ TVA — toujours affichée, toujours noire, même si 0 --}}
            <tr>
                <td style="color:#000;">{{ $labelTva }}</td>
                <td class="money" style="color:#000;">
                    {{ number_format($montantTva, 0, ',', ' ') }}
                </td>
            </tr>

            {{-- ✅ IR — toujours affiché, toujours noir, même si 0 --}}
            <tr>
                <td style="color:#000;">{{ $labelIr }}</td>
                <td class="money" style="color:#000;">
                    {{ number_format($montantIr, 0, ',', ' ') }}
                </td>
            </tr>

            {{-- NET A PAYER --}}
            <tr>
                <td class="total-final">NET A PAYER</td>
                <td class="money total-final">
                    {{ number_format(
                                    $bonCommande->net_a_percevoir
                                    ?? ($montantTtc - $montantIr),
                                    0, ',', ' '
                                ) }}
                </td>
            </tr>

            {{-- MONTANT TOTAL TTC --}}
            <tr>
                <td class="total-final">MONTANT TOTAL TTC</td>
                <td class="money total-final">
                    {{ number_format($montantTtc, 0, ',', ' ') }}
                </td>
            </tr>
        </table>
    </div>

    <div class="montant-lettres">
        Arrete le present bon de commande a la somme de
        <strong style="text-transform: uppercase;">
            @yield('montant_lettres')
        </strong>
    </div>

    <div class="bas-page">
        <div class="mention-gauche">
            <div>Ref. Offre : __________________</div>
            <div style="margin-top: 6px;">Conditions : voir au verso</div>
        </div>
        <div class="signature">
            <div class="signature-box">
                <div class="fonction">
                    {{ $parametres->fonction_ordonnateur ?? 'LE DIRECTEUR GENERAL' }}
                </div>
                <div class="nom">{{ $parametres->nom_ordonnateur ?? '' }}</div>
            </div>
        </div>
    </div>

</div>{{-- fin .bloc-recapitulatif --}}

<div class="page-number-inline">
    Page {{ $nombrePages }} sur {{ $nombrePages }}
</div>

@else
<div class="page-number-inline">
    Page {{ $pageIndex + 1 }} sur {{ $nombrePages }}
</div>
<div class="page-break"></div>
@endif

@endforeach

@endsection