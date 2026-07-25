{{-- resources/views/filament/pages/partials/budget-programme-soustotal-row.blade.php --}}
{{-- Attend : $sousTotal (tableau accumulé), $groupeLibelle --}}
<tr style="background:#dbeafe; font-weight:700; font-size:.75rem; border-top:2px solid #93c5fd; border-bottom:2px solid #93c5fd;">
    <td colspan="2" style="padding:.4rem .75rem; color:#1e3a5f;">Sous-total — {{ $groupeLibelle }}</td>

    <td style="padding:.4rem .5rem; text-align:right; border-left:1px solid #93c5fd;">{{ number_format($sousTotal['prev_n_2'] ?? 0, 0, ',', ' ') }}</td>
    <td style="padding:.4rem .5rem; text-align:right;">{{ number_format($sousTotal['real_n_2'] ?? 0, 0, ',', ' ') }}</td>
    <td style="padding:.4rem .5rem; text-align:center;">—</td>

    <td style="padding:.4rem .5rem; text-align:right; border-left:1px solid #93c5fd;">{{ number_format($sousTotal['prev_n_1'] ?? 0, 0, ',', ' ') }}</td>
    <td style="padding:.4rem .5rem; text-align:right;">{{ number_format($sousTotal['real_n_1'] ?? 0, 0, ',', ' ') }}</td>
    <td style="padding:.4rem .5rem; text-align:center;">—</td>

    <td style="padding:.4rem .5rem; text-align:right; border-left:1px solid #93c5fd;">{{ number_format($sousTotal['prev_n'] ?? 0, 0, ',', ' ') }}</td>
    <td style="padding:.4rem .5rem; text-align:right;">{{ number_format($sousTotal['real_n'] ?? 0, 0, ',', ' ') }}</td>
    <td style="padding:.4rem .5rem; text-align:center;">—</td>

    <td style="padding:.4rem .5rem; text-align:right; background:#fef9c3;">{{ number_format($sousTotal['prev_n1'] ?? 0, 0, ',', ' ') }}</td>
    <td style="padding:.4rem .5rem; text-align:right; background:#fef9c3;">{{ number_format($sousTotal['prev_n2'] ?? 0, 0, ',', ' ') }}</td>

    <td style="padding:.4rem .5rem; text-align:right; border-left:1px solid #93c5fd;">{{ number_format($sousTotal['total_n1_n2'] ?? 0, 0, ',', ' ') }}</td>
    <td style="padding:.4rem .5rem; text-align:right; color:#166534;">{{ number_format($sousTotal['total_n_n1_n2'] ?? 0, 0, ',', ' ') }}</td>
</tr>