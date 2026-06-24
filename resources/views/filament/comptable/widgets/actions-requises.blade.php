<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">🔔 Actions Requises</x-slot>

        <div style="display:flex; flex-direction:column; gap:10px;">
            @foreach ($actions as $action)
                <div style="display:flex; align-items:center; gap:12px; padding:12px;
                            border-left:4px solid {{ $action['couleur'] }};
                            background:#f8fafc; border-radius:0 8px 8px 0;">
                    <div style="font-size:1.5rem; flex-shrink:0;">{{ $action['icon'] }}</div>
                    <div style="flex:1;">
                        <div style="font-weight:600; font-size:.9rem;">{{ $action['titre'] }}</div>
                        <div style="font-size:.8rem; color:#64748b; margin-top:2px;">{{ $action['detail'] }}</div>
                    </div>
                    @if ($action['url'] && $action['bouton'])
                        <a href="{{ $action['url'] }}" style="padding:6px 14px; background:{{ $action['couleur'] }}; color:#fff;
                                  border-radius:6px; font-size:.8rem; font-weight:600;
                                  text-decoration:none; white-space:nowrap; flex-shrink:0;">
                            {{ $action['bouton'] }}
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>