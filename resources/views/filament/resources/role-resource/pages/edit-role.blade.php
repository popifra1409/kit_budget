<x-filament-panels::page>
    {{-- Formulaire principal --}}
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" :full-width="$this->hasFullWidthFormActions()" />
    </x-filament-panels::form>

    {{-- Section des permissions avec le composant Livewire --}}
    <div class="mt-8">
        <x-filament::section>
            <x-slot name="heading">
                Gestion des permissions
            </x-slot>

            <x-slot name="description">
                Cochez les permissions que vous souhaitez attribuer à ce rôle
            </x-slot>

            @livewire('role-permissions-table', ['role' => $this->record])
        </x-filament::section>
    </div>
</x-filament-panels::page>
