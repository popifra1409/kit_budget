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

        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 7pt;
            line-height: 1.2;
            color: #000;
        }

        .page {
            width: 100%;
            padding: 5mm;
        }

        /* En-tête - COMPACT */
        .header {
            margin-bottom: 8px;
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .logo {
            width: 40px;
            height: 40px;
        }

        .title {
            text-align: center;
            flex: 1;
        }

        .title h1 {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .title .subtitle {
            font-size: 8pt;
            font-style: italic;
        }

        .header-info {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 6px;
            margin-top: 6px;
            font-size: 7pt;
        }

        .info-box {
            border: 1px solid #000;
            padding: 3px;
        }

        .info-box label {
            font-weight: bold;
            display: block;
            margin-bottom: 1px;
            font-size: 6pt;
        }

        .info-box .value {
            font-size: 7pt;
        }

        /* Hiérarchie budgétaire - COMPACT */
        .hierarchie {
            margin: 6px 0;
            background-color: #f0f0f0;
            padding: 5px;
            border: 1px solid #000;
        }

        .hierarchie h2 {
            font-size: 9pt;
            margin-bottom: 4px;
            text-decoration: underline;
        }

        .hierarchie table {
            width: 100%;
            font-size: 6pt;
        }

        .hierarchie td {
            padding: 2px 3px;
        }

        .hierarchie td:first-child {
            font-weight: bold;
            width: 80px;
        }

        /* Résumé budgétaire - COMPACT */
        .resume {
            margin: 6px 0;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
        }

        .resume-box {
            border: 2px solid #000;
            padding: 4px;
            text-align: center;
        }

        .resume-box .label {
            font-size: 6pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .resume-box .montant {
            font-size: 9pt;
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
            font-size: 11pt;
        }

        /* Tableau des engagements - OPTIMISÉ PAYSAGE */
        table.engagements {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0;
            font-size: 6pt;
        }

        table.engagements th {
            background-color: #333;
            color: #fff;
            padding: 3px 2px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #000;
            font-size: 6pt;
            line-height: 1.1;
        }

        table.engagements td {
            padding: 2px 2px;
            border: 1px solid #000;
            vertical-align: top;
            font-size: 6pt;
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
            padding: 4px 2px;
            border: 2px solid #000;
            font-size: 7pt;
        }

        /* Pied de page - COMPACT */
        .footer {
            position: fixed;
            bottom: 5mm;
            left: 10mm;
            right: 10mm;
            text-align: center;
            font-size: 6pt;
            border-top: 1px solid #ccc;
            padding-top: 3px;
        }

        /* Signatures - COMPACT */
        .signatures {
            margin-top: 10px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            font-size: 6pt;
        }

        .signature-box {
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 3px;
        }

        .signature-box .titre {
            font-weight: bold;
            margin-bottom: 15px;
        }

        .signature-box .nom {
            margin-top: 15px;
            font-style: italic;
        }

        /* Légende - COMPACT */
        .legende {
            margin: 4px 0;
            font-size: 5pt;
            font-style: italic;
            color: #666;
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
                        <text x="50" y="60" font-size="40" font-weight="bold" fill="#fff"
                            text-anchor="middle">FC</text>
                    </svg>
                </div>
                <div class="title">
                    <h1>Fiche de Contrôle des Engagements des Crédits</h1>
                    <div class="subtitle">Suivi de la Consommation Budgétaire</div>
                </div>
                <div style="width: 60px;"></div>
            </div>
            {{-- ✅ Gestionnaire de Crédits --}}
            <div style="margin-top: 15px; text-align: center; font-size: 8pt; font-weight: bold;">
                GESTIONNAIRE DE CRÉDITS : {{ strtoupper($gestionnaireCredits ?? 'Non défini') }}
            </div>
            <div class="header-info">
                <div class="info-box">
                    <label>EXERCICE BUDGÉTAIRE</label>
                    {{-- ✅ CORRIGÉ : Utiliser ligneBudgetaire->budget->exercice --}}
                    <div class="value">
                        @if ($ligneBudgetaire->budget)
                            {{ $ligneBudgetaire->budget->code ?? 'N/A' }}
                            @if ($ligneBudgetaire->budget->libelle)
                                - {{ $ligneBudgetaire->budget->libelle }}
                            @endif
                        @else
                            N/A
                        @endif
                    </div>
                </div>
                <div class="info-box">
                    <label>BUDGET</label>
                    {{-- ✅ CORRIGÉ : Utiliser ligneBudgetaire->budget --}}
                    <div class="value">
                        {{ $ligneBudgetaire->budget?->code ?? 'N/A' }}
                        @if ($ligneBudgetaire->budget?->libelle)
                            - {{ $ligneBudgetaire->budget->libelle }}
                        @endif
                    </div>
                </div>
                <div class="info-box">
                    <label>DATE DE GÉNÉRATION</label>
                    <div class="value">{{ $date_generation ?? now()->format('d/m/Y à H:i') }}</div>
                </div>
            </div>
        </div>

        {{-- Hiérarchie budgétaire --}}
        @if (isset($hierarchie) && is_array($hierarchie) && count($hierarchie) > 0)

            <div class="hierarchie">
                <h2>IMPUTATION</h2>

                <table style="width:100%; border-collapse: collapse;">
                    <tr>

                        {{-- ================= COLONNE GAUCHE ================= --}}
                        <td style="width:40%; vertical-align: top; padding-right:10px;">

                            <h3 style="margin:0;">PARAGRAPHE (Compte)</h3>

                            <table style="width:100%; border-collapse: collapse;">
                                <tr>
                                    <td><strong>CODE :</strong></td>
                                    <td>{{ $nomenclature?->code ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>LIBELLÉ :</strong></td>
                                    <td>{{ $nomenclature?->libelle ?? 'N/A' }}</td>
                                </tr>
                            </table>

                        </td>

                        {{-- ================= COLONNE DROITE ================= --}}
                        <td style="width:60%; vertical-align: top;">

                            <h3 style="margin:0;"><U>CADRE LOGIQUE</U></h3>

                            <table style="width:100%; border-collapse: collapse;">

                                @if (!empty($hierarchie['programme']))
                                    <tr>
                                        <td><strong>PROGRAMME :</strong></td>
                                        <td>{{ $hierarchie['programme'] }}</td>
                                    </tr>
                                @endif

                                @if (!empty($hierarchie['action']))
                                    <tr>
                                        <td><strong>ACTION :</strong></td>
                                        <td>{{ $hierarchie['action'] }}</td>
                                    </tr>
                                @endif

                                @if (!empty($hierarchie['activite']))
                                    <tr>
                                        <td><strong>ACTIVITÉ :</strong></td>
                                        <td>{{ $hierarchie['activite'] }}</td>
                                    </tr>
                                @endif

                                @if (!empty($hierarchie['tache']))
                                    <tr>
                                        <td><strong>TÂCHE :</strong></td>
                                        <td>{{ $hierarchie['tache'] }}</td>
                                    </tr>
                                @endif

                                @if (!empty($hierarchie['article']))
                                    <tr>
                                        <td><strong>ARTICLE :</strong></td>
                                        <td>{{ $hierarchie['article'] }}</td>
                                    </tr>
                                @endif

                                @if (!empty($hierarchie['paragraphe']))
                                    <tr>
                                        <td><strong>PARAGRAPHE :</strong></td>
                                        <td>{{ $hierarchie['paragraphe'] }}</td>
                                    </tr>
                                @endif

                            </table>

                        </td>

                    </tr>
                </table>
            </div>

        @endif

        {{-- Résumé budgétaire --}}
        <div class="resume">
            <div class="resume-box dotation">
                <div class="label">Dotation Initiale</div>
                <div class="montant">{{ number_format($dotation_initiale ?? 0, 0, ',', ' ') }}</div>
            </div>
            <div class="resume-box engage">
                <div class="label">Total Engagé</div>
                <div class="montant">{{ number_format($total_engage ?? 0, 0, ',', ' ') }}</div>
            </div>
            <div class="resume-box disponible {{ ($disponible ?? 0) < 0 ? 'negatif' : '' }}">
                <div class="label">Crédits Disponibles</div>
                <div class="montant">{{ number_format($disponible ?? 0, 0, ',', ' ') }}</div>
            </div>
            <div class="resume-box taux">
                <div class="label">Taux de Consommation</div>
                <div class="montant">{{ number_format($taux_consommation ?? 0, 1) }}%</div>
            </div>
        </div>

        {{-- Tableau des engagements --}}
        <table class="engagements">
            <thead>
                <tr>
                    <th style="width: 6%;">N° ENGAGEMENT</th>
                    <th style="width: 13%;">BÉNÉFICIAIRE</th>
                    <th style="width: 18%;">OBJET</th>
                    <th style="width: 6%;">RÉFÉRENCE</th>
                    <th style="width: 6%;">DATE</th>
                    <th style="width: 9%;">MONTANT<br>ENGAGÉ</th>
                    <th style="width: 9%;">DISPONIBLE<br>APRÈS</th>
                    <th style="width: 6%;">N° OP</th>
                    <th style="width: 9%;">MONTANT<br>OP</th>
                    <th style="width: 9%;">MONTANT<br>OPT</th>
                    <th style="width: 9%;">OBSERVATIONS</th>
                </tr>
            </thead>
            <tbody>
                @forelse($engagements ?? [] as $index => $engagement)
                    <tr>
                        <td style="text-align: center;">{{ $engagement['numero_engagement'] ?? '-' }}</td>
                        <td>{{ $engagement['beneficiaire'] ?? '-' }}</td>
                        <td>{{ $engagement['objet'] ?? '-' }}</td>
                        <td>{{ $engagement['reference'] ?? '-' }}</td>
                        <td style="text-align: center;">{{ $engagement['date_engagement'] ?? '-' }}</td>
                        <td class="montant">{{ number_format($engagement['montant_engage'] ?? 0, 0, ',', ' ') }}</td>
                        <td class="montant {{ ($engagement['disponible_apres'] ?? 0) < 0 ? 'negatif' : 'positif' }}">
                            {{ number_format($engagement['disponible_apres'] ?? 0, 0, ',', ' ') }}
                        </td>
                        <td style="text-align: center;">{{ $engagement['numero_op'] ?? '-' }}</td>
                        <td class="montant">{{ number_format($engagement['montant_op'] ?? 0, 0, ',', ' ') }}</td>
                        <td class="montant">{{ number_format($engagement['montant_opt'] ?? 0, 0, ',', ' ') }}</td>
                        <td style="font-size: 5pt;">{{ $engagement['observations'] ?? '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 15px; font-style: italic; color: #999;">
                            Aucun engagement enregistré sur cette ligne budgétaire
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align: right;">TOTAL GÉNÉRAL :</td>
                    <td class="montant">{{ number_format($total_engage ?? 0, 0, ',', ' ') }}</td>
                    <td class="montant {{ ($disponible ?? 0) < 0 ? 'negatif' : 'positif' }}">
                        {{ number_format($disponible ?? 0, 0, ',', ' ') }}
                    </td>
                    <td></td>
                    <td class="montant">{{ number_format($total_op ?? 0, 0, ',', ' ') }}</td>
                    <td class="montant">{{ number_format($total_opt ?? 0, 0, ',', ' ') }}</td>
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
                <div class="titre">LE DIRECTEUR GENERAL</div>
                <div class="nom">Nom et Signature</div>
            </div>
        </div>

        {{-- Pied de page --}}
        <div class="footer">
            Document généré le {{ $date_generation ?? now()->format('d/m/Y à H:i') }} par
            {{ $generePar ?? 'Système' }}
            | Page 1/1
            | Fiche de Contrôle - Nomenclature {{ $nomenclature?->code ?? 'N/A' }}
        </div>
    </div>
</body>

</html>
