<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cadre logique étendu — {{ $rapport->titre() }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.5px; }
        h1 { font-size: 13px; text-align: center; margin-bottom: 2px; }
        p.chapeau { text-align: center; font-size: 8.5px; color: #444; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        .cl-etendu th, .cl-etendu td { border: 1px solid #666; padding: 2.5px; text-align: left; vertical-align: top; }
        .cl-etendu th { background-color: #e8e8e8; text-align: center; font-weight: bold; }
        .cl-etendu td.center { text-align: center; }
        .cl-etendu td.num { text-align: right; white-space: nowrap; }
        /* L'action est hors du tableau ; l'activité ouvre le tableau et groupe ses tâches. */
        .entete-action { border: 1px solid #666; background-color: #dcdcdc; padding: 4px; margin: 8px 0 2px; }
        .entete-action .titre { font-weight: bold; font-size: 8.5px; }
        .entete-action .objectif, .entete-action .description { font-style: italic; }
        .entete-action .totaux { font-weight: bold; }
        .cl-etendu td.activite { background-color: #f2f2f2; }
        .cl-etendu td.activite .titre { font-weight: bold; }
        .cl-etendu tr.groupe-debut td { border-top: 1.5px solid #6b7280; }
        .cl-etendu td.suite { color: #777; font-style: italic; }
        .cl-etendu .meta { color: #444; }
        .cl-etendu tr.vide td { font-style: italic; color: #777; }
        .cl-etendu tr.detail td { background-color: #fafafa; font-size: 7px; }
        .cl-etendu tr.detail .legende { font-weight: bold; text-decoration: underline; }
        .cl-etendu tr.total td { font-weight: bold; background-color: #e8e8e8; }
        .cl-etendu .palier + .palier { padding-left: 8px; }
        p.note { font-size: 7px; color: #555; margin-top: 6px; }
        table.pilotage { width: auto; margin-bottom: 8px; }
        table.pilotage th, table.pilotage td { border: 1px solid #666; padding: 2.5px; }
        table.pilotage th { background-color: #e8e8e8; }
    </style>
</head>
<body>
    {{-- le chapeau cite déjà un nombre d'anomalies : elles se calculent avant lui --}}
    @php $anomalies = $rapport->anomalies(); @endphp

    <h1>Cadre logique — modèle étendu</h1>
    <p class="chapeau">
        {{ $rapport->titre() }} — exercice {{ $rapport->exercice->annee }}<br>
        {{ count($rapport->lignes()) }} ligne(s) de tâche ·
        @if ($anomalies['sans_budget'] !== []){{ count($anomalies['sans_budget']) }} sans compte lié · @endif
        AE total {{ $rapport->formatMontant($rapport->totalAe()) }} ·
        CP total {{ $rapport->formatMontant($rapport->totalCp()) }}
    </p>

    @if ($rapport->estVide())
        <p class="note" style="text-align:center; font-style:italic">
            Aucune action rattachée à ce sous-programme pour l'exercice {{ $rapport->exercice->annee }}.
        </p>
    @else
        <table class="pilotage">
            <tr>
                <th>Programme(s) porteur(s)</th>
                <th>Agent responsable du programme</th>
            </tr>
            @foreach ($rapport->pilotage() as $ligne)
                <tr>
                    <td>{{ $ligne['programme'] }}</td>
                    <td>{{ $ligne['pilote'] ?: 'agent non désigné' }}</td>
                </tr>
            @endforeach
        </table>

        @include('filament.planification.pdf._cadre-logique-etendu-table-pdf', [
            'blocs' => $rapport->blocs(),
        ])

        @if ($anomalies['reclassees'] !== [])
            <p class="note">
                {{ count($anomalies['reclassees']) }} sous-tâche(s) sont imprimées sous l'activité de leur tâche
                mère alors que leur propre rattachement les donne à une autre activité
                ({{ collect($anomalies['reclassees'])->pluck('code')->unique()->implode(', ') }}).
                Le total de l'activité suit la hiérarchie de saisie, comme les agrégats du modèle.
            </p>
        @endif
        @if ($anomalies['absentes'] !== [])
            <p class="note">
                {{ count($anomalies['absentes']) }} tâche(s) porteuse(s) rattachée(s) à ces activités ne
                descendent d'aucune tâche de l'état et sont comptées ailleurs.
            </p>
        @endif
        @if ($anomalies['sans_budget'] !== [])
            @php $codesHorsCredit = collect($anomalies['sans_budget'])->pluck('code')->unique()->values(); @endphp
            <p class="note">
                {{ count($anomalies['sans_budget']) }} ligne(s) de ce sous-programme n'ont reçu jusqu'ici aucune
                nomenclature : elles sont imprimées, leur montant reste compté dans les totaux comme à l'écran de
                saisie, mais aucun compte du budget n'y correspond
                ({{ $codesHorsCredit->take(10)->implode(', ') }}{{ $codesHorsCredit->count() > 10 ? ', …' : '' }}).
                Reliez-leur un compte pour qu'elles aient une case de crédit.
            </p>
        @endif
    @endif
</body>
</html>
