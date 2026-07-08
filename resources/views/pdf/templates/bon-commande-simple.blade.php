@php
$disableFooter = true;
$disableHeader = true;

$bonCommande = $donnees['_raw'];
$parametres  = \App\Models\ParametresStructure::where('actif', true)->first();
$etatConfig  = $donnees['_etat_config'] ?? null;

// ── En-tête (identique à bon-commande.blade) ─────────────
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
$sigle = $entete['sigle'] ?? $parametres?->sigle ?? 'CHUY';

// ── Logo (identique à bon-commande.blade) ────────────────
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

// ── Taux ─────────────────────────────────────────────────
$montantHtBrut  = (float)($bonCommande->montant_ht  ?? 0);
$montantTvaBrut = (float)($bonCommande->montant_tva ?? 0);
$montantIrBrut  = (float)($bonCommande->montant_ir  ?? 0);
$tauxTva = 0;
if (isset($bonCommande->taux_tva) && $bonCommande->taux_tva > 0) $tauxTva = (float)$bonCommande->taux_tva;
elseif ($montantHtBrut > 0 && $montantTvaBrut > 0) $tauxTva = round(($montantTvaBrut/$montantHtBrut)*100,2);
$tauxIr = 0;
if (isset($bonCommande->taux_ir) && $bonCommande->taux_ir > 0) $tauxIr = (float)$bonCommande->taux_ir;
elseif ($montantHtBrut > 0 && $montantIrBrut > 0) $tauxIr = round(($montantIrBrut/$montantHtBrut)*100,2);

$labelTva = 'MONTANT TVA (' . rtrim(rtrim(number_format($tauxTva,2,',',''),'0'),',') . '%)';
$labelIr  = 'MONTANT IR ('  . rtrim(rtrim(number_format($tauxIr, 2,',',''),'0'),',') . '%)';

// ✅ Totaux depuis HT total (identique à bon-commande.blade)
$montantHt  = (float) $bonCommande->lignes->sum('montant_ht');
$montantTva = round($montantHt * $tauxTva / 100, 2);
$montantIr  = round($montantHt * $tauxIr  / 100, 2);
$montantTtc = round($montantHt + $montantTva, 2);
$netAPayer  = (int) round($montantHt - $montantIr, 0);

// ✅ Mode arrondi — contrôle le nombre de décimales dans tout le PDF
$modeArrondi = $bonCommande->mode_arrondi ?? true;
$decimales   = $modeArrondi ? 0 : 2;
// En mode décimal : montants avec décimales, pas d'arrondi intermédiaire
if (!$modeArrondi) {
    $montantTva = round($montantHt * $tauxTva / 100, 2);
    $montantIr  = round($montantHt * $tauxIr  / 100, 2);
    $montantTtc = round($montantHt + $montantTva, 2);
    $netAPayer  = round($montantHt - $montantIr, 2);
}

// ── Créateur ─────────────────────────────────────────────
$dateImpression = now()->format('d/m/Y à H:i');
$createur = null; $nomCreateur = '—'; $dateCreation = '—';
if ($bonCommande->created_by ?? null) $createur = \App\Models\User::find($bonCommande->created_by);
if (!$createur && ($bonCommande->user_id ?? null)) $createur = \App\Models\User::find($bonCommande->user_id);
if ($createur) $nomCreateur = $createur->username ?? $createur->login ?? $createur->name ?? '—';
if ($bonCommande->created_at)
    $dateCreation = \Carbon\Carbon::parse($bonCommande->created_at)->format('d/m/Y à H:i');


// ─────────────────────────────────────────────────────────
$lignesPage1     = 14;
$lignesPagesSuiv = 22;

$lignesAll       = $bonCommande->lignes;
$lignesChunked   = collect();
$lignesRest      = $lignesAll;
$lignesChunked->push($lignesRest->take($lignesPage1));
$lignesRest = $lignesRest->skip($lignesPage1);
while ($lignesRest->count() > 0) {
    $lignesChunked->push($lignesRest->take($lignesPagesSuiv));
    $lignesRest = $lignesRest->skip($lignesPagesSuiv);
}
if ($lignesChunked->isEmpty()) $lignesChunked->push(collect());
$nombrePages = $lignesChunked->count();


$nombreLignes = $bonCommande->lignes->count();

$totauxSurNouvellePage =
    $nombreLignes >= ($lignesPage1 - 3);

@endphp

@extends('pdf.layouts.master', ['orientation' => 'portrait'])
@section('title', 'BC N° ' . $bonCommande->numero)
@section('montant_lettres')
{{ \App\Helpers\NombreEnLettres::montantCFA($montantTtc) }}
@endsection

@push('styles')
<style>
@page {
    size: A4 portrait !important;
    margin-top:    {{ $logoOverride ? '0.5cm' : '6mm' }};
    margin-bottom: 3.2cm;
    margin-left:   1cm;
    margin-right:  1cm;
}
body { font-size: 8.5pt; }
.entete-separateur { border: none; border-top: 1px solid #000; margin: 4px 0 6px; }
.clearfix::after   { content: ""; display: table; clear: both; }

/* ── Rappel N° sur pages de suite ──────────────────────── */
.rappel-bc{
    margin-bottom:10px;
    padding-bottom:5px;
    border-bottom:1px solid #999;
}

.rappel-bc-box{
    display:inline-block;
    border:2px solid #000;
    padding:5px 14px;
    font-weight:bold;
    font-size:10pt;
    background:#f8f8f8;
}

/* ── Tableau articles ─────────────────────────────────── */
.articles-table       { width: 100%; border-collapse: collapse; margin: 6px 0; font-size: 8.5pt; table-layout: fixed; }
.articles-table thead { display: table-header-group; }
.articles-table tr    { page-break-inside: avoid; page-break-after: auto; }
.articles-table th    { background: #f0f0f0; font-weight: bold; text-align: center; font-size: 8pt; border: 1px solid #000; padding: 3px; overflow: hidden; word-wrap: break-word; }
.articles-table td    { text-align: left; font-size: 8pt; border: 1px solid #000; padding: 3px; overflow: hidden; word-wrap: break-word; white-space: normal; vertical-align: top; }
.articles-table td.nombre { text-align: right; white-space: nowrap; }
.articles-table tfoot{
    display:table-footer-group;
}

.articles-table tbody tr{
    page-break-inside:avoid;
}

.col-ref   { width: 16%; }
.col-des   { width: 46%; }
.col-qte   { width: 10%; }
.col-pu    { width: 14%; }
.col-tot   { width: 14%; }

/* ── Séparateurs ────────────────────────────────────────── */
.separateur-totaux { border: none; border-top: 2px solid #000; margin: 8px 0 5px; }
.separateur-recap  { border: none; border-top: 1px dashed #999; margin: 10px 0 6px; }

/* ── Blocs indivisibles ─────────────────────────────────── */
.bloc-totaux        { clear: both; page-break-inside: avoid; break-inside: avoid; }
.bloc-recap         { clear: both; page-break-inside: avoid; break-inside: avoid; }
.montant-lettres    { text-align: center; font-style: italic; font-size: 8pt; margin-bottom: 8px; page-break-inside: avoid; }

.bas-page{
    page-break-inside:avoid;
    break-inside:avoid;
    margin-top:10px;
}

/* ── Saut de page ──────────────────────────────────────── */
.page-break { page-break-after: always; break-after: page; }

/* ── Initiales — une seule fois en bas de dernière page ── */
.initiales { margin-top: 14px; padding-top: 4px; border-top: 1px solid #ccc; font-size: 6.5pt; color: #333; page-break-inside: avoid; break-inside: avoid; }
.initiales table { width: 100%; border-collapse: collapse; }
.initiales td    { border: none; padding: 0 4px; font-size: 6.5pt; vertical-align: top; }

/* ── Footer fixe (identique à bon-commande.blade) ────── */
.pdf-footer-fixe {
    position: fixed; bottom: 0; left: 0; right: 0;
    height: 1.6cm; border-top: 1px solid #ccc;
    background: #fff; padding-top: 2px; font-size: 6.5pt; color: #333;
}

.pdf-footer-fixe table { width: 100%; border-collapse: collapse; }
.pdf-footer-fixe td    { border: none; padding: 0 4px; font-size: 6.5pt; vertical-align: middle; }

.bloc-fin-document{
    page-break-inside:avoid;
    break-inside:avoid;

    margin-bottom:20mm;
}
</style>
@endpush

@section('content')

{{-- Footer fixe --}}
<div class="pdf-footer-fixe">
    <table>
        <tr>
            <td style="width:35%">Imprimé le : {{ $dateImpression }}</td>
            <td style="width:30%;text-align:center;font-weight:bold;">{{ $sigle }} — N° {{ $bonCommande->numero }}</td>
            <td style="width:35%;text-align:right;">
                Créé le : {{ $dateCreation }}
                @if($nomCreateur !== '—') &nbsp;|&nbsp; Par : {{ $nomCreateur }} @endif
            </td>
        </tr>
    </table>
</div>

@foreach ($lignesChunked as $pageIndex => $lignesPage)

    @if ($pageIndex === 0)
        {{-- ── En-tête première page : bicéphale + contenu spécifique ── --}}
        @if($logoOverride && $logoBase64)
            <div style="width:100%;margin-bottom:6px;line-height:0;font-size:0;">
                <img src="data:{{ $logoMimeType }};base64,{{ $logoBase64 }}" style="width:100%;display:block;height:auto;">
            </div>
        @else
            <table style="width:100%;border-collapse:collapse;border:none;margin-bottom:3px;">
                <tr>
                    <td style="width:22%;vertical-align:top;text-align:center;font-size:7pt;border:none;">
                        <strong>REPUBLIQUE DU CAMEROUN</strong><br><em>Paix - Travail - Patrie</em><br>
                        <span style="font-size:6.5pt;">{{ $entete['ministere_fr'] ?? 'MINISTERE DE LA SANTE PUBLIQUE' }}</span>
                        @if($entete['sous_direction_fr'] ?? null)<br><span style="font-size:6pt;font-style:italic;">{{ $entete['sous_direction_fr'] }}</span>@endif
                    </td>
                    <td style="width:56%;text-align:center;vertical-align:top;border:none;">
                        <div style="font-size:9.5pt;font-weight:bold;text-transform:uppercase;">{{ $entete['titre_fr'] }}</div>
                        <div style="font-size:7.5pt;font-style:italic;">{{ $entete['titre_en'] }}</div>
                        @if($logoStdBase64)<img src="data:{{ $logoStdMimeType }};base64,{{ $logoStdBase64 }}" style="height:30px;margin-top:2px;margin-bottom:2px;">@endif
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

        {{-- Contenu spécifique bon-commande-simple ──────────────────── --}}
        <div style="text-align:right;margin:4px 0;font-size:10pt;">
            <div style="display:inline-block;border:2px solid #000;padding:4px 12px;font-weight:bold;font-size:10pt;">
                COMMANDE {{ $sigle }} N° {{ $bonCommande->numero }}
            </div>
            &nbsp;&nbsp;<strong>Yaounde, le</strong>
            {{ \Carbon\Carbon::parse($bonCommande->date_emission)->format('d/m/Y') }}
        </div>

        <div class="info">
            <table class="info-table" style="width:100%;border-collapse:collapse;margin-top:4px;font-size:8.5pt;">
                <tr>
                    <td style="width:38%;padding:2px 4px;font-weight:bold;">Nom ou raison du Prestataire :</td>
                    <td style="padding:2px 4px;">{{ $bonCommande->fournisseur->raison_sociale ?? '' }}</td>
                </tr>
                <tr>
                    <td style="padding:2px 4px;font-weight:bold;vertical-align:top;">
                        Livraison - Reception<br>
                        de 7h30 a 12h du Lundi au Mercredi<br>
                        (Sauf urgence)
                    </td>
                    <td style="padding:2px 4px;vertical-align:middle;">{{ $bonCommande->serviceDemandeur->nom ?? '' }}</td>
                </tr>
                <tr>
                    <td style="padding:2px 4px;font-weight:bold;">DELAI :</td>
                    <td style="padding:2px 4px;">le plus court possible et a nous confirmer au plus tard le _____________</td>
                </tr>
                <tr>
                    <td style="padding:2px 4px;font-weight:bold;">IMPUTATION :</td>
                    <td style="padding:2px 4px;">
                        @php $nomenclature = $bonCommande->getNomenclaturePrincipale(); @endphp
                        {{ $nomenclature?->code ?? 'N/A' }} - {{ $nomenclature?->libelle ?? 'Non définie' }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:2px 4px;font-weight:bold;">OBJET :</td>
                    <td style="padding:2px 4px;">{{ $bonCommande->engagement?->objet ?? ($bonCommande->objet ?? '') }}</td>
                </tr>
            </table>
        </div>

   @else

<div class="rappel-bc">

    <table style="width:100%;border-collapse:collapse;margin-bottom:8px;">
        <tr>
            <td style="width:65%;">
                <span class="rappel-bc-box">
                    COMMANDE {{ $sigle }} N° {{ $bonCommande->numero }}
                </span>
            </td>

            <td style="width:35%;text-align:right;font-size:8pt;">
                <strong>PAGE DE SUITE</strong>
            </td>
        </tr>
    </table>

</div>

@endif

    {{-- Tableau des lignes ────────────────────────────────────── --}}
    <table class="articles-table">
        <colgroup>
            <col class="col-ref"><col class="col-des">
            <col class="col-qte"><col class="col-pu"><col class="col-tot">
        </colgroup>
        <thead>
            <tr>
                <th class="col-ref">REFERENCE</th>
                <th class="col-des">DESIGNATION</th>
                <th class="col-qte">QTES</th>
                <th class="col-pu">P.U</th>
                <th class="col-tot">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lignesPage as $ligne)
            <tr>
                <td>{{ $ligne->reference ?? '-' }}</td>
                <td>{{ $ligne->designation }}</td>
                <td class="nombre">{{ number_format($ligne->quantite, 0, ',', ' ') }}</td>
                <td class="nombre">{{ number_format($ligne->prix_unitaire_ht, $decimales, ',', ' ') }}</td>
                <td class="nombre">{{ number_format($ligne->montant_ht, $decimales, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if ($loop->last)

        {{-- Totaux ──────────────────────────────────────────────── --}}
        <div class="bloc-fin-document">

        @if($totauxSurNouvellePage)
            <div class="page-break"></div>

            <div class="rappel-bc">
                <span class="rappel-bc-box">
                    COMMANDE {{ $sigle }} N° {{ $bonCommande->numero }}
                </span>
            </div>
        @endif
        
        <div class="bloc-totaux">
            <table style="width:auto;min-width:280px;margin-left:auto;border-collapse:collapse;">
                <tr>
                    <td style="padding:2px 8px;font-size:8.5pt;">MONTANT HT</td>
                    <td style="padding:2px 8px;font-size:8.5pt;text-align:right;font-weight:bold;">{{ number_format($montantHt,$decimales,',',' ') }}</td>
                </tr>
                <tr>
                    <td style="padding:2px 8px;font-size:8.5pt;">{{ $labelTva }}</td>
                    <td style="padding:2px 8px;font-size:8.5pt;text-align:right;font-weight:bold;">{{ number_format($montantTva,$decimales,',',' ') }}</td>
                </tr>
                <tr>
                    <td style="padding:2px 8px;font-size:8.5pt;">{{ $labelIr }}</td>
                    <td style="padding:2px 8px;font-size:8.5pt;text-align:right;font-weight:bold;">{{ number_format($montantIr,$decimales,',',' ') }}</td>
                </tr>
                <tr style="border-top:1px solid #000;">
                    <td style="padding:2px 8px;font-size:8.5pt;font-weight:bold;">NET A PAYER</td>
                    <td style="padding:2px 8px;font-size:8.5pt;text-align:right;font-weight:bold;">{{ number_format($netAPayer,$decimales,',',' ') }}</td>
                </tr>
                <tr style="border-top:2px solid #000;background:#f0f0f0;">
                    <td style="padding:3px 8px;font-size:9pt;font-weight:bold;">MONTANT TOTAL TTC</td>
                    <td style="padding:3px 8px;font-size:9pt;text-align:right;font-weight:bold;">{{ number_format($montantTtc,$decimales,',',' ') }}</td>
                </tr>
            </table>
        </div>
        </div>


        {{-- Récapitulatif ───────────────────────────────────────── --}}
        <hr class="separateur-recap">

        <div class="bloc-recap">
            <div class="montant-lettres">
                Arrete le present bon de commande a la somme de
                <strong style="text-transform:uppercase;">@yield('montant_lettres')</strong>
            </div>

            <div class="bas-page clearfix">
                <div style="float:left;width:40%;">
                    <div>Ref. Offre : __________________</div>
                    <div style="margin-top:6px;">Conditions : voir au verso</div>
                </div>
                <div style="float:right;width:35%;text-align:center;">
                    <div style="font-weight:bold;">{{ $parametres->fonction_ordonnateur ?? 'LE DIRECTEUR GENERAL' }}</div>
                    <div style="margin-top:18mm;">{{ $parametres->nom_ordonnateur ?? '' }}</div>
                </div>
            </div>

           
        </div>

    @else
        <div class="page-break"></div>
    @endif

@endforeach

{{-- Pagination DomPDF — au niveau racine (identique à bon-commande.blade) --}}

<script type="text/php">
if (isset($pdf)) {

    $font = $fontMetrics->get_font(
        "Helvetica",
        "normal"
    );

    $pdf->text(
        50,
        50,
        "TEST PAGINATION",
        $font,
        12
    );
}
</script>
</script>

@endsection