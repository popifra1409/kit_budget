<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>État des Disponibilités Budgétaires - {{ $budget->libelle }}</title>
    <style>
        @page {
            margin: 12mm 8mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 7pt;
            color: #333;
            line-height: 1.3;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #1F4E78;
        }

        .header h1 {
            font-size: 16pt;
            color: #1F4E78;
            margin-bottom: 6px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .header .subtitle {
            font-size: 10pt;
            color: #666;
            margin-bottom: 4px;
        }

        .header .info {
            font-size: 8pt;
            color: #999;
            font-style: italic;
        }

        .budget-info {
            background: #F8F9FA;
            padding: 8px;
            margin-bottom: 12px;
            border-left: 4px solid #4472C4;
            border-radius: 3px;
        }

        .budget-info table {
            width: 100%;
        }

        .budget-info td {
            padding: 2px 6px;
            font-size: 8pt;
        }

        .budget-info td:first-child {
            font-weight: bold;
            width: 20%;
            color: #1F4E78;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 7pt;
        }

        table.data-table thead {
            background: #4472C4;
            color: white;
        }

        table.data-table thead th {
            padding: 6px 3px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #2B5797;
            font-size: 7pt;
            line-height: 1.2;
        }

        table.data-table tbody td {
            padding: 5px 3px;
            border: 1px solid #ddd;
        }

        table.data-table tbody tr:nth-child(even) {
            background: #F9F9F9;
        }

        .col-code {
            text-align: center;
            font-weight: 600;
            width: 5%;
            font-size: 7pt;
        }

        .col-libelle {
            text-align: left;
            width: 15%;
            font-size: 6.5pt;
        }

        .col-chapitre,
        .col-article,
        .col-paragraphe {
            text-align: center;
            width: 4%;
            font-size: 6pt;
        }

        .col-montant {
            text-align: right;
            font-family: 'Courier New', monospace;
            width: 7%;
            font-size: 7pt;
        }

        .col-taux {
            text-align: center;
            font-weight: 600;
            width: 5%;
        }

        .montant-positif {
            color: #28A745;
        }

        .montant-negatif {
            color: #DC3545;
            font-weight: bold;
        }

        .taux-bon {
            color: #28A745;
            font-weight: bold;
        }

        .taux-moyen {
            color: #FFC107;
            font-weight: bold;
        }

        .taux-mauvais {
            color: #DC3545;
            font-weight: bold;
        }

        tfoot {
            background: #E7E6E6;
            font-weight: bold;
            border-top: 2px solid #333;
        }

        tfoot td {
            padding: 6px 3px;
            border: 1px solid #999;
            font-size: 7.5pt;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 6pt;
            color: #999;
            padding-top: 4px;
            border-top: 1px solid #ddd;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: #999;
            font-style: italic;
        }

        .legend {
            margin-top: 12px;
            padding: 8px;
            background: #FFF9E6;
            border-left: 4px solid #FFC107;
            font-size: 7pt;
        }

        .legend strong {
            color: #856404;
        }
    </style>
</head>
<body>
    <!-- En-tête -->
    <div class="header">
        <h1>État des Disponibilités Budgétaires</h1>
        <div class="subtitle">{{ $budget->libelle }}</div>
        <div class="info">Généré le {{ now()->format('d/m/Y à H:i') }}</div>
    </div>

    <!-- Informations du budget -->
    <div class="budget-info">
        <table>
            <tr>
                <td>Exercice :</td>
                <td>{{ $budget->exercice }}</td>
                <td>Code Budget :</td>
                <td>{{ $budget->code }}</td>
            </tr>
            <tr>
                <td>Statut :</td>
                <td>
                    @switch($budget->statut)
                        @case('elaboration') En élaboration @break
                        @case('adopte') Adopté @break
                        @case('execution') En exécution @break
                        @case('cloture') Clôturé @break
                        @default {{ $budget->statut }}
                    @endswitch
                </td>
                <td>Date adoption :</td>
                <td>{{ $budget->date_adoption ? $budget->date_adoption->format('d/m/Y') : 'N/A' }}</td>
            </tr>
            <tr>
                <td>Nombre de lignes :</td>
                <td>{{ $lignes->count() }}</td>
                <td>Budget total :</td>
                <td>{{ number_format($lignes->sum('budget_rectifie'), 0, ',', ' ') }} FCFA</td>
            </tr>
        </table>
    </div>

    <!-- Tableau des disponibilités -->
    @if($lignes->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-code">Code</th>
                    <th class="col-libelle">Nomenclature</th>
                    <th class="col-chapitre">Ch.</th>
                    <th class="col-article">Art.</th>
                    <th class="col-paragraphe">§</th>
                    <th class="col-montant">Budget<br>Initial</th>
                    <th class="col-montant">Vir.<br>Entrants</th>
                    <th class="col-montant">Vir.<br>Sortants</th>
                    <th class="col-montant">Budget<br>Rectifié</th>
                    <th class="col-montant">Engagé</th>
                    <th class="col-montant">Ordonné</th>
                    <th class="col-montant">Payé</th>
                    <th class="col-montant">Dispo.<br>Eng.</th>
                    <th class="col-montant">Dispo.<br>Ord.</th>
                    <th class="col-taux">Tx<br>Eng.</th>
                    <th class="col-taux">Tx<br>Exec.</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalInitial = 0;
                    $totalVirementsEntrants = 0;
                    $totalVirementsSortants = 0;
                    $totalRectifie = 0;
                    $totalEngage = 0;
                    $totalOrdonne = 0;
                    $totalPaye = 0;
                    $totalDisponibleEng = 0;
                    $totalDisponibleOrd = 0;
                @endphp

                @foreach($lignes->sortBy(fn($l) => $l->nomenclature?->code ?? 'ZZZ') as $ligne)
                    @php
                        $budgetInitial = $ligne->budget_initial ?? 0;
                        $virementsEntrants = $ligne->virements_entrants ?? 0;
                        $virementsSortants = $ligne->virements_sortants ?? 0;
                        $budgetRectifie = $ligne->budget_rectifie ?? ($budgetInitial + $virementsEntrants - $virementsSortants);
                        
                        $engage = $ligne->engage ?? 0;
                        $ordonne = $ligne->ordonne ?? 0;
                        $paye = $ligne->paye ?? 0;
                        
                        $disponibleEng = $ligne->disponible_engagement ?? ($budgetRectifie - $engage);
                        $disponibleOrd = $ligne->disponible_ordonnancement ?? ($budgetRectifie - $ordonne);
                        
                        $tauxEngagement = $budgetRectifie > 0 ? ($engage / $budgetRectifie) * 100 : 0;
                        $tauxExecution = $budgetRectifie > 0 ? ($paye / $budgetRectifie) * 100 : 0;

                        $totalInitial += $budgetInitial;
                        $totalVirementsEntrants += $virementsEntrants;
                        $totalVirementsSortants += $virementsSortants;
                        $totalRectifie += $budgetRectifie;
                        $totalEngage += $engage;
                        $totalOrdonne += $ordonne;
                        $totalPaye += $paye;
                        $totalDisponibleEng += $disponibleEng;
                        $totalDisponibleOrd += $disponibleOrd;
                    @endphp

                    <tr>
                        <td class="col-code">{{ $ligne->nomenclature?->code ?? '-' }}</td>
                        <td class="col-libelle">{{ $ligne->nomenclature?->libelle ?? '' }}</td>
                        <td class="col-chapitre">{{ $ligne->nomenclature?->chapitre ?? '' }}</td>
                        <td class="col-article">{{ $ligne->nomenclature?->article ?? '' }}</td>
                        <td class="col-paragraphe">{{ $ligne->nomenclature?->paragraphe ?? '' }}</td>
                        <td class="col-montant">{{ number_format($budgetInitial, 0, ',', ' ') }}</td>
                        <td class="col-montant">{{ number_format($virementsEntrants, 0, ',', ' ') }}</td>
                        <td class="col-montant">{{ number_format($virementsSortants, 0, ',', ' ') }}</td>
                        <td class="col-montant">{{ number_format($budgetRectifie, 0, ',', ' ') }}</td>
                        <td class="col-montant">{{ number_format($engage, 0, ',', ' ') }}</td>
                        <td class="col-montant">{{ number_format($ordonne, 0, ',', ' ') }}</td>
                        <td class="col-montant">{{ number_format($paye, 0, ',', ' ') }}</td>
                        <td class="col-montant {{ $disponibleEng < 0 ? 'montant-negatif' : 'montant-positif' }}">
                            {{ number_format($disponibleEng, 0, ',', ' ') }}
                        </td>
                        <td class="col-montant {{ $disponibleOrd < 0 ? 'montant-negatif' : 'montant-positif' }}">
                            {{ number_format($disponibleOrd, 0, ',', ' ') }}
                        </td>
                        <td class="col-taux {{ $tauxEngagement >= 90 ? 'taux-mauvais' : ($tauxEngagement >= 70 ? 'taux-moyen' : 'taux-bon') }}">
                            {{ number_format($tauxEngagement, 1) }}%
                        </td>
                        <td class="col-taux {{ $tauxExecution >= 90 ? 'taux-mauvais' : ($tauxExecution >= 70 ? 'taux-moyen' : 'taux-bon') }}">
                            {{ number_format($tauxExecution, 1) }}%
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align: right; font-weight: bold;">TOTAL GÉNÉRAL</td>
                    <td class="col-montant">{{ number_format($totalInitial, 0, ',', ' ') }}</td>
                    <td class="col-montant">{{ number_format($totalVirementsEntrants, 0, ',', ' ') }}</td>
                    <td class="col-montant">{{ number_format($totalVirementsSortants, 0, ',', ' ') }}</td>
                    <td class="col-montant">{{ number_format($totalRectifie, 0, ',', ' ') }}</td>
                    <td class="col-montant">{{ number_format($totalEngage, 0, ',', ' ') }}</td>
                    <td class="col-montant">{{ number_format($totalOrdonne, 0, ',', ' ') }}</td>
                    <td class="col-montant">{{ number_format($totalPaye, 0, ',', ' ') }}</td>
                    <td class="col-montant {{ $totalDisponibleEng < 0 ? 'montant-negatif' : 'montant-positif' }}">
                        {{ number_format($totalDisponibleEng, 0, ',', ' ') }}
                    </td>
                    <td class="col-montant {{ $totalDisponibleOrd < 0 ? 'montant-negatif' : 'montant-positif' }}">
                        {{ number_format($totalDisponibleOrd, 0, ',', ' ') }}
                    </td>
                    <td class="col-taux">
                        {{ $totalRectifie > 0 ? number_format(($totalEngage / $totalRectifie) * 100, 1) : 0 }}%
                    </td>
                    <td class="col-taux">
                        {{ $totalRectifie > 0 ? number_format(($totalPaye / $totalRectifie) * 100, 1) : 0 }}%
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Légende -->
        <div class="legend">
            <strong>Légende :</strong> 
            <span style="color: #28A745;">■</span> Disponible positif &nbsp;&nbsp;
            <span style="color: #DC3545;">■</span> Disponible négatif (dépassement) &nbsp;&nbsp;
            Dispo. Eng. = Disponible à l'engagement &nbsp;&nbsp;
            Dispo. Ord. = Disponible à l'ordonnancement
        </div>
    @else
        <div class="no-data">
            Aucune ligne budgétaire trouvée pour ce budget.
        </div>
    @endif

    <!-- Pied de page -->
    <div class="footer">
        Document généré automatiquement - 
        {{ config('app.name') }} - {{ now()->format('d/m/Y à H:i') }}
    </div>
</body>
</html>