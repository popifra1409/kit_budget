<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 9px; }
        h1 { font-size: 15px; } h2 { font-size: 12px; margin-top: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #999; padding: 3px; text-align: right; }
        th, td:first-child { text-align: left; }
        .total-row { font-weight: bold; background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>CBMT / CDMT — {{ $cdmt->numero }}</h1>
    <p>PSP : {{ $cdmt->cbmtExercice->planStrategiqueEp?->libelle }} | Version : {{ $cdmt->version }}</p>

    <h2>Tableau 9 — Ressources</h2>
    <table>
        <thead><tr><th>Titre</th><th>N-1</th><th>N</th><th>N+1</th><th>N+2</th><th>N+3</th></tr></thead>
        <tbody>
            @foreach ($tableau9 as $t)
                <tr>
                    <td>Titre {{ $t['titre'] }} — {{ $t['libelle'] }}</td>
                    <td>{{ number_format($t['total_n_moins_1'], 0, ',', ' ') }}</td>
                    <td>{{ number_format($t['total_n'], 0, ',', ' ') }}</td>
                    <td>{{ number_format($t['total_n_plus_1'], 0, ',', ' ') }}</td>
                    <td>{{ number_format($t['total_n_plus_2'], 0, ',', ' ') }}</td>
                    <td>{{ number_format($t['total_n_plus_3'], 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Tableau 10 — Dépenses</h2>
    <table>
        <thead><tr><th>Titre</th><th>N-1</th><th>N</th><th>N+1</th><th>N+2</th><th>N+3</th></tr></thead>
        <tbody>
            @foreach ($tableau10 as $t)
                <tr>
                    <td>Titre {{ $t['titre'] }} — {{ $t['libelle'] }}</td>
                    <td>{{ number_format($t['total_n_moins_1'], 0, ',', ' ') }}</td>
                    <td>{{ number_format($t['total_n'], 0, ',', ' ') }}</td>
                    <td>{{ number_format($t['total_n_plus_1'], 0, ',', ' ') }}</td>
                    <td>{{ number_format($t['total_n_plus_2'], 0, ',', ' ') }}</td>
                    <td>{{ number_format($t['total_n_plus_3'], 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Test de soutenabilité CDMT ↔ CBMT</h2>
    <table>
        <thead><tr><th>Année</th><th>Plafond</th><th>Programmé</th><th>Écart</th></tr></thead>
        <tbody>
            @foreach (['n_plus_1'=>'N+1','n_plus_2'=>'N+2','n_plus_3'=>'N+3'] as $k=>$label)
                <tr>
                    <td>{{ $label }}</td>
                    <td>{{ number_format($ecart[$k]['plafond'], 0, ',', ' ') }}</td>
                    <td>{{ number_format($ecart[$k]['programme'], 0, ',', ' ') }}</td>
                    <td>{{ number_format($ecart[$k]['ecart'], 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Annexe C — Programmation des dépenses par activités</h2>
    @foreach ($annexeC as $sp)
        <p><strong>{{ $sp['sous_programme']->libelle }}</strong></p>
        <table>
            <thead><tr><th>Action / Activité</th><th>N+1 AE</th><th>N+1 CP</th><th>N+2 AE</th><th>N+2 CP</th><th>N+3 AE</th><th>N+3 CP</th></tr></thead>
            <tbody>
                @foreach ($sp['actions'] as $act)
                    @foreach ($act['lignes'] as $l)
                        <tr>
                            <td>{{ $act['action']?->libelle }} — {{ $l->libelle }}</td>
                            <td>{{ number_format($l->n_plus_1_ae, 0, ',', ' ') }}</td>
                            <td>{{ number_format($l->n_plus_1_cp, 0, ',', ' ') }}</td>
                            <td>{{ number_format($l->n_plus_2_ae, 0, ',', ' ') }}</td>
                            <td>{{ number_format($l->n_plus_2_cp, 0, ',', ' ') }}</td>
                            <td>{{ number_format($l->n_plus_3_ae, 0, ',', ' ') }}</td>
                            <td>{{ number_format($l->n_plus_3_cp, 0, ',', ' ') }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>