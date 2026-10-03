{{-- resources/views/pdf/partials/modifications-ligne.blade.php --}}
{{--
    Modifications d'une ligne budgétaire depuis sa dotation initiale :
    collectifs budgétaires (augmentation, réduction, virement) et virements budgétaires.
    Données : $historique = HistoriqueLigneBudgetaireService::synthese($ligne)
    Utilisation : @include('pdf.partials.modifications-ligne', ['historique' => $historique])
    Styles en ligne (compatibles DomPDF).
--}}
@if (isset($historique))
<table style="width:100%; border-collapse:collapse; margin:6px 0 10px 0; font-size:8pt;">
    <thead>
        <tr>
            <th colspan="6" style="border:1px solid #000; background:#d9e2f3; padding:4px; text-align:left; font-size:8.5pt;">
                MODIFICATIONS DE LA LIGNE (collectifs budgétaires et virements)
            </th>
        </tr>
        <tr>
            <th style="border:1px solid #000; background:#f0f0f0; padding:3px; width:10%;">Date</th>
            <th style="border:1px solid #000; background:#f0f0f0; padding:3px; width:12%;">Nature</th>
            <th style="border:1px solid #000; background:#f0f0f0; padding:3px; width:20%;">Origine</th>
            <th style="border:1px solid #000; background:#f0f0f0; padding:3px; width:12%;">Contrepartie</th>
            <th style="border:1px solid #000; background:#f0f0f0; padding:3px;">Motif</th>
            <th style="border:1px solid #000; background:#f0f0f0; padding:3px; width:14%; text-align:right;">Montant (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="5" style="border:1px solid #000; padding:3px;"><strong>Dotation initiale</strong></td>
            <td style="border:1px solid #000; padding:3px; text-align:right;">
                <strong>{{ number_format($historique['dotation_initiale'], 0, ',', ' ') }}</strong>
            </td>
        </tr>

        @forelse ($historique['modifications'] as $m)
            <tr>
                <td style="border:1px solid #000; padding:3px; text-align:center;">{{ $m['date']?->format('d/m/Y') ?? '—' }}</td>
                <td style="border:1px solid #000; padding:3px; color:{{ $m['montant'] >= 0 ? '#006100' : '#9c0006' }}; font-weight:bold;">
                    {{ $m['libelle_nature'] }}
                </td>
                <td style="border:1px solid #000; padding:3px;">{{ $m['origine'] }} {{ $m['reference'] }}</td>
                <td style="border:1px solid #000; padding:3px; text-align:center;">
                    @if ($m['contrepartie'])
                        {{ $m['montant'] >= 0 ? 'depuis' : 'vers' }} {{ $m['contrepartie'] }}
                    @else
                        —
                    @endif
                </td>
                <td style="border:1px solid #000; padding:3px;">{{ $m['motif'] ?? '—' }}</td>
                <td style="border:1px solid #000; padding:3px; text-align:right; color:{{ $m['montant'] >= 0 ? '#006100' : '#9c0006' }};">
                    {{ $m['montant'] >= 0 ? '+' : '' }}{{ number_format($m['montant'], 0, ',', ' ') }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="border:1px solid #000; padding:3px; text-align:center; font-style:italic; color:#555;">
                    Aucune modification : la ligne a conservé sa dotation initiale.
                </td>
            </tr>
        @endforelse

        <tr>
            <td colspan="5" style="border:1px solid #000; padding:3px; background:#f0f0f0; text-align:right;">
                <strong>DOTATION FINALE (budget rectifié)</strong>
            </td>
            <td style="border:1px solid #000; padding:3px; background:#f0f0f0; text-align:right;">
                <strong>{{ number_format($historique['dotation_finale'], 0, ',', ' ') }}</strong>
            </td>
        </tr>
    </tbody>
</table>
@endif