@extends('pdf.layouts.master')

@php
    $bonCommande = $donnees['_raw'];
    $parametres = \App\Models\ParametresStructure::where('actif', true)->first();
@endphp

@section('title', 'Bon de Commande ' . $bonCommande->numero)

@section('montant_lettres')
    {{ \App\Helpers\NombreEnLettres::montantCFA($bonCommande->montant_ttc) }}
@endsection

@section('content')
    {{-- Date et numéro de commande --}}
    <div style="text-align: right; margin: 15px 0; font-size: 10pt;">
        <div class="commande-box">
            COMMANDE {{ $parametres->sigle }} N° {{ $bonCommande->numero }}
        </div>
        <strong>Yaoundé, le</strong> {{ \Carbon\Carbon::parse($bonCommande->date_emission)->format('d/m/Y') }}
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
                    Livraison – Réception<br>
                    de 7h30 à 12h du Lundi au Mercredi<br>
                    (Sauf urgence)
                </td>
                <td class="value">
                    {{ $bonCommande->serviceDemandeur->nom ?? '' }}
                </td>
            </tr>

            <tr>
                <td class="label">DÉLAI :</td>
                <td class="value">
                    le plus court possible et à nous confirmer au plus tard le _____________
                </td>
            </tr>

            <tr>
                <td class="label">IMPUTATION :</td>
                <td class="value">
                    {{ $bonCommande->engagement->nomenclaturePrincipale->code ?? '' }}
                    –
                    {{ $bonCommande->engagement->nomenclaturePrincipale->libelle ?? '' }}
                </td>
            </tr>

            <tr>
                <td class="label">OBJET :</td>
                <td class="value">
                    {{ $bonCommande->engagement->objet ?? '' }}
                </td>
            </tr>
        </table>
    </div>

    {{-- Tableau des lignes --}}
    <table>
        <thead>
            <tr>
                <th>REF</th>
                <th>DESIGNATION</th>
                <th>QTÉS</th>
                <th>P.U</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bonCommande->lignes as $i => $ligne)
                <tr>
                    <td class="num">{{ $i + 1 }}</td>
                    <td class="designation">{{ $ligne->designation }}</td>
                    <td class="num">{{ $ligne->quantite }}</td>
                    <td class="money">{{ number_format($ligne->prix_unitaire_ht, 0, ',', ' ') }}</td>
                    <td class="money">{{ number_format($ligne->montant_ht, 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

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
                <td class="total-final">NET À PAYER</td>
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
@endsection
