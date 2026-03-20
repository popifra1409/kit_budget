@php
    // ✅ DÉSACTIVER LE FOOTER AUTOMATIQUE DU MASTER
    $disableFooter = true;

    $bonCommande = $donnees['_raw'];
    $parametres = \App\Models\ParametresStructure::where('actif', true)->first();

    // ✅ PAGINATION DYNAMIQUE
    $lignesPage1 = 17;
    $lignesPagesSuivantes = 25;

    $totalLignes = $bonCommande->lignes->count();

    // Découper intelligemment les lignes
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

    // ✅ Déterminer si les totaux vont sauter de page
    $derniereLigneCount = $lignesChunked->last()?->count() ?? 0;
    $totauxVontSauter = $derniereLigneCount >= 22;
@endphp

@extends('pdf.layouts.master')

@section('title', 'Bon de Commande ' . $bonCommande->numero)

@section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($bonCommande->montant_ttc) }}
@endsection

{{-- CSS pour la pagination --}}
@push('styles')
    <style>
        @page {
            size: A4 portrait !important;
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

        /* Forcer le saut de page */
        .page-break {
            page-break-after: always;
            break-after: page;
        }

        /* En-tête de page suivante */
        .page-header-continue {
            text-align: right;
            margin-bottom: 20px;
            font-size: 10pt;
        }

        .commande-box-continue {
            display: inline-block;
            border: 2px solid #000;
            padding: 8px 15px;
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: 10px;
        }

        /* Numérotation des pages */
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
         BOUCLE AVEC PAGINATION
         ======================================== --}}
    @foreach ($lignesChunked as $pageIndex => $lignesPage)
        {{-- ✅ EN-TÊTE --}}
        @if ($pageIndex > 0)
            {{-- Pages suivantes : en-tête simplifié --}}
            <div style="margin-top: 15px;">
                <strong>Suite - Page {{ $pageIndex + 1 }}</strong>
            </div>
            <div class="page-header-continue">
                <div class="commande-box-continue">
                    COMMANDE {{ $parametres->sigle }} N° {{ $bonCommande->numero }}
                </div>
            </div>
        @else
            {{-- Page 1 : en-tête complet --}}
            <div style="text-align: right; margin: 15px 0; font-size: 10pt;">
                <div class="commande-box">
                    COMMANDE {{ $parametres->sigle }} N° {{ $bonCommande->numero }}
                </div>
                <strong>Yaounde, le</strong> {{ \Carbon\Carbon::parse($bonCommande->date_emission)->format('d/m/Y') }}
            </div>

            {{-- Informations du bon de commande --}}
            <div class="info">
                <table class="info-table">
                    <tr>
                        <td class="label">Nom ou raison du Prestataire :</td>
                        <td class="value">
                            {{ $bonCommande->fournisseur->raison_sociale ?? '' }}
                        </td>
                    </tr>

                    <tr>
                        <td class="label">
                            Livraison - Reception<br>
                            de 7h30 a 12h du Lundi au Mercredi<br>
                            (Sauf urgence)
                        </td>
                        <td class="value">
                            {{ $bonCommande->serviceDemandeur->nom ?? '' }}
                        </td>
                    </tr>

                    <tr>
                        <td class="label">DELAI :</td>
                        <td class="value">
                            le plus court possible et a nous confirmer au plus tard le _____________
                        </td>
                    </tr>

                    <tr>
                        <td class="label">IMPUTATION :</td>
                        <td class="value">
                            @php
                                $nomenclature = $bonCommande->getNomenclaturePrincipale();
                            @endphp
                            {{ $nomenclature?->code ?? 'N/A' }} - {{ $nomenclature?->libelle ?? 'Non définie' }}
                        </td>
                    </tr>

                    <tr>
                        <td class="label">OBJET :</td>
                        <td class="value">
                            {{ $bonCommande->engagement?->objet ?? ($bonCommande->objet ?? '') }}
                        </td>
                    </tr>
                </table>
            </div>
        @endif

        {{-- ✅ TABLEAU DES LIGNES --}}
        <table>
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
                @foreach ($lignesPage as $i => $ligne)
                    <tr>
                        <td class="ref">{{ $ligne->reference ?? '-' }}</td>
                        <td class="designation">{{ $ligne->designation }}</td>
                        <td class="num">{{ number_format($ligne->quantite, 0, ',', ' ') }}</td>
                        <td class="money">{{ number_format($ligne->prix_unitaire_ht, 0, ',', ' ') }}</td>
                        <td class="money">{{ number_format($ligne->montant_ht, 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ✅ TOTAUX + MONTANT + SIGNATURES (dernière page) --}}
        @if ($loop->last)
            {{-- ✅ En-tête SEULEMENT si totaux vont sauter --}}
            @if ($totauxVontSauter)
                <div style="page-break-after: avoid; margin-top: 15px; margin-bottom: 10px;">
                    <div style="text-align: right;">
                        <div
                            style="display: inline-block; border: 2px solid #000; padding: 6px 12px; font-weight: bold; font-size: 10pt;">
                            COMMANDE {{ $parametres->sigle }} N° {{ $bonCommande->numero }}
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
            <div class="montant-lettres" style="page-break-inside: avoid;">
                Arrete le present bon de commande a la somme de
                <strong style="text-transform: uppercase;">@yield('montant_lettres')</strong>
            </div>

            {{-- Bas de page avec mentions et signature --}}
            <div class="bas-page" style="page-break-inside: avoid;">
                <div class="mention-gauche">
                    <div>Ref. Offre : __________________</div>
                    <div style="margin-top:6px;">
                        Conditions : voir au verso
                    </div>
                </div>

                <div class="signature">
                    <div class="signature-box">
                        <div class="fonction">
                            {{ $parametres->fonction_ordonnateur ?? 'LE DIRECTEUR GENERAL' }}
                        </div>
                        <div class="nom">
                            {{ $parametres->nom_ordonnateur ?? '' }}
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Numérotation --}}
        <div class="page-number">
            Page {{ $pageIndex + 1 }} sur {{ $nombrePages }}
        </div>

        {{-- Saut de page sauf dernière --}}
        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
@endsection
