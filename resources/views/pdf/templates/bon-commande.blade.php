@extends('pdf.layouts.master')

@section('styles')
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

        .articles-table {
            margin: 15px 0;
            font-size: 9pt;
        }

        .articles-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            font-size: 9pt;
        }

        .articles-table td {
            text-align: left;
            font-size: 9pt;
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
    </style>
@endsection

@section('content')

    {{-- Service et numéro BCA --}}
    <div class="service-info">
        SERVICE {{ $donnees['service'] ?? 'RESSOURCES HUMAINES' }}
    </div>

    <div class="bca-numero">
        BCA N°: {{ $donnees['numero_bca'] ?? '.........' }}
    </div>

    <div class="date-impression">
        Imprimé le {{ $donnees['date_impression'] ?? date('d/m/Y') }}
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
                <td class="font-bold">{{ $donnees['prestataire_nom'] ?? '' }}</td>
            </tr>
            <tr>
                <td>Adresse</td>
                <td>{{ $donnees['prestataire_adresse'] ?? '...............' }}</td>
            </tr>
            <tr>
                <td></td>
                <td>Tél: {{ $donnees['prestataire_tel'] ?? '......................' }}</td>
            </tr>
            <tr>
                <td>N° contribuable</td>
                <td>{{ $donnees['prestataire_contribuable'] ?? '........................' }}</td>
            </tr>
        </table>
    </div>

    {{-- Tableau des articles --}}
    <table class="articles-table bordered">
        <thead>
            <tr>
                <th style="width: 10%;">Qté</th>
                <th style="width: 50%;">Désignation</th>
                <th style="width: 20%;">PU</th>
                <th style="width: 20%;">Montant</th>
            </tr>
        </thead>
        <tbody>
            @if (isset($donnees['articles']) && is_array($donnees['articles']))
                @foreach ($donnees['articles'] as $article)
                    <tr>
                        <td class="nombre">{{ $article['quantite'] ?? 1 }}</td>
                        <td>{{ $article['designation'] ?? '' }}</td>
                        <td class="nombre">{{ number_format(floatval($article['prix_unitaire'] ?? 0), 0, ',', ' ') }}</td>
                        <td class="nombre">{{ number_format(floatval($article['montant'] ?? 0), 0, ',', ' ') }} F cfa</td>
                    </tr>
                @endforeach
            @else
                {{-- Article unique --}}
                <tr>
                    <td class="nombre">{{ $donnees['quantite'] ?? 1 }}</td>
                    <td>{{ $donnees['designation'] ?? ($donnees['objet'] ?? '') }}</td>
                    <td class="nombre">
                        {{ number_format(floatval($donnees['_raw']['prix_unitaire'] ?? ($donnees['_raw']['montant_total'] ?? 0)), 0, ',', ' ') }}
                    </td>
                    <td class="nombre">{{ $donnees['montant'] ?? '0' }}</td>
                </tr>
            @endif
            <tr>
                <td colspan="3" class="text-right font-bold">TOTAL</td>
                <td class="nombre font-bold">{{ $donnees['montant'] ?? '0' }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Section totaux --}}
    <div class="totaux-section">
        <p class="font-bold mb-10">Les parties arrêtent la présente commande à:</p>

        <table class="totaux-table simple">
            <tr>
                <td class="label">Prix total HT</td>
                <td class="valeur font-bold">{{ $donnees['montant_ht'] ?? ($donnees['montant'] ?? '0') }}</td>
            </tr>
            <tr>
                <td class="label">TVA</td>
                <td class="valeur font-bold">{{ $donnees['montant_tva'] ?? '0 F cfa' }}</td>
            </tr>
            <tr>
                <td class="label">Prix total TTC</td>
                <td class="valeur font-bold">{{ $donnees['montant_ttc'] ?? ($donnees['montant'] ?? '0') }}</td>
            </tr>
        </table>

        <div class="mt-10">
            <strong>Montant total en</strong> {{ $donnees['montant_lettres'] ?? '' }}
        </div>

        <div class="mt-10">
            <strong>Délai de livraison</strong>
        </div>
    </div>

    {{-- Signatures --}}
    <div class="mt-20">
        <div class="text-right" style="margin-bottom: 15px; font-size: 8pt;">
            1/1 Signé à Yaoundé Le..............................................
        </div>

        <table style="width: 100%;">
            <tr>
                <td style="width: 33%; text-align: center;">
                    <div class="font-bold">Le Prestataire</div>
                    <div class="mt-10 font-bold">{{ $donnees['prestataire_nom'] ?? 'AGENT COMPTABLE' }}</div>
                </td>

                <td style="width: 33%; text-align: center;">
                    <!-- Vide -->
                </td>

                <td style="width: 33%; text-align: center;">
                    <div class="font-bold">L'ordonnateur</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Signatures --}}
    <div class="mt-20 clearfix" style="clear: both;">
        <div class="text-right" style="margin-bottom: 20px;">
            1/1 Signé à Yaoundé Le..............................................
        </div>

        <div class="signature-container clearfix">
            <div class="signature-block" style="width: 33%;">
                <div class="font-bold">Le Prestataire</div>
                <div class="mt-10 font-bold">{{ $donnees['prestataire_nom'] ?? 'AGENT COMPTABLE' }}</div>
            </div>

            <div class="signature-block" style="width: 33%;">
                <!-- Vide -->
            </div>

            <div class="signature-block" style="width: 33%;">
                <div class="font-bold">L'ordonnateur</div>
            </div>
        </div>
    </div>

@endsection
