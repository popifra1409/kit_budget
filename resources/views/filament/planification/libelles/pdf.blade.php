<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; }
        h1 { font-size: 15px; text-align: center; margin: 0 0 6px; }
        h3 { font-size: 11px; margin: 10px 0 2px; }
        .saut { page-break-before: always; }
    </style>
    @include('filament.suivi-evaluation.matrice._styles')
</head>
<body>
    <h1>TABLEAU DE REVUE DES LIBELLÉS — EXERCICE {{ $t['exercice']?->annee }}</h1>

    <table class="mx-table">
        <tr><th style="width:18%">CSP</th><td>{{ $t['csp']?->libelle ?? '—' }}</td></tr>
        <tr><th>PSP</th><td>{{ $t['psp']->libelle }}</td></tr>
        <tr><th>Objectif stratégique</th><td>{{ $t['psp']->objectif_strategique ?? '—' }}</td></tr>
    </table>
    <p class="mx-muted">Cellules surlignées ⚠ : libellé à revoir (détail dans les observations de chaque sous-programme).</p>

    @foreach ($t['sous_programmes'] as $bloc)
        <div @class(['saut' => !$loop->first])>
            @include('filament.planification.libelles._entete-sp', ['bloc' => $bloc])
            @include('filament.planification.libelles._table-sp', ['bloc' => $bloc])
            <h3>Observations sur les libellés</h3>
            @include('filament.planification.libelles._observations', ['bloc' => $bloc])
        </div>
    @endforeach

    <p class="mx-muted">Généré le {{ now()->format('d/m/Y à H:i') }} par {{ auth()->user()->name }}.</p>
</body>
</html>