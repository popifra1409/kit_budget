{{-- resources/views/pdf/partials/groupes-virements.blade.php --}}
{{--
    Virements regroupés par lignes communes, au format des résolutions du CA (cellules fusionnées) :
      n1 : plusieurs débits → un crédit (crédit fusionné, avec le total) ;
      1n : un débit → plusieurs crédits (débit fusionné, montants en face de chaque crédit) ;
      nn : une ligne par mouvement.
    Paramètres : $groupes, $f (formatage), $b (style de cellule), $avecActe (bool), $titre (string).
--}}
@php $num = 0; @endphp
<table>
    <thead>
        <tr>
            <th rowspan="2" style="{{ $b }}width:3%;">N°</th>
            <th colspan="5" style="{{ $b }}">DÉBIT</th>
            <th colspan="5" style="{{ $b }}">CRÉDIT</th>
            @if ($avecActe)<th rowspan="2" style="{{ $b }}width:10%;">Acte / sous-programmes</th>@endif
        </tr>
        <tr>
            <th style="{{ $b }}">Imputation</th><th style="{{ $b }}">LR/MN</th><th style="{{ $b }}">Libellé</th><th style="{{ $b }}">AE</th><th style="{{ $b }}">CP</th>
            <th style="{{ $b }}">Imputation</th><th style="{{ $b }}">LR/MN</th><th style="{{ $b }}">Libellé</th><th style="{{ $b }}">AE</th><th style="{{ $b }}">CP</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($groupes as $g)
            @php $k = $g['mouvements']->count(); $num++; @endphp
            @foreach ($g['mouvements'] as $i => $m)
                <tr>
                    @if ($i === 0)<td rowspan="{{ $k }}" style="{{ $b }}" class="centre">{{ str_pad($num, 2, '0', STR_PAD_LEFT) }}</td>@endif

                    {{-- DÉBIT --}}
                    @if ($g['mode'] === '1n')
                        @if ($i === 0)
                            <td rowspan="{{ $k }}" style="{{ $b }}" class="centre">{{ $m['debit_code'] }}</td>
                            <td rowspan="{{ $k }}" style="{{ $b }}" class="centre">{{ $m['debit_lrmn'] }}</td>
                            <td rowspan="{{ $k }}" style="{{ $b }}"><strong>{{ $m['debit_libelle'] }}</strong></td>
                        @endif
                    @else
                        <td style="{{ $b }}" class="centre">{{ $m['debit_code'] }}</td>
                        <td style="{{ $b }}" class="centre">{{ $m['debit_lrmn'] }}</td>
                        <td style="{{ $b }}">{{ $m['debit_libelle'] }}</td>
                    @endif
                    <td style="{{ $b }}" class="num">{{ $f($m['montant']) }}</td>
                    <td style="{{ $b }}" class="num">{{ $f($m['montant']) }}</td>

                    {{-- CRÉDIT --}}
                    @if ($g['mode'] === 'n1')
                        @if ($i === 0)
                            <td rowspan="{{ $k }}" style="{{ $b }}" class="centre">{{ $m['credit_code'] }}</td>
                            <td rowspan="{{ $k }}" style="{{ $b }}" class="centre">{{ $m['credit_lrmn'] }}</td>
                            <td rowspan="{{ $k }}" style="{{ $b }}">{{ $m['credit_libelle'] }}</td>
                            <td rowspan="{{ $k }}" style="{{ $b }}" class="num">{{ $f($g['total']) }}</td>
                            <td rowspan="{{ $k }}" style="{{ $b }}" class="num">{{ $f($g['total']) }}</td>
                        @endif
                    @else
                        <td style="{{ $b }}" class="centre">{{ $m['credit_code'] }}</td>
                        <td style="{{ $b }}" class="centre">{{ $m['credit_lrmn'] }}</td>
                        <td style="{{ $b }}">{{ $m['credit_libelle'] }}</td>
                        <td style="{{ $b }}" class="num">{{ $f($m['montant']) }}</td>
                        <td style="{{ $b }}" class="num">{{ $f($m['montant']) }}</td>
                    @endif

                    @if ($avecActe)
                        <td style="{{ $b }}font-size:6.3pt;">{{ $m['numero'] ?? '' }}{{ !empty($m['reference']) ? ' — ' . $m['reference'] : '' }}<br>{{ $m['sp_source']?->code ?? '?' }} → {{ $m['sp_destination']?->code ?? '?' }}</td>
                    @endif
                </tr>
            @endforeach
        @endforeach
        @php $total = $groupes->sum('total'); @endphp
        <tr class="total">
            <td colspan="4" style="{{ $b }}">TOTAL {{ $titre }}</td>
            <td style="{{ $b }}" class="num">{{ $f($total) }}</td>
            <td style="{{ $b }}" class="num">{{ $f($total) }}</td>
            <td colspan="3" style="{{ $b }}">TOTAL</td>
            <td style="{{ $b }}" class="num">{{ $f($total) }}</td>
            <td style="{{ $b }}" class="num">{{ $f($total) }}</td>
            @if ($avecActe)<td style="{{ $b }}"></td>@endif
        </tr>
    </tbody>
</table>