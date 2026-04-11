    <x-filament-widgets::widget>
        <x-filament::section>
            <form wire:submit="changerMotDePasse">
                {{ $this->form }}

                <div class="mt-4">
                    <x-filament::button type="submit">
                        Changer le mot de passe
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </x-filament-widgets::widget>
