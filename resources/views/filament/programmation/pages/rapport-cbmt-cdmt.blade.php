<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php
        $cbmt = $this->getCbmt();
        $cdmt = $this->getCdmt();
        $annexeB = $this->getAnnexeB();
        $ecart = $cdmt?->getEcartAvecCbmt();
    @endphp

    @if ($cbmt)
        <div class="mt-6 space-y-6">

            {{-- RESSOURCES PAR TITRES (détail des lignes sous chaque titre) --}}
            <div class="fi-section rounded-xl bg-white p-6 dark:bg-gray-800 overflow-x-auto">
                <h2 class="text-lg font-bold mb-3">Prévision à moyen terme des ressources par titres</h2>
                @include('filament.programmation.partials.cbmt-par-titres', ['cbmt' => $cbmt, 'nature' => 'ressource'])
            </div>

            {{-- DÉPENSES PAR TITRES (détail des lignes sous chaque titre) --}}
            <div class="fi-section rounded-xl bg-white p-6 dark:bg-gray-800 overflow-x-auto">
                <h2 class="text-lg font-bold mb-3">Prévision à moyen terme des dépenses par titres</h2>
                @include('filament.programmation.partials.cbmt-par-titres', ['cbmt' => $cbmt, 'nature' => 'depense'])
            </div>

            {{-- ÉQUILIBRE RESSOURCES / DÉPENSES --}}
            <div class="fi-section rounded-xl bg-white p-6 dark:bg-gray-800">
                <h2 class="text-lg font-bold mb-3">Équilibre ressources − dépenses</h2>
                @include('filament.programmation.partials.cbmt-equilibre', ['cbmt' => $cbmt])
            </div>

            {{-- Sections du CDMT : uniquement si un CDMT est choisi --}}
            @if (!$cdmt)
                <div class="fi-section rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                    Choisissez un CDMT de ce CBMT pour afficher le test de cohérence CDMT ↔ CBMT et la programmation (Annexe B).
                </div>
            @else

            {{-- TEST ECART CDMT <-> CBMT --}}
            <div class="fi-section rounded-xl bg-white p-6 dark:bg-gray-800">
                <h2 class="text-lg font-bold mb-3">Test de cohérence CDMT ↔ CBMT</h2>
                <table class="w-full text-sm border">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr><th class="border p-2">Année</th><th class="border p-2">Plafond CBMT</th><th class="border p-2">Programmé CDMT</th><th class="border p-2">Écart</th></tr>
                    </thead>
                    <tbody>
                        @foreach (['n_plus_1' => 'N+1', 'n_plus_2' => 'N+2', 'n_plus_3' => 'N+3'] as $key => $label)
                            <tr>
                                <td class="border p-2 text-center">{{ $label }}</td>
                                <td class="border p-2 text-right">{{ number_format($ecart[$key]['plafond'], 0, ',', ' ') }}</td>
                                <td class="border p-2 text-right">{{ number_format($ecart[$key]['programme'], 0, ',', ' ') }}</td>
                                <td class="border p-2 text-right font-bold {{ $ecart[$key]['ecart'] < 0 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ number_format($ecart[$key]['ecart'], 0, ',', ' ') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- ANNEXE B --}}
            <div class="fi-section rounded-xl bg-white p-6 dark:bg-gray-800">
                <h2 class="text-lg font-bold mb-3">Annexe B — Mémo de programmation financière du triennat</h2>
                @foreach ($annexeB as $sp)
                    <div class="mb-4 border rounded-lg p-3">
                        <h3 class="font-bold">{{ $sp['sous_programme']->libelle }}</h3>
                        @foreach ($sp['actions'] as $act)
                            <div class="ml-3 mt-2">
                                <div class="text-sm font-semibold">{{ $act['action']?->libelle ?? 'Sans action' }}</div>
                                <table class="w-full text-xs border mt-1">
                                    <thead class="bg-gray-50 dark:bg-gray-900">
                                        <tr>
                                            <th class="border p-1 text-left">Activité</th><th class="border p-1">Nature</th>
                                            <th class="border p-1">Maturité</th><th class="border p-1">Coût total</th>
                                            <th class="border p-1">N+1 AE</th><th class="border p-1">N+1 CP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($act['lignes'] as $l)
                                            <tr>
                                                <td class="border p-1">{{ $l['libelle'] }}</td>
                                                <td class="border p-1 text-center">{{ $l['nature'] }}</td>
                                                <td class="border p-1 text-center">{{ $l['maturite'] ?? '—' }}</td>
                                                <td class="border p-1 text-right">{{ number_format($l['cout_total'], 0, ',', ' ') }}</td>
                                                <td class="border p-1 text-right">{{ number_format($l['n_plus_1_ae'], 0, ',', ' ') }}</td>
                                                <td class="border p-1 text-right">{{ number_format($l['n_plus_1_cp'], 0, ',', ' ') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="text-xs text-right font-semibold mt-1">TOTAL ACTION : {{ number_format($act['total_action_ae'], 0, ',', ' ') }}</div>
                            </div>
                        @endforeach
                        <div class="text-sm text-right font-bold mt-2 border-t pt-1">TOTAL SOUS-PROGRAMME : {{ number_format($sp['total_sp'], 0, ',', ' ') }}</div>
                    </div>
                @endforeach
            </div>

            @endif

        </div>
    @endif
</x-filament-panels::page>