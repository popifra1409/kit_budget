<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Bon de Commande HGY</title>
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
            line-height: 1.2;
        }

        .header-space {
            height: 98mm;
        }

        .content {
            padding: 0 18mm 0 12mm;
        }

        .date-numero {
            text-align: right;
            margin: -38mm 0 12mm 0;
            font-size: 11pt;
        }

        .commande-box {
            display: inline-block;
            border: 1px solid #000;
            padding: 3px 5px;
            font-weight: bold;
            font-size: 11.5pt;
            margin-top: -43px;
            margin-left: 30px;
        }

        .date-line {
            /* margin-top: 10px; */
            margin-bottom: 160px;
            font-size: 10pt;
        }

        .lignes-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 3px 0;
        }

        .lignes-table td {
            padding: 7px 3px;
            vertical-align: top;
            border: none;
            font-size: 9.5pt;
        }

        .lignes-table .ref {
            width: 15%;
            text-align: left;
            font-size: 8.5pt;
        }

        .lignes-table .designation {
            width: 53%;
            padding-left: 6px;
        }

        .lignes-table .qte {
            width: 9%;
            text-align: center;
        }

        .lignes-table .pu {
            width: 11%;
            text-align: right;
            padding-right: 6px;
        }

        .lignes-table .total {
            width: 12%;
            text-align: right;
            padding-right: 6px;
        }

        /* Section totaux SANS bordures */
        .totaux-section {
            margin-top: 8px;
        }

        .totaux-section table {
            width: 100%;
            border-collapse: collapse;
        }

        .totaux-section td {
            padding: 5px 6px;
            border: none;
            /* ✅ SANS bordures */
            font-size: 10pt;
        }

        .totaux-section .label-col {
            width: 57%;
            font-weight: bold;
            text-align: left;
            padding-left: 200px;
        }

        .totaux-section .vide-col {
            width: 9%;
        }

        .totaux-section .montant-col {
            width: 34%;
            text-align: right;
            font-weight: bold;
            padding-right: 10px;
        }

        /* Ligne montant en lettres */
        .totaux-section .ligne-lettres .montant-col {
            font-weight: normal;
            text-align: left;
            padding-left: 6px;
            padding-top: 130px;
            font-size: 9.5pt;
        }

        .ligne-lettres {
            margin-top: 30px;
        }

        /* Ligne TOTAL finale avec soulignement */
        .totaux-section .ligne-finale {
            border-top: 1px solid #000;
            /* Seulement bordure supérieure */
        }

        .totaux-section .ligne-finale td {
            padding: 6px;
            font-size: 10.5pt;
            font-weight: bold;
        }


        .mention-gauche {
            display: table-cell;
            width: 35%;
            font-size: 8pt;
        }

        .signature-droite {
            display: table-cell;
            width: 65%;
            text-align: right;
        }

        .signature-droite .titre {
            font-weight: bold;
            font-size: 10pt;
            text-decoration: underline;
            margin-bottom: 45px;
        }
    </style>
</head>

<body>
    @php
        $bonCommande = $donnees['_raw'];
        $parametres = \App\Models\ParametresStructure::where('actif', true)->first();
        $netAPayer = $bonCommande->net_a_percevoir ?? $bonCommande->montant_ttc - $bonCommande->montant_ir;
    @endphp

    <div class="header-space"></div>
    <div class="content">
        <div class="date-numero">
            <div class="commande-box"> {{ $bonCommande->numero }}</div>
            <div class="date-line">{{ \Carbon\Carbon::parse($bonCommande->date_emission)->format('d/m/Y') }}
            </div>
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

        <table class="lignes-table">
            @foreach ($bonCommande->lignes as $index => $ligne)
                <tr>
                    <td class="ref">
                        {{ $ligne->reference_personnalisee ?? ($ligne->reference ?? ($ligne->referenceMercuriale?->code_reference ?? '-')) }}
                    </td>
                    <td class="designation">{{ $ligne->designation }}</td>
                    <td class="qte">{{ number_format($ligne->quantite, 0, ',', ' ') }}</td>
                    <td class="pu">{{ number_format($ligne->prix_unitaire_ht, 0, ',', ' ') }}</td>
                    <td class="total">{{ number_format($ligne->montant_ht, 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </table>

        {{-- ✅ Section totaux SANS bordures --}}
        <div class="totaux-section">
            <table>
                <tr>
                    <td class="label-col">MONTANT HT</td>
                    <td class="vide-col"></td>
                    <td class="montant-col">{{ number_format($bonCommande->montant_ht, 0, ',', ' ') }}</td>
                </tr>
                <tr>
                    <td class="label-col">MONTANT TVA</td>
                    <td class="vide-col"></td>
                    <td class="montant-col">{{ number_format($bonCommande->montant_tva, 0, ',', ' ') }}</td>
                </tr>
                <tr>
                    <td class="label-col">MONTANT IR</td>
                    <td class="vide-col"></td>
                    <td class="montant-col">{{ number_format($bonCommande->montant_ir, 0, ',', ' ') }}</td>
                </tr>
                <tr>
                    <td class="label-col">NET A PAYER</td>
                    <td class="vide-col"></td>
                    <td class="montant-col">{{ number_format($netAPayer, 0, ',', ' ') }}</td>
                </tr>

                {{-- ✅ Ligne montant en lettres --}}
                <tr class="ligne-lettres">
                    <td colspan="3" class="montant-col">
                        Arrete le present bon de commande a la somme de
                        <strong>{{ \App\Helpers\NombreEnLettres::montantCFA($bonCommande->montant_ttc) }}</strong>
                    </td>
                </tr>

                {{-- ✅ Ligne TOTAL finale avec le montant TTC --}}
                <tr class="ligne-finale">
                    <td class=""></td>
                    <td class=""></td>
                    <td class="montant-col">{{ number_format($bonCommande->montant_ttc, 0, ',', ' ') }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- ✅ Footer supprimé --}}
</body>

</html>
