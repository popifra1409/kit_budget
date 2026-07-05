@php
$disableFooter = true;
$disableHeader = true;

$bonCommande = $donnees['_raw'];
$parametres  = \App\Models\ParametresStructure::where('actif', true)->first();
$etatConfig  = $donnees['_etat_config'] ?? null;

if ($etatConfig instanceof \App\Models\EtatConfig) {
    $entete = $etatConfig->getEntete($parametres);
} else {
    $entete = [
        'titre_fr'          => $parametres?->nom_complet      ?? 'CENTRE HOSPITALIER ET UNIVERSITAIRE DE YAOUNDE',
        'titre_en'          => $parametres?->nom_structure_en ?? 'YAOUNDE UNIVERSITY TEACHING HOSPITAL',
        'sigle'             => $parametres?->sigle             ?? 'CHUY',
        'ministere_fr'      => 'MINISTERE DE LA SANTE PUBLIQUE',
        'ministere_en'      => 'MINISTRY OF PUBLIC HEALTH',
        'sous_direction_fr' => null, 'sous_direction_en' => null,
        'titre_document'    => null, 'logo_override'     => null,
    ];
}

$titreDocument = $entete['titre_document'] ?? 'BON DE COMMANDE ADMINISTRATIF';
$sigle         = $entete['sigle'] ?? $parametres?->sigle ?? 'CHUY';

// ── Logo ────────────────────────────────────────────────────
$logoOverride = !empty($entete['logo_override']);
$logoBase64 = null; $logoMimeType = 'image/jpeg';
if ($logoOverride) {
    $p = storage_path('app/public/' . ltrim($entete['logo_override'], '/'));
    if (file_exists($p)) { $logoBase64 = base64_encode(file_get_contents($p)); $logoMimeType = mime_content_type($p) ?: 'image/jpeg'; }
    else $logoOverride = false;
}
$logoStdBase64 = null; $logoStdMimeType = 'image/jpeg';
if (!$logoOverride && $parametres?->logo) {
    $p = storage_path('app/public/' . ltrim($parametres->logo, '/'));
    if (file_exists($p)) { $logoStdBase64 = base64_encode(file_get_contents($p)); $logoStdMimeType = mime_content_type($p) ?: 'image/jpeg'; }
}

// ── Infos document ──────────────────────────────────────────
$service        = $donnees['service']         ?? ($bonCommande->serviceDemandeur->nom      ?? 'DIRECTION GENERALE');
$numeroBca      = $donnees['numero_bca']      ?? ($bonCommande->numero                     ?? '.........');
$dateImpression = now()->format('d/m/Y à H:i');
$prestataireNom = $donnees['prestataire_nom'] ?? ($bonCommande->fournisseur->raison_sociale ?? '');

// ── Taux (depuis lignes du BC ou valeurs par défaut) ────────
$premiereLigne = $bonCommande->lignes->first();
$tauxTva = (float) ($premiereLigne?->taux_tva ?? ($bonCommande->taux_tva ?? 19.25));
$tauxIr  = (float) ($premiereLigne?->taux_ir  ?? ($bonCommande->taux_ir  ?? 5.5));

// ════════════════════════════════════════════════════════════
// ✅ TOTAUX CALCULÉS DEPUIS LE MONTANT HT TOTAL
//
//   Total HT  = somme des montant_ht des lignes
//   Total TVA = Total HT × tauxTva%
//   Total IR  = Total HT × tauxIr%
//   Total TTC = Total HT + Total TVA
//   NAP       = Total HT − Total IR
//
//   → cohérence garantie : pas de cumul d'arrondis individuels
// ════════════════════════════════════════════════════════════
$totalHt  = (float) $bonCommande->lignes->sum('montant_ht');
$totalTva = round($totalHt * $tauxTva / 100, 2);
$totalIr  = round($totalHt * $tauxIr  / 100, 2);
$totalTtc = round($totalHt + $totalTva, 2);
$netAPayer = round($totalHt - $totalIr, 2);

// Entiers pour affichage
$totalHtAff  = (int) round($totalHt,  0);
$totalTvaAff = (int) round($totalTva, 0);
$totalIrAff  = (int) round($totalIr,  0);
$totalTtcAff = (int) round($totalTtc, 0);
$netAPayerAff = (int) round($netAPayer, 0);

$labelTva = 'TVA (' . rtrim(rtrim(number_format($tauxTva, 2, ',', ''), '0'), ',') . '%)';
$labelIr  = 'IR ('  . rtrim(rtrim(number_format($tauxIr,  2, ',', ''), '0'), ',') . '%)';

// ── Créateur ────────────────────────────────────────────────
$createur = null; $initiauxCreateur = '';
if ($bonCommande->created_by ?? null) $createur = \App\Models\User::find($bonCommande->created_by);
if (!$createur && ($bonCommande->user_id ?? null)) $createur = \App\Models\User::find($bonCommande->user_id);
if ($createur) $initiauxCreateur = $createur->username ?? $createur->login ?? $createur->name ?? '';

// ── Imputation budgétaire ───────────────────────────────────
$nomenclatureCode = null; $nomenclatureLib = null;
$anneeImputation = now()->year; $moisImputation = now()->format('m');
$codeArticle = null; $ligneImputation = null;
$engagementBC = null;
if ($bonCommande->engagement_id)
    $engagementBC = \App\Models\Engagement::with('nomenclaturePrincipale','exercice')->find($bonCommande->engagement_id);
if (!$engagementBC)
    $engagementBC = \App\Models\Engagement::with('nomenclaturePrincipale','exercice')
        ->where('engageable_type','App\Models\BonCommande')->where('engageable_id',$bonCommande->id)->first();
if ($engagementBC?->nomenclaturePrincipale) {
    $nomenclatureCode = $engagementBC->nomenclaturePrincipale->code;
    $nomenclatureLib  = $engagementBC->nomenclaturePrincipale->libelle;
    $codeArticle      = $engagementBC->nomenclaturePrincipale->getCodeArticle() ?? substr($nomenclatureCode, 0, 6) ?? null;
    $dateEng          = \Carbon\Carbon::parse($engagementBC->date_engagement);
    $anneeImputation  = $engagementBC->exercice?->annee ?? $dateEng->year;
    $moisImputation   = $dateEng->format('m');
}
if ($nomenclatureCode) {
    $pa = ($codeArticle && $codeArticle !== $nomenclatureCode) ? $codeArticle . '-' : '';
    $ligneImputation = $anneeImputation . '-' . $moisImputation . '-' . $pa . $nomenclatureCode;
    if ($nomenclatureLib) $ligneImputation .= ' (' . strtoupper($nomenclatureLib) . ')';
}

// ── Pagination des lignes ───────────────────────────────────
$lignesPage1        = 12;
$lignesPagesSuiv    = 20;
$lignesAll          = $bonCommande->lignes;
$totalLignes        = $lignesAll->count();
$lignesChunked      = collect();
$lignesRest         = $lignesAll;
$lignesChunked->push($lignesRest->take($lignesPage1));
$lignesRest = $lignesRest->skip($lignesPage1);
while ($lignesRest->count() > 0) {
    $lignesChunked->push($lignesRest->take($lignesPagesSuiv));
    $lignesRest = $lignesRest->skip($lignesPagesSuiv);
}
if ($lignesChunked->isEmpty()) $lignesChunked->push(collect());
$nombrePages = $lignesChunked->count();
@endphp

@extends('pdf.layouts.master', ['orientation' => 'portrait'])
@section('title', 'BCA N° ' . $numeroBca)
@section('montant_lettres')
{{ $donnees['montant_lettres'] ?? \App\Helpers\NombreEnLettres::montantCFA($totalTtc) }}
@endsection

@push('styles')
<style>
@page {
    size: A4 portrait !important;
    margin-top:    {{ $logoOverride ? '0.5cm' : '6mm' }};
    margin-bottom: 2cm;
    margin-left:   1cm;
    margin-right:  1cm;
}
body              { font-size: 8.5pt; }
.entete-separateur { border: none; border-top: 1px solid #000; margin: 4px 0 8px; }
.text-center      { text-align: center; }
.mb-8             { margin-bottom: 8px; }
.mb-10            { margin-bottom: 10px; }
.font-bold        { font-weight: bold; }
.font-normal      { font-weight: 400; }
.clearfix::after  { content: ""; display: table; clear: both; }
table.simple              { width: 100%; border-collapse: collapse; }
table.simple td           { border: none; padding: 3px; font-size: 8.5pt; }
table.simple td:first-child { width: 30%; }

/* ── Rappel BCA en haut des pages de suite ─────────────────── */
.rappel-bca {
    text-align: right;
    margin-bottom: 8px;
    border-bottom: 1px solid #ccc;
    padding-bottom: 4px;
}
.rappel-bca-box {
    display: inline-block;
    border: 2px solid #000;
    padding: 4px 12px;
    font-weight: bold;
    font-size: 9.5pt;
}

/* ── Tableau articles ──────────────────────────────────────── */
.articles-table        { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 8.5pt; table-layout: fixed; }
.articles-table thead  { display: table-header-group; }
.articles-table tr     { page-break-inside: avoid; page-break-after: auto; }
.articles-table th     { background: #f0f0f0; font-weight: bold; text-align: center; font-size: 8.5pt; border: 1px solid #000; padding: 4px 3px; overflow: hidden; word-wrap: break-word; }
.articles-table td     { text-align: left; font-size: 8.5pt; border: 1px solid #000; padding: 4px 3px; overflow: hidden; word-wrap: break-word; white-space: normal; vertical-align: top; }
.articles-table td.nombre { text-align: right; white-space: nowrap; }
.col-reference   { width: 18%; }
.col-designation { width: 44%; }
.col-qte         { width: 8%; }
.col-pu          { width: 15%; }
.col-total       { width: 15%; }

/* ── Séparateur entre tableau et totaux ────────────────────── */
.separateur-totaux {
    border: none;
    border-top: 2px solid #000;
    margin: 10px 0 6px;
}

/* ── Bloc totaux ───────────────────────────────────────────── */
.bloc-totaux {
    clear: both;
    display: block;
    page-break-inside: avoid;
    break-inside: avoid;
    margin-bottom: 0;
}
.totaux-table {
    width: auto;
    min-width: 280px;
    margin-left: auto;
    border-collapse: collapse;
}
.totaux-table td {
    padding: 3px 10px;
    font-size: 8.5pt;
    border: none;
}
.totaux-table tr.ligne-ttc {
    border-top: 1px solid #000;
    font-weight: bold;
}
.totaux-table tr.ligne-nap {
    border-top: 2px solid #000;
    background: #f0f0f0;
    font-weight: bold;
    font-size: 9pt;
}
.totaux-table td.val { text-align: right; }

/* ── Bloc récapitulatif (lettres + signature) ──────────────── */
.separateur-recap {
    border: none;
    border-top: 1px dashed #999;
    margin: 12px 0 8px;
}
.bloc-recap {
    clear: both;
    display: block;
    page-break-inside: avoid;
    break-inside: avoid;
}
.montant-lettres-box { text-align: center; font-style: italic; font-size: 8pt; margin-bottom: 10px; }
.signature-container { margin-top: 10px; page-break-inside: avoid; }

/* ── Saut de page ──────────────────────────────────────────── */
.page-break { page-break-after: always; break-after: page; }

/* ── Imputation ─────────────────────────────────────────────── */
.ligne-imputation        { font-size: 8pt; margin-bottom: 5px; }
.ligne-imputation strong { font-weight: bold; text-decoration: underline; }

/* ── Footer fixe ───────────────────────────────────────────── */
.pdf-footer-fixe {
    position: fixed; bottom: 0; left: 0; right: 0;
    height: 1.6cm; border-top: 1px solid #ccc;
    background: #fff; padding-top: 2px; font-size: 6.5pt; color: #333;
}
.pdf-footer-fixe table { width: 100%; border-collapse: collapse; }
.pdf-footer-fixe td    { border: none; padding: 0 4px; font-size: 6.5pt; vertical-align: middle; }
</style>
@endpush

@section('content')

{{-- Pagination DomPDF — au niveau racine --}}
<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->getFont("DejaVu Sans");
    $pdf->page_text(260, 820, "Page {PAGE_NUM} / {PAGE_COUNT}", $font, 7);
}
</script>

{{-- Footer fixe --}}
<div class="pdf-footer-fixe">
    <table>
        <tr>
            <td style="width:35%">Imprimé le : {{ $dateImpression }}</td>
            <td style="width:30%;text-align:center">{{ $sigle }} — BCA {{ $numeroBca }}</td>
            <td style="width:35%;text-align:right">
                @if($initiauxCreateur)Par : {{ $initiauxCreateur }}@endif
            </td>
        </tr>
    </table>
</div>

{{-- ════════════════════════════════════════════════════════════
     BOUCLE SUR LES PAGES (1 chunk = 1 page)
     ════════════════════════════════════════════════════════════ --}}
@foreach ($lignesChunked as $pageIndex => $lignesPage)

    @if ($pageIndex === 0)
        {{-- ── En-tête première page ─────────────────────────── --}}
        @if($logoOverride && $logoBase64)
            <div style="width:100%;margin-bottom:6px;line-height:0;font-size:0;">
                <img src="data:{{ $logoMimeType }};base64,{{ $logoBase64 }}" style="width:100%;display:block;height:auto;">
            </div>
        @else
            <table style="width:100%;border-collapse:collapse;border:none;margin-bottom:4px;">
                <tr>
                    <td style="width:22%;vertical-align:top;text-align:center;font-size:7pt;border:none;">
                        <strong>REPUBLIQUE DU CAMEROUN</strong><br><em>Paix - Travail - Patrie</em><br>
                        <span style="font-size:6.5pt;">{{ $entete['ministere_fr'] ?? 'MINISTERE DE LA SANTE PUBLIQUE' }}</span>
                        @if($entete['sous_direction_fr'] ?? null)<br><span style="font-size:6pt;font-style:italic;">{{ $entete['sous_direction_fr'] }}</span>@endif
                    </td>
                    <td style="width:56%;text-align:center;vertical-align:top;border:none;">
                        <div style="font-size:9.5pt;font-weight:bold;text-transform:uppercase;">{{ $entete['titre_fr'] }}</div>
                        <div style="font-size:7.5pt;font-style:italic;">{{ $entete['titre_en'] }}</div>
                        @if($logoStdBase64)<img src="data:{{ $logoStdMimeType }};base64,{{ $logoStdBase64 }}" style="height:34px;margin-top:3px;margin-bottom:2px;">@endif
                        @if($entete['sous_direction_fr'] ?? null)
                            <div style="font-size:7pt;margin-top:2px;">{{ $entete['sous_direction_fr'] }}
                                @if($entete['sous_direction_en'] ?? null)<br><em style="font-size:6.5pt;">{{ $entete['sous_direction_en'] }}</em>@endif
                            </div>
                        @endif
                    </td>
                    <td style="width:22%;vertical-align:top;text-align:center;font-size:7pt;border:none;">
                        <strong>REPUBLIC OF CAMEROON</strong><br><em>Peace - Work - Fatherland</em><br>
                        <span style="font-size:6.5pt;">{{ $entete['ministere_en'] ?? 'MINISTRY OF PUBLIC HEALTH' }}</span>
                        @if($entete['sous_direction_en'] ?? null)<br><span style="font-size:6pt;font-style:italic;">{{ $entete['sous_direction_en'] }}</span>@endif
                    </td>
                </tr>
            </table>
        @endif

        <hr class="entete-separateur">
        <div style="font-size:9pt;font-weight:bold;margin-bottom:3px;">
            DEMANDEUR : <span class="font-normal">{{ strtoupper($service) }}</span>
        </div>
        <div style="text-align:right;font-weight:bold;margin-bottom:5px;font-size:9pt;">
            BCA N° : {{ $numeroBca }}
        </div>
        <div class="text-center font-bold mb-8">{{ strtoupper($titreDocument) }}</div>
        <div class="text-center font-bold mb-10">Pour les objets et matières ci-après :</div>
        <div class="mb-10">
            <table class="simple">
                <tr>
                    <td><strong>Objet du bon de commande :</strong></td>
                    <td class="font-normal">{{ $bonCommande->engagement?->objet ?? ($bonCommande->objet ?? '') }}</td>
                </tr>
                <tr>
                    <td><strong>Nom ou raison du Prestataire :</strong></td>
                    <td class="font-bold">{{ $prestataireNom }}</td>
                </tr>
            </table>
        </div>
        @if($ligneImputation)
            <div class="ligne-imputation"><strong>Ligne d'imputation budgétaire :</strong> {{ $ligneImputation }}</div>
        @endif

    @else
        {{-- ── ✅ RAPPEL DU N° BCA EN HAUT DE CHAQUE PAGE DE SUITE ──── --}}
        <div class="rappel-bca">
            <span class="rappel-bca-box">BCA N° : {{ $numeroBca }}</span>
            <span style="font-size:8pt;margin-left:10px;color:#555;">
                Suite ({{ $pageIndex + 1 }} / {{ $nombrePages }})
            </span>
        </div>
    @endif

    {{-- ── Tableau des articles ──────────────────────────────── --}}
    <table class="articles-table">
        <colgroup>
            <col class="col-reference"><col class="col-designation">
            <col class="col-qte"><col class="col-pu"><col class="col-total">
        </colgroup>
        <thead>
            <tr>
                <th class="col-reference">RÉFÉRENCE</th>
                <th class="col-designation">DÉSIGNATION</th>
                <th class="col-qte">QTES</th>
                <th class="col-pu">P.U (FCFA)</th>
                <th class="col-total">TOTAL HT (FCFA)</th>
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

        {{-- ════════════════════════════════════════════════════
             ✅ BLOC TOTAUX — séparé du tableau par un filet
             Calculés depuis le Total HT :
               TVA = HT × tauxTva%
               IR  = HT × tauxIr%
               TTC = HT + TVA
               NAP = HT − IR
             ════════════════════════════════════════════════════ --}}

        <div class="bloc-totaux">
            <table class="totaux-table">
                <tr>
                    <td>MONTANT HT</td>
                    <td class="val">{{ number_format($totalHtAff, 0, ',', ' ') }} F</td>
                </tr>
                <tr>
                    <td>{{ $labelTva }}</td>
                    <td class="val">
                        @if($totalTvaAff <= 0) EXONÉRÉE
                        @else {{ number_format($totalTvaAff, 0, ',', ' ') }} F
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>{{ $labelIr }}</td>
                    <td class="val">{{ number_format($totalIrAff, 0, ',', ' ') }} F</td>
                </tr>
                 <tr class="ligne-nap">
                    <td>NET À PAYER</td>
                    <td class="val">{{ number_format($netAPayerAff, 0, ',', ' ') }} F</td>
                </tr>
                <tr class="ligne-ttc">
                    <td>MONTANT TTC</td>
                    <td class="val">{{ number_format($totalTtcAff, 0, ',', ' ') }} F</td>
                </tr>
                
            </table>
        </div>

        {{-- ════════════════════════════════════════════════════
             ✅ BLOC RÉCAPITULATIF — séparé des totaux par tirets
             Montant en lettres + Signatures
             ════════════════════════════════════════════════════ --}}
        <hr class="separateur-recap">

        <div class="bloc-recap">
            <div class="montant-lettres-box">
                Arrêté le présent bon de commande administratif à la somme TTC de
                <strong style="text-transform:uppercase;">@yield('montant_lettres')</strong>
            </div>

            <div class="signature-container clearfix">
                <div style="text-align:right;margin-bottom:15px;font-size:8pt;">
                    Yaoundé, Le __________________________
                </div>
                <div style="width:100%;">
                    <div style="width:33%;float:left;text-align:center;">
                        <div class="font-bold">Le Prestataire</div>
                    </div>
                    <div style="width:34%;float:left;"></div>
                    <div style="width:33%;float:left;text-align:center;">
                        <div class="font-bold" style="margin-top:10px;">
                            {{ $parametres->fonction_ordonnateur ?? 'LE DIRECTEUR GENERAL' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

    @else
        <div class="page-break"></div>
    @endif

@endforeach

@endsection