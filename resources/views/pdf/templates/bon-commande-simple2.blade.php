<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Bon de Commande {{ $donnees['_raw']->numero }}</title>
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            margin: 0;
            padding: 0;
        }

        /* Espace réservé pour l'en-tête préimprimé */
        .header-space {
            height: 50mm;
            /* Ajuster selon votre papier préimprimé */
        }

        .content {
            padding: 0 15mm;
        }

        /* Date et numéro */
        .date-numero {
            text-align: right;
            margin: 10px 70px 120px;
            font-size: 12pt;
        }

        .commande-box {
            display: inline-block;
            border: 2px solid #000;
            padding: 5px 10px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        /* Tableau d'informations */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .info-table td {
            padding: 4px 8px;
            vertical-align: top;
        }

        .info-table .label {
            width: 35%;
            font-weight: bold;
        }

        .info-table .value {
            width: 65%;
        }

        /* Tableau des lignes - SANS BORDURES */
        .lignes-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .lignes-table td {
            padding: 6px;
            vertical-align: top;
            border: none;
            /* Suppression des bordures */
        }

        .lignes-table .ref {
            width: 12%;
            text-align: center;
        }

        .lignes-table .designation {
            width: 52%;
            /* Élargi de 40% à 52% */
        }

        .lignes-table .qte {
            width: 8%;
            text-align: center;
        }

        .lignes-table .pu {
            width: 14%;
            /* Réduit de 17.5% à 14% */
            text-align: right;
        }

        .lignes-table .total {
            width: 14%;
            /* Réduit de 17.5% à 14% */
            text-align: right;
        }

        /* Totaux */
        .totaux {
            width: 50%;
            margin-left: auto;
            margin-top: 15px;
        }

        .totaux table {
            width: 100%;
            border-collapse: collapse;
        }

        .totaux td {
            padding: 5px;
            border: 1px solid #000;
        }

        .totaux .label-tot {
            width: 60%;
            font-weight: bold;
        }

        .totaux .montant-tot {
            width: 40%;
            text-align: right;
        }

        .totaux .total-final {
            font-weight: bold;
            font-size: 11pt;
        }

        /* Montant en lettres */
        .montant-lettres {
            margin: 15px 0;
            padding: 10px;
            border: 1px solid #000;
            text-align: center;
            font-size: 10pt;
        }

        /* Bas de page */
        .bas-page {
            display: table;
            width: 100%;
            margin-top: 20px;
        }

        .mention-gauche {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            font-size: 9pt;
        }

        .signature {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: bottom;
        }

        .signature-box {
            margin-top: 40px;
        }

        .signature .fonction {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 40px;
        }

        .signature .nom {
            font-weight: bold;
        }

        /* Espace pour le footer préimprimé */
        .footer-space {
            height: 20mm;
            /* Ajuster selon votre papier */
        }
    </style>
</head>

<body>
    @php
        $bonCommande = $donnees['_raw'];
        $parametres = \App\Models\ParametresStructure::where('actif', true)->first();
    @endphp

    {{-- Espace réservé pour l'en-tête préimprimé --}}
    <div class="header-space"></div>

    <div class="content">
        {{-- Date et numéro de commande --}}
        <div class="date-numero">
            <div class="commande-box">
                N° {{ $bonCommande->numero }}
            </div>
            <div>
                {{ \Carbon\Carbon::parse($bonCommande->date_emission)->format('d/m/Y') }}
            </div>
        </div>

        {{-- Tableau des lignes - SANS BORDURES NI EN-TÊTE --}}
        <table class="lignes-table">
            <tbody>
                @foreach ($bonCommande->lignes as $ligne)
                    <tr>
                        <td class="ref">{{ $ligne->reference ?? '-' }}</td>
                        <td class="designation">{{ $ligne->designation }}</td>
                        <td class="qte">{{ number_format($ligne->quantite, 0, ',', ' ') }}</td>
                        <td class="pu">{{ number_format($ligne->prix_unitaire_ht, 0, ',', ' ') }}</td>
                        <td class="total">{{ number_format($ligne->montant_ht, 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totaux --}}
        <div class="totaux">
            <table>
                <tr>
                    <td class="label-tot">MONTANT HT</td>
                    <td class="montant-tot">{{ number_format($bonCommande->montant_ht, 0, ',', ' ') }}</td>
                </tr>
                <tr>
                    <td class="label-tot">MONTANT TVA</td>
                    <td class="montant-tot">{{ number_format($bonCommande->montant_tva, 0, ',', ' ') }}</td>
                </tr>
                <tr>
                    <td class="label-tot">MONTANT IR</td>
                    <td class="montant-tot">{{ number_format($bonCommande->montant_ir, 0, ',', ' ') }}</td>
                </tr>
                <tr>
                    <td class="label-tot total-final">NET A PAYER</td>
                    <td class="montant-tot total-final">
                        {{ number_format($bonCommande->net_a_percevoir, 0, ',', ' ') }}
                    </td>
                </tr>
                <tr>
                    <td class="label-tot total-final">MONTANT TOTAL TTC</td>
                    <td class="montant-tot total-final">
                        {{ number_format($bonCommande->montant_ttc, 0, ',', ' ') }}
                    </td>
                </tr>
            </table>
        </div>

        {{-- Montant en lettres --}}
        <div class="montant-lettres">
            Arrete le present bon de commande a la somme de
            <strong>{{ \App\Helpers\NombreEnLettres::montantCFA($bonCommande->montant_ttc) }}</strong>
        </div>
    </div>
</body>

</html>
