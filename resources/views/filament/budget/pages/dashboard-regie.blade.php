<x-filament-panels::page>
    @php
        $stats = $this->getStats();
        $regies = $stats['regies'];
        $journal = $this->getLivreJournal();
    @endphp

    {{-- ══ STATISTIQUES GLOBALES ══════════════════════════════════ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">

        <div class="rounded-xl p-4 shadow
            bg-blue-50 dark:bg-blue-900/30
            border border-blue-200 dark:border-blue-700
            text-blue-800 dark:text-blue-200">
            <div class="text-xs font-semibold uppercase tracking-wide opacity-70">
                Régies actives
            </div>
            <div class="text-3xl font-bold mt-1">{{ $stats['nb_regies_actives'] }}</div>
        </div>

        <div class="rounded-xl p-4 shadow
            bg-purple-50 dark:bg-purple-900/30
            border border-purple-200 dark:border-purple-700
            text-purple-800 dark:text-purple-200">
            <div class="text-xs font-semibold uppercase tracking-wide opacity-70">
                Menus Dépenses
            </div>
            <div class="text-3xl font-bold mt-1">{{ $stats['nb_md_actifs'] }}</div>
        </div>

        <div class="rounded-xl p-4 shadow
            bg-green-50 dark:bg-green-900/30
            border border-green-200 dark:border-green-700
            text-green-800 dark:text-green-200">
            <div class="text-xs font-semibold uppercase tracking-wide opacity-70">
                Total Alloué
            </div>
            <div class="text-xl font-bold mt-1">
                {{ number_format($stats['total_alloue'], 0, ',', ' ') }} FCFA
            </div>
        </div>

        <div
            class="rounded-xl p-4 shadow
            bg-{{ $stats['taux_moyen'] >= 90 ? 'red' : ($stats['taux_moyen'] >= 70 ? 'yellow' : 'emerald') }}-50
            dark:bg-{{ $stats['taux_moyen'] >= 90 ? 'red' : ($stats['taux_moyen'] >= 70 ? 'yellow' : 'emerald') }}-900/30
            border border-{{ $stats['taux_moyen'] >= 90 ? 'red' : ($stats['taux_moyen'] >= 70 ? 'yellow' : 'emerald') }}-200
            dark:border-{{ $stats['taux_moyen'] >= 90 ? 'red' : ($stats['taux_moyen'] >= 70 ? 'yellow' : 'emerald') }}-700
            text-{{ $stats['taux_moyen'] >= 90 ? 'red' : ($stats['taux_moyen'] >= 70 ? 'yellow' : 'emerald') }}-800
            dark:text-{{ $stats['taux_moyen'] >= 90 ? 'red' : ($stats['taux_moyen'] >= 70 ? 'yellow' : 'emerald') }}-200">
            <div class="text-xs font-semibold uppercase tracking-wide opacity-70">
                Taux moyen consommation
            </div>
            <div class="text-3xl font-bold mt-1">{{ $stats['taux_moyen'] }}%</div>
        </div>
    </div>

    {{-- ══ SITUATION PAR RÉGIE ════════════════════════════════════ --}}
    <div class="rounded-xl shadow mb-6
        bg-white dark:bg-gray-900
        border border-gray-200 dark:border-gray-700">

        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                📊 Situation par Régie / Menu Dépense
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800
                        text-gray-600 dark:text-gray-400
                        text-xs uppercase tracking-wide">
                        <th class="px-4 py-3 text-left">N° / Libellé</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Responsable</th>
                        <th class="px-4 py-3 text-right">Alloué</th>
                        <th class="px-4 py-3 text-right">Décaissé</th>
                        <th class="px-4 py-3 text-right">Dépensé</th>
                        <th class="px-4 py-3 text-right">Disponible</th>
                        <th class="px-4 py-3 text-center">Consommation</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($regies as $index => $regie)
                                    @php
                                        // ✅ Compatibilité array ET modèle Eloquent
                                        $r = is_array($regie) ? (object) $regie : $regie;
                                        $taux = $r->taux_consommation ?? 0;
                                        $color = $taux >= 90 ? 'red' : ($taux >= 70 ? 'yellow' : 'green');
                                        // ✅ Clé unique pour Livewire
                                        $key = is_array($regie)
                                            ? ($regie['id'] ?? $index)
                                            : ($regie->id ?? $index);
                                    @endphp
                                    {{-- ✅ wire:key explicite — évite getKey() sur array --}}
                                    <tr wire:key="regie-{{ $key }}" class="border-t border-gray-100 dark:border-gray-800
                                        hover:bg-gray-50 dark:hover:bg-gray-800/50
                                        text-gray-800 dark:text-gray-200">
                                        <td class="px-4 py-3">
                                            <div class="font-semibold">{{ $r->numero }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $r->libelle }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 rounded text-xs font-semibold
                                                {{ ($r->type ?? '') === 'rav'
                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'
                        : 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300'
                                                }}">
                                                {{ ($r->type ?? '') === 'rav' ? 'RAV' : 'MD' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            {{ $r->responsable?->name ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono">
                                            {{ number_format($r->montant_alloue ?? 0, 0, ',', ' ') }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono
                                            text-yellow-600 dark:text-yellow-400">
                                            {{ number_format($r->montant_decaisse ?? 0, 0, ',', ' ') }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono
                                            text-red-600 dark:text-red-400">
                                            {{ number_format($r->montant_depense ?? 0, 0, ',', ' ') }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono font-bold
                                            {{ ($r->montant_disponible ?? 0) < 0
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-green-600 dark:text-green-400'
                                            }}">
                                            {{ number_format($r->montant_disponible ?? 0, 0, ',', ' ') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                    <div class="h-2 rounded-full
                                                        bg-{{ $color }}-500 dark:bg-{{ $color }}-400"
                                                        style="width: {{ min(100, $taux) }}%">
                                                    </div>
                                                </div>
                                                <span class="text-xs font-semibold w-12 text-right
                                                    text-{{ $color }}-600 dark:text-{{ $color }}-400">
                                                    {{ $taux }}%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                    @empty
                        <tr wire:key="regie-empty">
                            <td colspan="8" class="px-4 py-8 text-center text-gray-400 dark:text-gray-600">
                                Aucune régie active
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-300 dark:border-gray-600
                        bg-gray-100 dark:bg-gray-800
                        font-bold text-gray-800 dark:text-gray-200">
                        <td colspan="3" class="px-4 py-3">TOTAUX</td>
                        <td class="px-4 py-3 text-right font-mono">
                            {{ number_format($stats['total_alloue'], 0, ',', ' ') }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono
                            text-yellow-600 dark:text-yellow-400">
                            {{ number_format($stats['total_decaisse'], 0, ',', ' ') }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono
                            text-red-600 dark:text-red-400">
                            {{ number_format($stats['total_depense'], 0, ',', ' ') }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono
                            text-green-600 dark:text-green-400">
                            {{ number_format($stats['total_disponible'], 0, ',', ' ') }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- ══ LIVRE JOURNAL ════════════════════════════════════════════ --}}
    <div class="rounded-xl shadow mb-6
        bg-white dark:bg-gray-900
        border border-gray-200 dark:border-gray-700">

        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700
            flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                📒 Livre Journal des Dépenses
            </h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ $journal->count() }} opération(s)
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800
                        text-gray-600 dark:text-gray-400
                        text-xs uppercase tracking-wide">
                        <th class="px-3 py-3 text-left">Date</th>
                        <th class="px-3 py-3 text-left">N°</th>
                        <th class="px-3 py-3 text-left">Type</th>
                        <th class="px-3 py-3 text-left">Régie</th>
                        <th class="px-3 py-3 text-left">Nomenclature</th>
                        <th class="px-3 py-3 text-left">Fournisseur</th>
                        <th class="px-3 py-3 text-left">Objet</th>
                        <th class="px-3 py-3 text-right">MHT</th>
                        <th class="px-3 py-3 text-right">TVA</th>
                        <th class="px-3 py-3 text-right">TTC</th>
                        <th class="px-3 py-3 text-right">IR</th>
                        <th class="px-3 py-3 text-right">NAP</th>
                        <th class="px-3 py-3 text-center">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totalMht = $totalTva = $totalTtc = $totalIr = $totalNap = 0; @endphp

                    @forelse($journal as $jIndex => $op)
                                        @php
                                            $totalMht += $op['montant_ht'] ?? 0;
                                            $totalTva += $op['montant_tva'] ?? 0;
                                            $totalTtc += $op['montant_ttc'] ?? 0;
                                            $totalIr += $op['montant_ir'] ?? 0;
                                            $totalNap += $op['net_a_payer'] ?? 0;
                                        @endphp
                                        {{-- ✅ wire:key explicite sur chaque ligne journal --}}
                                        <tr wire:key="journal-{{ $jIndex }}-{{ $op['numero'] ?? $jIndex }}" class="border-t border-gray-100 dark:border-gray-800
                                            hover:bg-gray-50 dark:hover:bg-gray-800/50
                                            text-gray-800 dark:text-gray-200">
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                {{ \Carbon\Carbon::parse($op['date'])->format('d/m/Y') }}
                                            </td>
                                            <td class="px-3 py-2 font-mono text-xs font-semibold">
                                                {{ $op['numero'] }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-0.5 rounded text-xs
                                                    {{ ($op['type'] ?? '') === 'Achat Direct'
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                            : 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'
                                                    }}">
                                                    {{ $op['type'] ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-xs">{{ $op['regie'] ?? '—' }}</td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-0.5 rounded text-xs
                                                    bg-gray-100 dark:bg-gray-700
                                                    text-gray-700 dark:text-gray-300">
                                                    {{ $op['nomenclature'] ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-xs">
                                                {{ $op['fournisseur'] ?? '—' }}
                                            </td>
                                            <td class="px-3 py-2 text-xs max-w-32 truncate" title="{{ $op['objet'] ?? '' }}">
                                                {{ $op['objet'] ?? '—' }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-mono text-xs">
                                                {{ number_format($op['montant_ht'] ?? 0, 0, ',', ' ') }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-mono text-xs">
                                                {{ number_format($op['montant_tva'] ?? 0, 0, ',', ' ') }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-mono text-xs font-semibold">
                                                {{ number_format($op['montant_ttc'] ?? 0, 0, ',', ' ') }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-mono text-xs
                                                text-red-600 dark:text-red-400">
                                                {{ number_format($op['montant_ir'] ?? 0, 0, ',', ' ') }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-mono text-xs font-bold
                                                text-green-600 dark:text-green-400">
                                                {{ number_format($op['net_a_payer'] ?? 0, 0, ',', ' ') }}
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="px-2 py-0.5 rounded text-xs
                                                    {{ match ($op['statut'] ?? '') {
                            'valide' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
                            'paye', 'livre' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                        } }}">
                                                    {{ ucfirst($op['statut'] ?? '') }}
                                                </span>
                                            </td>
                                        </tr>
                    @empty
                        <tr wire:key="journal-empty">
                            <td colspan="13" class="px-4 py-8 text-center
                                text-gray-400 dark:text-gray-600">
                                Aucune dépense enregistrée
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-300 dark:border-gray-600
                        bg-gray-100 dark:bg-gray-800
                        font-bold text-gray-800 dark:text-gray-200 text-sm">
                        <td colspan="7" class="px-3 py-3 text-right">TOTAUX :</td>
                        <td class="px-3 py-3 text-right font-mono">
                            {{ number_format($totalMht, 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono">
                            {{ number_format($totalTva, 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono">
                            {{ number_format($totalTtc, 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono
                            text-red-600 dark:text-red-400">
                            {{ number_format($totalIr, 0, ',', ' ') }}
                        </td>
                        <td class="px-3 py-3 text-right font-mono
                            text-green-600 dark:text-green-400">
                            {{ number_format($totalNap, 0, ',', ' ') }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</x-filament-panels::page>