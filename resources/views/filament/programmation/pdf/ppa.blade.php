<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; }
        h1 { font-size: 16px; } h2 { font-size: 13px; margin-top: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #999; padding: 4px; text-align: left; }
        .totals { margin-top: 14px; font-size: 12px; }
    </style>
</head>
<body>
    <h1>Programme de Performance Annuel (PPA) — {{ $ppa->numero }}</h1>
    <p><strong>PSP :</strong> {{ $ppa->planStrategiqueEp?->libelle }} | <strong>Exercice :</strong> {{ $ppa->exercice?->annee }}</p>

    <h2>Introduction</h2>
    <p>{{ $ppa->contexte_introduction ?: $ppa->planStrategiqueEp?->contexte_elaboration ?: '—' }}</p>

    <h2>Synthèse stratégique</h2>
    <p><strong>Domaines d'intervention :</strong> {{ $ppa->planStrategiqueEp?->domaines_intervention ?? '—' }}</p>
    <p><strong>Performances antérieures :</strong> {{ $ppa->performances_anterieures ?? '—' }}</p>
    <p><strong>Bilan technique :</strong> {{ $ppa->bilan_technique ?? '—' }}</p>
    <p><strong>Bilan financier :</strong> {{ $ppa->bilan_financier ?? '—' }}</p>
    <p><strong>Objectif stratégique :</strong> {{ $ppa->planStrategiqueEp?->objectif_strategique ?? '—' }}</p>

    <h2>Contenu des sous-programmes</h2>
    @foreach ($sousProgrammes as $sp)
        <p><strong>{{ $sp->libelle }}</strong> ({{ $sp->programmeBudgetaire?->code }}) — Objectif : {{ $sp->objectif ?? '—' }}</p>
                <table>
            <thead>
                <tr>
                    <th>Action</th><th>Activité</th><th>Indicateurs</th>
                    <th>AE prévu</th><th>CP prévu</th>
                    <th>Engagé (réel)</th><th>Disponible</th><th>Taux</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sp->actions as $action)
                    @foreach ($action->activites as $activite)
                        @php $exec = $activite->getExecutionBudgetaire(); @endphp
                        <tr>
                            <td>{{ $action->libelle }}</td>
                            <td>{{ $activite->libelle }}</td>
                            <td>{{ $activite->indicateurs->pluck('libelle')->implode(', ') ?: '—' }}</td>
                            <td>{{ number_format($activite->getTotalAe(), 0, ',', ' ') }}</td>
                            <td>{{ number_format($activite->getTotalCp(), 0, ',', ' ') }}</td>
                            <td>{{ number_format($exec['engage'], 0, ',', ' ') }}</td>
                            <td>{{ number_format($exec['disponible'], 0, ',', ' ') }}</td>
                            <td>{{ $exec['taux_engagement'] }}%</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="totals">
        <strong>Total AE :</strong> {{ number_format($ppa->getTotalAe(), 0, ',', ' ') }} FCFA
        &nbsp;&nbsp;
        <strong>Total CP :</strong> {{ number_format($ppa->getTotalCp(), 0, ',', ' ') }} FCFA
    </div>
</body>
</html>