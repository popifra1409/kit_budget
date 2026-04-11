@php
    $disableFooter = true;

    $bonCommande = $donnees['_raw'];
    $parametres  = \App\Models\ParametresStructure::where('actif', true)->first();

    $lignesPage1          = 8;
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

    // ✅ Nombre réel de pages — +1 si les totaux sautent
    $nombrePages = $lignesChunked->count() + ($totauxVontSauter ? 1 : 0);
@endphp

@extends('pdf.layouts.master')

@section('title', 'Bon de Commande ' . $bonCommande->numero)

@section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($bonCommande->montant_ttc) }}
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

    .content-wrapper { padding-top: 1.5cm; }

    .page-break {
        page-break-after: always;
        break-after: page;
    }

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

    /* ✅ Numéro de page INLINE — pas de fixed pour éviter la superposition */
    .page-number-inline {
        text-align: right;
        font-size: 9pt;
        color: #666;
        margin-top: 6px;
        padding-right: 2px;
    }

    .bloc-recapitulatif { page-break-inside: avoid; break-inside: avoid; }
    .totaux             { page-break-inside: avoid; break-inside: avoid; }
    .montant-lettres    { page-break-inside: avoid; break-inside: avoid;
                          page-break-before: avoid; break-before: avoid; }
    .bas-page           { page-break-inside: avoid; break-inside: avoid;
                          page-break-before: avoid; break-before: avoid; }
</style>
@endpush

@section('content')
    @foreach ($lignesChunked as $pageIndex => $lignesPage)

        {{-- ════════ EN-TÊTE ════════ --}}
        @if ($pageIndex === 0)
            <div style="text-align: right; margin: 15px 0; font-size: 10pt;">
                <div class="commande-box">
                    COMMANDE {{ $parametres->sigle }} N° {{ $bonCommande->numero }}
                </div>
                <strong>Yaounde, le</strong>
                {{ \Carbon\Carbon::parse($bonCommande->date_emission)->format('d/m/Y') }}
            </div>

            <div class="info">
                <table class="info-table">
                    <tr>
                        <td class="label">Nom ou raison du Prestataire :</td>
                        <td class="value">{{ $bonCommande->fournisseur->raison_sociale ?? '' }}</td>
                    </tr>
                    <tr>
                        <td class="label">
                            Livraison - Reception<br>
                            de 7h30 a 12h du Lundi au Mercredi<br>
                            (Sauf urgence)
                        </td>
                        <td class="value">{{ $bonCommande->serviceDemandeur->nom ?? '' }}</td>
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
                            @php $nomenclature = $bonCommande->getNomenclaturePrincipale(); @endphp
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
        @else
            <div class="page-header-continue">
                <div class="commande-box-continue">
                    COMMANDE {{ $parametres->sigle }} N° {{ $bonCommande->numero }}
                </div>
                <div style="font-size: 9pt; margin-top: 3px;">
                    <strong>Suite — Page {{ $pageIndex + 1 }}</strong>
                </div>
            </div>
        @endif

        {{-- ════════ TABLEAU DES LIGNES ════════ --}}
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
                @foreach ($lignesPage as $ligne)
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

        @if ($loop->last)
            {{-- ════════ DERNIÈRE PAGE : totaux ou saut si trop de lignes ════════ --}}

            @if ($totauxVontSauter)
                {{-- ✅ Numéro de la page des lignes (avant le saut) --}}
                <div class="page-number-inline">
                    Page {{ $pageIndex + 1 }} sur {{ $nombrePages }}
                </div>

                {{-- ✅ Saut explicite --}}
                <div class="page-break"></div>

                {{-- ✅ En-tête de la page des totaux --}}
                <div class="page-header-continue">
                    <div class="commande-box-continue">
                        COMMANDE {{ $parametres->sigle }} N° {{ $bonCommande->numero }}
                    </div>
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

                <div class="montant-lettres">
                    Arrete le present bon de commande a la somme de
                    <strong style="text-transform: uppercase;">@yield('montant_lettres')</strong>
                </div>

                <div class="bas-page">
                    <div class="mention-gauche">
                        <div>Ref. Offre : __________________</div>
                        <div style="margin-top: 6px;">Conditions : voir au verso</div>
                    </div>
                    <div class="signature">
                        <div class="signature-box">
                            <div class="fonction">
                                {{ $parametres->fonction_ordonnateur ?? 'LE DIRECTEUR GENERAL' }}
                            </div>
                            <div class="nom">{{ $parametres->nom_ordonnateur ?? '' }}</div>
                        </div>
                    </div>
                </div>

            </div>{{-- fin .bloc-recapitulatif --}}

            {{-- ✅ Numéro de la dernière page (totaux) --}}
            <div class="page-number-inline">
                Page {{ $nombrePages }} sur {{ $nombrePages }}
            </div>

        @else
            {{-- ✅ Numéro des pages intermédiaires --}}
            <div class="page-number-inline">
                Page {{ $pageIndex + 1 }} sur {{ $nombrePages }}
            </div>

            {{-- Saut entre chunks --}}
            <div class="page-break"></div>
        @endif

    @endforeach
@endsection