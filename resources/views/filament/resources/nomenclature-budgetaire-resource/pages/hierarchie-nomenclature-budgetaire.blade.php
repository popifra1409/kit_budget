<x-filament-panels::page>
    <div class="space-y-4">
        <!-- Boutons de filtre Type -->
        <div class="flex gap-2 mb-4">
            <x-filament::button wire:click="changerType('depense')" :color="$typeFiltre === 'depense' ? 'danger' : 'gray'"
                icon="heroicon-o-arrow-trending-down">
                Dépenses (Classe 6)
            </x-filament::button>

            <x-filament::button wire:click="changerType('recette')" :color="$typeFiltre === 'recette' ? 'success' : 'gray'" icon="heroicon-o-arrow-trending-up">
                Recettes (Classe 7)
            </x-filament::button>
        </div>

        <!-- Arbre hiérarchique -->
        <div class="bg-white rounded-lg shadow p-6 dark:bg-gray-800">
            @php
                $racines = App\Models\NomenclatureBudgetaire::whereNull('parent_id')
                    ->where('type', $typeFiltre)
                    ->whereNull('date_fin_validite')
                    ->orderBy('code')
                    ->get();
            @endphp

            @if ($racines->count() > 0)
                <div class="space-y-2">
                    @foreach ($racines as $racine)
                        @include('filament.components.nomenclature-tree-item', [
                            'item' => $racine,
                            'niveau' => 0,
                        ])
                    @endforeach
                </div>
            @else
                <div class="text-center text-gray-500 py-8">
                    <p>Aucune nomenclature {{ $typeFiltre === 'depense' ? 'de dépense' : 'de recette' }} trouvée.</p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
