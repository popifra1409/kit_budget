{{-- resources/views/pdf/partials/groupes-virements.blade.php --}}
{{--
    Virements à cellules fusionnées (format des résolutions du CA) : de chaque côté, les lignes consécutives
    d'un même compte sont fusionnées, avec le total de leurs montants.

    ⚠️ DomPDF gère mal une cellule fusionnée coupée par un saut de page (colonnes écrasées sur la page
    suivante). Chaque groupe numéroté est donc un petit tableau indépendant, aux largeurs identiques,
    qui n'est JAMAIS coupé entre deux pages : s'il ne tient pas en bas de page, il passe entier à la suivante.

    Paramètres : $groupes (['lignes' => …, 'total' => …]), $f, $b, $avecActe (bool), $titre (string).
--}}
@php
    $lib = $avecActe ? 16 : 23.5;
    $largeurs = array_merge([3], [5.5, 3.5, $lib, 8, 8], [5.5, 3.5, $lib, 8, 8], $avecActe ? [15] : []);

    // Découpage des lignes en groupes numérotés (une ligne avec num_rs > 0 ouvre un groupe)
    $blocs = [];
    foreach ($groupes['lignes'] as $ligne) {
        if (($ligne['num_rs'] ?? 0) > 0 || empty($blocs)) {
            $blocs[] = [];
        }
        $blocs[count($blocs) - 1][] = $ligne;
    }
@endphp

{{-- En-tête --}}
<table style="table-layout:fixed;width:100%;">
    <tr style="height:0;">
        @foreach ($largeurs as $l)<td style="width:{{ $l }}%;padding:0;border:none;height:0;"></td>@endforeach
    </tr>
    <tr>
        <th rowspan="2" style="{{ $b }}">N°</th>
        <th colspan="5" style="{{ $b }}">DÉBIT</th>
        <th colspan="5" style="{{ $b }}">CRÉDIT</th>
        @if ($avecActe)<th rowspan="2" style="{{ $b }}">Acte / sous-programmes</th>@endif
    </tr>
    <tr>
        <th style="{{ $b }}">Imputation</th><th style="{{ $b }}">LR/MN</th><th style="{{ $b }}">Libellé</th><th style="{{ $b }}">AE</th><th style="{{ $b }}">CP</th>
        <th style="{{ $b }}">Imputation</th><th style="{{ $b }}">LR/MN</th><th style="{{ $b }}">Libellé</th><th style="{{ $b }}">AE</th><th style="{{ $b }}">CP</th>
    </tr>
</table>

{{-- Un tableau insécable par groupe --}}
@foreach ($blocs as $bloc)
    <div style="page-break-inside:avoid;">
        <table style="table-layout:fixed;width:100%;margin-top:-1px;">
            <tr style="height:0;">
                @foreach ($largeurs as $l)<td style="width:{{ $l }}%;padding:0;border:none;height:0;"></td>@endforeach
            </tr>
            @foreach ($bloc as $m)
                <tr>
                    @if (($m['num_rs'] ?? 0) > 0)
                        <td rowspan="{{ $m['num_rs'] }}" style="{{ $b }}" class="centre">{{ str_pad($m['num'], 2, '0', STR_PAD_LEFT) }}</td>
                    @endif

                    @foreach (['debit', 'credit'] as $cote)
                        @if ($m["{$cote}_rs"] > 0)
                            @php $rs = $m["{$cote}_rs"]; @endphp
                            <td rowspan="{{ $rs }}" style="{{ $b }}" class="centre">{{ $m["{$cote}_code"] }}</td>
                            <td rowspan="{{ $rs }}" style="{{ $b }}" class="centre">{{ $m["{$cote}_lrmn"] }}</td>
                            <td rowspan="{{ $rs }}" style="{{ $b }}" class="lib">{!! $rs > 1 ? '<strong>' . e($m["{$cote}_libelle"]) . '</strong>' : e($m["{$cote}_libelle"]) !!}</td>
                            <td rowspan="{{ $rs }}" style="{{ $b }}" class="num">{{ $f($m["{$cote}_total"]) }}</td>
                            <td rowspan="{{ $rs }}" style="{{ $b }}" class="num">{{ $f($m["{$cote}_total"]) }}</td>
                        @endif
                    @endforeach

                    @if ($avecActe)
                        <td style="{{ $b }}font-size:6.3pt;">{{ $m['numero'] ?? '' }}{{ !empty($m['reference']) ? ' — ' . $m['reference'] : '' }}<br>{{ $m['sp_source']?->code ?? '?' }} → {{ $m['sp_destination']?->code ?? '?' }}</td>
                    @endif
                </tr>
            @endforeach
        </table>
    </div>
@endforeach

{{-- Total --}}
<table style="table-layout:fixed;width:100%;margin-top:-1px;">
    <tr style="height:0;">
        @foreach ($largeurs as $l)<td style="width:{{ $l }}%;padding:0;border:none;height:0;"></td>@endforeach
    </tr>
    <tr class="total">
        <td colspan="4" style="{{ $b }}">TOTAL {{ $titre }}</td>
        <td style="{{ $b }}" class="num">{{ $f($groupes['total']) }}</td>
        <td style="{{ $b }}" class="num">{{ $f($groupes['total']) }}</td>
        <td colspan="3" style="{{ $b }}">TOTAL</td>
        <td style="{{ $b }}" class="num">{{ $f($groupes['total']) }}</td>
        <td style="{{ $b }}" class="num">{{ $f($groupes['total']) }}</td>
        @if ($avecActe)<td style="{{ $b }}"></td>@endif
    </tr>
</table>