@php
/**
 * ✅ Template passant par EtatConfig
 * Variables reçues :
 *   $donnees['_raw']          → RegieAvance
 *   $donnees['_etat_config']  → EtatConfig (pour getEntete)
 *   $donnees['depenses']      → Collection BonCommandeRegie (montant_ir > 0)
 *   $donnees['numeroTranche'] → string
 *   $donnees['dateDebut']     → Carbon
 *   $donnees['dateFin']       → Carbon
 *   $donnees['totaux']        → array [ttc, tva, ir, net]
 */
$regie         = $donnees['_raw'];
$etatConfig    = $donnees['_etat_config'] ?? null;
$depenses      = $donnees['depenses'];
$numeroTranche = $donnees['numeroTranche'] ?? '';
$dateDebut     = $donnees['dateDebut'];
$dateFin       = $donnees['dateFin'];
$totaux        = $donnees['totaux'];

// ✅ Entête via EtatConfig — même pattern que tous les autres documents
$parametres = \App\Models\ParametresStructure::where('actif', true)->first();

if ($etatConfig instanceof \App\Models\EtatConfig) {
    $entete = $etatConfig->getEntete($parametres);
} else {
    $entete = [
        'titre_fr'          => $parametres?->nom_complet      ?? 'CENTRE HOSPITALIER ET UNIVERSITAIRE DE YAOUNDE',
        'titre_en'          => $parametres?->nom_structure_en ?? 'YAOUNDE UNIVERSITY TEACHING HOSPITAL',
        'sigle'             => $parametres?->sigle             ?? 'CHUY',
        'ministere_fr'      => 'MINISTERE DE LA SANTE PUBLIQUE',
        'ministere_en'      => 'MINISTRY OF PUBLIC HEALTH',
        'sous_direction_fr' => 'DIRECTION GÉNÉRALE',
        'sous_direction_en' => 'GENERAL MANAGEMENT',
        'titre_document'    => 'ÉTAT DES RETENUES FISCALES (IR)',
        'logo_override'     => null,
    ];
}

$sigle = $entete['sigle'] ?? $parametres?->sigle ?? 'CHUY';

// ✅ Logo standard
$logoStdBase64 = null; $logoStdMimeType = 'image/jpeg';
if ($parametres?->logo) {
    $p = storage_path('app/public/' . ltrim($parametres->logo, '/'));
    if (file_exists($p)) {
        $logoStdBase64   = base64_encode(file_get_contents($p));
        $logoStdMimeType = mime_content_type($p) ?: 'image/jpeg';
    }
}

// ── Pagination dynamique ──────────────────────────────────────
$budgetPage1    = 18;
$budgetSuivante = 26;

$chunks = collect(); $page = collect(); $poids = 0; $budget = $budgetPage1;
foreach ($depenses as $d) {
    $p = max(1, (int) ceil(mb_strlen($d->fournisseur?->raison_sociale ?? '') / 26));
    if ($page->isNotEmpty() && ($poids + $p) > $budget) {
        $chunks->push($page); $page = collect(); $poids = 0; $budget = $budgetSuivante;
    }
    $page->push($d); $poids += $p;
}
if ($page->isNotEmpty()) $chunks->push($page);
if ($chunks->isEmpty()) $chunks->push(collect());

$dateImpression = now()->format('d/m/Y à H:i');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>État Retenues IR — {{ $regie->numero }}</title>
<style>
    @page {
        size: A4 landscape !important;
        margin-top: 8mm;
        margin-bottom: 1.4cm;
        margin-left: 1.2cm;
        margin-right: 1.2cm;
    }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color:#000; }

    .entete-table { width:100%; border-collapse:collapse; border:none; margin-bottom:5px; }
    .entete-table td { border:none; vertical-align:top; font-size:7pt; text-align:center; }
    .entete-logo { height:36px; }
    .entete-sep  { border:none; border-top:1px solid #000; margin:3px 0 8px; }

    .titre-doc   { text-align:center; font-weight:bold; font-size:10.5pt; margin:6px 0 3px; text-transform:uppercase; }
    .sous-titre  { text-align:center; font-weight:bold; font-size:9.5pt; margin-bottom:6px; }

    table.depenses { width:100%; border-collapse:collapse; font-size:8.5pt; table-layout:fixed; }
    table.depenses th {
        border:1px solid #000; background:#e6e6e6; padding:5px 3px;
        text-align:center; font-weight:bold; font-size:8.5pt;
    }
    table.depenses td {
        border:1px solid #000; padding:4px 3px; font-size:8.5pt;
        word-wrap:break-word; vertical-align:top;
    }
    table.depenses td.num   { text-align:center; }
    table.depenses td.money { text-align:right; white-space:nowrap; }
    table.depenses tr.total-row td { font-weight:bold; background:#f0f0f0; }

    .col-no   { width:5%; }
    .col-fact { width:34%; }
    .col-four { width:26%; }
    .col-ttc  { width:13%; }
    .col-tva  { width:11%; }
    .col-ir   { width:11%; }

    .page-break { page-break-after:always; break-after:page; }

    /* ✅ Pied de page fixe — toutes les pages */
    .pdf-footer {
        position:fixed; bottom:0; left:0; right:0; height:1.6cm;
        border-top:1px solid #ccc; background:#fff; padding-top:3px;
        font-size:6.5pt; color:#333;
    }
    .pdf-footer table { width:100%; border-collapse:collapse; }
    .pdf-footer td    { border:none; padding:0 4px; font-size:6.5pt; }
    .pdf-footer .pag  { text-align:center; font-size:6.5pt; color:#666; border-top:1px dotted #ddd; padding-top:2px; margin-top:2px; }
</style>
</head>
<body>

{{-- ✅ Pied de page fixe — initiales + pagination toutes les pages --}}
<div class="pdf-footer">
    <table>
        <tr>
            <td style="width:34%; text-align:left;"><strong>Imprimé le :</strong> {{ $dateImpression }}</td>
            <td style="width:32%; text-align:center; font-weight:bold;">{{ $sigle }} — {{ $regie->numero }}</td>
            <td style="width:34%; text-align:right;"><strong>État Retenues IR</strong></td>
        </tr>
    </table>
    <div class="pag">
        <script type="text/php">
            if (isset($pdf)) {
                $f = $fontMetrics->getFont("DejaVu Sans", "normal");
                $pdf->page_text($pdf->get_width()/2 - 25, $pdf->get_height() - 18,
                    "Page {PAGE_NUM} / {PAGE_COUNT}", $f, 7, [0.5,0.5,0.5]);
            }
        </script>
    </div>
</div>

@foreach ($chunks as $i => $lignesPage)

@if ($i === 0)
    {{-- ✅ En-tête via EtatConfig --}}
    <table class="entete-table">
        <tr>
            <td style="width:20%;">
                <strong>REPUBLIQUE DU CAMEROUN</strong><br>
                <em>Paix - Travail - Patrie</em><br>
                <span style="font-size:6.5pt;">{{ $entete['ministere_fr'] ?? 'MINISTERE DE LA SANTE PUBLIQUE' }}</span>
                @if($entete['sous_direction_fr'] ?? null)
                <br><span style="font-size:6pt; font-style:italic;">{{ $entete['sous_direction_fr'] }}</span>
                @endif
            </td>
            <td style="width:60%;">
                <div style="font-size:9.5pt; font-weight:bold; text-transform:uppercase;">{{ $entete['titre_fr'] }}</div>
                <div style="font-size:7.5pt; font-style:italic;">{{ $entete['titre_en'] }}</div>
                @if($logoStdBase64)
                <img src="data:{{ $logoStdMimeType }};base64,{{ $logoStdBase64 }}" class="entete-logo">
                @endif
                @if($entete['sous_direction_fr'] ?? null)
                <div style="font-size:7pt; margin-top:2px; font-weight:bold;">
                    {{ $entete['sous_direction_fr'] }}
                    @if($entete['sous_direction_en'] ?? null)
                    <br><em style="font-size:6.5pt; font-weight:normal;">{{ $entete['sous_direction_en'] }}</em>
                    @endif
                </div>
                @endif
            </td>
            <td style="width:20%;">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace - Work - Fatherland</em><br>
                <span style="font-size:6.5pt;">{{ $entete['ministere_en'] ?? 'MINISTRY OF PUBLIC HEALTH' }}</span>
                @if($entete['sous_direction_en'] ?? null)
                <br><span style="font-size:6pt; font-style:italic;">{{ $entete['sous_direction_en'] }}</span>
                @endif
            </td>
        </tr>
    </table>
    <hr class="entete-sep">

    <div class="titre-doc">
        ÉTAT DES RETENUES FISCALES (IR) OPÉRÉES SUR L'UTILISATION DE
        {{ strtoupper($numeroTranche ?: 'L\'ENCAISSE') }}
        DE LA RÉGIE D'AVANCE N° {{ $regie->numero }}
    </div>
    <div class="sous-titre">
        @if($dateDebut && $dateFin)
            DU {{ $dateDebut->format('d/m/Y') }} AU {{ $dateFin->format('d/m/Y') }}
        @endif
        — {{ strtoupper($regie->libelle) }}
    </div>

    <div style="font-weight:bold; font-size:9pt; margin:6px 0 4px;">
        Tableau 1 : État des Retenues Fiscales (IR)
    </div>
@else
    <div style="text-align:right; font-size:8.5pt; margin-bottom:8px;">
        <strong>{{ $sigle }} — {{ $regie->numero }}</strong> — Suite Page {{ $i + 1 }}
    </div>
@endif

<table class="depenses">
    <colgroup>
        <col class="col-no"><col class="col-fact"><col class="col-four">
        <col class="col-ttc"><col class="col-tva"><col class="col-ir">
    </colgroup>
    <thead>
        <tr>
            <th class="col-no">N°</th>
            <th class="col-fact">N° FACTURE</th>
            <th class="col-four">FOURNISSEUR</th>
            <th class="col-ttc">TTC</th>
            <th class="col-tva">TVA (19,25%)</th>
            <th class="col-ir">IR (5,5%)</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lignesPage as $idx => $d)
        <tr>
            <td class="num">{{ $idx + 1 }}</td>
            <td>{{ $d->numero }}@if($d->date_emission) du {{ \Carbon\Carbon::parse($d->date_emission)->format('d/m/Y') }}@endif</td>
            <td>{{ $d->fournisseur?->raison_sociale ?? '—' }}</td>
            <td class="money">{{ number_format($d->montant_ttc, 0, ',', ' ') }}</td>
            <td class="money">{{ $d->montant_tva > 0 ? number_format($d->montant_tva, 0, ',', ' ') : '0' }}</td>
            <td class="money">{{ number_format($d->montant_ir, 0, ',', ' ') }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center; padding:10px;">Aucune retenue IR sur la période sélectionnée</td></tr>
        @endforelse
    </tbody>
</table>

@if ($loop->last)
    <table class="depenses" style="margin-top:0;">
        <tr class="total-row">
            <td colspan="3" style="text-align:right; padding:3px 8px;">TOTAL</td>
            <td class="money">{{ number_format($totaux['ttc'], 0, ',', ' ') }}</td>
            <td class="money">{{ number_format($totaux['tva'], 0, ',', ' ') }}</td>
            <td class="money">{{ number_format($totaux['ir'],  0, ',', ' ') }}</td>
        </tr>
    </table>

    <div style="margin-top:12px; text-align:right; font-size:9pt; page-break-inside:avoid;">
        Le Régisseur
    </div>
@else
    <div class="page-break"></div>
@endif

@endforeach
</body>
</html>