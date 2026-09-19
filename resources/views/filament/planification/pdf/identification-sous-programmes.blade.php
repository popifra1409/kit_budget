<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        h2 { font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #999; padding: 4px; text-align: left; }
        .section { margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Tableau 14 — Identification des Sous-Programmes</h1>
    <p><strong>PSP :</strong> {{ $psp->libelle }}</p>

    @foreach ($psp->sousProgrammes as $i => $sp)
        <div class="section">
            <h2>Sous-programme n°{{ $i + 1 }} : {{ $sp->libelle }}</h2>
            <p><strong>Programme de rattachement :</strong> {{ $sp->programmeBudgetaire?->code }} — {{ $sp->programmeBudgetaire?->libelle }}</p>
            <p><strong>Objectif :</strong> {{ $sp->objectif ?? '—' }}</p>

            <table>
                <thead><tr><th>Indicateur</th><th>Référence</th><th>Cible</th></tr></thead>
                <tbody>
                    @forelse ($sp->indicateurs as $ind)
                        <tr>
                            <td>{{ $ind->libelle }}</td>
                            <td>{{ $ind->valeur_reference ?? '—' }} ({{ $ind->annee_reference ?? '—' }})</td>
                            <td>{{ $ind->valeur_cible ?? '—' }} ({{ $ind->annee_cible ?? '—' }})</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Aucun indicateur</td></tr>
                    @endforelse
                </tbody>
            </table>

            <p><strong>Stratégie :</strong> {{ $sp->strategie ?? '—' }}</p>
            <p><strong>Cadre institutionnel :</strong> {{ $sp->cadre_institutionnel ?? '—' }}</p>
            <p><strong>Responsable :</strong> {{ $sp->responsable?->name ?? '—' }}</p>
        </div>
    @endforeach
</body>
</html>