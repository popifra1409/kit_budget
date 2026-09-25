@php $sp = $bloc['sp']; @endphp

<p class="mx-titre">
    {{ $sp->code }} — {{ $sp->libelle }}
    <span class="mx-badge mx-{{ $bloc['appreciation']['couleur'] }}">{{ $bloc['appreciation']['libelle'] }}</span>
</p>

<table class="mx-table">
    <tr>
        <th style="width:18%">Programme de rattachement</th>
        <td>{{ $sp->programmeBudgetaire?->code }} {{ $sp->programmeBudgetaire?->libelle ?? '—' }}</td>
        <th style="width:12%">Responsable</th>
        <td>{{ $sp->responsable?->name ?? '—' }}</td>
    </tr>
    <tr><th>Objectif du sous-programme</th><td colspan="3">{{ $sp->objectif ?? '—' }}</td></tr>
    @if ($sp->strategie)
        <tr><th>Stratégie</th><td colspan="3">{{ $sp->strategie }}</td></tr>
    @endif
</table>

<table class="mx-table">
    <tr>
        <th>Part du budget PSP</th><th>AE</th><th>CP</th><th>Engagé</th><th>Exécution</th>
        <th>Complétude arrimage</th><th>Atteinte indicateurs</th><th>Réalisation extrants</th>
    </tr>
    <tr>
        <td class="mx-num">{{ $bloc['part_budget'] }} %</td>
        <td class="mx-num">{{ number_format($bloc['ae'], 0, ',', ' ') }}</td>
        <td class="mx-num">{{ number_format($bloc['cp'], 0, ',', ' ') }}</td>
        <td class="mx-num">{{ number_format($bloc['engage'], 0, ',', ' ') }}</td>
        <td class="mx-num">{{ $bloc['taux_execution'] }} %</td>
        <td class="mx-num">{{ $bloc['score_completude'] }} %</td>
        <td class="mx-num">{{ $bloc['score_performance'] !== null ? $bloc['score_performance'] . ' %' : 'n.m.' }}</td>
        <td class="mx-num">{{ $bloc['score_extrants'] !== null ? $bloc['score_extrants'] . ' %' : 'n.m.' }}</td>
    </tr>
</table>

@if (!empty($bloc['indicateurs']))
    <table class="mx-table">
        <tr><th>Indicateur du sous-programme</th><th>Unité</th><th>Référence</th><th>Cible</th><th>Réalisé</th><th>Période</th><th>Atteinte</th></tr>
        @foreach ($bloc['indicateurs'] as $i)
            <tr>
                <td>{{ $i['libelle'] }}</td>
                <td>{{ $i['unite'] ?? '—' }}</td>
                <td class="mx-num">{{ $i['reference'] ?? '—' }}</td>
                <td class="mx-num">{{ $i['cible'] ?? '—' }}</td>
                <td class="mx-num">{{ $i['realise'] ?? '—' }}</td>
                <td>{{ $i['periode'] ?? '—' }}</td>
                <td class="mx-num">{{ $i['taux'] !== null ? $i['taux'] . ' %' : '—' }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if ($bloc['anomalies']->isNotEmpty())
    <table class="mx-table">
        <tr><th style="width:10%">Gravité</th><th>Points à examiner avec le responsable</th></tr>
        @foreach ($bloc['anomalies'] as $a)
            <tr>
                <td class="mx-{{ $a['gravite'] }}">{{ ucfirst($a['gravite']) }}</td>
                <td>{{ $a['message'] }}</td>
            </tr>
        @endforeach
    </table>
@endif