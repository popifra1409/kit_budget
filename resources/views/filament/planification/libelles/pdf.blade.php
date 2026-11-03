<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; }
        h1 { font-size: 15px; text-align: center; margin: 0 0 6px; }
        h3 { font-size: 11px; margin: 10px 0 2px; }
        .saut { page-break-before: always; }
        /* Tableau des libellés : regroupement rendu par les bordures (aucun rowspan, voir _table-sp-pdf). */
        .mx-table thead th.mx-action { background: #fff7ed; text-align: left; }
        .mx-debut td { border-top: 1.5px solid #6b7280; }
        .mx-suite { color: #9ca3af; font-style: italic; }
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

    @if ($t['sans_responsable']->isNotEmpty())
        <p class="mx-alerte">
            ⚠ {{ $t['sans_responsable']->count() }} sous-programme(s) sans responsable désigné :
            {{ $t['sans_responsable']->pluck('sp_texte')->implode(' ; ') }}.
            Sans responsable, un sous-programme n'est visible que par les comptes habilités
            « Tous les libellés » et ne peut pas être contrôlé par son responsable de sous-programme.
        </p>
    @endif

    @foreach ($t['sous_programmes'] as $bloc)
        <div @class(['saut' => !$loop->first])>
            @include('filament.planification.libelles._entete-sp', ['bloc' => $bloc])
            @include('filament.planification.libelles._table-sp-pdf', ['bloc' => $bloc])
            <h3>Observations sur les libellés</h3>
            @include('filament.planification.libelles._observations', ['bloc' => $bloc])
        </div>
    @endforeach

    <p class="mx-muted">Généré le {{ now()->format('d/m/Y à H:i') }} par {{ auth()->user()->name }}.</p>
</body>
</html>