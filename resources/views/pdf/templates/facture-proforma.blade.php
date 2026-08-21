@php
$facture     = $donnees['_raw'];
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
        'logo_override'     => null,
    ];
}

$sigle = $entete['sigle'] ?? $parametres?->sigle ?? 'CHUY';

$logoStdBase64 = null; $logoStdMimeType = 'image/jpeg';
if ($parametres?->logo) {
    $p = storage_path('app/public/' . ltrim($parametres->logo, '/'));
    if (file_exists($p)) {
        $logoStdBase64   = base64_encode(file_get_contents($p));
        $logoStdMimeType = mime_content_type($p) ?: 'image/jpeg';
    }
}

$dateImpression = now()->format('d/m/Y à H:i');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Facture Proforma — {{ $facture->numero }}</title>
<style>
    @page { size: A4 portrait !important; margin-top: 8mm; margin-bottom: 1.4cm; margin-left: 1.5cm; margin-right: 1.5cm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color:#000; }

    .entete-table { width:100%; border-collapse:collapse; border:none; margin-bottom:5px; }
    .entete-table td { border:none; vertical-align:top; font-size:7pt; text-align:center; }
    .entete-logo { height:36px; }
    .entete-sep  { border:none; border-top:1px solid #000; margin:3px 0 10px; }

    .titre-doc { text-align:center; font-weight:bold; font-size:12pt; background:#eee; border:1px solid #000; padding:8px 4px; margin-bottom:10px; }

    table.info { width:100%; border-collapse:collapse; margin-bottom:12px; }
    table.info td { border:none; padding:3px 6px; font-size:9pt; vertical-align:top; }
    table.info td.label { font-weight:bold; width:22%; }

    table.lignes { width:100%; border-collapse:collapse; font-size:8.5pt; margin-bottom:10px; }
    table.lignes th { border:1px solid #000; background:#e6e6e6; padding:5px 4px; text-align:center; font-weight:bold; }
    table.lignes td { border:1px solid #000; padding:4px 4px; }
    table.lignes td.money { text-align:right; white-space:nowrap; }
    table.lignes td.num   { text-align:center; }
    tr.total-row td { font-weight:bold; background:#f0f0f0; }

    .col-no    { width:4%; }
    .col-des   { width:28%; }
    .col-unite { width:6%; }
    .col-qte   { width:8%; }
    .col-pu    { width:12%; }
    .col-tva   { width:8%; }
    .col-ttc   { width:12%; }
    .col-ir    { width:11%; }
    .col-net   { width:11%; }

    .montant-lettres { margin-top:10px; padding:8px; background:#f8fafc; border:1px solid #cbd5e1; font-size:8.5pt; }

    .signature { margin-top:40px; text-align:right; font-size:9pt; }

    .pdf-footer { position:fixed; bottom:0; left:0; right:0; height:1.4cm; border-top:1px solid #ccc; background:#fff; padding-top:3px; font-size:6.5pt; color:#333; }
    .pdf-footer table { width:100%; border-collapse:collapse; }
    .pdf-footer td { border:none; padding:0 4px; font-size:6.5pt; }
</style>
</head>
<body>

<div class="pdf-footer">
    <table>
        <tr>
            <td style="width:34%; text-align:left;"><strong>Imprimé le :</strong> {{ $dateImpression }}</td>
            <td style="width:32%; text-align:center; font-weight:bold;">{{ $sigle }} — {{ $facture->numero }}</td>
            <td style="width:34%; text-align:right;"><strong>Facture Proforma</strong></td>
        </tr>
    </table>
</div>

<table class="entete-table">
    <tr>
        <td style="width:20%;">
            <strong>REPUBLIQUE DU CAMEROUN</strong><br><em>Paix - Travail - Patrie</em><br>
            <span style="font-size:6.5pt;">{{ $entete['ministere_fr'] ?? 'MINISTERE DE LA SANTE PUBLIQUE' }}</span>
        </td>
        <td style="width:60%;">
            <div style="font-size:9.5pt; font-weight:bold; text-transform:uppercase;">{{ $entete['titre_fr'] }}</div>
            <div style="font-size:7.5pt; font-style:italic;">{{ $entete['titre_en'] }}</div>
            @if($logoStdBase64)
                <img src="data:{{ $logoStdMimeType }};base64,{{ $logoStdBase64 }}" class="entete-logo">
            @endif
        </td>
        <td style="width:20%;">
            <strong>REPUBLIC OF CAMEROON</strong><br><em>Peace - Work - Fatherland</em><br>
            <span style="font-size:6.5pt;">{{ $entete['ministere_en'] ?? 'MINISTRY OF PUBLIC HEALTH' }}</span>
        </td>
    </tr>
</table>
<hr class="entete-sep">

<div class="titre-doc">FACTURE PROFORMA N° {{ $facture->numero }}</div>

<table class="info">
    <tr>
        <td class="label">Fournisseur :</td>
        <td>{{ $facture->fournisseur?->raison_sociale }}</td>
        <td class="label">Date :</td>
        <td>{{ $facture->date_facture?->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="label">Objet :</td>
        <td colspan="3">{{ $facture->objet }}</td>
    </tr>
</table>

<table class="lignes">
    <thead>
        <tr>
            <th class="col-no">N°</th>
            <th class="col-des">Désignation</th>
            <th class="col-unite">Unité</th>
            <th class="col-qte">Qté</th>
            <th class="col-pu">P.U HT</th>
            <th class="col-tva">TVA</th>
            <th class="col-ttc">Montant TTC</th>
            <th class="col-ir">IR</th>
            <th class="col-net">Net à Percevoir</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($facture->lignes as $i => $ligne)
        <tr>
            <td class="num">{{ $i + 1 }}</td>
            <td>{{ $ligne->designation }}</td>
            <td class="num">{{ $ligne->unite ?? '-' }}</td>
            <td class="num">{{ number_format($ligne->quantite, 2, ',', ' ') }}</td>
            <td class="money">{{ number_format($ligne->prix_unitaire_ht, 0, ',', ' ') }}</td>
            <td class="num">{{ number_format($ligne->taux_tva, 2, ',', '') }}%</td>
            <td class="money">{{ number_format($ligne->montant_ttc, 0, ',', ' ') }}</td>
            <td class="money">{{ number_format($ligne->montant_ir, 0, ',', ' ') }}</td>
            <td class="money">{{ number_format($ligne->net_a_percevoir, 0, ',', ' ') }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td colspan="4" style="text-align:right;">TOTAL</td>
            <td class="money">{{ number_format($facture->montant_ht, 0, ',', ' ') }}</td>
            <td></td>
            <td class="money">{{ number_format($facture->montant_ttc, 0, ',', ' ') }}</td>
            <td class="money">{{ number_format($facture->montant_ir, 0, ',', ' ') }}</td>
            <td class="money">{{ number_format($facture->net_a_percevoir, 0, ',', ' ') }}</td>
        </tr>
    </tfoot>
</table>

<div class="montant-lettres">
    Arrêtée la présente facture proforma à la somme TTC de
    <strong style="text-transform:uppercase;">{{ ucfirst(\App\Helpers\NombreEnLettres::montantCFA($facture->montant_ttc ?? 0)) }}</strong>.
</div>

@if ($facture->observations)
    <div style="font-size:8.5pt; margin-top:8px;"><strong>Observations :</strong> {{ $facture->observations }}</div>
@endif

<div class="signature">
    Fait le {{ now()->format('d/m/Y') }}<br><br><br>
    <strong>Le Fournisseur</strong>
</div>

</body>
</html>