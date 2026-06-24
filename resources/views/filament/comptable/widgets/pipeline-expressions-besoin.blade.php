<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">📋 Pipeline — Expressions de Besoins</x-slot>

        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
            @foreach ($pipeline as $etape)
                <div style="flex:1; min-width:120px; text-align:center; padding:12px 8px;
                            border-radius:8px; background:#f8fafc; border:1px solid #e2e8f0;">
                    <div style="font-size:1.6rem;">{{ $etape['icon'] }}</div>
                    <div style="font-size:1.4rem; font-weight:700; margin:4px 0;">
                        {{ $etape['count'] }}
                    </div>
                    <div style="font-size:.75rem; color:#64748b; line-height:1.3;">
                        {{ $etape['label'] }}
                    </div>
                </div>
            @endforeach
        </div>

        <div style="font-size:.85rem; color:#64748b; text-align:right;">
            Total : <strong>{{ $total }}</strong> expressions
            | Ce mois : <strong>{{ $ce_mois }}</strong>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>