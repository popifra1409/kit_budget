@php
    // ✅ DÉSACTIVER LE FOOTER AUTOMATIQUE DU MASTER
    $disableFooter = true;

    $bonCommande = $donnees['_raw'];
    $parametres = \App\Models\ParametresStructure::where('actif', true)->first();

    // Configuration de la pagination
    $lignesParPage = 10; // Nombre de lignes par page
    $totalLignes = $bonCommande->lignes->count();
    $nombrePages = ceil($totalLignes / $lignesParPage);
    $lignesChunked = $bonCommande->lignes->chunk($lignesParPage);
@endphp

@extends('pdf.layouts.master')

@section('title', 'Bon de Commande ' . $bonCommande->numero)

@section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($bonCommande->montant_ttc) }}
@endsection

{{-- CSS pour la pagination --}}
@push('styles')
    <style>
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
         PAGE 1 : En-tête complet
         ======================================== --}}

    {{-- Date et numero de commande --}}
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
                        $bonCommande = $donnees['bon_commande'];
                        $nomenclature = $bonCommande->getNomenclaturePrincipale();
                    @endphp

                    {{ $nomenclature?->code ?? 'N/A' }} - {{ $nomenclature?->libelle ?? 'Non définie' }}
                </td>
            </tr>
            </tr>

            <tr>
                <td class="label">OBJET :</td>
                <td class="value">
                    {{ $bonCommande->engagement?->objet ?? ($bonCommande->objet ?? '') }}
                </td>
            </tr>
        </table>
    </div>

    {{-- ========================================
         PAGES : Tableau des lignes avec pagination
         ======================================== --}}
    @foreach ($lignesChunked as $pageIndex => $lignesPage)
        {{-- En-tête simplifié pour les pages suivantes --}}
        @if ($pageIndex > 0)
            <div class="page-header-continue">
                <div class="commande-box-continue">
                    COMMANDE {{ $parametres->sigle }} N° {{ $bonCommande->numero }}
                </div>
                <div style="margin-top: 5px;">
                    <strong>Suite</strong>
                </div>
            </div>
        @endif

        {{-- Tableau des lignes pour cette page --}}
        <table>
            <thead>
                <tr>
                    <th>REFERENCE</th>
                    <th>DESIGNATION</th>
                    <th>QTES</th>
                    <th>P.U</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lignesPage as $i => $ligne)
                    <tr>
                        {{-- <td class="num">{{ $pageIndex * $lignesParPage + $loop->iteration }}</td> --}}
                        <td class="ref">{{ $ligne->reference ?? '-' }}</td>
                        <td class="designation">{{ $ligne->designation }}</td>
                        <td class="num">{{ number_format($ligne->quantite, 0, ',', ' ') }}</td>
                        <td class="money">{{ number_format($ligne->prix_unitaire_ht, 0, ',', ' ') }}</td>
                        <td class="money">{{ number_format($ligne->montant_ht, 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Numérotation de la page --}}
        <div class="page-number">
            Page {{ $pageIndex + 1 }} sur {{ $nombrePages }}
        </div>

        {{-- Saut de page sauf pour la dernière page --}}
        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach

    {{-- ========================================
         DERNIÈRE PAGE : Totaux et signature
         ======================================== --}}

    {{-- Totaux --}}
    <div class="totaux">
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
    <div class="montant-lettres">
        Arrete le present bon de commande a la somme de
        <strong>@yield('montant_lettres')</strong>
    </div>

    {{-- Bas de page avec mentions et signature --}}
    <div class="bas-page">
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
@endsection
