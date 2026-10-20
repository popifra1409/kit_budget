{{-- Tableau du cadre logique : $premiereColonne (string), $blocs (array), $annees (int[]) --}}
@php
    [$realisation, $estimation, $cible1, $cible2, $cible3] = $annees;
@endphp

<table>
    <thead>
        <tr>
            <th rowspan="2" style="width: 12%;">{{ $premiereColonne }}</th>
            <th rowspan="2" style="width: 15%;">Objectifs</th>
            <th rowspan="2" style="width: 19%;">Indicateurs</th>
            <th rowspan="2" style="width: 7%;">Unité (de mesure de l'indicateur)</th>
            <th rowspan="2" style="width: 10%;">Source de vérification</th>
            <th style="width: 5%;">Réalisation</th>
            <th style="width: 5%;">Estimation</th>
            <th colspan="3" style="width: 15%;">Valeurs cibles</th>
            <th rowspan="2" style="width: 12%;">Responsable de mise en œuvre</th>
        </tr>
        <tr>
            <th style="width: 5%;">{{ $realisation }}</th>
            <th style="width: 5%;">{{ $estimation }}</th>
            <th style="width: 5%;">{{ $cible1 }}</th>
            <th style="width: 5%;">{{ $cible2 }}</th>
            <th style="width: 5%;">{{ $cible3 }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($blocs as $bloc)
            @if (empty($bloc['lignes']))
                <tr>
                    <td class="bloc">{{ $bloc['titre'] }}</td>
                    <td>{{ $bloc['objectif'] ?? '—' }}</td>
                    <td colspan="8" class="center italic">Aucun indicateur défini</td>
                    <td class="bloc">{{ $bloc['responsable'] ?? '—' }}</td>
                </tr>
            @else
                @foreach ($bloc['lignes'] as $i => $ligne)
                    <tr>
                        @if ($i === 0)
                            <td rowspan="{{ count($bloc['lignes']) }}" class="bloc">{{ $bloc['titre'] }}</td>
                            <td rowspan="{{ count($bloc['lignes']) }}">{{ $bloc['objectif'] ?? '—' }}</td>
                        @endif

                        <td>{{ $ligne['indicateur'] }}</td>
                        <td>{{ $ligne['unite'] ?? '' }}</td>
                        <td>{{ $ligne['source'] ?? '' }}</td>

                        @foreach ($annees as $annee)
                            <td class="center">{{ $ligne['valeurs'][$annee] !== null ? (string) $ligne['valeurs'][$annee] : '' }}</td>
                        @endforeach

                        @if ($i === 0)
                            <td rowspan="{{ count($bloc['lignes']) }}" class="bloc">{{ $bloc['responsable'] ?? '' }}</td>
                        @endif
                    </tr>
                @endforeach
            @endif
        @endforeach
    </tbody>
</table>
