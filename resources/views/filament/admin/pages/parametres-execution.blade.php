<x-filament-panels::page>
    <form wire:submit="enregistrer" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Enregistrer les modifications
            </x-filament::button>
        </div>
    </form>

    @php
        $historique = $this->getHistorique();
        $definitions = \App\Services\ParametresExecution::definitions();
    @endphp

    <x-filament::section heading="Historique des modifications" collapsible>
        @if ($historique->isEmpty())
            <p class="text-sm text-gray-500">Aucune modification enregistrée : les valeurs par défaut (instruction 2026) s'appliquent.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-gray-500">
                            <th class="py-2 pr-4">Date d'effet</th>
                            <th class="py-2 pr-4">Paramètre</th>
                            <th class="py-2 pr-4">Valeur</th>
                            <th class="py-2 pr-4">Motif</th>
                            <th class="py-2">Par / le</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($historique as $h)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 whitespace-nowrap">{{ $h->date_effet?->format('d/m/Y') }}</td>
                                <td class="py-2 pr-4">{{ $definitions[$h->cle]['libelle'] ?? $h->cle }}</td>
                                <td class="py-2 pr-4 font-semibold">{{ \App\Services\ParametresExecution::libelleValeur($h->cle, $h->valeur) }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ $h->motif ?? '—' }}</td>
                                <td class="py-2 text-gray-500 whitespace-nowrap">{{ $h->auteur?->name ?? '—' }} · {{ $h->created_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>