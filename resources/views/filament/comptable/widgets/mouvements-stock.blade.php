<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">📊 Mouvements de Stock — 6 derniers mois</x-slot>

        <div style="margin-bottom:12px; display:flex; gap:20px; font-size:.85rem;">
            <span>
                Ce mois — <strong style="color:#10b981;">Entrées : {{ $totalEntreesMois }}</strong>
                &nbsp;|&nbsp;
                <strong style="color:#ef4444;">Sorties : {{ $totalSortiesMois }}</strong>
            </span>
        </div>

        {{-- Graphique en barres simple HTML/CSS (compatible DomPDF non requis ici) --}}
        @php
            $max = collect($mois)->map(fn($m) => max($m['entrees'], $m['sorties']))->max();
            $max = $max > 0 ? $max : 1;
        @endphp

        <div style="display:flex; align-items:flex-end; gap:8px; height:120px;">
            @foreach ($mois as $m)
                <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:2px;">
                    <div style="width:100%; display:flex; gap:2px; align-items:flex-end; height:90px;">
                        <div style="flex:1; background:#10b981; border-radius:3px 3px 0 0;
                                    height:{{ $max > 0 ? round(($m['entrees'] / $max) * 90) : 0 }}px;"
                            title="Entrées: {{ $m['entrees'] }}"></div>
                        <div style="flex:1; background:#ef4444; border-radius:3px 3px 0 0;
                                    height:{{ $max > 0 ? round(($m['sorties'] / $max) * 90) : 0 }}px;"
                            title="Sorties: {{ $m['sorties'] }}"></div>
                    </div>
                    <div style="font-size:.65rem; color:#94a3b8; text-align:center;">
                        {{ $m['label'] }}
                    </div>
                </div>
            @endforeach
        </div>

        <div style="margin-top:10px; display:flex; gap:16px; font-size:.8rem;">
            <span><span style="display:inline-block; width:12px; height:12px;
                background:#10b981; border-radius:2px; vertical-align:middle;"></span>
                Entrées</span>
            <span><span style="display:inline-block; width:12px; height:12px;
                background:#ef4444; border-radius:2px; vertical-align:middle;"></span>
                Sorties</span>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>