<x-filament-panels::page>
@php
    $annees = $donnees['annees'] ?? [];
    $n_2 = $annees['n_2'] ?? ''; $n1 = $annees['n1'] ?? ''; $n2 = $annees['n2'] ?? '';
@endphp

<div class="space-y-6">

    {{-- ── Bandeau info ──────────────────────────────────── --}}
    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:.5rem; padding:.75rem 1rem; font-size:.82rem; color:#1e40af;">
        📊 <strong>Budget Programme Triennal {{ $n_2 }}-{{ $n2 }}</strong>
        — Dépenses et Recettes, groupées par groupe de nomenclature
        &nbsp;|&nbsp;
        🟡 Colonnes jaunes = prévisions à saisir ({{ $n1 }}, {{ $n2 }})
    </div>

    {{-- ── Section DÉPENSES ─────────────────────────────────── --}}
    @include('filament.pages.partials.budget-programme-section', [
        'section' => $donnees['depenses'] ?? ['lignes' => [], 'total_general' => []],
        'annees'  => $annees,
        'titre'   => 'Dépenses',
        'icone'   => '💸',
    ])

    {{-- ── Section RECETTES ─────────────────────────────────── --}}
    @include('filament.pages.partials.budget-programme-section', [
        'section' => $donnees['recettes'] ?? ['lignes' => [], 'total_general' => []],
        'annees'  => $annees,
        'titre'   => 'Recettes',
        'icone'   => '💰',
    ])

    <p style="font-size:.7rem; color:#94a3b8; text-align:right;">
        🟡 Prévisions N+1 ({{ $n1 }}) et N+2 ({{ $n2 }}) à saisir via "Saisir prévisions" — les réalisations sont calculées depuis les engagements/recouvrements du logiciel.
    </p>

</div>
</x-filament-panels::page>