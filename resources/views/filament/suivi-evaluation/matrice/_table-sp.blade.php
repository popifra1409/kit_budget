<table class="mx-table">
    <thead>
        <tr>
            <th>Action</th><th>Activité</th><th>Extrants</th><th>Indicateurs</th><th>Tâche</th>
            <th>Sous-tâche</th><th>Ligne budgétaire</th><th>AE</th><th>CP</th><th>Engagé</th><th>Taux</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($bloc['lignes'] as $row)
            <tr>
                @if ($row['action'])
                    <td rowspan="{{ $row['action']['rowspan'] }}" class="mx-action">
                        <strong>{{ $row['action']['code'] }}</strong> {{ $row['action']['libelle'] }}
                    </td>
                @endif

                @if ($row['activite'])
                    @php $a = $row['activite']; @endphp
                    <td rowspan="{{ $a['rowspan'] }}">
                        <strong>{{ $a['libelle'] }}</strong>
                        @if ($a['responsable'])<div class="mx-muted">Resp. : {{ $a['responsable'] }}</div>@endif
                        @if ($a['zone'])<div class="mx-muted">Zone : {{ $a['zone'] }}</div>@endif
                    </td>
                    <td rowspan="{{ $a['rowspan'] }}">
                        @forelse ($a['extrants'] as $e)
                            <div>• {{ $e['libelle'] }}
                                @if ($e['prevu'] !== null)
                                    <span class="mx-muted">({{ (float) ($e['realise'] ?? 0) }} / {{ (float) $e['prevu'] }} {{ $e['unite'] }})</span>
                                @endif
                            </div>
                        @empty
                            <span class="mx-alerte">Aucun</span>
                        @endforelse
                    </td>
                    <td rowspan="{{ $a['rowspan'] }}">
                        @forelse ($a['indicateurs'] as $i)
                            <div>• {{ $i['libelle'] }}
                                <span class="mx-muted">réf. {{ $i['reference'] ?? '—' }} → cible {{ $i['cible'] ?? '—' }} | réalisé {{ $i['realise'] ?? '—' }}@if ($i['taux'] !== null) ({{ $i['taux'] }} %)@endif</span>
                            </div>
                        @empty
                            <span class="mx-alerte">Aucun</span>
                        @endforelse
                    </td>
                @endif

                @if ($row['message'])
                    <td colspan="{{ $row['message_colspan'] }}" class="mx-vide">{{ $row['message'] }}</td>
                @else
                    @if ($row['tache'])
                        <td rowspan="{{ $row['tache']['rowspan'] }}">{{ $row['tache']['libelle'] }}</td>
                    @endif
                    @php $l = $row['ligne']; @endphp
                    <td>{{ $l['sous_tache'] }}</td>
                    <td>
                        @if ($l['code'])
                            <strong>{{ $l['code'] }}</strong> {{ $l['nomenclature'] }}
                            @if ($l['quote_part'] < 1)
                                <div class="mx-muted">ligne partagée — quote-part {{ round($l['quote_part'] * 100, 1) }} %</div>
                            @endif
                        @else
                            <span class="mx-alerte">Non imputée</span>
                        @endif
                    </td>
                    <td class="mx-num">{{ number_format($l['ae'], 0, ',', ' ') }}</td>
                    <td class="mx-num">{{ number_format($l['cp'], 0, ',', ' ') }}</td>
                    <td class="mx-num">{{ number_format($l['engage'], 0, ',', ' ') }}</td>
                    <td class="mx-num">{{ $l['taux'] !== null ? $l['taux'] . ' %' : '—' }}</td>
                @endif
            </tr>
        @empty
            <tr><td colspan="11" class="mx-vide">Aucune action rattachée à ce sous-programme.</td></tr>
        @endforelse

        <tr class="mx-total">
            <td colspan="7">TOTAL {{ $bloc['sp']->code }}</td>
            <td class="mx-num">{{ number_format($bloc['ae'], 0, ',', ' ') }}</td>
            <td class="mx-num">{{ number_format($bloc['cp'], 0, ',', ' ') }}</td>
            <td class="mx-num">{{ number_format($bloc['engage'], 0, ',', ' ') }}</td>
            <td class="mx-num">{{ $bloc['taux_execution'] }} %</td>
        </tr>
    </tbody>
</table>