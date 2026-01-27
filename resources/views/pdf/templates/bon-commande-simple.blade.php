<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">

    @php
        $bonCommande = $donnees['_raw'];
        $parametres = \App\Models\ParametresStructure::where('actif', true)->first();
    @endphp

    <title>Bon de Commande {{ $bonCommande->numero }}</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 18mm 15mm;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9.5pt;
            color: #000;
        }

        /* ================= HEADER ================= */
        .header-table {
            width: 100%;
            border-bottom: 2px solid #000;
            margin-bottom: 12px;
        }

        .header-table td {
            vertical-align: top;
        }

        .header-left {
            width: 50%;
        }

        .header-right {
            width: 50%;
            text-align: right;
        }

        .logo {
            max-width: 90px;
            margin-bottom: 4px;
        }

        .structure {
            font-weight: bold;
            font-size: 10pt;
            line-height: 1.2;
        }

        .adresse {
            font-size: 8pt;
        }

        .republique {
            font-weight: bold;
            font-size: 8.5pt;
            line-height: 1.2;
        }

        .commande-box {
            border: 2px solid #000;
            padding: 5px;
            font-weight: bold;
            text-align: center;
            margin-top: 6px;
        }

        /* ================= INFOS ================= */
        .info {
            margin: 10px 0;
            font-size: 8.8pt;
        }

        .info span {
            display: inline-block;
            min-width: 140px;
            font-weight: bold;
        }

        /* ================= TABLE ================= */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 8.5pt;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 4px;
        }

        th {
            background: #e6e6e6;
            text-align: center;
            font-weight: bold;
        }

        td.num {
            text-align: center;
            white-space: nowrap;
        }

        td.money {
            text-align: right;
            white-space: nowrap;
        }

        td.designation {
            word-break: break-word;
        }

        /* ================= TOTAUX ================= */
        .totaux {
            width: 45%;
            margin-left: auto;
            margin-top: 10px;
            font-size: 8.8pt;
        }

        .totaux table {
            width: 100%;
        }

        .totaux td {
            border: none;
            padding: 3px;
        }

        .total-final {
            border: 2px solid #000;
            font-weight: bold;
            background: #e6e6e6;
        }

        /* ================= SIGNATURE ================= */
        .montant-lettres {
            margin-top: 30px;
            text-align: center;
            font-style: italic;
            font-size: 8.5pt;
        }

        .signature {
            margin-top: 40px;
            text-align: right;
        }

        .signature-box {
            display: inline-block;
            width: 200px;
            text-align: center;
        }

        .fonction {
            font-weight: bold;
            margin-bottom: 45px;
        }

        .nom {
            border-top: 1px solid #000;
            padding-top: 4px;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <!-- ================= HEADER ================= -->
    <table class="header-table">
        <tr>
            <td class="header-left">
                @if ($parametres && $parametres->logo)
                    <img src="{{ public_path('storage/' . $parametres->logo) }}" class="logo">
                @endif
                <div class="structure">
                    {{ $parametres->sigle ?? 'CHY' }} DE {{ strtoupper($parametres->ville ?? 'YAOUNDÉ') }}
                </div>
                <div class="adresse">
                    {{ $parametres->adresse ?? '' }}<br>
                    Tél : {{ $parametres->telephone ?? '' }}
                </div>
            </td>

            <td class="header-right">
                <div class="republique">
                    RÉPUBLIQUE DU CAMEROUN<br>
                    <em>Paix – Travail – Patrie</em>
                </div>
                <div class="republique" style="margin-top:4px">
                    REPUBLIC OF CAMEROON<br>
                    <em>Peace – Work – Fatherland</em>
                </div>

                <div class="commande-box">
                    COMMANDE N° {{ $bonCommande->numero }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Date et lieu --}}
    <div style="text-align: right; margin: 15px 0; font-size: 10pt;">
        <strong>Yaoundé, le</strong> {{ \Carbon\Carbon::parse($bonCommande->date_emission)->format('d/m/Y') }}
    </div>

    <!-- ================= INFOS ================= -->
    <div class="info">
        <div class="info-row">
            <div><span>Fournisseur :</span> {{ $bonCommande->fournisseur->raison_sociale ?? '' }}</div>
        </div>
        <div class="info-row">
            <span class="info-label">Livraison – Réception de 7h30 à 12h <br>du Lundi au Mercredi - Sauf urgence</span>
            {{ $bonCommande->serviceDemandeur->nom ?? '' }}
        </div>
        <div class="info-row">
            <span class="info-label">DELAI</span>: le plus court possible <br>et à nous confirmer au plus tard le
            _____________
            <span class="info-label">IMPUTATION</span>:
            {{ $bonCommande->engagement->nomenclaturePrincipale->code ?? '' }} -
            {{ $bonCommande->engagement->objet ?? '' }}<br> <span class="info-label">OBJET</span>:
            {{ $bonCommande->engagement->nomenclaturePrincipale->libelle ?? '' }}

        </div>

        <!-- ================= TABLE ================= -->
        <table>
            <thead>
                <tr>
                    <th>REF</th>
                    <th>DESIGNATION</th>
                    <th>QTES</th>
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
                        <td class="money">{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }}</td>
                        <td class="money">{{ number_format($ligne->total, 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- ================= TOTAUX ================= -->
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
                    <td>TVA</td>
                    <td class="money">{{ number_format($bonCommande->montant_tva, 0, ',', ' ') }}</td>
                </tr>

                <tr>
                    <td class="total-final">TOTAL TTC</td>
                    <td class="money total-final">
                        {{ number_format($bonCommande->montant_ttc, 0, ',', ' ') }}
                    </td>
                </tr>
            </table>
        </div>

        <!-- ================= SIGNATURE ================= -->
        <div class="montant-lettres">
            Arrêté le présent bon de commande à la somme de :
            <strong>{{ $bonCommande->montant_lettres }}</strong>
        </div>

        <div class="signature">
            <div class="signature-box">
                <div class="fonction">Le Directeur</div>
                <div class="nom">{{ $parametres->directeur ?? '' }}</div>
            </div>
        </div>

</body>

</html>
