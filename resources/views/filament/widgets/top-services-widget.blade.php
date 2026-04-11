<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            🏆 Top 10 Nomenclatures Budgétaires
        </x-slot>

        <div class="space-y-2">
            @forelse ($services as $index => $service)
                <div
                    class="flex items-center justify-between p-3 rounded-lg {{ $index < 3 ? 'bg-primary-50 dark:bg-primary-900/20' : 'bg-gray-50 dark:bg-gray-800' }}">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold
                            {{ $index === 0 ? 'bg-yellow-500 text-white' : '' }}
                            {{ $index === 1 ? 'bg-gray-400 text-white' : '' }}
                            {{ $index === 2 ? 'bg-orange-600 text-white' : '' }}
                            {{ $index > 2 ? 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300' : '' }}
                        ">
                            {{ $index + 1 }}
                        </span>
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ $service['service'] }}
                        </span>
                    </div>
                    <span class="text-lg font-bold text-primary-600 dark:text-primary-400">
                        {{ number_format($service['montant'], 0, ',', ' ') }} FCFA
                    </span>
                </div>
            @empty
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <p>Aucune donnée disponible</p>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
