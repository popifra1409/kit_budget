<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titre }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8px;
            line-height: 1.3;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
        }

        .header h1 {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 10px;
            color: #666;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background-color: #4472C4;
            color: white;
            padding: 6px 4px;
            text-align: left;
            font-size: 7px;
            font-weight: bold;
            border: 1px solid #2c5aa0;
        }

        td {
            padding: 4px 3px;
            border: 1px solid #ddd;
            vertical-align: top;
            font-size: 7px;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tbody tr:hover {
            background-color: #f0f0f0;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: bold;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 7px;
            color: #666;
            padding: 5px 0;
            border-top: 1px solid #ccc;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>{{ $titre }}</h1>
        <p>Généré le {{ now()->format('d/m/Y à H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">Prog.</th>
                <th style="width: 8%;">Objectif Principal</th>
                <th style="width: 4%;">Act.</th>
                <th style="width: 8%;">Objectif Spécifique</th>
                <th style="width: 4%;">Activ.</th>
                <th style="width: 6%;">Tâche</th>
                <th style="width: 5%;">Nom.</th>
                <th style="width: 8%;">Nomenclature</th>
                <th style="width: 6%;">Délai</th>
                <th style="width: 7%;">Guichet</th>
                <th style="width: 8%;">Service</th>
                <th style="width: 6%;">AE</th>
                <th style="width: 6%;">CP</th>
                <th style="width: 10%;">Résultat</th>
                <th style="width: 9%;">Indicateur</th>
            </tr>
        </thead>
        <tbody>
            @forelse($taches as $tache)
                @php
                    $activite = $tache->activite;
                    $action = $activite->action;
                    $programme = $action->programme;
                @endphp
                <tr>
                    <td class="font-bold">{{ $programme->code }}</td>
                    <td>{{ Str::limit($programme->objectifsPrincipaux->first()?->libelle ?? '', 100) }}</td>
                    <td class="font-bold">{{ $action->code }}</td>
                    <td>{{ Str::limit($action->objectifsSpecifiques->first()?->libelle ?? '', 100) }}</td>
                    <td class="font-bold">{{ $activite->code }}</td>
                    <td>{{ Str::limit($tache->libelle, 80) }}</td>
                    <td class="font-bold">{{ $tache->nomenclature->code ?? '' }}</td>
                    <td>{{ Str::limit($tache->nomenclature->libelle ?? '', 80) }}</td>
                    <td>{{ $tache->delai ?? '-' }}</td>
                    <td>{{ $tache->guichet ?? '-' }}</td>
                    <td>{{ $tache->service?->nom ?? '-' }}</td>
                    <td class="text-right">{{ number_format($tache->ae, 0, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format($tache->cp, 0, ',', ' ') }}</td>
                    <td>{{ Str::limit($tache->resultat_attendu ?? '-', 100) }}</td>
                    <td>{{ Str::limit($tache->indicateur_resultat ?? '-', 100) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="15" class="text-center">Aucune tâche trouvée pour cet exercice.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="11" class="text-right">TOTAL</th>
                <th class="text-right">{{ number_format($taches->sum('ae'), 0, ',', ' ') }}</th>
                <th class="text-right">{{ number_format($taches->sum('cp'), 0, ',', ' ') }}</th>
                <th colspan="2"></th>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Page <span class="pagenum"></span>
    </div>
</body>

</html>
