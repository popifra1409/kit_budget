<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            ⚠️ Alertes et Notifications
        </x-slot>

        <div class="space-y-3">
            @forelse ($alertes as $alerte)
                <div
                    class="flex items-start gap-3 p-3 rounded-lg border
                    @if ($alerte['type'] === 'danger') border-danger-200 bg-danger-50 dark:border-danger-800 dark:bg-danger-900/20
                    @elseif($alerte['type'] === 'warning') border-warning-200 bg-warning-50 dark:border-warning-800 dark:bg-warning-900/20
                    @else border-info-200 bg-info-50 dark:border-info-800 dark:bg-info-900/20 @endif
                ">
                    <div class="flex-shrink-0">
                        @if ($alerte['type'] === 'danger')
                            <x-filament::icon icon="heroicon-o-exclamation-circle"
                                class="w-6 h-6 text-danger-600 dark:text-danger-400" />
                        @elseif($alerte['type'] === 'warning')
                            <x-filament::icon icon="heroicon-o-exclamation-triangle"
                                class="w-6 h-6 text-warning-600 dark:text-warning-400" />
                        @else
                            <x-filament::icon icon="heroicon-o-information-circle"
                                class="w-6 h-6 text-info-600 dark:text-info-400" />
                        @endif
                    </div>
                    <div class="flex-1">
                        <p
                            class="font-medium
                            @if ($alerte['type'] === 'danger') text-danger-900 dark:text-danger-100
                            @elseif($alerte['type'] === 'warning') text-warning-900 dark:text-warning-100
                            @else text-info-900 dark:text-info-100 @endif
                        ">
                            {{ $alerte['message'] }}
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <span
                            class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold rounded-full
                            @if ($alerte['type'] === 'danger') bg-danger-600 text-white
                            @elseif($alerte['type'] === 'warning') bg-warning-600 text-white
                            @else bg-info-600 text-white @endif
                        ">
                            {{ $alerte['count'] }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="flex items-center justify-center py-8 text-center">
                    <div>
                        <x-filament::icon icon="heroicon-o-check-circle"
                            class="w-12 h-12 mx-auto text-success-600 dark:text-success-400 mb-2" />
                        <p class="text-success-600 dark:text-success-400 font-medium">
                            ✅ Aucune alerte
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Tout est en ordre !
                        </p>
                    </div>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
