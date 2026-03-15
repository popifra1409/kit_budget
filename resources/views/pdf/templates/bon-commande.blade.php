@php
    // ✅ DÉSACTIVER LE FOOTER AUTOMATIQUE DU MASTER
    $disableFooter = true;

    // Récupérer les données du bon
    $bonCommande = $donnees['_raw'];

    // Définir des valeurs par défaut
    $service = $donnees['service'] ?? ($bonCommande->serviceDemandeur->nom ?? 'DIRECTION GENERALE');
    $numeroBca = $donnees['numero_bca'] ?? ($bonCommande->numero ?? '.........');
    $dateImpression = $donnees['date_impression'] ?? date('d/m/Y');
    $prestataireNom = $donnees['prestataire_nom'] ?? ($bonCommande->fournisseur->raison_sociale ?? '');
    $prestataireAdresse = $donnees['prestataire_adresse'] ?? ($bonCommande->fournisseur->adresse ?? '...............');
    $prestataireTel = $donnees['prestataire_tel'] ?? ($bonCommande->fournisseur->telephone ?? '......................');
    $prestataireContribuable =
        $donnees['prestataire_contribuable'] ?? ($bonCommande->fournisseur->nif ?? '........................');

    // Configuration de la pagination
    $lignesParPage = 10;
    $totalLignes = $bonCommande->lignes->count();
    $nombrePages = ceil($totalLignes / $lignesParPage);
    $nombrePages = $nombrePages > 0 ? $nombrePages : 1; // Au moins 1 page
    $lignesChunked = $bonCommande->lignes->chunk($lignesParPage);

@endphp

@extends('pdf.layouts.master')

@section('title', 'BCA N° ' . $numeroBca)

@section('montant_lettres')
    {{ $donnees['montant_lettres'] ?? \App\Helpers\NombreEnLettres::montantCFA($bonCommande->montant_ttc ?? 0) }}
@endsection

@push('styles')
    <style>
        /* ================= STYLES GÉNÉRAUX ================= */
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

        .mt-10 {
            margin-top: 10px;
        }

        .mt-20 {
            margin-top: 20px;
        }

        .font-bold {
            font-weight: bold;
        }

        .font-normal {
            font-weight: 400;
        }

        .text-right {
            text-align: right;
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

        .articles-table {
            margin: 15px 0;
            font-size: 9pt;
        }

        .articles-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            font-size: 9pt;
            border: 1px solid #000;
            padding: 6px;
        }

        .articles-table td {
            text-align: left;
            font-size: 9pt;
            border: 1px solid #000;
            padding: 6px;
        }

        .articles-table td.nombre {
            text-align: right;
        }

        .totaux-section {
            width: 100%;
            margin-top: 10px;
        }

        .totaux-table {
            width: 50%;
            margin-left: auto;
        }

        .totaux-table td.label {
            width: 50%;
            padding: 4px;
        }

        .totaux-table td.valeur {
            width: 40%;
            text-align: right;
            padding: 4px;
        }

        .signature-container {
            width: 100%;
            margin-top: 20px;
        }

        .signature-block {
            float: left;
            text-align: center;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        .montant-lettres-box {
            margin-top: 20px;
            text-align: center;
            font-style: italic;
            font-size: 10.5pt;
        }

        /* ================= STYLES PAGINATION ================= */
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

        .page-number {
            position: fixed;
            bottom: 1cm;
            right: 1.5cm;
            font-size: 9pt;
            color: #666;
        }
    </style>
@endpush

@section('content')
    {{-- ========================================
         PAGE 1 : En-tête complet
         ======================================== --}}

    {{-- Service et numéro BCA --}}
    <div class="service-info">
        DEMANDEUR: <span class="font-normal">{{ strtoupper($service) }}</span>
    </div>

    <div class="bca-numero">
        BCA N°: {{ $numeroBca }}
    </div>

    <div class="date-impression">
        Imprimé le {{ $dateImpression }}
    </div>

    {{-- Section: Pour les objets et matières ci-après --}}
    <div class="text-center font-bold mb-15">
        BON DE COMMANDE ADMINISTRATIF
    </div>
    <div class="text-center font-bold mb-15">
        Pour les objets et matières ci-après:
    </div>

    {{-- Informations prestataire --}}
    <div class="mb-15">
        <table class="simple">
            <tr>
                <td><strong>Objet du bon de commande: </strong></td>
                <td class="font-normal">{{ $bonCommande->engagement?->objet ?? ($bonCommande->objet ?? '') }}</td>
            </tr>
            <tr>
                <td><strong>Nom ou raison du Prestataire</strong></td>
                <td class="font-bold">{{ $prestataireNom }}</td>
            </tr>
        </table>
    </div>

    {{-- ========================================
         ✅ TABLEAU UNIQUE : Lignes + Totaux
         ======================================== --}}
    @if (isset($bonCommande->lignes) && $bonCommande->lignes->count() > 0)
        @foreach ($lignesChunked as $pageIndex => $lignesPage)
            @if ($pageIndex > 0)
                <div class="page-header-continue">
                    <div class="bca-box-continue">BCA N°: {{ $numeroBca }}</div>
                    <div style="margin-top: 5px;"><strong>Suite</strong></div>
                </div>
            @endif

            {{-- ✅ TABLEAU UNIQUE --}}
            <table class="articles-table">
                <thead>
                    <tr>
                        <th style="width: 16%;">REFERENCE</th>
                        <th style="width: 53%;">DESIGNATION</th>
                        <th style="width: 8%;">QTES</th>
                        <th style="width: 10%;">P.U</th>
                        <th style="width: 13%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Lignes de commande --}}
                    @foreach ($lignesPage as $i => $ligne)
                        <tr>
                            <td>{{ $ligne->reference ?? '-' }}</td>
                            <td>{{ $ligne->designation }}</td>
                            <td class="nombre">{{ number_format($ligne->quantite, 0, ',', ' ') }}</td>
                            <td class="nombre">{{ number_format($ligne->prix_unitaire_ht, 0, ',', ' ') }}</td>
                            <td class="nombre">{{ number_format($ligne->montant_ht, 0, ',', ' ') }}</td>
                        </tr>
                    @endforeach

                    {{-- ✅ TOTAUX dans le même tableau (dernière page seulement) --}}
                    @if ($loop->last)
                        {{-- Ligne de séparation --}}
                        <tr>
                            <td colspan="5" style="border-top: 2px solid #000; padding: 0; height: 2px;"></td>
                        </tr>

                        {{-- MONTANT HT --}}
                        <tr>
                            <td></td>
                            <td class="label font-bold" style="text-align: left; padding-left:300px;" colspan="2">MONTANT
                                HT</td>
                            <td class="nombre font-bold" colspan="2">{{ number_format($bonCommande->montant_ht ?? 0, 0, ',', ' ') }}
                                FCFA</td>
                        </tr>

                        {{-- MONTANT TVA --}}
                        <tr>
                            <td></td>
                            <td class="label font-bold" style="text-align: left; padding-left:300px;" colspan="2">MONTANT
                                TVA</td>
                            <td class="nombre font-bold" colspan="2">
                                {{ number_format($bonCommande->montant_tva ?? 0, 0, ',', ' ') }}
                                FCFA</td>
                        </tr>

                        {{-- MONTANT IR --}}
                        <tr>
                            <td></td>
                            <td class="label font-bold" style="text-align: left; padding-left:300px;" colspan="2">MONTANT
                                IR</td>
                            <td class="nombre font-bold" colspan="2">
                                {{ number_format($bonCommande->montant_ir ?? 0, 0, ',', ' ') }}
                                FCFA</td>
                        </tr>

                        {{-- NET À PAYER --}}
                        <tr style="background-color: #f0f0f0;">
                            <td></td>
                            <td class="label font-bold" style="text-align: left; padding-left:300px;" colspan="2">NET À
                                PAYER</td>
                            <td class="nombre font-bold" colspan="2">
                                {{ number_format($bonCommande->net_a_percevoir ?? 0, 0, ',', ' ') }} FCFA</td>
                        </tr>

                        {{-- MONTANT TOTAL TTC --}}
                        <tr style="background-color: #e8e8e8;">
                            <td></td>
                            <td class="label font-bold" style="text-align: left; padding-left:300px; font-size: 10pt;"
                                colspan="2">MONTANT
                                TOTAL TTC</td>
                            <td class="nombre font-bold" style="font-size: 10pt;" colspan="2">
                                {{ number_format($bonCommande->montant_ttc ?? 0, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            {{-- Montant en lettres (après le tableau, dernière page) --}}
            @if ($loop->last)
                <div class="montant-lettres-box" style="margin-top: 15px;">
                    Arrêté le présent bon de commande administratif à la somme TTC de
                    <strong>@yield('montant_lettres')</strong>
                </div>
            @endif

            {{-- Numérotation --}}
            <div class="page-number">
                Page {{ $pageIndex + 1 }} sur {{ $nombrePages }}
            </div>

            @if (!$loop->last)
                <div class="page-break"></div>
            @endif
        @endforeach
    @else
        {{-- Tableau vide --}}
        <table class="articles-table">
            <thead>
                <tr>
                    <th style="width: 16%;">REFERENCE</th>
                    <th style="width: 53%;">DESIGNATION</th>
                    <th style="width: 8%;">QTES</th>
                    <th style="width: 10%;">P.U</th>
                    <th style="width: 13%;">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="5" class="text-center" style="padding: 20px; color: #999;">
                        Aucune ligne de commande
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="page-number">Page 1 sur {{ $nombrePages }}</div>
    @endif

    {{-- ✅ SIGNATURES : Position absolue en bas SANS fixed --}}
    <div style="margin-top: 50px; page-break-inside: avoid;">
        <div class="text-right" style="margin-bottom: 20px; font-size: 8pt;">
            Yaoundé Le__________________________
        </div>

        <div class="clearfix">
            <div style="width: 33%; float: left; text-align: center;">
                <div class="font-bold">Le Prestataire</div>
            </div>

            <div style="width: 33%; float: left;">
                <!-- Vide -->
            </div>

            <div style="width: 33%; float: left; text-align: center;">
                <div class="mt-10 font-bold">
                    @php
                        $parametres = \App\Models\ParametresStructure::where('actif', true)->first();
                    @endphp
                    {{ $parametres->fonction_ordonnateur ?? 'LE DIRECTEUR GENERAL' }}
                </div>
            </div>
        </div>
    </div>
@endsection
