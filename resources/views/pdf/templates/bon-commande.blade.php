@php
    $disableFooter = true;

    $bonCommande = $donnees['_raw'];

    $service = $donnees['service'] ?? ($bonCommande->serviceDemandeur->nom ?? 'DIRECTION GENERALE');
    $numeroBca = $donnees['numero_bca'] ?? ($bonCommande->numero ?? '.........');
    $dateImpression = $donnees['date_impression'] ?? date('d/m/Y');
    $prestataireNom = $donnees['prestataire_nom'] ?? ($bonCommande->fournisseur->raison_sociale ?? '');
    $prestataireAdresse = $donnees['prestataire_adresse'] ?? ($bonCommande->fournisseur->adresse ?? '...............');
    $prestataireTel = $donnees['prestataire_tel'] ?? ($bonCommande->fournisseur->telephone ?? '......................');
    $prestataireContribuable = $donnees['prestataire_contribuable'] ?? ($bonCommande->fournisseur->nif ?? '........................');

    // ── Pagination ────────────────────────────────────────────
    $lignesPage1 = 8;
    $lignesPagesSuivantes = 25;
    $seuilSautTotaux = 15; // si dernière page >= X lignes → saut avant totaux

    $totalLignes = $bonCommande->lignes->count();
    $lignesChunked = collect();
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
    $totauxVontSauter = $derniereLigneCount >= $seuilSautTotaux;

    // ✅ Nombre réel de pages — +1 si les totaux sautent sur une page dédiée
    $nombrePages = $lignesChunked->count() + ($totauxVontSauter ? 1 : 0);

    $parametres = \App\Models\ParametresStructure::where('actif', true)->first();
@endphp

@extends('pdf.layouts.master', ['orientation' => 'landscape'])

@section('title', 'BCA N° ' . $numeroBca)

@section('montant_lettres')
    {{ $donnees['montant_lettres'] ?? \App\Helpers\NombreEnLettres::montantCFA($bonCommande->montant_ttc ?? 0) }}
@endsection

@push('styles')
    <style>
        @page {
            size: A4 portrait !important;
            margin-top: 2cm;
            margin-bottom: 2cm;
            margin-left: 1.5cm;
            margin-right: 1.5cm;
        }

        .content-wrapper {
            padding-top: 1.5cm;
        }

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

        /* ── Pagination ── */
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

        /* ✅ Numéro de page INLINE — pas de position:fixed (apparaîtrait sur toutes les pages) */
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
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        /* ── Blocs indivisibles ── */
        .bloc-recapitulatif {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .totaux {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .montant-lettres-box {
            page-break-inside: avoid;
            break-inside: avoid;
            page-break-before: avoid;
            break-before: avoid;
        }

        .signature-container {
            page-break-inside: avoid;
            break-inside: avoid;
            page-break-before: avoid;
            break-before: avoid;
        }
    </style>
@endpush

@section('content')
    @foreach ($lignesChunked as $pageIndex => $lignesPage)

        {{-- ════════════════════════════════════════
        EN-TÊTE DE PAGE
        ════════════════════════════════════════ --}}
        @if ($pageIndex === 0)
            {{-- Page 1 : en-tête complet --}}
            <div class="service-info">
                DEMANDEUR: <span class="font-normal">{{ strtoupper($service) }}</span>
            </div>
            <div class="bca-numero">BCA N°: {{ $numeroBca }}</div>
            <div class="date-impression">Imprimé le {{ $dateImpression }}</div>
            <div class="text-center font-bold mb-10">BON DE COMMANDE ADMINISTRATIF</div>
            <div class="text-center font-bold mb-15">Pour les objets et matières ci-après:</div>

            <div class="mb-15">
                <table class="simple">
                    <tr>
                        <td><strong>Objet du bon de commande: </strong></td>
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
        @else
            {{-- Pages 2+ : en-tête réduit --}}
            <div class="page-header-continue">
                <div class="bca-box-continue">BCA N°: {{ $numeroBca }}</div>
                <div style="font-size: 9pt; margin-top: 3px;">
                    <strong>Suite — Page {{ $pageIndex + 1 }}</strong>
                </div>
            </div>
        @endif

        {{-- ════════════════════════════════════════
        TABLEAU DES LIGNES
        ════════════════════════════════════════ --}}
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
            {{-- ════════════════════════════════════════
            DERNIÈRE PAGE : totaux + signatures
            ════════════════════════════════════════ --}}

            @if ($totauxVontSauter)
                {{-- ✅ Numéro de la page des lignes AVANT le saut --}}
                <div class="page-number-inline">
                    Page {{ $pageIndex + 1 }} sur {{ $nombrePages }}
                </div>

                {{-- ✅ Saut de page explicite --}}
                <div class="page-break"></div>

                {{-- ✅ En-tête réduit sur la page des totaux --}}
                <div class="page-header-continue">
                    <div class="bca-box-continue">BCA N°: {{ $numeroBca }}</div>
                    <div style="font-size: 9pt; margin-top: 3px;">
                        <strong>Récapitulatif — Page {{ $nombrePages }}</strong>
                    </div>
                </div>
            @endif

            {{-- ✅ Bloc totaux + lettres + signatures — indivisible --}}
            <div class="bloc-recapitulatif">

                <div class="totaux">
                    <table>
                        <tr>
                            <td>MONTANT HT</td>
                            <td class="money">{{ number_format($bonCommande->montant_ht, 0, ',', ' ') }}</td>
                        </tr>
                        <tr>
                            <td>MONTANT TVA(19,25%)</td>
                            <td class="money">{{ number_format($bonCommande->montant_tva, 0, ',', ' ') }}</td>
                        </tr>
                        <tr>
                            <td>MONTANT IR(5,5%)</td>
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

                <div class="montant-lettres-box">
                    Arrêté le présent bon de commande administratif à la somme TTC de
                    <strong style="text-transform: uppercase;">@yield('montant_lettres')</strong>
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

            {{-- ✅ Numéro de la dernière page (totaux) --}}
            <div class="page-number-inline">
                Page {{ $nombrePages }} sur {{ $nombrePages }}
            </div>

        @else
            {{-- ════════════════════════════════════════
            PAGES INTERMÉDIAIRES
            ════════════════════════════════════════ --}}

            {{-- ✅ Numéro de page inline --}}
            <div class="page-number-inline">
                Page {{ $pageIndex + 1 }} sur {{ $nombrePages }}
            </div>

            {{-- Saut entre chunks --}}
            <div class="page-break"></div>
        @endif

    @endforeach
@endsection