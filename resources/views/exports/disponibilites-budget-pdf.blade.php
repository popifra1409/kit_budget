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
            width: 7%;
            font-size: 7pt;
        }

        .col-libelle {
            text-align: left;
            width: 21%;
            font-size: 6.5pt;
        }

        .col-montant {
            text-align: right;
            font-family: 'Courier New', monospace;
            width: 6.4%;
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

        /* ── Regroupement Programme / Sous-Programme ────────── */
        .row-programme td {
            background: #1F4E78 !important;
            color: #FFFFFF;
            font-weight: bold;
            font-size: 8pt;
            padding: 5px 6px;
            border: 1px solid #163a5c;
        }

        .row-sous-programme td {
            background: #D9E2F3 !important;
            color: #1F4E78;
            font-weight: bold;
            font-size: 7.5pt;
            padding: 4px 6px;
            border: 1px solid #b9c9e8;
        }

        .row-sous-total td {
            background: #F0F2F5 !important;
            font-weight: bold;
            font-style: italic;
            border-top: 1px solid #999;
            border-bottom: 1px solid #999;
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
                        @case('elaboration')
                            En élaboration
                        @break

                        @case('adopte')
                            Adopté
                        @break

                        @case('execution')
                            En exécution
                        @break

                        @case('cloture')
                            Clôturé
                        @break

                        @default
                            {{ $budget->statut }}
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

    @php
        // Note : "Payé" = montant net verse au beneficiaire (OP standard).
        // "Taxes Reversees" = montant des retenues (TVA, IR, TSR...) reversees
        // separement au Tresor via l'OPT liee. Engage (brut) = Paye + Taxes
        // Reversees, une fois le circuit solde.

        // ── Calcul par ligne + regroupement Programme > Sous-Programme ──
        $lignesEnrichies = $lignes->map(function ($ligne) {
            $budgetInitial = $ligne->budget_initial ?? 0;
            $virementsEntrants = $ligne->virements_entrants ?? 0;
            $virementsSortants = $ligne->virements_sortants ?? 0;
            $budgetRectifie = $ligne->budget_rectifie ?? ($budgetInitial + $virementsEntrants - $virementsSortants);

            $engage = \App\Models\Engagement::where('budget_id', $ligne->budget_id)
                ->where('nomenclature_principale_id', $ligne->nomenclature_id)
                ->sum('montant_engage') ?? 0;

            $ordonne = \App\Models\Engagement::where('budget_id', $ligne->budget_id)
                ->where('nomenclature_principale_id', $ligne->nomenclature_id)
                ->whereHas('ordonnancesPaiement')
                ->sum('montant_engage') ?? 0;

            $paye = \App\Models\OrdonnancePaiement::whereHas('engagement', function ($q) use ($ligne) {
                    $q->where('budget_id', $ligne->budget_id)
                      ->where('nomenclature_principale_id', $ligne->nomenclature_id);
                })
                ->where('type_ordonnance', 'standard')
                ->where('statut', 'payee')
                ->sum('montant_net') ?? 0;

            $taxesReversees = \App\Models\OrdonnancePaiement::whereHas('engagement', function ($q) use ($ligne) {
                    $q->where('budget_id', $ligne->budget_id)
                      ->where('nomenclature_principale_id', $ligne->nomenclature_id);
                })
                ->where('type_ordonnance', 'impot')
                ->where('statut', 'payee')
                ->sum('montant_net') ?? 0;

            $disponibleEng = $budgetRectifie - $engage;
            $disponibleOrd = $budgetRectifie - $ordonne;
            $tauxEngagement = $budgetRectifie > 0 ? ($engage / $budgetRectifie) * 100 : 0;
            $tauxExecution = $budgetRectifie > 0 ? (($paye + $taxesReversees) / $budgetRectifie) * 100 : 0;

            $classification = $ligne->getClassificationStrategique();

            return (object) [
                'ligne' => $ligne,
                'budget_initial' => $budgetInitial,
                'virements_entrants' => $virementsEntrants,
                'virements_sortants' => $virementsSortants,
                'budget_rectifie' => $budgetRectifie,
                'engage' => $engage,
                'ordonne' => $ordonne,
                'paye' => $paye,
                'taxes_reversees' => $taxesReversees,
                'disponible_eng' => $disponibleEng,
                'disponible_ord' => $disponibleOrd,
                'taux_engagement' => $tauxEngagement,
                'taux_execution' => $tauxExecution,
                'programme' => $classification['programme'],
                'sous_programme' => $classification['sous_programme'],
            ];
        });

        $groupesProgramme = $lignesEnrichies->groupBy(fn ($l) => $l->programme?->id ?? 'non_affecte');

        $totalInitialGeneral = 0;
        $totalVirEntrantsGeneral = 0;
        $totalVirSortantsGeneral = 0;
        $totalRectifieGeneral = 0;
        $totalEngageGeneral = 0;
        $totalOrdonneGeneral = 0;
        $totalPayeGeneral = 0;
        $totalTaxesGeneral = 0;
        $totalDispoEngGeneral = 0;
        $totalDispoOrdGeneral = 0;
    @endphp

    <!-- Tableau des disponibilités -->
    @if ($lignes->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-code">Code</th>
                    <th class="col-libelle">Nomenclature</th>
                    <th class="col-montant">Budget<br>Initial</th>
                    <th class="col-montant">Vir.<br>Entrants</th>
                    <th class="col-montant">Vir.<br>Sortants</th>
                    <th class="col-montant">Budget<br>Rectifié</th>
                    <th class="col-montant">Engagé</th>
                    <th class="col-montant">Ordonné</th>
                    <th class="col-montant">Payé</th>
                    <th class="col-montant">Taxes<br>Reversées</th>
                    <th class="col-montant">Dispo.<br>Eng.</th>
                    <th class="col-montant">Dispo.<br>Ord.</th>
                    <th class="col-taux">Tx<br>Eng.</th>
                    <th class="col-taux">Tx<br>Exec.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groupesProgramme as $programmeId => $lignesDuProgramme)
                    @php
                        $programmeLabel = $programmeId === 'non_affecte'
                            ? 'NON AFFECTÉ À UN PROGRAMME'
                            : ($lignesDuProgramme->first()->programme->code . ' — ' . $lignesDuProgramme->first()->programme->libelle);
                    @endphp

                    <tr class="row-programme">
                        <td colspan="14">📁 PROGRAMME : {{ $programmeLabel }}</td>
                    </tr>

                    @php
                        $sousGroupes = $lignesDuProgramme->groupBy(fn ($l) => $l->sous_programme?->id ?? 'sans_sous_programme');
                    @endphp

                    @foreach ($sousGroupes as $sousProgrammeId => $lignesDuSousGroupe)
                        @if ($sousProgrammeId !== 'sans_sous_programme')
                            <tr class="row-sous-programme">
                                <td colspan="14">&nbsp;&nbsp;↳ Sous-programme (gestion interne) : {{ $lignesDuSousGroupe->first()->sous_programme->code }} — {{ $lignesDuSousGroupe->first()->sous_programme->libelle }}</td>
                            </tr>
                        @endif

                        @php
                            $sTotalInitial = 0; $sTotalVirEnt = 0; $sTotalVirSort = 0; $sTotalRectifie = 0;
                            $sTotalEngage = 0; $sTotalOrdonne = 0; $sTotalPaye = 0; $sTotalTaxes = 0;
                            $sTotalDispoEng = 0; $sTotalDispoOrd = 0;
                        @endphp

                        @foreach ($lignesDuSousGroupe->sortBy(fn($l) => $l->ligne->nomenclature?->code ?? 'ZZZ') as $l)
                            @php
                                $sTotalInitial += $l->budget_initial;
                                $sTotalVirEnt += $l->virements_entrants;
                                $sTotalVirSort += $l->virements_sortants;
                                $sTotalRectifie += $l->budget_rectifie;
                                $sTotalEngage += $l->engage;
                                $sTotalOrdonne += $l->ordonne;
                                $sTotalPaye += $l->paye;
                                $sTotalTaxes += $l->taxes_reversees;
                                $sTotalDispoEng += $l->disponible_eng;
                                $sTotalDispoOrd += $l->disponible_ord;

                                $totalInitialGeneral += $l->budget_initial;
                                $totalVirEntrantsGeneral += $l->virements_entrants;
                                $totalVirSortantsGeneral += $l->virements_sortants;
                                $totalRectifieGeneral += $l->budget_rectifie;
                                $totalEngageGeneral += $l->engage;
                                $totalOrdonneGeneral += $l->ordonne;
                                $totalPayeGeneral += $l->paye;
                                $totalTaxesGeneral += $l->taxes_reversees;
                                $totalDispoEngGeneral += $l->disponible_eng;
                                $totalDispoOrdGeneral += $l->disponible_ord;
                            @endphp
                            <tr>
                                <td class="col-code">{{ $l->ligne->nomenclature?->code ?? '-' }}</td>
                                <td class="col-libelle">{{ $l->ligne->nomenclature?->libelle ?? '' }}</td>
                                <td class="col-montant">{{ number_format($l->budget_initial, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($l->virements_entrants, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($l->virements_sortants, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($l->budget_rectifie, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($l->engage, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($l->ordonne, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($l->paye, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($l->taxes_reversees, 0, ',', ' ') }}</td>
                                <td class="col-montant {{ $l->disponible_eng < 0 ? 'montant-negatif' : 'montant-positif' }}">
                                    {{ number_format($l->disponible_eng, 0, ',', ' ') }}
                                </td>
                                <td class="col-montant {{ $l->disponible_ord < 0 ? 'montant-negatif' : 'montant-positif' }}">
                                    {{ number_format($l->disponible_ord, 0, ',', ' ') }}
                                </td>
                                <td class="col-taux {{ $l->taux_engagement >= 90 ? 'taux-mauvais' : ($l->taux_engagement >= 70 ? 'taux-moyen' : 'taux-bon') }}">
                                    {{ number_format($l->taux_engagement, 1) }}%
                                </td>
                                <td class="col-taux {{ $l->taux_execution >= 90 ? 'taux-mauvais' : ($l->taux_execution >= 70 ? 'taux-moyen' : 'taux-bon') }}">
                                    {{ number_format($l->taux_execution, 1) }}%
                                </td>
                            </tr>
                        @endforeach

                        @if ($sousProgrammeId !== 'sans_sous_programme')
                            <tr class="row-sous-total">
                                <td colspan="2" style="text-align:right;">Sous-total sous-programme :</td>
                                <td class="col-montant">{{ number_format($sTotalInitial, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($sTotalVirEnt, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($sTotalVirSort, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($sTotalRectifie, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($sTotalEngage, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($sTotalOrdonne, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($sTotalPaye, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($sTotalTaxes, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($sTotalDispoEng, 0, ',', ' ') }}</td>
                                <td class="col-montant">{{ number_format($sTotalDispoOrd, 0, ',', ' ') }}</td>
                                <td class="col-taux">{{ $sTotalRectifie > 0 ? number_format(($sTotalEngage / $sTotalRectifie) * 100, 1) : 0 }}%</td>
                                <td class="col-taux">{{ $sTotalRectifie > 0 ? number_format((($sTotalPaye + $sTotalTaxes) / $sTotalRectifie) * 100, 1) : 0 }}%</td>
                            </tr>
                        @endif
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align: right; font-weight: bold;">TOTAL GÉNÉRAL</td>
                    <td class="col-montant">{{ number_format($totalInitialGeneral, 0, ',', ' ') }}</td>
                    <td class="col-montant">{{ number_format($totalVirEntrantsGeneral, 0, ',', ' ') }}</td>
                    <td class="col-montant">{{ number_format($totalVirSortantsGeneral, 0, ',', ' ') }}</td>
                    <td class="col-montant">{{ number_format($totalRectifieGeneral, 0, ',', ' ') }}</td>
                    <td class="col-montant">{{ number_format($totalEngageGeneral, 0, ',', ' ') }}</td>
                    <td class="col-montant">{{ number_format($totalOrdonneGeneral, 0, ',', ' ') }}</td>
                    <td class="col-montant">{{ number_format($totalPayeGeneral, 0, ',', ' ') }}</td>
                    <td class="col-montant">{{ number_format($totalTaxesGeneral, 0, ',', ' ') }}</td>
                    <td class="col-montant {{ $totalDispoEngGeneral < 0 ? 'montant-negatif' : 'montant-positif' }}">
                        {{ number_format($totalDispoEngGeneral, 0, ',', ' ') }}
                    </td>
                    <td class="col-montant {{ $totalDispoOrdGeneral < 0 ? 'montant-negatif' : 'montant-positif' }}">
                        {{ number_format($totalDispoOrdGeneral, 0, ',', ' ') }}
                    </td>
                    <td class="col-taux">
                        {{ $totalRectifieGeneral > 0 ? number_format(($totalEngageGeneral / $totalRectifieGeneral) * 100, 1) : 0 }}%
                    </td>
                    <td class="col-taux">
                        {{ $totalRectifieGeneral > 0 ? number_format((($totalPayeGeneral + $totalTaxesGeneral) / $totalRectifieGeneral) * 100, 1) : 0 }}%
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
            Dispo. Ord. = Disponible à l'ordonnancement &nbsp;&nbsp;
            Taxes Reversées = retenues (TVA, IR, TSR...) reversées au Trésor via l'OPT liée &nbsp;&nbsp;
            📁 = Programme (rattachement tutelle) &nbsp;&nbsp;
            ↳ = Sous-programme (subdivision de gestion interne, le cas échéant)
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