<div class="space-y-6">
    {{-- En-tête avec recherche et filtres --}}
    <div class="flex items-center justify-between gap-4">
        <div class="flex-1">
            <input type="text" wire:model.live="search" placeholder="Rechercher une permission..."
                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
        </div>

        <select wire:model.live="filterResource"
            class="rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            <option value="all">Toutes les ressources</option>
            @foreach ($this->resources as $resource)
                <option value="{{ $resource }}">{{ ucfirst(str_replace('_', ' ', $resource)) }}</option>
            @endforeach
        </select>

        <button wire:click="save"
            class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
            Enregistrer
        </button>
    </div>

    {{-- Statistiques --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-600 dark:text-gray-400">
                <strong>{{ count($selectedPermissions) }}</strong> permission(s) sélectionnée(s)
            </span>
            <span class="text-gray-600 dark:text-gray-400">
                sur <strong>{{ $this->totalPermissions }}</strong> total
            </span>
        </div>
    </div>

    {{-- Table des permissions par ressource --}}
    @foreach ($this->groupedPermissions as $resource => $permissions)
        <div class="border dark:border-gray-700 rounded-lg overflow-hidden">
            {{-- En-tête de la ressource --}}
            <div class="bg-gray-100 dark:bg-gray-800 px-4 py-3 flex items-center justify-between">
                <h3 class="font-semibold text-lg">
                    {{ ucfirst(str_replace('_', ' ', $resource)) }}
                    <span class="text-sm text-gray-500 font-normal ml-2">
                        ({{ $permissions->count() }} permissions)
                    </span>
                </h3>

                <button wire:click="toggleAll('{{ $resource }}')"
                    class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                    @php
                        $resourcePermIds = $permissions->pluck('id')->toArray();
                        $allSelected = empty(array_diff($resourcePermIds, $selectedPermissions));
                    @endphp
                    {{ $allSelected ? 'Tout désélectionner' : 'Tout sélectionner' }}
                </button>
            </div>

            {{-- Liste des permissions --}}
            <div class="divide-y dark:divide-gray-700">
                @foreach ($permissions as $permission)
                    <label
                        class="flex items-center gap-4 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition"
                        wire:key="permission-{{ $permission->id }}">
                        <input type="checkbox" wire:model.live="selectedPermissions" value="{{ $permission->id }}"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">

                        <div class="flex-1">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $permission->name }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                @php
                                    $descriptions = [
                                        'view_any' => 'Voir la liste',
                                        'view' => 'Voir le détail',
                                        'create' => 'Créer',
                                        'update' => 'Modifier',
                                        'delete' => 'Supprimer',
                                        'engage' => 'Engager',
                                        'valider' => 'Valider',
                                        'emettre' => 'Émettre',
                                        'viser' => 'Viser',
                                        'payer' => 'Marquer comme payé',
                                    ];

                                    $action = explode('_', $permission->name)[0];
                                    echo $descriptions[$action] ?? ucfirst(str_replace('_', ' ', $permission->name));
                                @endphp
                            </div>
                        </div>

                        @if (in_array($permission->id, $selectedPermissions))
                            <span
                                class="text-xs bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100 px-2 py-1 rounded-full">
                                ✓ Activée
                            </span>
                        @endif
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- Message si aucun résultat --}}
    @if ($this->groupedPermissions->isEmpty())
        <div class="text-center py-12 text-gray-500">
            Aucune permission trouvée
        </div>
    @endif
</div>
