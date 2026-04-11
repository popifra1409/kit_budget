<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01">
                    </path>
                </svg>
                <span>Gestion du Cache & Performance</span>
            </div>
        </x-slot>

        <x-slot name="description">
            Optimisez les performances de l'application
        </x-slot>

        <div class="space-y-4">
            <!-- Statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @php
                    $stats = $this->getCacheStats();
                @endphp

                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Driver Cache</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ ucfirst($stats['cache_driver']) }}
                    </div>
                </div>

                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Driver Session</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ ucfirst($stats['session_driver']) }}
                    </div>
                </div>

                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Durée Session</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $stats['session_lifetime'] }}
                    </div>
                </div>

                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">File d'attente</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ ucfirst($stats['queue_driver']) }}
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-3">
                <button type="button" wire:click="clearApplicationCache"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-danger-600 hover:bg-danger-700 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                        </path>
                    </svg>
                    Vider le Cache
                </button>

                <button type="button" wire:click="optimizeApplication"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-success-600 hover:bg-success-700 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Optimiser
                </button>

                <button type="button" wire:click="clearSessionCache"
                    wire:confirm="Êtes-vous sûr ? Vous serez déconnecté."
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-warning-600 hover:bg-warning-700 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                        </path>
                    </svg>
                    Réinitialiser Session
                </button>
            </div>

            <!-- Conseils de sécurité -->
            <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4 border border-blue-200 dark:border-blue-800">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="text-sm text-blue-700 dark:text-blue-300">
                        <p class="font-semibold mb-2">Conseils de sécurité et performance :</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Ne sauvegardez jamais vos mots de passe dans le navigateur</li>
                            <li>Déconnectez-vous toujours après utilisation sur un poste partagé</li>
                            <li>Videz le cache uniquement en cas de problème de performance</li>
                            <li>L'optimisation recharge les fichiers de configuration en cache</li>
                            <li>La réinitialisation de session vous déconnectera immédiatement</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>

    @push('scripts')
        <script>
            // Écouter l'événement de nettoyage de session
            window.addEventListener('session-cleared', () => {
                setTimeout(() => {
                    window.location.href = '{{ filament()->getLoginUrl() }}';
                }, 2000);
            });
        </script>
    @endpush
</x-filament-widgets::widget>
