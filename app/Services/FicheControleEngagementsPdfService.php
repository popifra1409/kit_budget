<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche de Contrôle des Engagements</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 9pt;
            line-height: 1.3;
            color: #000;
        }

        .page {
            width: 100%;
            padding: 15mm;
        }

        /* En-tête */
        .header {
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .logo {
            width: 60px;
            height: 60px;
        }

        .title {
            text-align: center;
            flex: 1;
        }

        .title h1 {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .title .subtitle {
            font-size: 10pt;
            font-style: italic;
        }

        .header-info {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
            font-size: 8pt;
        }

        .info-box {
            border: 1px solid #000;
            padding: 5px;
        }

        .info-box label {
            font-weight: bold;
            display: block;
            margin-bottom: 2px;
        }

        .info-box .value {
            font-size: 9pt;
        }

        /* Hiérarchie budgétaire */
        .hierarchie {
            margin: 15px 0;
            background-color: #f0f0f0;
            padding: 10px;
            border: 1px solid #000;
        }

        .hierarchie h2 {
            font-size: 11pt;
            margin-bottom: 8px;
            text-decoration: underline;
        }

        .hierarchie table {
            width: 100%;
            font-size: 8pt;
        }

        .hierarchie td {
            padding: 3px 5px;
        }

        .hierarchie td:first-child {
            font-weight: bold;
            width: 100px;
        }

        /* Résumé budgétaire */
        .resume {
            margin: 15px 0;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .resume-box {
            border: 2px solid #000;
            padding: 8px;
            text-align: center;
        }

        .resume-box .label {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .resume-box .montant {
            font-size: 12pt;
            font-weight: bold;
        }

        .resume-box.dotation .montant {
            color: #0066cc;
        }

        .resume-box.engage .montant {
            color: #ff6600;
        }

        .resume-box.disponible .montant {
            color: #009900;
        }

        .resume-box.disponible.negatif .montant {
            color: #cc0000;
        }

        .resume-box.taux .montant {
            font-size: 14pt;
        }

        /* Tableau des engagements */
        table.engagements {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 8pt;
        }

        table.engagements th {
            background-color: #333;
            color: #fff;
            padding: 6px 4px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #000;
            font-size: 7pt;
        }

        table.engagements td {
            padding: 5px 4px;
            border: 1px solid #000;
            vertical-align: top;
        }

        table.engagements tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table.engagements tbody tr:nth-child(odd) {
            background-color: #fff;
        }

        table.engagements .montant {
            text-align: right;
            font-weight: bold;
        }

        table.engagements .negatif {
            color: #cc0000;
        }

        table.engagements .positif {
            color: #009900;
        }

        /* Totaux */
        table.engagements tfoot {
            font-weight: bold;
            background-color: #e0e0e0;
        }

        table.engagements tfoot td {
            padding: 8px 4px;
            border: 2px solid #000;
        }

        /* Pied de page */
        .footer {
            position: fixed;
            bottom: 10mm;
            left: 15mm;
            right: 15mm;
            text-align: center;
            font-size: 7pt;
            border-top: 1px solid #ccc;
            padding-top: 5px;
        }

        /* Signatures */
        .signatures {
            margin-top: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            font-size: 8pt;
        }

        .signature-box {
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 5px;
        }

        .signature-box .titre {
            font-weight: bold;
            margin-bottom: 40px;
        }

        .signature-box .nom {
            margin-top: 40px;
            font-style: italic;
        }

        /* Légende */
        .legende {
            margin: 10px 0;
            font-size: 7pt;
            font-style: italic;
            color: #666;
        }

        /* Page break */
        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>
    <div class="page">
        {{-- En-tête --}}
        <div class="header">
            <div class="header-top">
                <div class="logo">
                    {{-- Logo à ajouter --}}
                    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="50" cy="50" r="45" fill="#0066cc" />
                        <text x="50" y="60" font-size="40" font-weight="bold" fill="#fff" text-anchor="middle">FC</text>
                    </svg>
                </div>
                <div class="title">
                    <h1>Fiche de Contrôle des Engagements des Crédits</h1>
                    <div class="subtitle">Suivi de la Consommation Budgétaire</div>
                </div>
                <div style="width: 60px;"></div>
            </div>

            <div class="header-info">
                <div class="info-box">
                    <label>EXERCICE BUDGÉTAIRE</label>
                    <div class="value">{{ $exercice->annee }} - {{ $exercice->libelle }}</div>
                </div>
                <div class="info-box">
                    <label>BUDGET</label>
                    <div class="value">{{ $budget->code }} - {{ $budget->libelle }}</div>
                </div>
                <div class="info-box">
                    <label>DATE DE GÉNÉRATION</label>
                    <div class="value">{{ $date_generation }}</div>
                </div>
            </div>
        </div>

        {{-- Hiérarchie budgétaire --}}
        <div class="hierarchie">
            <h2>NOMENCLATURE BUDGÉTAIRE</h2>
            <table>
                @if($hierarchie['programme'])
                <tr>
                    <td>PROGRAMME :</td>
                    <td>{{ $hierarchie['programme'] }}</td>
                </tr>
                @endif
                @if($hierarchie['action'])
                <tr>
                    <td>ACTION :</td>
                    <td>{{ $hierarchie['action'] }}</td>
                </tr>
                @endif
                @if($hierarchie['activite'])
                <tr>
                    <td>ACTIVITÉ :</td>
                    <td>{{ $hierarchie['activite'] }}</td>
                </tr>
                @endif
                @if($hierarchie['tache'])
                <tr>
                    <td>TÂCHE :</td>
                    <td>{{ $hierarchie['tache'] }}</td>
                </tr>
                @endif
                @if($hierarchie['article'])
                <tr>
                    <td>ARTICLE :</td>
                    <td>{{ $hierarchie['article'] }}</td>
                </tr>
                @endif
                @if($hierarchie['paragraphe'])
                <tr>
                    <td>PARAGRAPHE :</td>
                    <td>{{ $hierarchie['paragraphe'] }}</td>
                </tr>
                @endif
            </table>
        </div>

        {{-- Résumé budgétaire --}}
        <div class="resume">
            <div class="resume-box dotation">
                <div class="label">Dotation Initiale</div>
                <div class="montant">{{ number_format($dotation_initiale, 0, ',', ' ') }}</div>
            </div>
            <div class="resume-box engage">
                <div class="label">Total Engagé</div>
                <div class="montant">{{ number_format($total_engage, 0, ',', ' ') }}</div>
            </div>
            <div class="resume-box disponible {{ $disponible < 0 ? 'negatif' : '' }}">
                <div class="label">Crédits Disponibles</div>
                <div class="montant">{{ number_format($disponible, 0, ',', ' ') }}</div>
            </div>
            <div class="resume-box taux">
                <div class="label">Taux de Consommation</div>
                <div class="montant">{{ number_format($taux_consommation, 1) }}%</div>
            </div>
        </div>

        {{-- Tableau des engagements --}}
        <table class="engagements">
            <thead>
                <tr>
                    <th style="width: 5%;">N°</th>
                    <th style="width: 18%;">BÉNÉFICIAIRE</th>
                    <th style="width: 25%;">OBJET DE L'ENGAGEMENT</th>
                    <th style="width: 10%;">RÉFÉRENCE</th>
                    <th style="width: 8%;">DATE</th>
                    <th style="width: 12%;">ENGAGEMENT</th>
                    <th style="width: 12%;">DISPONIBLE<br>APRÈS</th>
                    <th style="width: 10%;">OBSERVATIONS</th>
                </tr>
            </thead>
            <tbody>
                @forelse($engagements as $index => $engagement)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $engagement['beneficiaire'] }}</td>
                    <td>{{ $engagement['objet'] }}</td>
                    <td>{{ $engagement['reference'] }}</td>
                    <td style="text-align: center;">{{ $engagement['date_engagement'] }}</td>
                    <td class="montant">{{ number_format($engagement['montant_engage'], 0, ',', ' ') }}</td>
                    <td class="montant {{ $engagement['disponible_apres'] < 0 ? 'negatif' : 'positif' }}">
                        {{ number_format($engagement['disponible_apres'], 0, ',', ' ') }}
                    </td>
                    <td style="font-size: 7pt;">{{ $engagement['observations'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px; font-style: italic; color: #999;">
                        Aucun engagement enregistré sur cette ligne budgétaire
                    </td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align: right;">TOTAL GÉNÉRAL :</td>
                    <td class="montant">{{ number_format($total_engage, 0, ',', ' ') }}</td>
                    <td class="montant {{ $disponible < 0 ? 'negatif' : 'positif' }}">
                        {{ number_format($disponible, 0, ',', ' ') }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="legende">
            * Les montants sont exprimés en FCFA (Francs CFA)
            <br>* Les crédits disponibles sont calculés de manière progressive après chaque engagement
            <br>* Les montants en rouge indiquent un dépassement de crédits
        </div>

        {{-- Signatures --}}
        <div class="signatures">
            <div class="signature-box">
                <div class="titre">LE CONTRÔLEUR FINANCIER</div>
                <div class="nom">Nom et Signature</div>
            </div>
            <div class="signature-box">
                <div class="titre">LE CHEF DE SERVICE</div>
                <div class="nom">Nom et Signature</div>
            </div>
            <div class="signature-box">
                <div class="titre">LE DIRECTEUR</div>
                <div class="nom">Nom et Signature</div>
            </div>
        </div>

        {{-- Pied de page --}}
        <div class="footer">
            Document généré le {{ $date_generation }} par {{ $generePar }}
            | Page 1/1
            | Fiche de Contrôle - Nomenclature {{ $nomenclature->code }}
        </div>
    </div>
</body>

</html>