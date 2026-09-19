<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php $psp = $this->getPsp(); @endphp

    @if ($psp)
        <div class="mt-6 space-y-6">
            <div class="fi-section rounded-xl bg-white p-6 dark:bg-gray-800">
                <h2 class="text-lg font-bold">I. Synthèse des choix stratégiques</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $psp->description }}</p>
            </div>

            @foreach ($psp->sousProgrammes as $i => $sp)
                <div class="fi-section rounded-xl bg-white p-6 dark:bg-gray-800 space-y-3">
                    <h3 class="text-base font-bold">
                        {{ ['III','IV','V','VI','VII','VIII','IX','X'][$i] ?? ($i+1) }}.
                        Sous-programme de mise en œuvre n°{{ $i + 1 }}
                    </h3>

                    <div><span class="font-semibold">Programme de rattachement :</span>
                        {{ $sp->programmeBudgetaire?->code }} — {{ $sp->programmeBudgetaire?->libelle }}</div>
                    <div><span class="font-semibold">1. Nom du sous-programme :</span> {{ $sp->libelle }}</div>
                    <div><span class="font-semibold">2. Objectif du sous-programme :</span> {{ $sp->objectif ?? '—' }}</div>

                    <div>
                        <span class="font-semibold">3. Indicateur(s) du sous-programme :</span>
                        <table class="w-full mt-2 text-sm border">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="border p-2 text-left">Nom de l'indicateur</th>
                                    <th class="border p-2">Valeur de référence</th>
                                    <th class="border p-2">Valeur cible (fin de période)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($sp->indicateurs as $ind)
                                    <tr>
                                        <td class="border p-2">{{ $ind->libelle }}</td>
                                        <td class="border p-2 text-center">{{ $ind->valeur_reference ?? '—' }} ({{ $ind->annee_reference ?? '—' }})</td>
                                        <td class="border p-2 text-center">{{ $ind->valeur_cible ?? '—' }} ({{ $ind->annee_cible ?? '—' }})</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="border p-2 text-center text-gray-400">Aucun indicateur défini</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div><span class="font-semibold">4. Stratégie du sous-programme :</span> {{ $sp->strategie ?? '—' }}</div>
                    <div><span class="font-semibold">5. Cadre institutionnel de mise en œuvre :</span> {{ $sp->cadre_institutionnel ?? '—' }}</div>
                    <div><span class="font-semibold">6. Responsable de mise en œuvre :</span> {{ $sp->responsable?->name ?? '—' }}</div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>