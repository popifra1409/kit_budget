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

$logoOverride   = !empty($entete['logo_override']);
$logoBase64     = null; $logoMimeType = 'image/jpeg';
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

$service        = $donnees['service']         ?? ($bonCommande->serviceDemandeur->nom      ?? 'DIRECTION GENERALE');
$numeroBca      = $donnees['numero_bca']      ?? ($bonCommande->numero                     ?? '.........');
$dateImpression = now()->format('d/m/Y à H:i');
$prestataireNom = $donnees['prestataire_nom'] ?? ($bonCommande->fournisseur->raison_sociale ?? '');

$montantHt        = (float)($bonCommande->montant_ht  ?? 0);
$montantTva       = (float)($bonCommande->montant_tva ?? 0);
$montantIr        = (float)($bonCommande->montant_ir  ?? 0);
$montantTtc       = (float)($bonCommande->montant_ttc ?? 0);
$montantHtArrondi = (int) round($montantHt);
$montantIrArrondi = (int) round($montantIr);
$netAPayer        = $montantHtArrondi - $montantIrArrondi;

$tauxTva = 0;
if (isset($bonCommande->taux_tva) && $bonCommande->taux_tva > 0) $tauxTva = (float)$bonCommande->taux_tva;
elseif ($montantHt > 0 && $montantTva > 0) $tauxTva = round(($montantTva/$montantHt)*100,2);
$tauxIr = 0;
if (isset($bonCommande->taux_ir) && $bonCommande->taux_ir > 0) $tauxIr = (float)$bonCommande->taux_ir;
elseif ($montantHt > 0 && $montantIr > 0) $tauxIr = round(($montantIr/$montantHt)*100,2);

$labelTva = 'MONTANT TVA ('.rtrim(rtrim(number_format($tauxTva,2,',',''),'0'),',').'%)';
$labelIr  = 'MONTANT IR (' .rtrim(rtrim(number_format($tauxIr, 2,',',''),'0'),',').'%)';

$createur = null; $initiauxCreateur = '';
if ($bonCommande->created_by ?? null) $createur = \App\Models\User::find($bonCommande->created_by);
if (!$createur && ($bonCommande->user_id ?? null)) $createur = \App\Models\User::find($bonCommande->user_id);
if ($createur) $initiauxCreateur = $createur->username ?? $createur->login ?? $createur->name ?? '';

$nomenclatureCode = null; $nomenclatureLib = null;
$anneeImputation  = now()->year; $moisImputation = now()->format('m');
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
    $codeArticle      = $engagementBC->nomenclaturePrincipale->getCodeArticle()??substr($nomenclatureCode,0,6)??null;
    $dateEng          = \Carbon\Carbon::parse($engagementBC->date_engagement);
    $anneeImputation  = $engagementBC->exercice?->annee ?? $dateEng->year;
    $moisImputation   = $dateEng->format('m');
}
if ($nomenclatureCode) {
    $pa = ($codeArticle && $codeArticle !== $nomenclatureCode) ? $codeArticle.'-' : '';
    $ligneImputation = $anneeImputation.'-'.$moisImputation.'-'.$pa.$nomenclatureCode;
    if ($nomenclatureLib) $ligneImputation .= ' ('.strtoupper($nomenclatureLib).')';
}

$lignesChunked = collect([$bonCommande->lignes]);
@endphp

@extends('pdf.layouts.master', ['orientation' => 'portrait'])
@section('title', 'BCA N° ' . $numeroBca)
@section('montant_lettres')
{{ $donnees['montant_lettres'] ?? \App\Helpers\NombreEnLettres::montantCFA($bonCommande->montant_ttc ?? 0) }}
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
.content-wrapper  { padding-top: 0.3cm; }
.service-info     { margin-bottom: 3px; font-weight: bold; font-size: 9pt; }
.bca-numero       { text-align: right; font-weight: bold; margin-bottom: 5px; font-size: 9pt; }
.text-center      { text-align: center; }
.mb-8             { margin-bottom: 8px; }
.mb-10            { margin-bottom: 10px; }
.font-bold        { font-weight: bold; }
.font-normal      { font-weight: 400; }
.clearfix::after  { content: ""; display: table; clear: both; }
.entete-separateur { border: none; border-top: 1px solid #000; margin: 4px 0 8px; }
table.simple              { width: 100%; border-collapse: collapse; }
table.simple td           { border: none; padding: 3px; font-size: 8.5pt; }
table.simple td:first-child { width: 30%; }
.articles-table        { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 8.5pt; table-layout: fixed; }
.articles-table thead  { display: table-header-group; }
.articles-table tfoot  { display: table-footer-group; }
.articles-table tr     { page-break-inside: avoid; page-break-after: auto; }
.articles-table th     { background: #f0f0f0; font-weight: bold; text-align: center; font-size: 8.5pt; border: 1px solid #000; padding: 4px 3px; overflow: hidden; word-wrap: break-word; }
.articles-table td     { text-align: left; font-size: 8.5pt; border: 1px solid #000; padding: 4px 3px; overflow: hidden; word-wrap: break-word; white-space: normal; vertical-align: top; }
.articles-table td.nombre { text-align: right; white-space: nowrap; }
.col-reference   { width: 18%; }
.col-designation { width: 44%; }
.col-qte         { width: 8%;  }
.col-pu          { width: 15%; }
.col-total       { width: 15%; }

/*
 * ✅ BLOC RÉCAPITULATIF
 * page-break-inside:avoid → DomPDF envoie le bloc ENTIER sur la page suivante
 * si la place est insuffisante sur la page courante.
 *
 * ⚠️  Limitation DomPDF : avoid fonctionne mieux sur les blocs
 *     de taille raisonnable (<30% de la hauteur de page).
 *     Pour les cas extrêmes, forcer un page-break-before en PHP.
 */
.bloc-recapitulatif   { clear: both; display: block; margin-top: 15px; page-break-inside: avoid; break-inside: avoid; }
.totaux               { display: block; width: 100%; page-break-inside: avoid; break-inside: avoid; }
.montant-lettres-box  { margin-top: 14px; text-align: center; font-style: italic; font-size: 8pt; page-break-inside: avoid; break-inside: avoid; }
.signature-container  { margin-top: 15px; page-break-inside: avoid; break-inside: avoid; }
.ligne-imputation        { font-size: 8pt; margin-bottom: 5px; }
.ligne-imputation strong { font-weight: bold; text-decoration: underline; }
.page-break            { page-break-after: always; break-after: page; }
.page-header-continue  { text-align: right; margin-bottom: 12px; font-size: 9pt; }
.bca-box-continue      { display: inline-block; border: 2px solid #000; padding: 5px 12px; font-weight: bold; font-size: 10pt; margin-bottom: 5px; }

/*
 * ✅ FOOTER FIXE
 * position:fixed → DomPDF répète ce bloc sur CHAQUE page.
 * margin-bottom:2cm sur @page → espace réservé pour éviter le chevauchement.
 * La pagination (Page X/Y) est injectée par page_text() HORS de ce div.
 */
.pdf-footer-fixe {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    height: 1.6cm;
    border-top: 1px solid #ccc;
    background: #fff;
    padding-top: 2px;
    font-size: 6.5pt;
    color: #333;
}
.pdf-footer-fixe table { width: 100%; border-collapse: collapse; }
.pdf-footer-fixe td    { border: none; padding: 0 4px; font-size: 6.5pt; vertical-align: middle; }
</style>
@endpush

@section('content')

{{--
    ✅ SCRIPT PAGINATION — DOIT ÊTRE AU NIVEAU RACINE DU DOCUMENT
    ─────────────────────────────────────────────────────────────
    RÈGLE DomPDF : le <script type="text/php"> doit être placé
    HORS de tout élément position:fixed/absolute/relative.
    S'il est DANS le .pdf-footer-fixe, DomPDF ne l'exécute pas
    de façon fiable (comportement dépendant de la version).

    page_text() inscrit "Page X / Y" sur CHAQUE page automatiquement.
    Les variables {PAGE_NUM} et {PAGE_COUNT} sont résolues par DomPDF
    au moment du rendu de chaque page — elles sont donc toujours correctes,
    même si le bloc récapitulatif bascule en page 2.

    Coordonnées (points DomPDF, origine = coin supérieur gauche) :
      x = 260  → centré horizontalement sur A4 (595pt de large)
      y = 820  → dans la zone footer (842pt de haut - 2cm de marge ≈ 785pt)
--}}
<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->getFont("DejaVu Sans");
    $pdf->page_text(260, 820, "Page {PAGE_NUM} / {PAGE_COUNT}", $font, 7);
}
</script>

{{-- ── Footer fixe — infos sur toutes les pages ─────────────── --}}
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
    {{-- La ligne "Page X / Y" est positionnée par page_text() ci-dessus --}}
</div>

{{-- ── Contenu ───────────────────────────────────────────────── --}}
@foreach ($lignesChunked as $pageIndex => $lignesPage)

    @if ($pageIndex === 0)
        @if ($logoOverride && $logoBase64)
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
        <div class="service-info">DEMANDEUR : <span class="font-normal">{{ strtoupper($service) }}</span></div>
        <div class="bca-numero">BCA N° : {{ $numeroBca }}</div>
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
        @if ($ligneImputation)
            <div class="ligne-imputation"><strong>Ligne d'imputation budgétaire :</strong> {{ $ligneImputation }}</div>
        @endif

    @else
        <div class="page-header-continue">
            <div class="bca-box-continue">BCA N° : {{ $numeroBca }}</div>
            <div style="font-size:8.5pt;margin-top:3px;"><strong>Suite — Page {{ $pageIndex + 1 }}</strong></div>
        </div>
    @endif

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
        {{-- Séparateur — coupe le contexte table avant le bloc récapitulatif --}}
        <div style="height:1px;font-size:1px;line-height:1px;">&nbsp;</div>

        <div class="bloc-recapitulatif">

            <div class="totaux">
                <table style="width:auto;min-width:300px;margin-left:auto;border-collapse:collapse;">
                    <tr>
                        <td style="padding:3px 8px;font-size:8.5pt;">MONTANT HT</td>
                        <td style="padding:3px 8px;font-size:8.5pt;text-align:right;font-weight:bold;">{{ number_format($montantHt,0,',',' ') }} F</td>
                    </tr>
                    <tr>
                        <td style="padding:3px 8px;font-size:8.5pt;">{{ $labelTva }}</td>
                        <td style="padding:3px 8px;font-size:8.5pt;text-align:right;font-weight:bold;">
                            @if($montantTva<=0) EXONÉRÉE @else {{ number_format($montantTva,0,',',' ') }} F @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:3px 8px;font-size:8.5pt;">{{ $labelIr }}</td>
                        <td style="padding:3px 8px;font-size:8.5pt;text-align:right;font-weight:bold;">{{ number_format($montantIr,0,',',' ') }} F</td>
                    </tr>
                    <tr style="border-top:1px solid #000;">
                        <td style="padding:3px 8px;font-size:8.5pt;font-weight:bold;">NET À PAYER</td>
                        <td style="padding:3px 8px;font-size:8.5pt;text-align:right;font-weight:bold;">{{ number_format($netAPayer,0,',',' ') }} F</td>
                    </tr>
                    <tr style="border-top:2px solid #000;background:#f0f0f0;">
                        <td style="padding:4px 8px;font-size:9pt;font-weight:bold;">MONTANT TOTAL TTC</td>
                        <td style="padding:4px 8px;font-size:9pt;text-align:right;font-weight:bold;">{{ number_format($montantTtc,0,',',' ') }} F</td>
                    </tr>
                </table>
            </div>

            <div class="montant-lettres-box">
                Arrêté le présent bon de commande administratif à la somme TTC de
                <strong style="text-transform:uppercase;">@yield('montant_lettres')</strong>
            </div>

            <div class="signature-container clearfix">
                <div style="text-align:right;margin-bottom:15px;font-size:8pt;">
                    Yaoundé Le__________________________
                </div>
                <div style="width:100%;">
                    <div style="width:33%;float:left;text-align:center;">
                        <div class="font-bold">Le Prestataire</div>
                    </div>
                    <div style="width:33%;float:left;"></div>
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