<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cadre logique — {{ $rapport->psp->libelle }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.5px; }
        h1 { font-size: 13px; text-align: center; margin-bottom: 2px; }
        h2 { font-size: 10px; margin: 10px 0 4px; text-decoration: underline; }
        p.chapeau { text-align: center; font-size: 8.5px; color: #444; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #666; padding: 3px; text-align: left; vertical-align: top; }
        th { background-color: #e8e8e8; text-align: center; font-weight: bold; }
        td.bloc { font-weight: bold; }
        td.center { text-align: center; }
        .italic { font-style: italic; color: #777; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @php
        $psp = $rapport->psp;
        $annees = $rapport->annees();
        $matrices = $rapport->matrices();
        $avecCadre = ! $rapport->filtreUnSousProgramme() && $matrices->isNotEmpty();
    @endphp

    <h1>Proposition de cadre stratégique de performance {{ $annees[2] }}-{{ $annees[4] }}</h1>
    <p class="chapeau">
        {{ $psp->libelle }} — exercice de référence {{ $rapport->anneeReference() }}<br>
        Réalisation {{ $annees[0] }} · Estimation {{ $annees[1] }} · Valeurs cibles {{ $annees[2] }}, {{ $annees[3] }} et {{ $annees[4] }}
    </p>

    @if ($avecCadre)
        <h2>Tableau 1 : Cadre Stratégique des Sous-programmes</h2>
        @include('filament.planification.pdf._cadre-logique-table', [
            'premiereColonne' => 'Sous-programmes',
            'blocs' => $rapport->cadreStrategique()->all(),
            'annees' => $annees,
        ])
    @endif

    @foreach ($matrices as $matrice)
        @if ($avecCadre || ! $loop->first)
            <div class="page-break"></div>
        @endif

        <h2>Matrice du Cadre Logique — {{ $rapport->titreSousProgramme($matrice['sous_programme']) }}</h2>

        @include('filament.planification.pdf._cadre-logique-table', [
            'premiereColonne' => 'Sous-programme/Actions',
            'blocs' => array_merge([$matrice['bloc']], $matrice['actions']->all()),
            'annees' => $annees,
        ])
    @endforeach

    @if ($matrices->isEmpty())
        <p class="center italic" style="text-align:center">
            Aucun sous-programme rattaché à ce plan stratégique.
        </p>
    @endif
</body>
</html>
