<x-filament-widgets::widget>
    @php
        $data = $this->getData();
    @endphp

    <x-filament::section>
        <div class="space-y-6">
            <!-- En-tête de bienvenue -->
            <div class="flex items-center gap-4">
                <div class="flex-shrink-0">
                    <div
                        class="w-16 h-16 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center">
                        <svg class="w-8 h-8 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                </div>

                <div class="flex-1">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Bienvenue, {{ $data['name'] }} !
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $data['email'] }}
                    </p>
                </div>
            </div>

            <!-- Informations du compte -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Rôle(s) -->
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                            </path>
                        </svg>
                        <h3 class="font-semibold text-gray-700 dark:text-gray-300">Rôle(s)</h3>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($data['roles'] as $role)
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                                {{ $role }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <!-- Service -->
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                        <h3 class="font-semibold text-gray-700 dark:text-gray-300">Service</h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $data['service'] }}
                    </p>
                </div>

                <!-- Permissions -->
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                            </path>
                        </svg>
                        <h3 class="font-semibold text-gray-700 dark:text-gray-300">Permissions</h3>
                    </div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $data['permissions_count'] }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        permissions actives
                    </p>
                </div>
            </div>

            <!-- Message selon les permissions -->
            @if ($data['permissions_count'] === 0)
                <!-- Aucune permission -->
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700 dark:text-yellow-200">
                                <strong>Aucune permission active</strong> - Votre compte est configuré mais n'a pas
                                encore d'accès.
                                Contactez votre administrateur pour obtenir les permissions nécessaires.
                            </p>
                        </div>
                    </div>
                </div>
            @else
                <!-- A des permissions -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700 dark:text-blue-200">
                                Utilisez le menu de navigation sur la gauche pour accéder aux fonctionnalités
                                disponibles.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Liens utiles -->
            <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                    Liens utiles
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @if (auth()->user()->can('view_any_user'))
                        <a href="{{ route('filament.admin.resources.users.index') }}"
                            class="flex items-center gap-2 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 transition">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                </path>
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Utilisateurs</span>
                        </a>
                    @endif

                    @if (auth()->user()->can('view_any_engagement'))
                        <a href="{{ route('filament.budget.resources.engagements.index') }}"
                            class="flex items-center gap-2 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 transition">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                </path>
                            </svg>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Engagements</span>
                        </a>
                    @endif

                    <a href="{{ route('filament.budget.auth.profile') }}"
                        class="flex items-center gap-2 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 transition">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                            </path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Mon Profil</span>
                    </a>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
