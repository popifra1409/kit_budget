<x-filament-panels::page>
    <form wire:submit="import">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" color="primary" icon="heroicon-o-arrow-down-tray">
                Importer
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
