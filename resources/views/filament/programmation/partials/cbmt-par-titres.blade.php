{{-- resources/views/filament/programmation/partials/cbmt-par-titres.blade.php --}}
{{--
    Prévision à moyen terme par titres, avec le détail des lignes de chaque titre.
    Paramètres : $cbmt (CbmtExercice), $nature ('ressource' | 'depense').
    Partagé par l'écran de rapport et le PDF (styles en ligne, compatibles DomPDF).
--}}
@php
    $n = $cbmt->anneeReference();
    $groupes = $cbmt->syntheseParTitres($nature);
    $depense = $nature === 'depense';
    $colonnes = array_filter([
        'prevision_n_initiale'     => "Prév. {$n} initiale",
        'montant_n'                => "Prév. {$n} actualisée",
        'realisation_n'            => $depense ? "Réal. {$n} engagé" : "Réal. {$n} recouvré",
        'realisation_n_ordonnance' => $depense ? "Réal. {$n} ordonnancé" : null,
        'montant_n_plus_1'         => (string) ($n + 1),
        'montant_n_plus_2'         => (string) ($n + 2),
        'montant_n_plus_3'         => (string) ($n + 3),
    ]);
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $b = 'border:1px solid #999;padding:3px 4px;';
@endphp

<table style="width:100%;border-collapse:collapse;font-size:8.5pt;">
    <thead>
        <tr style="background:#e8edf5;">
            <th style="{{ $b }}text-align:left;width:8%;">Compte</th>
            <th style="{{ $b }}text-align:left;">Libellé</th>
            <th style="{{ $b }}text-align:center;width:4%;">LR/MN</th>
            @foreach ($colonnes as $libelle)
                <th style="{{ $b }}text-align:right;">{{ $libelle }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($groupes as $g)
            <tr style="background:#f3f4f6;font-weight:bold;">
                <td colspan="3" style="{{ $b }}">{{ $g['libelle'] }}</td>
                @foreach (array_keys($colonnes) as $c)
                    <td style="{{ $b }}text-align:right;">{{ $fmt($g['totaux'][$c]) }}</td>
                @endforeach
            </tr>
            @foreach ($g['lignes'] as $l)
                <tr>
                    <td style="{{ $b }}">{{ $l->code }}</td>
                    <td style="{{ $b }}">{{ $l->libelle }}</td>
                    <td style="{{ $b }}text-align:center;{{ $l->type_ligne === 'MN' ? 'color:#b45309;font-weight:bold;' : '' }}">{{ $l->type_ligne }}</td>
                    @foreach (array_keys($colonnes) as $c)
                        <td style="{{ $b }}text-align:right;">{{ $fmt($l->{$c}) }}</td>
                    @endforeach
                </tr>
            @endforeach
        @empty
            <tr>
                <td colspan="{{ 3 + count($colonnes) }}" style="{{ $b }}text-align:center;font-style:italic;">
                    Aucune ligne : générez le CBMT à partir de l'exercice {{ $n }}.
                </td>
            </tr>
        @endforelse

        <tr style="background:#d9e2f3;font-weight:bold;">
            <td colspan="3" style="{{ $b }}">TOTAL {{ $depense ? 'DÉPENSES' : 'RESSOURCES' }}</td>
            @foreach (array_keys($colonnes) as $c)
                <td style="{{ $b }}text-align:right;">{{ $fmt($groupes->sum(fn ($g) => $g['totaux'][$c])) }}</td>
            @endforeach
        </tr>
    </tbody>
</table>
