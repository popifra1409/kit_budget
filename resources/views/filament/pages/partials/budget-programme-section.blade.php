{{-- resources/views/filament/pages/partials/budget-programme-section.blade.php --}}
{{-- Attend : $section (array avec 'lignes' et 'total_general'), $annees, $titre, $icone --}}
@php
    $lignes = $section['lignes'] ?? [];
    $total  = $section['total_general'] ?? [];
    $n_2 = $annees['n_2'] ?? ''; $n_1 = $annees['n_1'] ?? '';
    $n   = $annees['n']   ?? ''; $n1  = $annees['n1']  ?? ''; $n2 = $annees['n2'] ?? '';
@endphp

<div class="space-y-2">
    <h2 class="text-sm font-bold text-gray-800" style="font-size:.95rem;">{{ $icone }} {{ $titre }}</h2>

    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
        <table style="width:100%; font-size:.75rem; border-collapse:collapse; font-family:Arial,sans-serif;">
            <thead>
                <tr style="background:#1e3a5f; color:white;">
                    <th style="padding:.5rem .75rem; text-align:left; min-width:90px;">Imputation</th>
                    <th style="padding:.5rem .75rem; text-align:left; min-width:280px;">Rubrique</th>
                    <th colspan="3" style="padding:.5rem; text-align:center; background:#1e3a8a; border-left:2px solid #93c5fd;">{{ $n_2 }}</th>
                    <th colspan="3" style="padding:.5rem; text-align:center; background:#1e40af; border-left:2px solid #93c5fd;">{{ $n_1 }}</th>
                    <th colspan="3" style="padding:.5rem; text-align:center; background:#1d4ed8; border-left:2px solid #93c5fd;">{{ $n }} (en cours)</th>
                    <th style="padding:.5rem; text-align:center; background:#854d0e; border-left:2px solid #fbbf24; min-width:120px;">Prévis. {{ $n1 }}</th>
                    <th style="padding:.5rem; text-align:center; background:#854d0e; min-width:120px;">Prévis. {{ $n2 }}</th>
                    <th style="padding:.5rem; text-align:center; background:#1e3a5f; border-left:2px solid #93c5fd; min-width:130px;">Total {{ $n1 }}-{{ $n2 }}</th>
                    <th style="padding:.5rem; text-align:center; background:#166534; min-width:140px;">Total {{ $n }}-{{ $n1 }}-{{ $n2 }}</th>
                </tr>
                <tr style="background:#1e3a5f; color:#bfdbfe; font-size:.7rem;">
                    <th colspan="2"></th>
                    <th style="padding:.3rem; text-align:right;">Prévisions</th>
                    <th style="padding:.3rem; text-align:right;">Réalisations</th>
                    <th style="padding:.3rem; text-align:center;">%</th>
                    <th style="padding:.3rem; text-align:right; border-left:1px solid #3b82f6;">Prévisions</th>
                    <th style="padding:.3rem; text-align:right;">Réalisations</th>
                    <th style="padding:.3rem; text-align:center;">%</th>
                    <th style="padding:.3rem; text-align:right; border-left:1px solid #3b82f6;">Prévisions</th>
                    <th style="padding:.3rem; text-align:right;">Réalisations</th>
                    <th style="padding:.3rem; text-align:center;">%</th>
                    <th colspan="2" style="padding:.3rem; text-align:center; border-left:2px solid #fbbf24; color:#fde68a;">🟡 À saisir</th>
                    <th colspan="2"></th>
                </tr>
            </thead>

            <tbody>
            @php $currentGroupe = null; $currentChapter = null; $rowNum = 0; @endphp
            @forelse($lignes as $ligne)
                @php
                    $chapter = substr($ligne['imputation'], 0, 3);
                    $isArticle = strlen($ligne['imputation']) > 3;
                    $rowNum++;
                    $rowBg = $rowNum % 2 === 0 ? '#f8fafc' : 'white';
                @endphp

                @if($isArticle && ($ligne['groupe_libelle'] ?? null) !== $currentGroupe)
                    @php
                        $currentGroupe = $ligne['groupe_libelle'] ?? 'Non classées / Hors groupe';
                        $currentChapter = null;
                        $estHorsGroupe = empty($ligne['groupe_id']);
                    @endphp
                    <tr style="background:{{ $estHorsGroupe ? '#fefce8' : '#0f172a' }}; font-weight:800; font-size:.78rem;">
                        <td colspan="15" style="padding:.55rem .75rem; color:{{ $estHorsGroupe ? '#854d0e' : 'white' }}; text-transform:uppercase; letter-spacing:.03em;">
                            {{ $estHorsGroupe ? '⚠️ ' : '📁 ' }}{{ $currentGroupe }}
                        </td>
                    </tr>
                @endif

                @if($isArticle && $chapter !== $currentChapter)
                    @php
                        $currentChapter = $chapter;
                        $chapLibelle = \App\Models\NomenclatureBudgetaire::where('code', $chapter)->value('libelle') ?? "Chapitre {$chapter}";
                    @endphp
                    <tr style="background:#e0e7ff; font-weight:700; font-size:.73rem;">
                        <td style="padding:.4rem .75rem; color:#1e3a5f;">{{ $chapter }}</td>
                        <td colspan="14" style="padding:.4rem .75rem; color:#1e3a5f; text-transform:uppercase;">{{ $chapLibelle }}</td>
                    </tr>
                @endif

                <tr style="background:{{ $rowBg }}; border-bottom:1px solid #e5e7eb;">
                    <td style="padding:.35rem .75rem; font-family:monospace; color:#374151;">{{ $ligne['imputation'] }}</td>
                    <td style="padding:.35rem .75rem; color:#374151;">{{ $ligne['rubrique'] }}</td>

                    <td style="padding:.35rem .5rem; text-align:right; border-left:1px solid #e5e7eb;">
                        @if(($ligne['prev_n_2'] ?? 0) > 0) {{ number_format($ligne['prev_n_2'], 0, ',', ' ') }} @else — @endif
                    </td>
                    <td style="padding:.35rem .5rem; text-align:right;">
                        @if(($ligne['real_n_2'] ?? 0) > 0) {{ number_format($ligne['real_n_2'], 0, ',', ' ') }} @else — @endif
                    </td>
                    <td style="padding:.35rem .5rem; text-align:center; color:{{ ($ligne['taux_n_2'] ?? 0) >= 0.9 ? '#166534' : '#991b1b' }};">
                        {{ isset($ligne['taux_n_2']) ? number_format($ligne['taux_n_2'] * 100, 1) . '%' : '—' }}
                    </td>

                    <td style="padding:.35rem .5rem; text-align:right; border-left:1px solid #bfdbfe;">
                        @if(($ligne['prev_n_1'] ?? 0) > 0) {{ number_format($ligne['prev_n_1'], 0, ',', ' ') }} @else — @endif
                    </td>
                    <td style="padding:.35rem .5rem; text-align:right;">
                        @if(($ligne['real_n_1'] ?? 0) > 0) {{ number_format($ligne['real_n_1'], 0, ',', ' ') }} @else — @endif
                    </td>
                    <td style="padding:.35rem .5rem; text-align:center; color:{{ ($ligne['taux_n_1'] ?? 0) >= 0.9 ? '#166534' : '#991b1b' }};">
                        {{ isset($ligne['taux_n_1']) ? number_format($ligne['taux_n_1'] * 100, 1) . '%' : '—' }}
                    </td>

                    <td style="padding:.35rem .5rem; text-align:right; border-left:1px solid #bfdbfe;">
                        @if(($ligne['prev_n'] ?? 0) > 0) {{ number_format($ligne['prev_n'], 0, ',', ' ') }} @else — @endif
                    </td>
                    <td style="padding:.35rem .5rem; text-align:right;">
                        @if(($ligne['real_n'] ?? 0) > 0) {{ number_format($ligne['real_n'], 0, ',', ' ') }} @else — @endif
                    </td>
                    <td style="padding:.35rem .5rem; text-align:center; color:{{ ($ligne['taux_n'] ?? 0) >= 0.9 ? '#166534' : '#991b1b' }};">
                        {{ isset($ligne['taux_n']) ? number_format($ligne['taux_n'] * 100, 1) . '%' : '—' }}
                    </td>

                    <td style="padding:.35rem .5rem; text-align:right; background:#fefce8; border-left:2px solid #fbbf24;">
                        @if(($ligne['prev_n1'] ?? 0) > 0)
                            <span style="color:#854d0e; font-weight:600;">{{ number_format($ligne['prev_n1'], 0, ',', ' ') }}</span>
                        @else
                            <span style="color:#d97706;">—</span>
                        @endif
                    </td>
                    <td style="padding:.35rem .5rem; text-align:right; background:#fefce8;">
                        @if(($ligne['prev_n2'] ?? 0) > 0)
                            <span style="color:#854d0e; font-weight:600;">{{ number_format($ligne['prev_n2'], 0, ',', ' ') }}</span>
                        @else
                            <span style="color:#d97706;">—</span>
                        @endif
                    </td>

                    <td style="padding:.35rem .5rem; text-align:right; border-left:1px solid #bfdbfe; font-weight:600;">
                        @if(($ligne['total_n1_n2'] ?? 0) > 0) {{ number_format($ligne['total_n1_n2'], 0, ',', ' ') }} @else — @endif
                    </td>
                    <td style="padding:.35rem .5rem; text-align:right; color:#166534; font-weight:700;">
                        @if(($ligne['total_n_n1_n2'] ?? 0) > 0) {{ number_format($ligne['total_n_n1_n2'], 0, ',', ' ') }} @else — @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="15" style="padding:1rem; text-align:center; color:#94a3b8; font-style:italic;">
                        Aucune ligne de nomenclature de ce type.
                    </td>
                </tr>
            @endforelse
            </tbody>

            @if(!empty($total))
            <tfoot>
                <tr style="background:#1e3a5f; color:white; font-weight:700; font-size:.78rem;">
                    <td colspan="2" style="padding:.6rem .75rem;">TOTAL {{ strtoupper($titre) }}</td>
                    <td style="padding:.6rem .5rem; text-align:right;">{{ number_format($total['prev_n_2'] ?? 0, 0, ',', ' ') }}</td>
                    <td style="padding:.6rem .5rem; text-align:right;">{{ number_format($total['real_n_2'] ?? 0, 0, ',', ' ') }}</td>
                    <td style="padding:.6rem .5rem; text-align:center;">—</td>
                    <td style="padding:.6rem .5rem; text-align:right;">{{ number_format($total['prev_n_1'] ?? 0, 0, ',', ' ') }}</td>
                    <td style="padding:.6rem .5rem; text-align:right;">{{ number_format($total['real_n_1'] ?? 0, 0, ',', ' ') }}</td>
                    <td style="padding:.6rem .5rem; text-align:center;">—</td>
                    <td style="padding:.6rem .5rem; text-align:right;">{{ number_format($total['prev_n'] ?? 0, 0, ',', ' ') }}</td>
                    <td style="padding:.6rem .5rem; text-align:right;">{{ number_format($total['real_n'] ?? 0, 0, ',', ' ') }}</td>
                    <td style="padding:.6rem .5rem; text-align:center;">—</td>
                    <td style="padding:.6rem .5rem; text-align:right; background:#854d0e;">{{ number_format($total['prev_n1'] ?? 0, 0, ',', ' ') }}</td>
                    <td style="padding:.6rem .5rem; text-align:right; background:#854d0e;">{{ number_format($total['prev_n2'] ?? 0, 0, ',', ' ') }}</td>
                    <td style="padding:.6rem .5rem; text-align:right;">{{ number_format($total['total_n1_n2'] ?? 0, 0, ',', ' ') }}</td>
                    <td style="padding:.6rem .5rem; text-align:right; background:#166534;">{{ number_format($total['total_n_n1_n2'] ?? 0, 0, ',', ' ') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>