<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; }
        h1 { font-size: 15px; text-align: center; margin: 0 0 6px; }
        h2 { font-size: 12px; margin: 10px 0 4px; border-bottom: 1px solid #c2410c; }
        .saut { page-break-before: always; }
    </style>
    @include('filament.suivi-evaluation.matrice._styles')
</head>
<body>
    <h1>MATRICE D'ARRIMAGE STRATÉGIQUE — EXERCICE {{ $m['exercice']?->annee }}</h1>

    <table class="mx-table">
        <tr><th style="width:18%">CSP</th><td>{{ $m['csp']?->libelle ?? '—' }}</td></tr>
        <tr><th>PSP</th><td>{{ $m['psp']->libelle }}</td></tr>
        <tr><th>Objectif stratégique</th><td>{{ $m['psp']->objectif_strategique ?? '—' }}</td></tr>
    </table>

    <h2>Synthèse par sous-programme</h2>
    @include('filament.suivi-evaluation.matrice._synthese', ['m' => $m])

    @foreach ($m['sous_programmes'] as $bloc)
        <div class="saut">
            @include('filament.suivi-evaluation.matrice._entete-sp', ['bloc' => $bloc])
            @include('filament.suivi-evaluation.matrice._table-sp', ['bloc' => $bloc])
        </div>
    @endforeach

    <p class="mx-muted">Généré le {{ now()->format('d/m/Y à H:i') }} par {{ auth()->user()->name }}.</p>
</body>
</html>