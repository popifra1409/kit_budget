@php
    $disableHeader = true;
    $disableFooter = true;

    $eb         = $donnees['_raw'];
    $etatConfig = $donnees['_etat_config'] ?? null;
    $lignes     = $eb->lignes;
    $parametres = \App\Models\ParametresStructure::where('actif', true)->first();

    $entete = $etatConfig
        ? $etatConfig->getEntete($parametres)
        : [
            'titre_fr'          => $parametres?->nom_complet      ?? 'CENTRE HOSPITALIER ET UNIVERSITAIRE DE YAOUNDE',
            'titre_en'          => $parametres?->nom_structure_en ?? 'YAOUNDE UNIVERSITY TEACHING HOSPITAL',
            'ministere_fr'      => 'MINISTERE DE LA SANTE PUBLIQUE',
            'ministere_en'      => 'MINISTRY OF PUBLIC HEALTH',
            'sigle'             => $parametres?->sigle             ?? 'CHUY',
            'sous_direction_fr' => 'DIRECTION MEDICALE',
            'sous_direction_en' => 'MEDICAL DEPARTMENT',
            'logo_override'     => null,
        ];

    // ✅ Résolution du logo — même logique que bon-commande.blade.php (BCA)
    $logoBase64      = null;
    $logoOverride    = $entete['logo_override'] ?? null;
    $modeLogoComplet = false;

    if ($logoOverride) {
        $logoPath = storage_path('app/public/' . $logoOverride);
        if (file_exists($logoPath)) {
            $imageData       = base64_encode(file_get_contents($logoPath));
            $mimeType        = mime_content_type($logoPath);
            $logoBase64      = "data:{$mimeType};base64,{$imageData}";
            $modeLogoComplet = true;
        }
    } elseif ($parametres?->logo) {
        $logoPath = storage_path('app/public/' . $parametres->logo);
        if (file_exists($logoPath)) {
            $imageData  = base64_encode(file_get_contents($logoPath));
            $mimeType   = mime_content_type($logoPath);
            $logoBase64 = "data:{$mimeType};base64,{$imageData}";
        }
    }

    // ✅ Pagination — même logique que bon-commande-simple.blade.php
    $lignesPage1          = 10;
    $lignesPagesSuivantes = 28;
    $seuilSautSignature   = 20;

    $totalLignes     = $lignes->count();
    $lignesChunked   = collect();
    $lignesRestantes = $lignes;

    if ($totalLignes > 0) {
        $lignesChunked->push($lignesRestantes->take($lignesPage1));
        $lignesRestantes = $lignesRestantes->skip($lignesPage1);
        while ($lignesRestantes->count() > 0) {
            $lignesChunked->push($lignesRestantes->take($lignesPagesSuivantes));
            $lignesRestantes = $lignesRestantes->skip($lignesPagesSuivantes);
        }
    }

    $derniereLigneCount = $lignesChunked->last()?->count() ?? 0;
    $signatureVaSauter  = $derniereLigneCount >= $seuilSautSignature;
    $nombrePages        = max(1, $lignesChunked->count() + ($signatureVaSauter ? 1 : 0));

    $dateExpression = $eb->date_expression
        ? \Carbon\Carbon::parse($eb->date_expression)->format('d/m/Y')
        : '—';

    // ✅ Date impression — affichée UNE SEULE FOIS en fin de document
    $dateImpression = now()->format('d/m/Y à H:i');

    $signataire = $eb->responsableService?->name ?? $parametres?->nom_ordonnateur ?? '';
@endphp

@extends('pdf.layouts.master')

@section('title', 'Expression de Besoin ' . $eb->numero)

@push('styles')
<style>
    @page {
        size: A4 portrait !important;
        margin-top: {{ $modeLogoComplet ? '0.2cm' : '2cm' }};
        margin-bottom: 2cm;
        margin-left: 1.5cm;
        margin-right: 1.5cm;
    }

    .page-break {
        page-break-after: always;
        break-after: page;
    }

    .page-number-inline {
        text-align: right;
        font-size: 9pt;
        color: #666;
        margin-top: 6px;
        padding-right: 2px;
    }

    .page-header-continue {
        text-align: right;
        margin-bottom: 12px;
        font-size: 10pt;
    }

    .eb-box-continue {
        display: inline-block;
        border: 2px solid #000;
        padding: 6px 12px;
        font-weight: bold;
        font-size: 11pt;
        margin-bottom: 6px;
    }

    .sous-titre {
        text-align: center;
        font-size: 9.5pt;
        line-height: 1.5;
        margin: 4px 0 10px 0;
    }

    .titre-document {
        text-align: center;
        font-weight: bold;
        font-size: 11pt;
        text-decoration: underline;
        margin: 10px 0 4px 0;
    }

    .date-droite {
        text-align: right;
        font-size: 9.5pt;
        margin-bottom: 10px;
    }

    table.lignes th,
    table.lignes td { text-align: center; }
    table.lignes td.designation { text-align: left; }

    .bloc-signature {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .signature-eb { margin-top: 60px; text-align: right; }
    .signature-eb .fonction { font-weight: bold; font-size: 9pt; }
    .signature-eb .po { margin: 6px 0; font-size: 9pt; }
    .signature-eb .nom {
        border-top: 1px solid #000;
        padding-top: 4px;
        font-weight: bold;
        font-size: 9pt;
        display: inline-block;
        min-width: 180px;
    }
</style>
@endpush

@section('content')

{{-- ════════════════════════════════════════════════════════
     ITÉRATION SUR LES PAGES
     ════════════════════════════════════════════════════════ --}}

@if ($lignesChunked->isEmpty())
    @php $pagesIterables = collect([collect()]); @endphp
@else
    @php $pagesIterables = $lignesChunked; @endphp
@endif

@foreach ($pagesIterables as $pageIndex => $lignesPage)

{{-- ════════ EN-TÊTE ════════ --}}
@if ($pageIndex === 0)

    @if ($modeLogoComplet && $logoBase64)
        {{-- ✅ MODE IMAGE PLEINE LARGEUR (logo override EtatConfig) --}}
        <div style="text-align:center; margin-bottom:8px;">
            <img src="{{ $logoBase64 }}"
                 style="width:100%; max-width:800px; height:auto; display:block; margin:0 auto;"
                 alt="En-tête">
        </div>
    @else
        {{-- ✅ MODE 3 COLONNES TEXTE --}}
        <table class="header-table">
            <tr>
                <td class="header-left">
                    <div class="republique">
                        RÉPUBLIQUE DU CAMEROUN<br>
                        <em>Paix – Travail – Patrie</em>
                    </div>
                    <div class="republique" style="margin-top:4px;">
                        {{ strtoupper($entete['ministere_fr'] ?? 'MINISTERE DE LA SANTE PUBLIQUE') }}
                    </div>
                </td>
                <td class="header-center">
                    @if ($logoBase64)
                        <img src="{{ $logoBase64 }}" class="logo" alt="Logo">
                    @endif
                    <div class="structure">
                        {{ $entete['titre_fr'] ?? $parametres?->nom_complet ?? '' }}
                    </div>
                    @if (!empty($entete['titre_en']))
                        <div class="adresse" style="font-style:italic;">
                            {{ $entete['titre_en'] }}
                        </div>
                    @endif
                    @if ($parametres?->telephone)
                        <div class="adresse">Tél : {{ $parametres->telephone }}</div>
                    @endif
                </td>
                <td class="header-right">
                    <div class="republique">
                        REPUBLIC OF CAMEROON<br>
                        <em>Peace – Work – Fatherland</em>
                    </div>
                    <div class="republique" style="margin-top:4px; font-style:italic;">
                        {{ $entete['ministere_en'] ?? 'MINISTRY OF PUBLIC HEALTH' }}
                    </div>
                </td>
            </tr>
        </table>
    @endif

    {{-- Sous-titres directionnels --}}
    <div class="sous-titre">
        Service Demandeur: {{ strtoupper($eb->serviceDemandeur->nom ?? 'SERVICE') }}
    </div>

    <div class="date-droite">
        Yaoundé le : {{ $dateExpression }}
    </div>

    <div class="titre-document">
        EXPRESSION DES BESOINS — {{ strtoupper($eb->objet ?? '') }}
    </div>

@else

    {{-- ════════ EN-TÊTE DE CONTINUATION (pages suivantes) ════════ --}}
    <div class="page-header-continue">
        <div class="eb-box-continue">
            EB {{ $entete['sigle'] ?? '' }} N° {{ $eb->numero }}
        </div>
        <div style="font-size:9pt; margin-top:3px;">
            <strong>Suite — Page {{ $pageIndex + 1 }}</strong>
        </div>
    </div>

@endif

{{-- ════════ TABLEAU DES LIGNES ════════ --}}
<table class="lignes">
    <thead>
        <tr>
            <th style="width:6%;">N°</th>
            <th style="width:38%;">Désignation</th>
            <th style="width:20%;">Conditionnement</th>
            <th style="width:18%;">Quantité<br>en stock</th>
            <th style="width:18%;">Quantité<br>commandée</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lignesPage as $index => $ligne)
        @php
            $numGlobal = $pageIndex === 0
                ? $index + 1
                : $lignesPage1 + (($pageIndex - 1) * $lignesPagesSuivantes) + $index + 1;
        @endphp
        <tr>
            <td>{{ str_pad($numGlobal, 2, '0', STR_PAD_LEFT) }}</td>
            <td class="designation">{{ $ligne->article?->designation ?? '—' }}</td>
            <td>{{ $ligne->conditionnement?->libelle ?? ($ligne->article?->uniteMesure?->libelle ?? '—') }}</td>
            <td>{{ str_pad((int) ($ligne->quantite_en_stock ?? 0), 2, '0', STR_PAD_LEFT) }}</td>
            <td>{{ $ligne->quantite_demandee }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="5" style="text-align:center; font-style:italic; color:#666;">
                Aucune ligne enregistrée
            </td>
        </tr>
        @endforelse
    </tbody>
</table>

@if ($loop->last)

    {{-- ════════ DERNIÈRE PAGE : signature + pied de document ════════ --}}

    @if ($signatureVaSauter)
        {{-- Trop de lignes → la signature saute sur une nouvelle page --}}
        <div class="page-number-inline">
            Page {{ $pageIndex + 1 }} sur {{ $nombrePages }}
        </div>
        <div class="page-break"></div>
        <div class="page-header-continue">
            <div class="eb-box-continue">
                EB {{ $entete['sigle'] ?? '' }} N° {{ $eb->numero }}
            </div>
            <div style="font-size:9pt; margin-top:3px;">
                <strong>Signature — Page {{ $nombrePages }}</strong>
            </div>
        </div>
    @endif

    {{-- ✅ Bloc signature + pied de document — indivisible --}}
    <div class="bloc-signature">

        <div class="signature-eb">
            <div class="fonction">LE CHEF DE SERVICE</div>
            <div class="po"></div>
            <div class="nom">{{ $signataire }}</div>
        </div>

        {{-- ✅ Pied de document — UNE SEULE FOIS, en fin de document --}}
        {{-- PAS fixe, PAS répété à chaque page --}}
        <table style="width:100%; border-collapse:collapse; margin-top:40px;
                      border-top:1px solid #ccc; font-size:7pt; color:#666;">
            <tr>
                <td style="border:none; text-align:left; padding:3px 0; width:40%;">
                    <strong>Imprimé le :</strong> {{ $dateImpression }}
                </td>
                <td style="border:none; text-align:center; font-weight:bold; padding:3px 0; width:30%;">
                    {{ $entete['sigle'] ?? '' }} — N° {{ $eb->numero }}
                </td>
                <td style="border:none; text-align:right; padding:3px 0; width:30%;">
                    Page {{ $nombrePages }} sur {{ $nombrePages }}
                </td>
            </tr>
        </table>

    </div>

@else

    {{-- Pages intermédiaires — numéro de page uniquement --}}
    <div class="page-number-inline">
        Page {{ $pageIndex + 1 }} sur {{ $nombrePages }}
    </div>
    <div class="page-break"></div>

@endif

@endforeach

@endsection