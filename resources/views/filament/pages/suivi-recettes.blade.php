{{-- resources/views/filament/pages/suivi-recettes.blade.php --}}
<x-filament-panels::page>

    @php
        // $data = $this->getData();
        // $totaux = $this->getTotauxParMois();
        // $exercices = $this->getExercices();
        // $previsions = $this->getPrevisions();
        // $exercice = $this->exerciceId ? \App\Models\Exercice::find($this->exerciceId) : null;
        // $moisLabels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        // $moisActuel = now()->month;
        // $anneeActuelle = now()->year;

        // $totalPrevu = collect($data)->sum('cumul_prevu');
        // $totalRecouvre = collect($data)->sum('cumul_recouvre');
        // $totalEcart = $totalRecouvre - $totalPrevu;
        // $tauxGlobal = $totalPrevu > 0 ? round(($totalRecouvre / $totalPrevu) * 100, 1) : 0;
    @endphp

    <style>
        /* ── Variables ──────────────────────────────────────────── */
        :root {
            --clr-prevu: #1e40af;
            --clr-reel: #166534;
            --clr-taux-ok: #166534;
            --clr-taux-warn: #854d0e;
            --clr-taux-bad: #991b1b;
            --clr-futur: #94a3b8;
        }

        /* ── Layout ─────────────────────────────────────────────── */
        .sr-wrap {
            font-family: inherit;
        }

        /* ── KPI Cards ──────────────────────────────────────────── */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .75rem;
            margin-bottom: 1.25rem;
        }

        @media(max-width:768px) {
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .kpi-card {
            background: var(--color-background-secondary);
            border: 1px solid var(--color-border-tertiary);
            border-radius: var(--border-radius-lg);
            padding: 1rem 1.25rem;
        }

        .kpi-label {
            font-size: .68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--color-text-secondary);
            margin-bottom: .3rem;
        }

        .kpi-value {
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }

        .kpi-sub {
            font-size: .72rem;
            color: var(--color-text-secondary);
            margin-top: .2rem;
        }

        /* ── Tableau ────────────────────────────────────────────── */
        .sr-outer {
            overflow-x: auto;
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--color-border-tertiary);
        }

        .sr-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .74rem;
            white-space: nowrap;
            min-width: 1400px;
        }

        /* En-têtes */
        .sr-table thead tr:first-child th {
            background: var(--color-background-secondary);
            padding: 8px 6px;
            font-weight: 700;
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--color-text-secondary);
            border-bottom: 1px solid var(--color-border-tertiary);
            text-align: center;
        }

        .sr-table thead tr:first-child th:first-child {
            text-align: left;
        }

        .sr-table thead tr:last-child th {
            background: var(--color-background-secondary);
            padding: 4px 6px;
            font-size: .64rem;
            color: var(--color-text-tertiary);
            border-bottom: 2px solid var(--color-border-secondary);
            text-align: right;
        }

        .sr-table thead tr:last-child th.th-prevu {
            color: var(--clr-prevu);
        }

        .sr-table thead tr:last-child th.th-reel {
            color: var(--clr-reel);
        }

        .sr-table thead tr:last-child th.th-taux {
            color: var(--color-text-secondary);
        }

        /* Corps */
        .sr-table tbody tr:hover td {
            background: var(--color-background-tertiary);
        }

        .sr-table tbody tr:last-child td {
            border-bottom: none;
        }

        .sr-table td {
            padding: 6px 6px;
            border-bottom: 1px solid var(--color-border-tertiary);
            vertical-align: middle;
        }

        .sr-table td.td-libelle {
            min-width: 200px;
            max-width: 220px;
            white-space: normal;
            word-break: break-word;
        }

        .sr-table td.td-code {
            font-weight: 600;
            font-size: .7rem;
            color: var(--color-text-info);
            text-align: center;
        }

        .sr-table td.td-num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .sr-table td.td-prevu {
            color: var(--clr-prevu);
        }

        .sr-table td.td-reel {
            color: var(--clr-reel);
            font-weight: 600;
        }

        .sr-table td.td-futur {
            color: var(--clr-futur);
            font-style: italic;
        }

        /* Taux badge */
        .taux-badge {
            display: inline-flex;
            align-items: center;
            padding: .1rem .4rem;
            border-radius: 4px;
            font-size: .68rem;
            font-weight: 700;
            min-width: 42px;
            justify-content: center;
        }

        .taux-ok {
            background: #dcfce7;
            color: #166534;
        }

        .taux-warn {
            background: #fef9c3;
            color: #854d0e;
        }

        .taux-bad {
            background: #fee2e2;
            color: #991b1b;
        }

        .taux-na {
            background: var(--color-background-tertiary);
            color: var(--color-text-tertiary);
        }

        /* Ligne de total */
        .sr-table tfoot td {
            font-weight: 700;
            background: var(--color-background-secondary);
            border-top: 2px solid var(--color-border-secondary);
            padding: 7px 6px;
        }

        /* Mois actuel highlight */
        .col-actuel {
            background: rgba(14, 165, 233, .04);
        }

        /* Filtre bar */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: .75rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .filter-bar select {
            background: var(--color-background-secondary);
            border: 1px solid var(--color-border-tertiary);
            border-radius: var(--border-radius-md);
            padding: .35rem .75rem;
            font-size: .82rem;
            color: var(--color-text-primary);
            cursor: pointer;
        }

        .legend {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: .72rem;
            color: var(--color-text-secondary);
            margin-left: auto;
        }

        .legend span {
            display: flex;
            align-items: center;
            gap: .3rem;
        }

        .leg-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
    </style>

    <div class="sr-wrap" wire:poll.10000ms>

        {{-- ── Filtres ────────────────────────────────────────── --}}
        <div class="filter-bar">
            <select wire:change="changerExercice($event.target.value)">
                @foreach($exercices as $id => $annee)
                    <option value="{{ $id }}" @selected($id == $this->exerciceId)>
                        Exercice {{ $annee }}
                    </option>
                @endforeach
            </select>

            @if(count($previsions) > 1)
                <select wire:change="changerPrevision($event.target.value)">
                    @foreach($previsions as $id => $libelle)
                        <option value="{{ $id }}" @selected($id == $this->previsionId)>
                            {{ $libelle }}
                        </option>
                    @endforeach
                </select>
            @endif
            {{-- Dans .filter-bar, après les selects --}}
<button wire:click="actualiser"
    style="background:var(--color-background-secondary);
           border:1px solid var(--color-border-tertiary);
           border-radius:var(--border-radius-md);
           padding:.35rem .75rem; font-size:.82rem;
           color:var(--color-text-secondary); cursor:pointer;
           display:flex; align-items:center; gap:.35rem;">
    <svg wire:loading.remove wire:target="actualiser"
         width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
    </svg>
    <svg wire:loading wire:target="actualiser"
         class="animate-spin" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
    </svg>
    Actualiser
</button>
            <div style="position:relative; flex:1; max-width:320px;">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="🔍 Rechercher une nomenclature..."
                    style="width:100%; background:var(--color-background-secondary);
                        border:1px solid var(--color-border-tertiary);
                        border-radius:var(--border-radius-md);
                        padding:.35rem .75rem .35rem 2rem;
                        font-size:.82rem; color:var(--color-text-primary);">
                <svg style="position:absolute; left:.6rem; top:50%; transform:translateY(-50%);
                            width:14px; height:14px; color:var(--color-text-tertiary);"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                @if($search)
                <button wire:click="$set('search', '')"
                        style="position:absolute; right:.6rem; top:50%; transform:translateY(-50%);
                            background:none; border:none; cursor:pointer;
                            color:var(--color-text-tertiary); font-size:.9rem;">✕</button>
                @endif
            </div>

            {{-- Compteur de résultats --}}
            @if($search)
            <span style="font-size:.72rem; color:var(--color-text-secondary);">
                {{ count($data) }} résultat{{ count($data) > 1 ? 's' : '' }}
                pour « {{ $search }} »
            </span>
            @endif

            <div class="legend">
                <span><span class="leg-dot" style="background:#1e40af;"></span>Prévu</span>
                <span><span class="leg-dot" style="background:#166534;"></span>Réalisé</span>
                <span><span class="leg-dot" style="background:#94a3b8;"></span>Mois futur</span>
            </div>
        </div>

        {{-- ── KPI Cards ──────────────────────────────────────── --}}
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Prévision annuelle</div>
                <div class="kpi-value" style="color:var(--clr-prevu);">
                    {{ number_format($totalPrevu, 0, ',', ' ') }}
                    <span style="font-size:.65rem; font-weight:400;"> FCFA</span>
                </div>
                <div class="kpi-sub">{{ count($data) }} ligne(s) de nomenclature</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-label">Total recouvré</div>
                <div class="kpi-value" style="color:var(--clr-reel);">
                    {{ number_format($totalRecouvre, 0, ',', ' ') }}
                    <span style="font-size:.65rem; font-weight:400;"> FCFA</span>
                </div>
                <div class="kpi-sub">Au {{ now()->format('d/m/Y') }}</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-label">Taux de recouvrement</div>
                <div class="kpi-value"
                    style="color:{{ $tauxGlobal >= 90 ? '#166534' : ($tauxGlobal >= 70 ? '#854d0e' : '#991b1b') }};">
                    {{ $tauxGlobal }}%
                </div>
                <div class="kpi-sub">
                    @if($tauxGlobal >= 90) ✅ Excellent
                    @elseif($tauxGlobal >= 70) ⚠️ À surveiller
                    @else ❌ Action requise
                    @endif
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-label">Écart global</div>
                <div class="kpi-value" style="color:{{ $totalEcart >= 0 ? '#166534' : '#991b1b' }};">
                    {{ $totalEcart >= 0 ? '+' : '' }}{{ number_format($totalEcart, 0, ',', ' ') }}
                    <span style="font-size:.65rem; font-weight:400;"> FCFA</span>
                </div>
                <div class="kpi-sub">
                    {{ $totalEcart >= 0 ? '↑ Surperformance' : '↓ Sous-performance' }}
                </div>
            </div>
        </div>

        {{-- ── Tableau 12 colonnes ────────────────────────────── --}}
        @if(empty($data))
            <div style="text-align:center; padding:3rem; color:var(--color-text-tertiary); font-size:.85rem;
                    border:1px dashed var(--color-border-tertiary); border-radius:var(--border-radius-lg);">
                ⚠️ Aucune prévision de recettes active pour cet exercice.<br>
                <a href="{{ \App\Filament\Budget\Resources\PrevisionRecetteResource::getUrl('index') }}"
                    style="color:var(--color-text-info); text-decoration:underline; font-size:.8rem; margin-top:.5rem; display:inline-block;">
                    Créer une prévision de recettes →
                </a>
            </div>
        @else
                    <div class="sr-outer">
                        <table class="sr-table">
                            <thead>
                                {{-- Ligne 1 : mois --}}
                                <tr>
                                    <th rowspan="2" style="text-align:left; min-width:40px;">Code</th>
                                    <th rowspan="2" style="text-align:left; min-width:180px;">Nomenclature</th>
                                    <th rowspan="2" style="text-align:right;">Prévu annuel</th>

                                    @foreach($moisLabels as $i => $label)
                                        <th colspan="3"
                                            style="{{ ($i + 1) === $moisActuel && $exercice?->annee === $anneeActuelle ? 'background:rgba(14,165,233,.08);' : '' }}">
                                            {{ $label }}
                                            @if(($i + 1) === $moisActuel && $exercice?->annee === $anneeActuelle)
                                                <span style="font-size:.6rem; color:#0ea5e9;">●</span>
                                            @endif
                                        </th>
                                    @endforeach

                                    <th colspan="2">Cumul</th>
                                    <th rowspan="2" style="text-align:right;">Écart</th>
                                </tr>

                                {{-- Ligne 2 : Prévu / Réel / Taux --}}
                                <tr>
                                    @foreach(range(1, 12) as $m)
                                        <th
                                            class="th-prevu {{ $m === $moisActuel && $exercice?->annee === $anneeActuelle ? 'col-actuel' : '' }}">
                                            Prévu</th>
                                        <th
                                            class="th-reel  {{ $m === $moisActuel && $exercice?->annee === $anneeActuelle ? 'col-actuel' : '' }}">
                                            Réel</th>
                                        <th
                                            class="th-taux  {{ $m === $moisActuel && $exercice?->annee === $anneeActuelle ? 'col-actuel' : '' }}">
                                            %</th>
                                    @endforeach
                                    <th class="th-prevu">Prévu</th>
                                    <th class="th-reel">Réel</th>
                                </tr>
                            </thead>

                            <tbody>
    @foreach($data as $row)
    {{-- ✅ wire:key — force Livewire à recalculer chaque ligne --}}
    <tr wire:key="row-{{ $row['id'] }}-{{ $lastRefresh }}">
        <td class="td-code">{{ $row['code'] }}</td>
        <td class="td-libelle" style="font-size:.76rem;">
            <div style="font-weight:500;">{{ $row['libelle'] }}</div>
        </td>
        <td class="td-num td-prevu" style="font-weight:600;">
            {{ number_format($row['prevu_annuel'], 0, ',', ' ') }}
        </td>

        @foreach(range(1, 12) as $m)
        @php
            $cell      = $row['mois'][$m];
            $futur     = $cell['est_futur'] ?? false;
            $taux      = $cell['taux'];
            $tauxClass = $taux === null ? 'taux-na'
                : ($taux >= 90 ? 'taux-ok' : ($taux >= 70 ? 'taux-warn' : 'taux-bad'));
            $isActuel  = $m === $moisActuel && ($exercice?->annee ?? 0) === $anneeActuelle;
        @endphp
        <td class="td-num {{ $futur ? 'td-futur' : 'td-prevu' }} {{ $isActuel ? 'col-actuel' : '' }}">
            {{ $cell['prevu'] > 0 ? number_format($cell['prevu'], 0, ',', ' ') : '—' }}
        </td>
        {{-- ✅ Colonne RÉEL — wire:key pour forcer le re-render --}}
        <td class="td-num {{ $futur ? 'td-futur' : 'td-reel' }} {{ $isActuel ? 'col-actuel' : '' }}"
            wire:key="reel-{{ $row['id'] }}-{{ $m }}-{{ $lastRefresh }}">
            {{ $cell['recouvre'] > 0 ? number_format($cell['recouvre'], 0, ',', ' ') : '—' }}
        </td>
        <td class="td-num {{ $isActuel ? 'col-actuel' : '' }}">
            @if($taux !== null && $cell['prevu'] > 0)
                <span class="taux-badge {{ $tauxClass }}">{{ $taux }}%</span>
            @else
                <span class="taux-badge taux-na">—</span>
            @endif
        </td>
        @endforeach

        <td class="td-num td-prevu" style="font-weight:600;">
            {{ number_format($row['cumul_prevu'], 0, ',', ' ') }}
        </td>
        <td class="td-num td-reel" style="font-weight:700;"
            wire:key="cumul-{{ $row['id'] }}-{{ $lastRefresh }}">
            {{ number_format($row['cumul_recouvre'], 0, ',', ' ') }}
        </td>
        <td class="td-num" style="font-weight:600; color:{{ $row['ecart'] >= 0 ? '#166534' : '#991b1b' }};">
            {{ $row['ecart'] >= 0 ? '+' : '' }}{{ number_format($row['ecart'], 0, ',', ' ') }}
        </td>
    </tr>
    @endforeach
</tbody>

                            {{-- LIGNE TOTAUX --}}
                            <tfoot>
                                <tr>
                                    <td colspan="2" style="font-size:.72rem; color:var(--color-text-secondary);">
                                        TOTAUX ({{ count($data) }} lignes)
                                    </td>
                                    <td class="td-num td-prevu">{{ number_format($totalPrevu, 0, ',', ' ') }}</td>

                                    @foreach(range(1, 12) as $m)
                                        @php
                                            $t = $totaux[$m];
                                            $taux = $t['taux'];
                                            $tc = $taux === null ? 'taux-na'
                                                : ($taux >= 90 ? 'taux-ok' : ($taux >= 70 ? 'taux-warn' : 'taux-bad'));
                                        @endphp
                                        <td
                                            class="td-num td-prevu {{ $m === $moisActuel && $exercice?->annee === $anneeActuelle ? 'col-actuel' : '' }}">
                                            {{ $t['prevu'] > 0 ? number_format($t['prevu'], 0, ',', ' ') : '—' }}
                                        </td>
                                        <td
                                            class="td-num td-reel {{ $m === $moisActuel && $exercice?->annee === $anneeActuelle ? 'col-actuel' : '' }}">
                                            {{ $t['recouvre'] > 0 ? number_format($t['recouvre'], 0, ',', ' ') : '—' }}
                                        </td>
                                        <td
                                            class="td-num {{ $m === $moisActuel && $exercice?->annee === $anneeActuelle ? 'col-actuel' : '' }}">
                                            @if($taux !== null && $t['prevu'] > 0)
                                                <span class="taux-badge {{ $tc }}">{{ $taux }}%</span>
                                            @else
                                                <span class="taux-badge taux-na">—</span>
                                            @endif
                                        </td>
                                    @endforeach

                                    <td class="td-num td-prevu">{{ number_format($totalPrevu, 0, ',', ' ') }}</td>
                                    <td class="td-num td-reel">{{ number_format($totalRecouvre, 0, ',', ' ') }}</td>
                                    <td class="td-num" style="color:{{ $totalEcart >= 0 ? '#166534' : '#991b1b' }};">
                                        {{ $totalEcart >= 0 ? '+' : '' }}{{ number_format($totalEcart, 0, ',', ' ') }}
                                    </td>
                                </tr>

                                {{-- Ligne taux global --}}
                                <tr style="font-size:.72rem;">
                                    <td colspan="3" style="color:var(--color-text-secondary);">Taux de réalisation</td>
                                    @foreach(range(1, 12) as $m)
                                                    @php $t = $totaux[$m];
                                                        $tp = $t['prevu'];
                                                        $tr = $t['recouvre'];
                                                        $taux = $tp > 0 ? round($tr / $tp * 100, 1) : null;
                                                        $tc = $taux === null ? 'taux-na' : ($taux >= 90 ? 'taux-ok' : ($taux >= 70 ? 'taux-warn' : 'taux-bad'));
                                                    @endphp
                                            <td             colspan="3" style="text-align:center; {{ $m === $moisActuel && $exercice?->annee === $anneeActuelle ? 'background:rgba(14,165,233,.04);' : '' }}">
                                            @if($taux !== null)
                                                <span class="taux-badge {{ $tc }}">{{ $taux }}%</span>
                                            @else
                                                            <span class="taux-badge taux-na">—</span>
                                                        @endif
                                        </td            >
                                    @endforeach
                                    <td colspan="2" style="text-align:center;">
                                        @php $tc2 = $tauxGlobal >= 90 ? 'taux-ok' : ($tauxGlobal >= 70 ? 'taux-warn' : 'taux-bad'); @endphp
                                        <span class="taux-badge {{ $tc2 }}" style="font-size:.76rem;">{{ $tauxGlobal }}%</span>
                                    </td>
                                    <td></td>
                            </tr>
                        </tfoot>
            </table>
                    </div>
        @endif

{{-- ── Note de lecture ──────────────────────────────── --}}
        <div style="margin-top:.75rem; padding:.5rem .75rem; background:var(--color-background-tertiary);
                    border-radius:var(--border-radius-md); font-size:.72rem; color:var(--color-text-tertiary);
                    display:flex; gap:1.5rem; flex-wrap:wrap;">
            <span>
                <span class="taux-badge taux-ok" style="font-size:.65rem;">≥90%</span> Objectif atteint
            </span>
            <span>
                <span class="taux-badge taux-warn" style="font-size:.65rem;">70–89%</span> À surveiller
            </span>
            <span>
                <span class="taux-badge taux-bad" style="font-size:.65rem;">&lt;70%</span> Action requise
            </span>
            <span style="margin-left:auto;">
        ● = Mois en cours — Les montants sont en FCFA
        </span>
</div>

</div>{{-- /.sr-wrap --}}
</x-filament-panels::page>