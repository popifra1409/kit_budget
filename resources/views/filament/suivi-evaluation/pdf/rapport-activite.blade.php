<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; }
        h1 { font-size: 15px; text-align: center; margin-bottom: 4px; }
        h2 { font-size: 12px; margin-top: 14px; border-bottom: 1px solid #c2410c; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #999; padding: 4px; }
        th { background: #f3f4f6; }
        .num { text-align: right; }
        .total td { font-weight: bold; background: #fafafa; }
        .neg { color: #dc2626; }
        .ident td:first-child { width: 30%; font-weight: bold; }
    </style>
</head>
<body>
    <h1>RAPPORT D'ACTIVITÉ {{ strtoupper($rapport->type_periode) }}</h1>
    <p style="text-align:center">N° {{ $rapport->numero }} — Période : {{ $rapport->periode }}</p>

    <h2>I. Identification</h2>
    <table class="ident">
        <tr><td>Action</td><td>{{ $rapport->activite?->action?->libelle ?? '—' }}</td></tr>
        <tr><td>Activité</td><td>{{ $rapport->activite?->libelle ?? '—' }}</td></tr>
        <tr><td>Responsable</td><td>{{ $rapport->activite?->responsable?->name ?? '—' }}</td></tr>
        <tr><td>Zone d'exécution</td><td>{{ $rapport->activite?->zone_execution ?? '—' }}</td></tr>
        <tr><td>Poids de l'activité / objectifs de l'action</td>
            <td>{{ $rapport->poids_activite !== null ? $rapport->poids_activite.' %' : '—' }}</td></tr>
    </table>

    @foreach (['tache' => ['II. Réalisation des tâches', $syntheseTache], 'moyen' => ['III. Mobilisation des moyens', $syntheseMoyen]] as $nature => [$titre, $synth])
        <h2>{{ $titre }}</h2>
        <table>
            <thead>
                <tr><th>Libellé</th><th>Unité</th><th>Prévision</th><th>Réalisation</th><th>Écart</th><th>Taux</th></tr>
            </thead>
            <tbody>
                @forelse ($rapport->lignes->where('nature', $nature) as $l)
                    <tr>
                        <td>{{ $l->libelle }}</td>
                        <td>{{ $l->unite ?? '—' }}</td>
                        <td class="num">{{ number_format($l->prevision, 0, ',', ' ') }}</td>
                        <td class="num">{{ number_format($l->realisation, 0, ',', ' ') }}</td>
                        <td class="num {{ $l->ecart < 0 ? 'neg' : '' }}">{{ number_format($l->ecart, 0, ',', ' ') }}</td>
                        <td class="num">{{ $l->taux_realisation !== null ? $l->taux_realisation.' %' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:#999">Aucune ligne</td></tr>
                @endforelse
                <tr class="total">
                    <td colspan="2">TOTAL</td>
                    <td class="num">{{ number_format($synth['prevision'], 0, ',', ' ') }}</td>
                    <td class="num">{{ number_format($synth['realisation'], 0, ',', ' ') }}</td>
                    <td class="num {{ $synth['ecart'] < 0 ? 'neg' : '' }}">{{ number_format($synth['ecart'], 0, ',', ' ') }}</td>
                    <td class="num">{{ $synth['taux'] !== null ? $synth['taux'].' %' : '—' }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach

    <h2>IV. Problèmes rencontrés</h2>
    <p>{!! nl2br(e($rapport->problemes_rencontres ?? '—')) !!}</p>

    <h2>V. Solutions proposées</h2>
    <p>{!! nl2br(e($rapport->solutions_proposees ?? '—')) !!}</p>
</body>
</html>