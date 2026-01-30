@extends('pdf.layouts.master')

@php
    // Récupérer les données du bon
    $bonCommande = $donnees['_raw'];

    // Définir des valeurs par défaut
    $service = $donnees['service'] ?? ($bonCommande->serviceDemandeur->nom ?? 'RESSOURCES HUMAINES');
    $numeroBca = $donnees['numero_bca'] ?? ($bonCommande->numero ?? '.........');
    $dateImpression = $donnees['date_impression'] ?? date('d/m/Y');
    $prestataireNom = $donnees['prestataire_nom'] ?? ($bonCommande->fournisseur->raison_sociale ?? '');
    $prestataireAdresse = $donnees['prestataire_adresse'] ?? ($bonCommande->fournisseur->adresse ?? '...............');
    $prestataireTel = $donnees['prestataire_tel'] ?? ($bonCommande->fournisseur->telephone ?? '......................');
    $prestataireContribuable =
        $donnees['prestataire_contribuable'] ??
        ($bonCommande->fournisseur->nif ?? '........................');
@endphp

@section('title', 'BCA N° ' . $numeroBca)

@section('montant_lettres')
    {{ $donnees['montant_lettres'] ?? \App\Helpers\NombreEnLettres::montantCFA($bonCommande->montant_ttc ?? 0) }}
@endsection

@section('additional_styles')
    <style>
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
            width: 60%;
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
    </style>
@endsection

@section('content')
    {{-- Service et numéro BCA --}}
    <div class="service-info">
        SERVICE {{ strtoupper($service) }}
    </div>

    <div class="bca-numero">
        BCA N°: {{ $numeroBca }}
    </div>

    <div class="date-impression">
        Imprimé le {{ $dateImpression }}
    </div>

    {{-- Section: Pour les objets et matières ci-après --}}
    <div class="text-center font-bold mb-15">
        Pour les objets et matières ci-après:
    </div>

    {{-- Informations prestataire --}}
    <div class="mb-15">
        <table class="simple">
            <tr>
                <td><strong>Nom ou raison du Prestataire</strong></td>
                <td class="font-bold">{{ $prestataireNom }}</td>
            </tr>
            <tr>
                <td>Adresse</td>
                <td>{{ $prestataireAdresse }}</td>
            </tr>
            <tr>
                <td></td>
                <td>Tél: {{ $prestataireTel }}</td>
            </tr>
            <tr>
                <td>N° contribuable</td>
                <td>{{ $prestataireContribuable }}</td>
            </tr>
        </table>
    </div>

    {{-- Tableau des articles --}}
    <table class="articles-table">
        <thead>
            <tr>
                <th style="width: 10%;">Qté</th>
                <th style="width: 50%;">Désignation</th>
                <th style="width: 20%;">PU</th>
                <th style="width: 20%;">Montant</th>
            </tr>
        </thead>
        <tbody>
            @if (isset($bonCommande->lignes) && $bonCommande->lignes->count() > 0)
                @foreach ($bonCommande->lignes as $ligne)
                    <tr>
                        <td class="nombre">{{ $ligne->quantite }}</td>
                        <td>{{ $ligne->designation }}</td>
                        <td class="nombre">{{ number_format($ligne->prix_unitaire_ht, 0, ',', ' ') }}</td>
                        <td class="nombre">{{ number_format($ligne->montant_ht, 0, ',', ' ') }} F cfa</td>
                    </tr>
                @endforeach
            @endif
            <tr>
                <td colspan="3" class="text-right font-bold">TOTAL</td>
                <td class="nombre font-bold">{{ number_format($bonCommande->montant_ht ?? 0, 0, ',', ' ') }} F cfa</td>
            </tr>
        </tbody>
    </table>

    {{-- Section totaux --}}
    <div class="totaux-section">
        <p class="font-bold mb-10">Les parties arrêtent la présente commande à:</p>

        <table class="totaux-table simple">
            <tr>
                <td class="label">Prix total HT</td>
                <td class="valeur font-bold">{{ number_format($bonCommande->montant_ht ?? 0, 0, ',', ' ') }} F cfa</td>
            </tr>
            <tr>
                <td class="label">TVA</td>
                <td class="valeur font-bold">{{ number_format($bonCommande->montant_tva ?? 0, 0, ',', ' ') }} F cfa</td>
            </tr>
            <tr>
                <td class="label">Prix total TTC</td>
                <td class="valeur font-bold">{{ number_format($bonCommande->montant_ttc ?? 0, 0, ',', ' ') }} F cfa</td>
            </tr>
        </table>

        <div class="mt-10">
            <strong>Montant total en lettres:</strong> @yield('montant_lettres')
        </div>

        <div class="mt-10">
            <strong>Délai de livraison:</strong>
            @if ($bonCommande->date_livraison_prevue)
                {{ \Carbon\Carbon::parse($bonCommande->date_livraison_prevue)->format('d/m/Y') }}
            @else
                ...................................
            @endif
        </div>
    </div>

    {{-- Signatures --}}
    <div class="mt-20 clearfix">
        <div class="text-right" style="margin-bottom: 20px; font-size: 8pt;">
            1/1 Signé à Yaoundé Le..............................................
        </div>

        <div class="signature-container">
            <div class="signature-block" style="width: 33%;">
                <div class="font-bold">Le Prestataire</div>
                <div class="mt-10 font-bold">{{ $prestataireNom }}</div>
            </div>

            <div class="signature-block" style="width: 33%;">
                <!-- Vide -->
            </div>

            <div class="signature-block" style="width: 33%;">
                <div class="font-bold">L'ordonnateur</div>
                <div class="mt-10 font-bold">{{ $parametres->fonction_ordonnateur ?? 'LE DIRECTEUR GENERAL' }}</div>
                <div style="margin-top: 40px; border-top: 1px solid #000; padding-top: 5px;">
                    {{ $parametres->nom_ordonnateur ?? '' }}
                </div>
            </div>
        </div>
    </div>
@endsection
