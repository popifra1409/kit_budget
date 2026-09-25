<table class="mx-table">
    <thead>
        <tr>
            <th style="width:14%">Action</th>
            <th style="width:16%">Activité</th>
            <th style="width:14%">Extrant(s)</th>
            <th style="width:16%">Indicateur(s)</th>
            <th style="width:13%">Tâche</th>
            <th style="width:13%">Sous-tâche</th>
            <th style="width:14%">Ligne budgétaire</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($bloc['lignes'] as $row)
            <tr>
                @if ($row['action'])
                    <td rowspan="{{ $row['action']['rowspan'] }}" @class(['mx-action', 'mx-flag' => $row['action']['alertes']])>
                        <strong>@include('filament.planification.libelles._libelle', ['item' => $row['action']])</strong>
                    </td>
                @endif

                @if ($row['activite'])
                    @php $a = $row['activite']; @endphp
                    <td rowspan="{{ $a['rowspan'] }}" @class(['mx-flag' => $a['alertes']])>
                        @include('filament.planification.libelles._libelle', ['item' => $a])
                    </td>
                    <td rowspan="{{ $a['rowspan'] }}">
                        @forelse ($a['extrants'] as $e)
                            <div @class(['mx-flag' => $e['alertes']])>• @include('filament.planification.libelles._libelle', ['item' => $e])</div>
                        @empty
                            <span class="mx-alerte">Aucun</span>
                        @endforelse
                    </td>
                    <td rowspan="{{ $a['rowspan'] }}">
                        @forelse ($a['indicateurs'] as $i)
                            <div @class(['mx-flag' => $i['alertes']])>• @include('filament.planification.libelles._libelle', ['item' => $i])</div>
                        @empty
                            <span class="mx-alerte">Aucun</span>
                        @endforelse
                    </td>
                @endif

                @if ($row['message'])
                    <td colspan="{{ $row['message_colspan'] }}" class="mx-vide">{{ $row['message'] }}</td>
                @else
                    @if ($row['tache'])
                        <td rowspan="{{ $row['tache']['rowspan'] }}" @class(['mx-flag' => $row['tache']['alertes']])>
                            @include('filament.planification.libelles._libelle', ['item' => $row['tache']])
                        </td>
                    @endif
                    <td>{{ $row['ligne']['sous_tache'] ?? '—' }}</td>
                    <td>
                        @if ($row['ligne']['nomenclature'])
                            {{ $row['ligne']['nomenclature'] }}
                        @else
                            <span class="mx-alerte">Non imputée</span>
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="7" class="mx-vide">Aucune action rattachée à ce sous-programme.</td></tr>
        @endforelse
    </tbody>
</table>