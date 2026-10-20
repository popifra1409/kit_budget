@php
    $brandName = filament()->getBrandName();
    $brandLogo = filament()->getBrandLogo();
    $brandLogoHeight = filament()->getBrandLogoHeight() ?? '1.5rem';
    $darkModeBrandLogo = filament()->getDarkModeBrandLogo();
    $hasDarkModeBrandLogo = filled($darkModeBrandLogo);

    $getLogoClasses = fn (bool $isDarkMode): string => \Illuminate\Support\Arr::toCssClasses([
        'fi-logo',
        'flex' => ! $hasDarkModeBrandLogo,
        'flex dark:hidden' => $hasDarkModeBrandLogo && (! $isDarkMode),
        'hidden dark:flex' => $hasDarkModeBrandLogo && $isDarkMode,
    ]);

    $logoStyles = "height: {$brandLogoHeight}";
@endphp

@capture($content, $logo, $isDarkMode = false)
    @if ($logo instanceof \Illuminate\Contracts\Support\Htmlable)
        <div
            {{
                $attributes
                    ->class([$getLogoClasses($isDarkMode)])
                    ->style([$logoStyles])
            }}
        >
            {{ $logo }}
        </div>
    @elseif (filled($logo))
        {{-- Fil d'Ariane au-dessus du logo : le texte du brand n'est plus remplacé par l'image --}}
        <div
            {{
                $attributes->class([
                    'fi-logo',
                    'flex flex-col items-start gap-0.5 leading-none' => ! $hasDarkModeBrandLogo,
                    'flex dark:hidden' => $hasDarkModeBrandLogo && (! $isDarkMode),
                    'hidden dark:flex' => $hasDarkModeBrandLogo && $isDarkMode,
                ])
            }}
        >
            <span
                title="{{ $brandName }}"
                class="max-w-full truncate text-[13px] font-bold tracking-tight text-gray-950 dark:text-white"
            >{{ $brandName }}</span>
            <img
                alt="{{ __('filament-panels::layout.logo.alt', ['name' => $brandName]) }}"
                src="{{ $logo }}"
                style="{{ $logoStyles }}"
                class="max-w-[9rem] object-contain"
            />
        </div>
    @else
        <div
            {{
                $attributes->class([
                    $getLogoClasses($isDarkMode),
                    'text-xl font-bold leading-5 tracking-tight text-gray-950 dark:text-white',
                ])
            }}
        >
            {{ $brandName }}
        </div>
    @endif
@endcapture

{{ $content($brandLogo) }}

@if ($hasDarkModeBrandLogo)
    {{ $content($darkModeBrandLogo, isDarkMode: true) }}
@endif
