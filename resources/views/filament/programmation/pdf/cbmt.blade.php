<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 12mm 16mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111; }
        h1 { font-size: 13pt; margin: 0 0 2px 0; }
        h2 { font-size: 10.5pt; margin: 14px 0 5px 0; }
        .entete { border-bottom: 2px solid #1f3a68; padding-bottom: 6px; margin-bottom: 8px; }
        .sous-titre { color: #555; font-size: 8.5pt; }
        .bloc { page-break-inside: auto; }
        .pied { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #777; text-align: center; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <div class="pied">
        {{ $structure?->sigle ?? $structure?->nom_structure }} — CBMT {{ $cbmt->numero }} — édité le {{ now()->format('d/m/Y à H:i') }}
    </div>

    <div class="entete">
        <h1>Cadre budgétaire à moyen terme {{ $cbmt->anneeReference() + 1 }}–{{ $cbmt->anneeReference() + 3 }}</h1>
        <div class="sous-titre">
            {{ $structure?->nom_complet ?? $structure?->nom_structure }}
            · CBMT {{ $cbmt->numero }}
            · Exercice de référence {{ $cbmt->anneeReference() }}
            @if ($cbmt->planStrategiqueEp) · PSP : {{ $cbmt->planStrategiqueEp->libelle }} @endif
            · Statut : {{ $cbmt->statut }}
        </div>
    </div>

    <div class="bloc">
        <h2>Prévision à moyen terme des ressources par titres</h2>
        @include('filament.programmation.partials.cbmt-par-titres', ['cbmt' => $cbmt, 'nature' => 'ressource'])
    </div>

    <div class="bloc">
        <h2>Prévision à moyen terme des dépenses par titres</h2>
        @include('filament.programmation.partials.cbmt-par-titres', ['cbmt' => $cbmt, 'nature' => 'depense'])
    </div>

    <div class="bloc">
        <h2>Équilibre ressources − dépenses</h2>
        @include('filament.programmation.partials.cbmt-equilibre', ['cbmt' => $cbmt])
    </div>

    @if (filled($cbmt->hypotheses_ressources) || filled($cbmt->commentaire_soutenabilite))
        <div class="bloc">
            <h2>Hypothèses et soutenabilité</h2>
            @if (filled($cbmt->hypotheses_ressources))
                <p><strong>Hypothèses de projection des ressources :</strong><br>{!! nl2br(e($cbmt->hypotheses_ressources)) !!}</p>
            @endif
            @if (filled($cbmt->commentaire_soutenabilite))
                <p><strong>Soutenabilité du cadrage :</strong><br>{!! nl2br(e($cbmt->commentaire_soutenabilite)) !!}</p>
            @endif
        </div>
    @endif
</body>
</html>