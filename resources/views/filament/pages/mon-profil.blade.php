<x-filament-panels::page>
    {{-- Section Informations personnelles --}}
    <x-filament::section>
        <x-slot name="heading">
            Informations personnelles
        </x-slot>

        <form wire:submit="updateProfile">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Nom complet
                    </label>
                    <input type="text" wire:model="name"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required />
                    @error('name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Email
                    </label>
                    <input type="email" wire:model="email"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required />
                    @error('email')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6">
                <x-filament::button type="submit">
                    Enregistrer les informations
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{-- Section Mot de passe --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">
            Changer le mot de passe
        </x-slot>

        <x-slot name="description">
            Tous les champs sont obligatoires pour changer le mot de passe
        </x-slot>

        <form wire:submit="updatePassword">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Mot de passe actuel
                    </label>
                    <input type="password" wire:model="current_password"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required />
                    @error('current_password')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Nouveau mot de passe
                    </label>
                    <input type="password" wire:model="password"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required />
                    <p class="mt-1 text-sm text-gray-500">Minimum 8 caractères</p>
                    @error('password')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Confirmer le mot de passe
                    </label>
                    <input type="password" wire:model="password_confirmation"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required />
                    @error('password_confirmation')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6">
                <x-filament::button type="submit" color="warning">
                    Changer le mot de passe
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
