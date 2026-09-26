{{-- resources/views/exports/partials/disponibilites-montants.blade.php --}}
@php
    $f = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $p = fn ($v) => number_format((float) $v, 1, '.', '') . '%';
@endphp
<td class="num">{{ $f($c['budgetInitial']) }}</td>
<td class="num">{{ $f($c['virementsEntrants']) }}</td>
<td class="num">{{ $f($c['virementsSortants']) }}</td>
<td class="num">{{ $f($c['budgetRectifie']) }}</td>
<td class="num">{{ $f($c['engage']) }}</td>
<td class="num">{{ $f($c['ordonne']) }}</td>
<td class="num">{{ $f($c['paye']) }}</td>
<td class="num">{{ $f($c['taxesReversees']) }}</td>
<td class="num {{ $c['disponibleEng'] < 0 ? 'neg' : 'pos' }}">{{ $f($c['disponibleEng']) }}</td>
<td class="num {{ $c['disponibleOrd'] < 0 ? 'neg' : 'pos' }}">{{ $f($c['disponibleOrd']) }}</td>
<td class="num">{{ $p($c['tauxEngagement']) }}</td>
<td class="num">{{ $p($c['tauxOrdonnancement']) }}</td>
<td class="num">{{ $p($c['tauxExecution']) }}</td>