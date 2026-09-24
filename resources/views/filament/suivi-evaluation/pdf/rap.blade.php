<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; }
        h1 { font-size: 16px; text-align: center; margin-bottom: 2px; }
        h2 { font-size: 13px; margin-top: 16px; border-bottom: 1px solid #c2410c; }
        h3 { font-size: 11px; margin: 10px 0 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #999; padding: 4px; }
        th { background: #f3f4f6; }
        .num { text-align: right; }
        .total td { font-weight: bold; background: #fafafa; }
    </style>
</head>
<body>
    <h1>RAPPORT ANNUEL DE PERFORMANCE — EXERCICE {{ $rap->exercice?->annee }}</h1>
    <p style="text-align:center">{{ $rap->numero }} — PSP : {{ $rap->planStrategiqueEp?->libelle }}
        @if ($rap->ppaExercice) — PPA : {{ $rap->ppaExercice->numero }} @endif</p>

    <h2>Note explicative</h2>
    <p>{!! nl2br(e($rap->note_explicative ?? '—')) !!}</p>

    <h2>Chapitre 1 — Contexte de mise en œuvre</h2>
    <p>{!! nl2br(e($rap->contexte_mise_oeuvre ?? '—')) !!}</p>

    <h2>Chapitre 2 — État de mise en œuvre par sous-programme</h2>

    <h3>2.1 Exécution financière</h3>
    <table>
        <thead>
            <tr><th>Code</th><th>Sous-programme</th><th>Nb activités</th><th>CP prévus</th><th>Engagé</th><th>Disponible</th><th>Taux</th></tr>
        </thead>
        <tbody>
            @foreach ($etat as $row)
                <tr>
                    <td>{{ $row['sous_programme']->programmeBudgetaire?->code ?? $row['sous_programme']->code }}</td>
                    <td>{{ $row['sous_programme']->libelle }}</td>
                    <td class="num">{{ $row['nb_activites'] }}</td>
                    <td class="num">{{ number_format($row['cp_prevu'], 0, ',', ' ') }}</td>
                    <td class="num">{{ number_format($row['engage'], 0, ',', ' ') }}</td>
                    <td class="num">{{ number_format($row['disponible'], 0, ',', ' ') }}</td>
                    <td class="num">{{ $row['taux_execution'] }} %</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">TOTAL</td>
                <td class="num">{{ number_format($totaux['cp_prevu'], 0, ',', ' ') }}</td>
                <td class="num">{{ number_format($totaux['engage'], 0, ',', ' ') }}</td>
                <td class="num">{{ number_format($totaux['disponible'], 0, ',', ' ') }}</td>
                <td class="num">{{ $totaux['taux_execution'] }} %</td>
            </tr>
        </tbody>
    </table>

    <h3>2.2 Performance (indicateurs)</h3>
    @foreach ($etat as $row)
        <p><strong>{{ $row['sous_programme']->libelle }}</strong></p>
        <table>
            <thead>
                <tr><th>Indicateur</th><th>Référence</th><th>Cible</th><th>Réalisé</th><th>Période</th><th>Taux d'atteinte</th></tr>
            </thead>
            <tbody>
                @forelse ($row['indicateurs'] as $ind)
                    <tr>
                        <td>{{ $ind['libelle'] }}</td>
                        <td class="num">{{ $ind['reference'] ?? '—' }}</td>
                        <td class="num">{{ $ind['cible'] ?? '—' }}</td>
                        <td class="num">{{ $ind['realise'] ?? '—' }}</td>
                        <td>{{ $ind['periode'] ?? '—' }}</td>
                        <td class="num">{{ $ind['taux'] !== null ? $ind['taux'].' %' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:#999">Aucun indicateur rattaché</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    <h2>Chapitre 3 — Difficultés rencontrées et solutions</h2>
    <p>{!! nl2br(e($rap->difficultes_solutions ?? '—')) !!}</p>

    <h2>Chapitre 4 — Bilan stratégique et perspectives</h2>
    <p>{!! nl2br(e($rap->bilan_strategique_perspectives ?? '—')) !!}</p>

    <h2>Leçons apprises (input du prochain cycle CDMT)</h2>
    <p>{!! nl2br(e($rap->lecons_apprises ?? '—')) !!}</p>
</body>
</html>