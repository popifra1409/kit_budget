<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Prévision de Recettes - {{ $prevision->code }}</title>
    <style>
        @page {
            margin: 1.5cm;
            size: A4 landscape;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #4472C4;
        }

        .header h1 {
            color: #1F4788;
            font-size: 18pt;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .header h2 {
            color: #555;
            font-size: 12pt;
            font-weight: normal;
        }

        .info-section {
            width: 100%;
            margin-bottom: 15px;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
        }

        .info-row {
            margin-bottom: 5px;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 20%;
        }

        .info-value {
            display: inline-block;
            width: 28%;
        }

        .table-container {
            width: 100%;
            margin-top: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }

        thead {
            background-color: #4472C4;
            color: white;
        }

        thead th {
            padding: 10px 6px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #2C5AA0;
        }

        tbody td {
            padding: 8px 6px;
            border: 1px solid #ddd;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .montant {
            font-family: 'Courier New', monospace;
            font-weight: 500;
        }

        .tfoot-total {
            background-color: #E7E6E6;
            font-weight: bold;
            font-size: 9pt;
        }

        .tfoot-total td {
            padding: 10px 6px;
            border: 2px solid #999;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: bold;
        }

        .badge-success {
            background-color: #28a745;
            color: white;
        }

        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
        }

        .badge-info {
            background-color: #17a2b8;
            color: white;
        }

        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 7pt;
            color: #666;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>PRÉVISION DE RECETTES</h1>
        <h2>
            {{ $prevision->libelle }} - Exercice
            @if (isset($prevision->exerciceBudgetaire) && is_object($prevision->exerciceBudgetaire))
                {{ $prevision->exerciceBudgetaire->annee }}
            @else
                {{ $prevision->exercice ?? 'N/A' }}
            @endif
        </h2>
    </div>

    <div class="info-section">
        <div class="info-row">
            <span class="info-label">Code :</span>
            <span class="info-value">{{ $prevision->code }}</span>
            <span class="info-label">Statut :</span>
            <span class="info-value">
                @if ($prevision->statut === 'elaboration')
                    <span class="badge badge-secondary">En élaboration</span>
                @elseif($prevision->statut === 'adopte')
                    <span class="badge badge-success">Adopté</span>
                @elseif($prevision->statut === 'execution')
                    <span class="badge badge-info">En exécution</span>
                @else
                    <span class="badge badge-danger">Clôturé</span>
                @endif
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">Date d'adoption :</span>
            <span
                class="info-value">{{ $prevision->date_adoption ? $prevision->date_adoption->format('d/m/Y') : 'N/A' }}</span>
            <span class="info-label">Date de révision :</span>
            <span
                class="info-value">{{ $prevision->date_revision ? $prevision->date_revision->format('d/m/Y') : 'N/A' }}</span>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">Code</th>
                    <th style="width: 25%;">Libellé</th>
                    <th style="width: 12%;">Montant Initial</th>
                    <th style="width: 12%;">Montant Rectifié</th>
                    <th style="width: 12%;">Montant Recouvré</th>
                    <th style="width: 11%;">Écart</th>
                    <th style="width: 8%;">Taux (%)</th>
                    <th style="width: 12%;">Observations</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lignes as $ligne)
                    <tr>
                        <td class="text-center">{{ $ligne->code_nomenclature }}</td>
                        <td>{{ $ligne->libelle_nomenclature }}</td>
                        <td class="text-right montant">{{ number_format($ligne->montant_prevu_initial, 0, ',', ' ') }}
                        </td>
                        <td class="text-right montant">{{ number_format($ligne->montant_rectifie, 0, ',', ' ') }}</td>
                        <td class="text-right montant">{{ number_format($ligne->montant_recouvre, 0, ',', ' ') }}</td>
                        <td class="text-right montant">{{ number_format($ligne->ecart, 0, ',', ' ') }}</td>
                        <td class="text-center">
                            @php
                                $taux = $ligne->taux_recouvrement;
                            @endphp
                            @if ($taux >= 90)
                                <span class="badge badge-success">{{ number_format($taux, 1) }}%</span>
                            @elseif($taux >= 70)
                                <span class="badge badge-warning">{{ number_format($taux, 1) }}%</span>
                            @else
                                <span class="badge badge-danger">{{ number_format($taux, 1) }}%</span>
                            @endif
                        </td>
                        <td style="font-size: 7pt;">
                            {{ $ligne->observations ? \Illuminate\Support\Str::limit($ligne->observations, 40) : '' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center" style="padding: 20px;">Aucune ligne de prévision</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="tfoot-total">
                    <td colspan="2" style="text-align: center;">TOTAL GÉNÉRAL</td>
                    <td class="text-right montant">{{ number_format($prevision->getTotalPrevuInitial(), 0, ',', ' ') }}
                    </td>
                    <td class="text-right montant">
                        {{ number_format($prevision->getTotalPrevuRectifie(), 0, ',', ' ') }}</td>
                    <td class="text-right montant">{{ number_format($prevision->getTotalRecouvre(), 0, ',', ' ') }}
                    </td>
                    <td class="text-right montant">{{ number_format($prevision->getEcartGlobal(), 0, ',', ' ') }}</td>
                    <td class="text-center">
                        @php
                            $tauxTotal = $prevision->getTauxRecouvrement();
                        @endphp
                        @if ($tauxTotal >= 90)
                            <span class="badge badge-success">{{ number_format($tauxTotal, 1) }}%</span>
                        @elseif($tauxTotal >= 70)
                            <span class="badge badge-warning">{{ number_format($tauxTotal, 1) }}%</span>
                        @else
                            <span class="badge badge-danger">{{ number_format($tauxTotal, 1) }}%</span>
                        @endif
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="footer">
        Édité le {{ now()->format('d/m/Y à H:i') }} | Document généré automatiquement
    </div>
</body>

</html>
