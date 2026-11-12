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
        .cl-etendu tr.bloc-action td { background-color: #dcdcdc; }
        .cl-etendu tr.bloc-action .titre { font-weight: bold; font-size: 8.5px; }
        .cl-etendu tr.bloc-action .objectif { display: block; font-style: italic; }
        .cl-etendu tr.bloc-action .totaux { display: block; font-weight: bold; }
        .cl-etendu tr.bloc-activite td { background-color: #f2f2f2; }
        .cl-etendu tr.bloc-activite .titre { font-weight: bold; }
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
    <h1>Cadre logique — modèle étendu</h1>
    <p class="chapeau">
        {{ $rapport->titre() }} — exercice {{ $rapport->exercice->annee }}<br>
        {{ count($rapport->lignes()) }} tâche(s) porteuse(s) de crédit ·
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

        @include('filament.planification.pdf._cadre-logique-etendu-table', [
            'blocs' => $rapport->blocs(),
            'totalAe' => $rapport->totalAe(),
            'totalCp' => $rapport->totalCp(),
            'libelleTotal' => 'Total du sous-programme',
        ])

        @php $anomalies = $rapport->anomalies(); @endphp
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
    @endif
</body>
</html>
