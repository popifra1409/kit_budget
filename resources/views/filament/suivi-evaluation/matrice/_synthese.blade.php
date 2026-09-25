<table class="mx-table">
    <thead>
        <tr>
            <th>Sous-programme</th><th>Programme</th><th>Responsable</th>
            <th>Actions / Activités / Tâches</th><th>CP</th><th>Engagé</th><th>Exécution</th>
            <th>Complétude</th><th>Indicateurs</th><th>Anomalies (C / M / m)</th><th>Appréciation</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($m['sous_programmes'] as $b)
            @php $an = $b['anomalies']->countBy(fn ($a) => $a['gravite']); @endphp
            <tr>
                <td><strong>{{ $b['sp']->code }}</strong> {{ $b['sp']->libelle }}</td>
                <td>{{ $b['sp']->programmeBudgetaire?->code ?? '—' }}</td>
                <td>{{ $b['sp']->responsable?->name ?? '—' }}</td>
                <td class="mx-num">{{ $b['compteurs']['actions'] }} / {{ $b['compteurs']['activites'] }} / {{ $b['compteurs']['taches'] }}</td>
                <td class="mx-num">{{ number_format($b['cp'], 0, ',', ' ') }}</td>
                <td class="mx-num">{{ number_format($b['engage'], 0, ',', ' ') }}</td>
                <td class="mx-num">{{ $b['taux_execution'] }} %</td>
                <td class="mx-num">{{ $b['score_completude'] }} %</td>
                <td class="mx-num">{{ $b['score_performance'] !== null ? $b['score_performance'] . ' %' : 'n.m.' }}</td>
                <td class="mx-num">
                    <span class="mx-critique">{{ $an['critique'] ?? 0 }}</span> /
                    <span class="mx-majeure">{{ $an['majeure'] ?? 0 }}</span> /
                    <span class="mx-mineure">{{ $an['mineure'] ?? 0 }}</span>
                </td>
                <td><span class="mx-badge mx-{{ $b['appreciation']['couleur'] }}">{{ $b['appreciation']['libelle'] }}</span></td>
            </tr>
        @endforeach
        <tr class="mx-total">
            <td colspan="4">TOTAL PSP</td>
            <td class="mx-num">{{ number_format($m['totaux']['cp'], 0, ',', ' ') }}</td>
            <td class="mx-num">{{ number_format($m['totaux']['engage'], 0, ',', ' ') }}</td>
            <td class="mx-num">{{ $m['totaux']['taux_execution'] }} %</td>
            <td class="mx-num">{{ $m['totaux']['completude'] }} %</td>
            <td></td>
            <td class="mx-num"><span class="mx-critique">{{ $m['totaux']['anomalies_critiques'] }} critique(s)</span></td>
            <td></td>
        </tr>
    </tbody>
</table>
<p class="mx-muted">n.m. = non mesuré (aucune valeur d'indicateur saisie sur l'exercice).</p>