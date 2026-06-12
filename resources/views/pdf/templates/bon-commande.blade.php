@php
$disableFooter = true;
$disableHeader = true;

$bonCommande = $donnees['_raw'];

// ✅ Charger ParametresStructure EN PREMIER — utilisé partout
$parametres = \App\Models\ParametresStructure::where('actif', true)->first();

// ✅ Config entête depuis EtatConfig
$etatConfig = $donnees['_etat_config'] ?? null;

if ($etatConfig instanceof \App\Models\EtatConfig) {
    $entete = $etatConfig->getEntete($parametres);
} else {
    $entete = [
        'titre_fr'          => $parametres?->nom_complet      ?? 'CENTRE HOSPITALIER ET UNIVERSITAIRE DE YAOUNDE',
        'titre_en'          => $parametres?->nom_structure_en ?? 'YAOUNDE UNIVERSITY TEACHING HOSPITAL',
        'sigle'             => $parametres?->sigle             ?? 'CHUY',
        'ministere_fr'      => 'MINISTERE DE LA SANTE PUBLIQUE',
        'ministere_en'      => 'MINISTRY OF PUBLIC HEALTH',
        'sous_direction_fr' => null,
        'sous_direction_en' => null,
        'titre_document'    => null,
        'logo_override'     => null,
    ];
}

$titreDocument = $entete['titre_document'] ?? 'BON DE COMMANDE ADMINISTRATIF';
$sigle         = $entete['sigle'] ?? $parametres?->sigle ?? 'CHUY';

// ✅ Logo override en base64 pour DomPDF
$logoOverride  = !empty($entete['logo_override']);
$logoBase64    = null;
$logoMimeType  = 'image/jpeg';

if ($logoOverride) {
    $logoFilePath = storage_path('app/public/' . ltrim($entete['logo_override'], '/'));
    if (file_exists($logoFilePath)) {
        $logoBase64   = base64_encode(file_get_contents($logoFilePath));
        $logoMimeType = mime_content_type($logoFilePath) ?: 'image/jpeg';
    } else {
        $logoOverride = false;
    }
}

// Logo standard (mode texte)
$logoStdBase64   = null;
$logoStdMimeType = 'image/jpeg';

if (!$logoOverride && $parametres?->logo) {
    $logoStdPath = storage_path('app/public/' . ltrim($parametres->logo, '/'));
    if (file_exists($logoStdPath)) {
        $logoStdBase64   = base64_encode(file_get_contents($logoStdPath));
        $logoStdMimeType = mime_content_type($logoStdPath) ?: 'image/jpeg';
    }
}

// ── Reste des variables (inchangé) ────────────────────────
$service                 = $donnees['service'] ?? ($bonCommande->serviceDemandeur->nom ?? 'DIRECTION GENERALE');
$numeroBca               = $donnees['numero_bca'] ?? ($bonCommande->numero ?? '.........');
$dateImpression          = $donnees['date_impression'] ?? now()->format('d/m/Y à H:i');
$prestataireNom          = $donnees['prestataire_nom'] ?? ($bonCommande->fournisseur->raison_sociale ?? '');
$prestataireAdresse      = $donnees['prestataire_adresse'] ?? ($bonCommande->fournisseur->adresse ?? '...............');
$prestataireTel          = $donnees['prestataire_tel'] ?? ($bonCommande->fournisseur->telephone ?? '......................');
$prestataireContribuable = $donnees['prestataire_contribuable'] ?? ($bonCommande->fournisseur->nif ?? '........................');

// ── Montants ──────────────────────────────────────────────
$montantHt  = (float) ($bonCommande->montant_ht  ?? 0);
$montantTva = (float) ($bonCommande->montant_tva ?? 0);

// ✅ Logo override = entête complète en image
$logoOverride  = !empty($entete['logo_override']);
$logoBase64    = null;
$logoMimeType  = 'image/jpeg';

if ($logoOverride) {
    $logoFilePath = storage_path('app/public/' . ltrim($entete['logo_override'], '/'));

    if (file_exists($logoFilePath)) {
        // ✅ DomPDF lit mieux les images en base64
        $logoBase64   = base64_encode(file_get_contents($logoFilePath));
        $logoMimeType = mime_content_type($logoFilePath) ?: 'image/jpeg';
    } else {
        // Fichier introuvable → retour mode texte
        $logoOverride = false;
        \Log::warning('BCA: logo_override introuvable', [
            'path'    => $logoFilePath,
            'entete'  => $entete['logo_override'],
        ]);
    }
}

// Logo standard (mode texte uniquement)
$logoStdBase64   = null;
$logoStdMimeType = 'image/jpeg';

if (!$logoOverride && $parametres?->logo) {
    $logoStdPath = storage_path('app/public/' . ltrim($parametres->logo, '/'));
    if (file_exists($logoStdPath)) {
        $logoStdBase64   = base64_encode(file_get_contents($logoStdPath));
        $logoStdMimeType = mime_content_type($logoStdPath) ?: 'image/jpeg';
    }
}

$montantIr  = (float) ($bonCommande->montant_ir  ?? 0);
$montantTtc = (float) ($bonCommande->montant_ttc ?? 0);

// ✅ NET A PAYER = MHT arrondi - IR arrondi (valeurs telles qu'affichées)
$montantHtArrondi = (int) number_format($montantHt, 0, '.', '');
$montantIrArrondi = (int) number_format($montantIr, 0, '.', '');
$netAPayer        = $montantHtArrondi - $montantIrArrondi;

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

// ✅ Labels dynamiques
$labelTva = 'MONTANT TVA ('
    . rtrim(rtrim(number_format($tauxTva, 2, ',', ''), '0'), ',')
    . '%)';

$labelIr = 'MONTANT IR ('
    . rtrim(rtrim(number_format($tauxIr, 2, ',', ''), '0'), ',')
    . '%)';

// ── ParametresStructure ───────────────────────────────────
$parametres = \App\Models\ParametresStructure::where('actif', true)->first();

// ── ✅ Config entête depuis EtatConfig (si transmise) ─────
$etatConfig = $donnees['_etat_config'] ?? null;

if ($etatConfig instanceof \App\Models\EtatConfig) {
    $entete = $etatConfig->getEntete($parametres);
} else {
    $entete = [
        'titre_fr'          => $parametres?->nom_complet      ?? 'CENTRE HOSPITALIER ET UNIVERSITAIRE DE YAOUNDE',
        'titre_en'          => $parametres?->nom_structure_en ?? 'YAOUNDE UNIVERSITY TEACHING HOSPITAL',
        'sigle'             => $parametres?->sigle             ?? 'CHUY',
        'ministere_fr'      => 'MINISTERE DE LA SANTE PUBLIQUE',
        'ministere_en'      => 'MINISTRY OF PUBLIC HEALTH',
        'sous_direction_fr' => null,
        'sous_direction_en' => null,
        'titre_document'    => null,
        'logo_override'     => null,
    ];
}

// ✅ Titre du document (configurable)
$titreDocument = $entete['titre_document'] ?? 'BON DE COMMANDE ADMINISTRATIF';

// ✅ Sigle pour le pied de page
$sigle = $entete['sigle'] ?? $parametres?->sigle ?? 'CHUY';

// ✅ Logo override = entête complète en image → masquer le texte
$logoOverride = !empty($entete['logo_override']);
$logoPath     = null;
$logoExists   = false;

if ($logoOverride) {
    // Logo alternatif fourni dans EtatConfig
    $logoPath   = public_path('storage/' . ltrim($entete['logo_override'], '/'));
    $logoExists = file_exists($logoPath);
    // Si le fichier n'existe pas physiquement, repasser en mode texte
    if (!$logoExists) {
        $logoOverride = false;
    }
}

if (!$logoOverride && $parametres?->logo) {
    // Logo standard inclus dans l'entête texte
    $logoPath   = public_path('storage/' . ltrim($parametres->logo, '/'));
    $logoExists = file_exists($logoPath);
}

// ── Nomenclature budgétaire ───────────────────────────────
$nomenclatureCode = null;
$nomenclatureLib  = null;
$anneeImputation  = now()->year;
$moisImputation   = now()->format('m');
$codeArticle      = null;
$ligneImputation  = null;
$engagementBC     = null;

// Chemin 1 : engagement_id direct sur le BC
if ($bonCommande->engagement_id) {
    $engagementBC = \App\Models\Engagement::with('nomenclaturePrincipale', 'exercice')
        ->find($bonCommande->engagement_id);
}
// Chemin 2 : via engageable
if (!$engagementBC) {
    $engagementBC = \App\Models\Engagement::with('nomenclaturePrincipale', 'exercice')
        ->where('engageable_type', 'App\Models\BonCommande')
        ->where('engageable_id', $bonCommande->id)
        ->first();
}

if ($engagementBC?->nomenclaturePrincipale) {
    $nomenclatureCode = $engagementBC->nomenclaturePrincipale->code;
    $nomenclatureLib  = $engagementBC->nomenclaturePrincipale->libelle;
    $codeArticle      = $engagementBC->nomenclaturePrincipale->getCodeArticle()
        ?? substr($nomenclatureCode, 0, 6)
        ?? null;
    $dateEng          = \Carbon\Carbon::parse($engagementBC->date_engagement);
    $anneeImputation  = $engagementBC->exercice?->annee ?? $dateEng->year;
    $moisImputation   = $dateEng->format('m');
}

if ($nomenclatureCode) {
    $partieArticle   = ($codeArticle && $codeArticle !== $nomenclatureCode)
        ? $codeArticle . '-'
        : '';
    $ligneImputation = $anneeImputation
        . '-' . $moisImputation
        . '-' . $partieArticle
        . $nomenclatureCode;
    if ($nomenclatureLib) {
        $ligneImputation .= ' (' . strtoupper($nomenclatureLib) . ')';
    }
}

// ── Créateur du document ──────────────────────────────────
$createur         = null;
$initiauxCreateur = '—';
$dateCreation     = '—';

if ($bonCommande->created_by ?? null) {
    $createur = \App\Models\User::find($bonCommande->created_by);
}
if (!$createur && ($bonCommande->user_id ?? null)) {
    $createur = \App\Models\User::find($bonCommande->user_id);
}
if ($createur) {
    $initiauxCreateur = $createur->username
        ?? $createur->login
        ?? $createur->name
        ?? '—';
}
if ($bonCommande->created_at) {
    $dateCreation = \Carbon\Carbon::parse($bonCommande->created_at)
        ->format('d/m/Y à H:i');
}

// ── Pagination ────────────────────────────────────────────
$lignesPage1          = 10;
$lignesPagesSuivantes = 25;
$seuilSautTotaux      = 15;

$totalLignes     = $bonCommande->lignes->count();
$lignesChunked   = collect();
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
$totauxVontSauter   = $derniereLigneCount >= $seuilSautTotaux;
$nombrePages        = $lignesChunked->count() + ($totauxVontSauter ? 1 : 0);
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
        margin-top: {{ $logoOverride ? '0.5cm' : '2cm' }};
        margin-bottom: 1.5cm;
        margin-left: 1.5cm;
        margin-right: 1.5cm;
    }

    .content-wrapper { padding-top: 1.5cm; }

    .service-info {
        margin-bottom: 4px;
        font-weight: bold;
        font-size: 10pt;
    }

    .bca-numero {
        text-align: right;
        font-weight: bold;
        margin-bottom: 8px;
        font-size: 10pt;
    }

    .text-center { text-align: center; }
    .mb-10       { margin-bottom: 10px; }
    .mb-15       { margin-bottom: 15px; }
    .font-bold   { font-weight: bold; }
    .font-normal { font-weight: 400; }

    table.simple               { width: 100%; border-collapse: collapse; }
    table.simple td            { border: none; padding: 4px; font-size: 9pt; }
    table.simple td:first-child { width: 30%; }

    .articles-table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
        font-size: 9pt;
        table-layout: fixed;
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
    }

    .articles-table td {
        text-align: left;
        font-size: 9pt;
        border: 1px solid #000;
        padding: 5px 4px;
        overflow: hidden;
        word-wrap: break-word;
        white-space: normal;
        vertical-align: top;
    }

    .articles-table td.nombre {
        text-align: right;
        white-space: nowrap;
    }

    .col-reference   { width: 18%; }
    .col-designation { width: 44%; }
    .col-qte         { width: 8%;  }
    .col-pu          { width: 15%; }
    .col-total       { width: 15%; }

    .entete-separateur {
        border: none;
        border-top: 1px solid #000;
        margin: 6px 0 10px;
    }

    .montant-lettres-box {
        margin-top: 20px;
        text-align: center;
        font-style: italic;
        font-size: 8.5pt;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .page-break { page-break-after: always; break-after: page; }

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
        break-inside: avoid;
        page-break-before: avoid;
        break-before: avoid;
    }

    .clearfix::after { content: ""; display: table; clear: both; }

    .bloc-recapitulatif { page-break-inside: avoid; break-inside: avoid; }
    .totaux             { page-break-inside: avoid; break-inside: avoid; }

    .pdf-footer-end {
        margin-top: 20px;
        padding-top: 6px;
        border-top: 1px solid #ccc;
        font-size: 7pt;
        color: #000;
    }

    .pdf-footer-end table { width: 100%; border-collapse: collapse; }
    .pdf-footer-end td {
        border: none;
        padding: 0 4px;
        vertical-align: top;
        font-size: 7pt;
        color: #000;
    }

    .ligne-imputation        { font-size: 8.5pt; margin-bottom: 8px; }
    .ligne-imputation strong { font-weight: bold; text-decoration: underline; }
</style>
@endpush

@section('content')

@foreach ($lignesChunked as $pageIndex => $lignesPage)

{{-- ════ EN-TÊTE (page 1 uniquement) ════ --}}
@if ($pageIndex === 0)

 @if ($logoOverride && $logoBase64)
    {{-- ✅ Logo pleine largeur — occupe tout l'en-tête A4 --}}
    <div style="
        width: 100%;
        margin-bottom: 8px;
        line-height: 0;
        font-size: 0;
    ">
        <img src="data:{{ $logoMimeType }};base64,{{ $logoBase64 }}"
             style="
                width: 100%;
                display: block;
                height: auto;
             ">
    </div>
@else

    {{-- ✅ MODE TEXTE : entête institutionnel structuré (sans bordures) --}}
    <table style="width:100%; border-collapse:collapse; border:none; margin-bottom:6px;">
        <tr>
            <td style="width:22%; vertical-align:top; text-align:center; font-size:7.5pt; border:none;">
                <strong>REPUBLIQUE DU CAMEROUN</strong><br>
                <em>Paix - Travail - Patrie</em><br>
                <span style="font-size:6.5pt;">
                    {{ $entete['ministere_fr'] ?? 'MINISTERE DE LA SANTE PUBLIQUE' }}
                </span>
                @if($entete['sous_direction_fr'] ?? null)
                <br><span style="font-size:6pt; font-style:italic;">
                    {{ $entete['sous_direction_fr'] }}
                </span>
                @endif
            </td>
            <td style="width:56%; text-align:center; vertical-align:top; border:none;">
                {{-- ✅ Noms institution (FR/EN) AU-DESSUS du logo --}}
                <div style="font-size:10pt; font-weight:bold; text-transform:uppercase;">
                    {{ $entete['titre_fr'] }}
                </div>
                <div style="font-size:8pt; font-style:italic;">
                    {{ $entete['titre_en'] }}
                </div>

                {{-- ✅ Logo structure EN DESSOUS des noms FR/EN --}}
                @if($logoStdBase64)
                    <img src="data:{{ $logoStdMimeType }};base64,{{ $logoStdBase64 }}"
                         style="height:38px; margin-top:4px; margin-bottom:3px;">
                @endif

                @if($entete['sous_direction_fr'] ?? null)
                <div style="font-size:7.5pt; margin-top:2px;">
                    {{ $entete['sous_direction_fr'] }}
                    @if($entete['sous_direction_en'] ?? null)
                    <br><em style="font-size:7pt;">{{ $entete['sous_direction_en'] }}</em>
                    @endif
                </div>
                @endif
            </td>
            <td style="width:22%; vertical-align:top; text-align:center; font-size:7.5pt; border:none;">
                <strong>REPUBLIC OF CAMEROON</strong><br>
                <em>Peace - Work - Fatherland</em><br>
                <span style="font-size:6.5pt;">
                    {{ $entete['ministere_en'] ?? 'MINISTRY OF PUBLIC HEALTH' }}
                </span>
                @if($entete['sous_direction_en'] ?? null)
                <br><span style="font-size:6pt; font-style:italic;">
                    {{ $entete['sous_direction_en'] }}
                </span>
                @endif
            </td>
        </tr>
    </table>
@endif

    <hr class="entete-separateur">

    <div class="service-info">
        DEMANDEUR: <span class="font-normal">{{ strtoupper($service) }}</span>
    </div>
    <div class="bca-numero">BCA N°: {{ $numeroBca }}</div>

    {{-- ✅ Titre document configurable --}}
    <div class="text-center font-bold mb-10">
        {{ strtoupper($titreDocument) }}
    </div>
    <div class="text-center font-bold mb-15">
        Pour les objets et matières ci-après :
    </div>

    <div class="mb-15">
        <table class="simple">
            <tr>
                <td><strong>Objet du bon de commande :</strong></td>
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

    {{-- ✅ Ligne d'imputation budgétaire --}}
    @if ($ligneImputation)
    <div class="ligne-imputation">
        <strong>Ligne d'imputation budgétaire :</strong>
        {{ $ligneImputation }}
    </div>
    @endif

@else
    {{-- ── En-tête pages suivantes ─────────────────── --}}
    <div class="page-header-continue">
        <div class="bca-box-continue">BCA N° : {{ $numeroBca }}</div>
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
            <td class="nombre">
                {{ number_format($ligne->quantite, 0, ',', ' ') }}
            </td>
            <td class="nombre">
                {{ number_format($ligne->prix_unitaire_ht, 0, ',', ' ') }}
            </td>
            <td class="nombre">
                {{ number_format($ligne->montant_ht, 0, ',', ' ') }}
            </td>
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
        <div class="bca-box-continue">BCA N° : {{ $numeroBca }}</div>
        <div style="font-size: 9pt; margin-top: 3px;">
            <strong>Récapitulatif — Page {{ $nombrePages }}</strong>
        </div>
    </div>
    @endif

    <div class="bloc-recapitulatif">

        <div class="totaux">
            <table style="width:auto; min-width:320px;
                          margin-left:auto; border-collapse:collapse;">

                {{-- Montant HT --}}
                <tr>
                    <td style="padding:3px 8px; font-size:9pt;">
                        MONTANT HT
                    </td>
                    <td style="padding:3px 8px; font-size:9pt;
                               text-align:right; font-weight:bold;">
                        {{ number_format($montantHt, 0, ',', ' ') }} F
                    </td>
                </tr>

                {{-- TVA --}}
                <tr>
                    <td style="padding:3px 8px; font-size:9pt;">
                        {{ $labelTva }}
                    </td>
                    <td style="padding:3px 8px; font-size:9pt;
                               text-align:right; font-weight:bold;">
                        @if ($montantTva <= 0)
                            EXONEREE
                        @else
                            {{ number_format($montantTva, 0, ',', ' ') }} F
                        @endif
                    </td>
                </tr>

                {{-- IR --}}
                <tr>
                    <td style="padding:3px 8px; font-size:9pt;">
                        {{ $labelIr }}
                    </td>
                    <td style="padding:3px 8px; font-size:9pt;
                               text-align:right; font-weight:bold;">
                        {{ number_format($montantIr, 0, ',', ' ') }} F
                    </td>
                </tr>

                {{-- ✅ NET A PAYER = MHT arrondi - IR arrondi --}}
                <tr style="border-top:1px solid #000;">
                    <td style="padding:3px 8px; font-size:9pt; font-weight:bold;">
                        NET A PAYER
                    </td>
                    <td style="padding:3px 8px; font-size:9pt;
                               text-align:right; font-weight:bold;">
                        {{ number_format($netAPayer, 0, ',', ' ') }} F
                    </td>
                </tr>

                {{-- MONTANT TOTAL TTC --}}
                <tr style="border-top:2px solid #000; background:#f0f0f0;">
                    <td style="padding:4px 8px; font-size:9.5pt; font-weight:bold;">
                        MONTANT TOTAL TTC
                    </td>
                    <td style="padding:4px 8px; font-size:9.5pt;
                               text-align:right; font-weight:bold;">
                        {{ number_format($montantTtc, 0, ',', ' ') }} F
                    </td>
                </tr>

            </table>
        </div>

        <div class="montant-lettres-box">
            Arrêté le présent bon de commande administratif à la somme TTC de
            <strong style="text-transform: uppercase;">
                @yield('montant_lettres')
            </strong>
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

    {{-- ✅ Pied de page EN FIN DE DOCUMENT — sigle depuis entete_config --}}
    <div class="pdf-footer-end">
        <table>
            <tr>
                <td style="width:30%; text-align:left;">
                    <strong>Imprimé le :</strong> {{ $dateImpression }}
                </td>
                <td style="width:35%; text-align:center; font-weight:bold;">
                    {{ $sigle }} — BCA N° {{ $numeroBca }}
                </td>
                <td style="width:35%; text-align:right;">
                    <strong>Créé le :</strong> {{ $dateCreation }}
                    @if($initiauxCreateur !== '—')
                    &nbsp;|&nbsp; <strong>Par :</strong> {{ $initiauxCreateur }}
                    @endif
                </td>
            </tr>
        </table>
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