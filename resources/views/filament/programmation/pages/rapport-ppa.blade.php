<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php $ppa = $this->getPpa(); @endphp

    @if ($ppa)
        <div class="mt-6 space-y-6">

            {{-- INTRODUCTION --}}
            <div class="fi-section rounded-xl bg-white p-6 dark:bg-gray-800">
                <h2 class="text-lg font-bold">Introduction</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ $ppa->contexte_introduction ?: $ppa->planStrategiqueEp?->contexte_elaboration ?: '—' }}
                </p>
            </div>

            {{-- SYNTHESE STRATEGIQUE --}}
            <div class="fi-section rounded-xl bg-white p-6 dark:bg-gray-800 space-y-3">
                <h2 class="text-lg font-bold">Synthèse stratégique</h2>

                <div><span class="font-semibold">1. Missions de l'EP / rattachement :</span>
                    {{ $ppa->planStrategiqueEp?->cspMinistere?->libelle ?? '—' }}</div>

                <div><span class="font-semibold">2. Domaines d'intervention :</span>
                    {{ $ppa->planStrategiqueEp?->domaines_intervention ?? '—' }}</div>

                <div><span class="font-semibold">3. Performances antérieures :</span>
                    {{ $ppa->performances_anterieures ?? '—' }}</div>

                <div><span class="font-semibold">4. Bilan technique :</span>
                    {{ $ppa->bilan_technique ?? '—' }}</div>

                <div><span class="font-semibold">5. Bilan financier :</span>
                    {{ $ppa->bilan_financier ?? '—' }}</div>

                <div><span class="font-semibold">6. Objectifs stratégiques :</span>
                    {{ $ppa->planStrategiqueEp?->objectif_strategique ?? '—' }}</div>

                <div><span class="font-semibold">7. Cadre institutionnel de mise en œuvre :</span>
                    {{ $ppa->planStrategiqueEp?->description ?? '—' }}</div>
            </div>

            {{-- CONTENU DES SOUS-PROGRAMMES --}}
            <div class="fi-section rounded-xl bg-white p-6 dark:bg-gray-800 space-y-4">
                <h2 class="text-lg font-bold">Contenu des sous-programmes — Exercice {{ $ppa->exercice?->annee }}</h2>

                @foreach ($ppa->getSousProgrammesAvecActivites() as $sp)
                    <div class="border rounded-lg p-4 space-y-2">
                        <h3 class="font-bold">{{ $sp->libelle }}
                            <span class="text-xs text-gray-500">({{ $sp->programmeBudgetaire?->code }})</span>
                        </h3>
                        <div class="text-sm"><span class="font-semibold">Objectif :</span> {{ $sp->objectif ?? '—' }}</div>

                                                <table class="w-full mt-2 text-sm border">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="border p-2 text-left">Action</th>
                                    <th class="border p-2 text-left">Activité</th>
                                    <th class="border p-2 text-left">Indicateurs</th>
                                    <th class="border p-2">AE prévu</th>
                                    <th class="border p-2">CP prévu</th>
                                    <th class="border p-2 bg-blue-50 dark:bg-blue-900/20">Engagé (réel)</th>
                                    <th class="border p-2 bg-blue-50 dark:bg-blue-900/20">Disponible</th>
                                    <th class="border p-2 bg-blue-50 dark:bg-blue-900/20">Taux exéc.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($sp->actions as $action)
                                    @forelse ($action->activites as $activite)
                                        @php $exec = $activite->getExecutionBudgetaire(); @endphp
                                        <tr>
                                            <td class="border p-2">{{ $action->libelle }}</td>
                                            <td class="border p-2">{{ $activite->libelle }}</td>
                                            <td class="border p-2">{{ $activite->indicateurs->pluck('libelle')->implode(', ') ?: '—' }}</td>
                                            <td class="border p-2 text-right">{{ number_format($activite->getTotalAe(), 0, ',', ' ') }}</td>
                                            <td class="border p-2 text-right">{{ number_format($activite->getTotalCp(), 0, ',', ' ') }}</td>
                                            <td class="border p-2 text-right bg-blue-50/50 dark:bg-blue-900/10">{{ number_format($exec['engage'], 0, ',', ' ') }}</td>
                                            <td class="border p-2 text-right bg-blue-50/50 dark:bg-blue-900/10">{{ number_format($exec['disponible'], 0, ',', ' ') }}</td>
                                            <td class="border p-2 text-right bg-blue-50/50 dark:bg-blue-900/10">
                                                <span class="{{ $exec['taux_engagement'] > 90 ? 'text-red-600 font-bold' : ($exec['taux_engagement'] > 60 ? 'text-orange-600' : 'text-green-600') }}">
                                                    {{ $exec['taux_engagement'] }}%
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="border p-2 text-center text-gray-400">Aucune activité pour cet exercice</td></tr>
                                    @endforelse
                                @empty
                                    <tr><td colspan="8" class="border p-2 text-center text-gray-400">Aucune action pour cet exercice</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>

            {{-- ANNEXES : TABLEAU DE BUDGETISATION --}}
            <div class="fi-section rounded-xl bg-white p-6 dark:bg-gray-800">
                <h2 class="text-lg font-bold">Annexes — Tableau de budgétisation</h2>
                <div class="mt-3 flex gap-8">
                    <div>
                        <div class="text-xs text-gray-500">Total AE</div>
                        <div class="text-xl font-bold">{{ number_format($ppa->getTotalAe(), 0, ',', ' ') }} FCFA</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Total CP</div>
                        <div class="text-xl font-bold">{{ number_format($ppa->getTotalCp(), 0, ',', ' ') }} FCFA</div>
                    </div>
                </div>
            </div>

        </div>
    @endif
</x-filament-panels::page>