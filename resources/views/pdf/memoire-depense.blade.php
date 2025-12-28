<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mémoire de Dépense - {{ $memoire->numero }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #000;
        }

        .page {
            padding: 15mm;
        }

        /* En-tête */
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        .header-top {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }

        .header-left,
        .header-center,
        .header-right {
            display: table-cell;
            vertical-align: top;
            width: 33.33%;
        }

        .header-left {
            text-align: left;
            font-size: 9pt;
        }

        .header-center {
            text-align: center;
        }

        .header-right {
            text-align: right;
            font-size: 9pt;
        }

        .logo {
            max-width: 80px;
            max-height: 80px;
        }

        .header-center h2 {
            font-size: 12pt;
            font-weight: bold;
            margin: 3px 0;
        }

        .header-center h3 {
            font-size: 10pt;
            font-weight: bold;
            margin: 2px 0;
        }

        .header-center p {
            font-size: 9pt;
            margin: 2px 0;
        }

        /* Titre du document */
        .document-title {
            text-align: center;
            margin: 20px 0 10px 0;
        }

        .document-title h1 {
            font-size: 14pt;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 8px;
        }

        .document-info {
            text-align: center;
            font-size: 10pt;
            margin-bottom: 15px;
        }

        /* Objet */
        .objet {
            margin: 15px 0;
            text-align: justify;
        }

        .objet strong {
            font-weight: bold;
        }

        /* Tableau */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
        }

        table th {
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 6px 4px;
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
        }

        table td {
            border: 1px solid #000;
            padding: 5px 4px;
            text-align: left;
        }

        table td.number {
            text-align: right;
        }

        table td.center {
            text-align: center;
        }

        table tfoot td {
            font-weight: bold;
            background-color: #f0f0f0;
        }

        /* Montant en lettres */
        .montant-lettres {
            margin: 15px 0;
            padding: 10px;
            border: 1px solid #000;
            text-align: center;
        }

        .montant-lettres p {
            font-weight: bold;
            font-size: 10pt;
        }

        /* Signature */
        .signature {
            margin-top: 30px;
            text-align: right;
        }

        .signature p {
            margin: 3px 0;
        }

        .signature .lieu-date {
            font-style: italic;
            margin-bottom: 10px;
        }

        .signature .fonction {
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 60px;
        }

        /* Pied de page */
        .footer {
            position: fixed;
            bottom: 10mm;
            left: 15mm;
            right: 15mm;
            text-align: center;
            font-size: 8pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 5px;
        }
    </style>
</head>

<body>
    <div class="page">
        <!-- En-tête -->
        <div class="header">
            <div class="header-top">
                <div class="header-left">
                    <strong>{{ strtoupper($structure->pays_gauche) }}</strong><br>
                    <em>{{ $structure->devise_gauche }}</em>
                </div>

                <div class="header-center">
                    @if ($structure->logo_url)
                        <img src="{{ public_path('storage/' . $structure->logo) }}" alt="Logo" class="logo">
                    @endif
                </div>

                <div class="header-right">
                    <strong>{{ strtoupper($structure->pays_droite) }}</strong><br>
                    <em>{{ $structure->devise_droite }}</em>
                </div>
            </div>

            <div class="header-center">
                @if ($structure->ministere_tutelle)
                    <h3>{{ strtoupper($structure->ministere_tutelle) }}</h3>
                @endif
                <h2>{{ strtoupper($structure->nom_structure) }}</h2>
                @if ($structure->sigle)
                    <h3>{{ strtoupper($structure->sigle) }}</h3>
                @endif
                @if ($structure->direction_generale)
                    <p>{{ strtoupper($structure->direction_generale) }}</p>
                @endif
                @if ($structure->sous_direction)
                    <p>{{ strtoupper($structure->sous_direction) }}</p>
                @endif
            </div>
        </div>

        <!-- Références décision -->
        @if ($memoire->numero_decision)
            <div class="document-info">
                <p><strong>DECISION N° {{ $memoire->numero_decision }}</strong>
                    @if ($memoire->date_decision)
                        DU {{ $memoire->date_decision->format('d/m/Y') }}
                    @endif
                </p>
            </div>
        @endif

        <!-- Titre -->
        <div class="document-title">
            <h1>MÉMOIRE DE DEPENSE N° {{ $memoire->numero }}</h1>
            <p>du {{ $memoire->date_memoire->format('d/m/Y') }}</p>
        </div>

        <!-- Référence CE -->
        @if ($memoire->numero_ce)
            <div class="document-info">
                <p><strong>CERTIFICAT D'ENGAGEMENT N° {{ $memoire->numero_ce }}</strong>
                    @if ($memoire->date_ce)
                        DU {{ $memoire->date_ce->format('d/m/Y') }}
                    @endif
                </p>
            </div>
        @endif

        <!-- Objet -->
        <div class="objet">
            <p><strong>RELATIF À :</strong> {{ $memoire->objet }}</p>
        </div>

        <!-- Tableau des dépenses -->
        <table>
            <thead>
                <tr>
                    <th style="width: 35%;">NATURE DE LA DÉPENSE</th>
                    <th style="width: 5%;">QTÉ</th>
                    <th style="width: 12%;">P.U</th>
                    <th style="width: 10%;">MHT</th>
                    <th style="width: 8%;">TVA</th>
                    <th style="width: 8%;">IR</th>
                    <th style="width: 11%;">NAP</th>
                    <th style="width: 11%;">TTC</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($memoire->lignes as $ligne)
                    <tr>
                        <td>{{ $ligne->nature_depense }}</td>
                        <td class="center">{{ number_format($ligne->quantite, 0, ',', ' ') }}</td>
                        <td class="number">{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }}</td>
                        <td class="number">{{ number_format($ligne->montant_ht, 0, ',', ' ') }}</td>
                        <td class="number">{{ number_format($ligne->montant_tva, 0, ',', ' ') }}</td>
                        <td class="number">{{ number_format($ligne->montant_ir, 0, ',', ' ') }}</td>
                        <td class="number">{{ number_format($ligne->net_a_payer, 0, ',', ' ') }}</td>
                        <td class="number">{{ number_format($ligne->montant_ttc, 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: center;"><strong>TOTAL</strong></td>
                    <td class="number">{{ number_format($memoire->montant_ht, 0, ',', ' ') }}</td>
                    <td class="number">{{ number_format($memoire->montant_tva, 0, ',', ' ') }}</td>
                    <td class="number">{{ number_format($memoire->montant_ir, 0, ',', ' ') }}</td>
                    <td class="number">{{ number_format($memoire->montant_net, 0, ',', ' ') }}</td>
                    <td class="number">{{ number_format($memoire->montant_ttc, 0, ',', ' ') }}</td>
                </tr>
            </tfoot>
        </table>

        <!-- Montant en lettres -->
        <div class="montant-lettres">
            <p>Arrêté à la somme TTC de :</p>
            <p>{{ $memoire->montant_lettres }}</p>
        </div>

        <!-- Signature -->
        <div class="signature">
            <p class="lieu-date">
                {{ $memoire->lieu_signature ?? 'Yaoundé' }},
                le
                {{ $memoire->date_signature ? $memoire->date_signature->format('d/m/Y') : $memoire->date_memoire->format('d/m/Y') }}
            </p>
            <p class="fonction">
                {{ $memoire->signataire_fonction ?? ($structure->fonction_ordonnateur ?? 'LE DIRECTEUR GENERAL') }}
            </p>
            @if ($memoire->signataire_nom)
                <p style="margin-top: 60px;">{{ $memoire->signataire_nom }}</p>
            @elseif($structure->nom_ordonnateur)
                <p style="margin-top: 60px;">{{ $structure->nom_ordonnateur }}</p>
            @endif
        </div>
    </div>

    <!-- Pied de page -->
    <div class="footer">
        @if ($structure->adresse || $structure->telephone || $structure->email)
            {{ $structure->adresse }}
            @if ($structure->telephone)
                | Tél: {{ $structure->telephone }}
            @endif
            @if ($structure->email)
                | Email: {{ $structure->email }}
            @endif
        @endif
    </div>
</body>

</html>
