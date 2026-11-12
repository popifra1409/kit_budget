<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>États isolés des actions — {{ $rapport->titre() }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.5px; }
        h1 { font-size: 13px; text-align: center; margin-bottom: 2px; }
        h2 { font-size: 10px; margin: 0 0 4px; text-decoration: underline; }
        p.chapeau { text-align: center; font-size: 8.5px; color: #444; margin-bottom: 8px; }
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
        .page-break { page-break-before: always; }
        p.note { font-size: 7px; color: #555; margin-top: 6px; }
    </style>
</head>
<body>
    <h1>États isolés des actions du sous-programme</h1>
    <p class="chapeau">
        {{ $rapport->titre() }} — exercice {{ $rapport->exercice->annee }}<br>
        {{ $rapport->nbEtats() }} état(s) · {{ count($rapport->lignes()) }} tâche(s) porteuse(s) de crédit ·
        AE {{ $rapport->formatMontant($rapport->totalAe()) }} · CP {{ $rapport->formatMontant($rapport->totalCp()) }}
    </p>

    @if ($rapport->estVide())
        <p class="note" style="text-align:center; font-style:italic">
            Aucune action rattachée à ce sous-programme pour l'exercice {{ $rapport->exercice->annee }}.
        </p>
    @else
        @foreach ($rapport->etats() as $etat)
            @if (! $loop->first)
                <div class="page-break"></div>
            @endif

            <h2>État {{ $loop->iteration }} / {{ $rapport->nbEtats() }} — {{ $etat['titre'] }}</h2>

            @include('filament.planification.pdf._cadre-logique-etendu-table', [
                'blocs' => [$etat],
                'totalAe' => $etat['ae'],
                'totalCp' => $etat['cp'],
                'libelleTotal' => 'Total de l\'action',
            ])
        @endforeach

        @php $anomalies = $rapport->anomalies(); @endphp
        @if ($anomalies['reclassees'] !== [])
            <p class="note">
                {{ count($anomalies['reclassees']) }} sous-tâche(s) sont imprimées sous l'activité de leur tâche
                mère alors que leur propre rattachement les donne à une autre activité
                ({{ collect($anomalies['reclassees'])->pluck('code')->unique()->implode(', ') }}).
            </p>
        @endif
    @endif
</body>
</html>
