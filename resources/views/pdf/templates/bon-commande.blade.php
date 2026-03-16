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

    $lignesPage1 = 20; // Page 1 avec en-tête complet
    $lignesPagesSuivantes = 25; // Pages suivantes avec en-tête mini

    $totalLignes = $bonCommande->lignes->count();

    // ✅ Découper intelligemment les lignes
    $lignesChunked = collect();
    $lignesRestantes = $bonCommande->lignes;

    if ($totalLignes > 0) {
        // Première page : prendre les X premières lignes
        $lignesChunked->push($lignesRestantes->take($lignesPage1));
        $lignesRestantes = $lignesRestantes->skip($lignesPage1);

        // Pages suivantes : découper par chunks de Y lignes
        while ($lignesRestantes->count() > 0) {
            $lignesChunked->push($lignesRestantes->take($lignesPagesSuivantes));
            $lignesRestantes = $lignesRestantes->skip($lignesPagesSuivantes);
        }
    }

    $nombrePages = $lignesChunked->count();

    $derniereLigneCount = $lignesChunked->last()?->count() ?? 0;
    // Si la dernière page a plus de 30 lignes, les totaux vont probablement sauter
    $totauxVontSauter = $derniereLigneCount >= 25;

    $parametres = \App\Models\ParametresStructure::where('actif', true)->first();

@endphp

@extends('pdf.layouts.master')

@section('title', 'BCA N° ' . $numeroBca)

@section('montant_lettres')
    {{ $donnees['montant_lettres'] ?? \App\Helpers\NombreEnLettres::montantCFA($bonCommande->montant_ttc ?? 0) }}
@endsection

{{-- ✅ CSS pour la pagination (comme le bon de commande) --}}
@push('styles')
    <style>
        /* ✅ MARGES DE PAGE POUR IMPRESSION PDF */
            @page {
                size: A4;
                margin-top: 2cm;
                margin-bottom: 2cm;
                margin-left: 1.5cm;
                margin-right: 1.5cm;
            }

            /* ✅ Container principal avec marges internes */
            .content-wrapper {
                padding-top: 1.5cm;
                min-height: 100vh;
            }

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

        .font-bold {
            font-weight: bold;
        }

        .font-normal {
            font-weight: 400;
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
            width: 100%;
            border-collapse: collapse;
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

        .montant-lettres-box {
            margin-top: 20px;
            text-align: center;
            font-style: italic;
            font-size: 8.5pt;
        }

        /* ✅ STYLES PAGINATION (comme le bon de commande) */
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

        .signature-container {
            margin-top: 50px;
            page-break-inside: avoid;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
@endpush

@section('content')
    {{-- ========================================
         PAGES : Boucle avec en-tête répété
         ======================================== --}}
    @foreach ($lignesChunked as $pageIndex => $lignesPage)
        {{-- ✅ EN-TÊTE COMPLET sur chaque page --}}
        @if ($pageIndex > 0)
            {{-- Pages suivantes : en-tête simplifié --}}
            <div style="margin-top: 15px;">
                <strong>Suite - Page {{ $pageIndex + 1 }}</strong>
            </div>
            <div class="page-header-continue">
                <div class="bca-box-continue">
                    BCA N°: {{ $numeroBca }}
                </div>
            </div>
        @else
            {{-- Page 1 : en-tête complet --}}
            <div class="service-info">
                DEMANDEUR: <span class="font-normal">{{ strtoupper($service) }}</span>
            </div>

            <div class="bca-numero">
                BCA N°: {{ $numeroBca }}
            </div>

            <div class="date-impression">
                Imprimé le {{ $dateImpression }}
            </div>

            <div class="text-center font-bold mb-10">
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
        @endif

        {{-- ✅ TABLEAU : Lignes + Totaux --}}
        <table class="articles-table">
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
            </tbody>
        </table>

        {{-- ✅ TOTAUX + MONTANT + SIGNATURES (dernière page, dans un bloc) --}}
        @if ($loop->last)
            {{-- ✅ Afficher l'en-tête SEULEMENT si totaux vont sauter --}}
            @if ($totauxVontSauter)
                <div style="page-break-after: avoid; margin-top: 15px; margin-bottom: 10px;">
                    <div style="text-align: right;">
                        <div
                            style="display: inline-block; border: 2px solid #000; padding: 6px 12px; font-weight: bold; font-size: 10pt;">
                            BCA N°: {{ $numeroBca }}
                        </div>
                        <div style="margin-top: 3px; font-size: 9pt;">
                            <strong>Récapitulatif</strong>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Totaux --}}
            <div class="totaux" style="page-break-inside: avoid;">
                <table>
                    <tr>
                        <td>MONTANT HT</td>
                        <td class="money">{{ number_format($bonCommande->montant_ht, 0, ',', ' ') }}</td>
                    </tr>
                    <tr>
                        <td>MONTANT TVA</td>
                        <td class="money">{{ number_format($bonCommande->montant_tva, 0, ',', ' ') }}</td>
                    </tr>
                    <tr>
                        <td>MONTANT IR</td>
                        <td class="money">{{ number_format($bonCommande->montant_ir, 0, ',', ' ') }}</td>
                    </tr>
                    <tr>
                        <td class="total-final">NET A PAYER</td>
                        <td class="money total-final">
                            {{ number_format($bonCommande->net_a_percevoir, 0, ',', ' ') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="total-final">MONTANT TOTAL TTC</td>
                        <td class="money total-final">
                            {{ number_format($bonCommande->montant_ttc, 0, ',', ' ') }}
                        </td>
                    </tr>
                </table>
            </div>

            {{-- Montant en lettres --}}
            <div class="montant-lettres-box" style="page-break-inside: avoid;">
                Arrêté le présent bon de commande administratif à la somme TTC de
                <strong style="text-transform: uppercase;">@yield('montant_lettres')</strong>
            </div>

            {{-- Signatures --}}
            <div class="signature-container clearfix" style="page-break-inside: avoid;">
                <div style="text-align: right; margin-bottom: 20px; font-size: 8pt;">
                    Yaoundé Le__________________________
                </div>

                <div style="width: 100%;">
                    <div style="width: 33%; float: left; text-align: center;">
                        <div class="font-bold">Le Prestataire</div>
                    </div>
                    <div style="width: 33%; float: left;"></div>
                    <div style="width: 33%; float: left; text-align: center;">
                        @php
                            $parametres = \App\Models\ParametresStructure::where('actif', true)->first();
                        @endphp
                        <div class="font-bold" style="margin-top: 10px;">
                            {{ $parametres->fonction_ordonnateur ?? 'LE DIRECTEUR GENERAL' }}
                        </div>
                    </div>
                </div>
            </div>
        @endif
        <div class="page-number">
            Page {{ $pageIndex + 1 }} sur {{ $nombrePages }}
        </div>
        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
@endsection
