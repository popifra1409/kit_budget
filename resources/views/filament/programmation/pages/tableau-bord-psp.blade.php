<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @if ($this->getPsp())
        @php
            $psp = $this->getPsp();
            $synthese = $psp->getSyntheseParExercice();
            $cumul = $psp->getCumulPluriannuel();
        @endphp

        <div class="mt-6 space-y-6">

            {{-- CARTES DE SYNTHESE CUMULEE --}}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="fi-section rounded-xl bg-white p-4 dark:bg-gray-800">
                    <div class="text-xs text-gray-500">Exercices couverts</div>
                    <div class="text-xl font-bold">{{ $cumul['nb_exercices_couverts'] }} / {{ $cumul['nb_exercices_psp'] ?? '—' }}</div>
                </div>
                <div class="fi-section rounded-xl bg-white p-4 dark:bg-gray-800">
                    <div class="text-xs text-gray-500">Total AE prévu</div>
                    <div class="text-lg font-bold">{{ number_format($cumul['total_ae_prevu'], 0, ',', ' ') }}</div>
                </div>
                <div class="fi-section rounded-xl bg-white p-4 dark:bg-gray-800">
                    <div class="text-xs text-gray-500">Total CP prévu</div>
                    <div class="text-lg font-bold">{{ number_format($cumul['total_cp_prevu'], 0, ',', ' ') }}</div>
                </div>
                <div class="fi-section rounded-xl bg-white p-4 dark:bg-gray-800">
                    <div class="text-xs text-gray-500">Engagé cumulé (réel)</div>
                    <div class="text-lg font-bold text-blue-600">{{ number_format($cumul['total_engage'], 0, ',', ' ') }}</div>
                </div>
                <div class="fi-section rounded-xl bg-white p-4 dark:bg-gray-800">
                    <div class="text-xs text-gray-500">Disponible cumulé</div>
                    <div class="text-lg font-bold text-green-600">{{ number_format($cumul['total_disponible'], 0, ',', ' ') }}</div>
                </div>
            </div>

            {{-- DETAIL ANNEE PAR ANNEE --}}
            <div class="fi-section rounded-xl bg-white p-6 dark:bg-gray-800">
                <h2 class="text-lg font-bold mb-3">Exécution année par année</h2>

                @if ($synthese->isEmpty())
                    <p class="text-gray-400 text-sm">Aucun PPA créé pour ce PSP pour le moment.</p>
                @else
                    <table class="w-full text-sm border">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="border p-2">Exercice</th>
                                <th class="border p-2">N° PPA</th>
                                <th class="border p-2">Statut PPA</th>
                                <th class="border p-2">AE prévu</th>
                                <th class="border p-2">CP prévu</th>
                                <th class="border p-2 bg-blue-50 dark:bg-blue-900/20">Engagé (réel)</th>
                                <th class="border p-2 bg-blue-50 dark:bg-blue-900/20">Disponible</th>
                                <th class="border p-2 bg-blue-50 dark:bg-blue-900/20">Taux exéc.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($synthese as $row)
                                <tr>
                                    <td class="border p-2 font-semibold text-center">{{ $row['exercice'] }}</td>
                                    <td class="border p-2">{{ $row['ppa_numero'] }}</td>
                                    <td class="border p-2">
                                        <span class="px-2 py-0.5 rounded-full text-xs
                                            {{ $row['statut_ppa'] === 'valide' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                            {{ ucfirst($row['statut_ppa']) }}
                                        </span>
                                    </td>
                                    <td class="border p-2 text-right">{{ number_format($row['ae_prevu'], 0, ',', ' ') }}</td>
                                    <td class="border p-2 text-right">{{ number_format($row['cp_prevu'], 0, ',', ' ') }}</td>
                                    <td class="border p-2 text-right bg-blue-50/50 dark:bg-blue-900/10">{{ number_format($row['engage_reel'], 0, ',', ' ') }}</td>
                                    <td class="border p-2 text-right bg-blue-50/50 dark:bg-blue-900/10">{{ number_format($row['disponible'], 0, ',', ' ') }}</td>
                                    <td class="border p-2 text-right bg-blue-50/50 dark:bg-blue-900/10">
                                        <span class="{{ $row['taux_execution'] > 90 ? 'text-red-600 font-bold' : ($row['taux_execution'] > 60 ? 'text-orange-600' : 'text-green-600') }}">
                                            {{ $row['taux_execution'] }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-100 dark:bg-gray-900 font-bold">
                                <td class="border p-2" colspan="3">CUMUL {{ $psp->periode_debut?->format('Y') }}–{{ $psp->periode_fin?->format('Y') }}</td>
                                <td class="border p-2 text-right">{{ number_format($cumul['total_ae_prevu'], 0, ',', ' ') }}</td>
                                <td class="border p-2 text-right">{{ number_format($cumul['total_cp_prevu'], 0, ',', ' ') }}</td>
                                <td class="border p-2 text-right">{{ number_format($cumul['total_engage'], 0, ',', ' ') }}</td>
                                <td class="border p-2 text-right">{{ number_format($cumul['total_disponible'], 0, ',', ' ') }}</td>
                                <td class="border p-2 text-right">
                                    {{ $cumul['total_cp_prevu'] > 0 ? round(($cumul['total_engage'] / $cumul['total_cp_prevu']) * 100, 1) : 0 }}%
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>

        </div>
    @endif
</x-filament-panels::page>