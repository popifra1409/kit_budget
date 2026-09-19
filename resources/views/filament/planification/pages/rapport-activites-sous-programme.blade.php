<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php
        $sp = $this->getSousProgramme();
        $activites = $this->getActivites();
    @endphp

    @if ($sp)
        <div class="mt-6 fi-section rounded-xl bg-white p-6 dark:bg-gray-800 space-y-2">
            <div><span class="font-semibold">Sous-programme :</span> {{ $sp->libelle }}</div>
            <div><span class="font-semibold">Objectif du sous-programme :</span> {{ $sp->objectif ?? '—' }}</div>
            <div>
                <span class="font-semibold">Indicateur(s) du sous-programme :</span>
                {{ $sp->indicateurs->pluck('libelle')->implode(', ') ?: '—' }}
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm border">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="border p-2 text-left">Désignation</th>
                        <th class="border p-2 text-left">Objectif</th>
                        <th class="border p-2 text-left">Indicateurs</th>
                        <th class="border p-2">Baseline</th>
                        <th class="border p-2">Cible</th>
                        <th class="border p-2 text-left">Zone d'exécution</th>
                        <th class="border p-2 text-left">Responsable</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activites as $act)
                        <tr>
                            <td class="border p-2">{{ $act->libelle }}</td>
                            <td class="border p-2">{{ $act->objectif ?? '—' }}</td>
                            <td class="border p-2">{{ $act->indicateurs->pluck('libelle')->implode(', ') ?: '—' }}</td>
                            <td class="border p-2 text-center">{{ $act->indicateurs->pluck('valeur_reference')->filter()->implode(', ') ?: '—' }}</td>
                            <td class="border p-2 text-center">{{ $act->indicateurs->pluck('valeur_cible')->filter()->implode(', ') ?: '—' }}</td>
                            <td class="border p-2">{{ $act->zone_execution ?? '—' }}</td>
                            <td class="border p-2">{{ $act->responsable?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="border p-2 text-center text-gray-400">Aucune activité</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>