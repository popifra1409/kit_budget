<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #999; padding: 4px; text-align: left; }
    </style>
</head>
<body>
    <h1>Tableau 15 — Présentation des activités</h1>
    <p><strong>Sous-programme :</strong> {{ $sousProgramme->libelle }}</p>
    <p><strong>Objectif :</strong> {{ $sousProgramme->objectif ?? '—' }}</p>

    <table>
        <thead>
            <tr>
                <th>Désignation</th><th>Objectif</th><th>Indicateurs</th>
                <th>Baseline</th><th>Cible</th><th>Zone</th><th>Responsable</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($activites as $act)
                <tr>
                    <td>{{ $act->libelle }}</td>
                    <td>{{ $act->objectif ?? '—' }}</td>
                    <td>{{ $act->indicateurs->pluck('libelle')->implode(', ') ?: '—' }}</td>
                    <td>{{ $act->indicateurs->pluck('valeur_reference')->filter()->implode(', ') ?: '—' }}</td>
                    <td>{{ $act->indicateurs->pluck('valeur_cible')->filter()->implode(', ') ?: '—' }}</td>
                    <td>{{ $act->zone_execution ?? '—' }}</td>
                    <td>{{ $act->responsable?->name ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>