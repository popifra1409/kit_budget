{{-- resources/views/filament/programmation/partials/cbmt-equilibre.blade.php --}}
{{-- Équilibre ressources − dépenses par année. Paramètre : $cbmt. Styles en ligne (DomPDF). --}}
@php
    $n = $cbmt->anneeReference();
    $test = $cbmt->getTestSoutenabilite();
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $b = 'border:1px solid #999;padding:3px 4px;';
@endphp
<table style="width:100%;border-collapse:collapse;font-size:8.5pt;">
    <thead>
        <tr style="background:#e8edf5;">
            <th style="{{ $b }}text-align:left;">Année</th>
            <th style="{{ $b }}text-align:right;">Ressources</th>
            <th style="{{ $b }}text-align:right;">Dépenses</th>
            <th style="{{ $b }}text-align:right;">Écart</th>
        </tr>
    </thead>
    <tbody>
        @foreach (['montant_n_plus_1' => 1, 'montant_n_plus_2' => 2, 'montant_n_plus_3' => 3] as $col => $d)
            @php $e = (float) $test[$col]['ecart']; @endphp
            <tr>
                <td style="{{ $b }}">{{ $n + $d }}</td>
                <td style="{{ $b }}text-align:right;">{{ $fmt($test[$col]['ressources']) }}</td>
                <td style="{{ $b }}text-align:right;">{{ $fmt($test[$col]['depenses']) }}</td>
                <td style="{{ $b }}text-align:right;font-weight:bold;color:{{ abs($e) < 1 ? '#166534' : '#b91c1c' }};">
                    {{ abs($e) < 1 ? 'Équilibré' : $fmt($e) }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
