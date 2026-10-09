<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Règle du jeu ───────────────────────────────── --}}
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 text-sm text-amber-900 dark:text-amber-100">
            <strong>↩️ Réinitialiser un collectif</strong> annule chacun de ses mouvements (effets recalculés
            sur les lignes touchées), supprime logiquement les lignes budgétaires et les virements qu'il a créés,
            puis supprime le collectif et met à jour les agrégats du tableau de bord.
            <br>
            <span class="font-semibold">Condition bloquante :</span> la réinitialisation n'est refusée que si elle
            ferait passer une ligne sous zéro — disponible négatif après retrait des crédits engagés, prévision
            inférieure aux recouvrements déjà encaissés, ou ligne créée par le collectif encore portée par des
            engagements / des mouvements extérieurs. Les collectifs déjà <em>adoptés</em> sont traitables, mais
            l'exercice ne doit pas être clôturé.
        </div>
        {{-- ── Sélection ──────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    Collectifs de l'exercice
                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">({{ count($lignesCollectifs) }})</span>
                </h2>
                <div class="flex gap-2">
                    <x-filament::button size="xs" color="gray" wire:click="toutSelectionner">
                        Tout sélectionner
                    </x-filament::button>
                    <x-filament::button size="xs" color="gray" wire:click="viderSelection">
                        Rien
                    </x-filament::button>
                </div>
            </div>

            @if ($lignesCollectifs === [])
                <p class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">
                    Aucun collectif budgétaire pour cet exercice.
                </p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2 w-10"></th>
                            <th class="px-4 py-2 text-left">N°</th>
                            <th class="px-4 py-2 text-left">Libellé</th>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-right">Mouvements</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($lignesCollectifs as $c)
                            @php $choisi = in_array($c['id'], $this->selectionnesTries(), true); @endphp
                            <tr class="{{ $choisi ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}">
                                <td class="px-4 py-2">
                                    <input type="checkbox" class="rounded"
                                           wire:click="toggle({{ $c['id'] }})"
                                           @checked($choisi)>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs">{{ $c['numero'] ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $c['libelle'] }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $c['date'] ?? '—' }}</td>
                                <td class="px-4 py-2">
                                    <x-filament::badge :color="match ($c['statut']) { 'adopte' => 'success', 'annule' => 'danger', default => 'gray' }">
                                        {{ $c['statut'] }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-4 py-2 text-right">{{ $c['mouvements'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- ── Option : forcer les retraits qui surengagent ── --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm px-4 py-3">
            <label for="tolerer-surengagement" class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" id="tolerer-surengagement" class="mt-1 rounded"
                       wire:model.live="tolererSurengagement">
                <span class="text-sm text-gray-700 dark:text-gray-200">
                    <span class="font-semibold">Autoriser le surengagement.</span>
                    Les retraits qui font passer une ligne sous ce qui est déjà engagé ne bloquent plus&nbsp;: les
                    crédits reviennent à leur montant initial et <strong>les engagements sont conservés</strong>,
                    la ligne ressort donc en disponible négatif et devra être réabondée.
                    <span class="text-gray-500 dark:text-gray-400">
                        À n'utiliser que pour remettre un exercice à plat. Les autres blocages (ligne créée portant
                        des engagements, écritures extérieures, exercice clôturé) restent bloquants.
                    </span>
                </span>
            </label>
        </div>

        {{-- ── Aperçu ─────────────────────────────────────── --}}
        @if ($rapport)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3 flex-wrap">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Aperçu</h2>
                    @if ($rapport['nb_blocages'] === 0)
                        <x-filament::badge color="success">✅ Prêt — aucun blocage</x-filament::badge>
                    @elseif ($rapport['nb_prets'] > 0)
                        <x-filament::badge color="warning">{{ $rapport['nb_prets'] }} prêt(s) · {{ $rapport['nb_ecartes'] }} bloqué(s)</x-filament::badge>
                    @else
                        <x-filament::badge color="danger">⛔ Aucun collectif réinitialisable</x-filament::badge>
                    @endif
                    @if (($rapport['nb_surengagees'] ?? 0) > 0)
                        <x-filament::badge color="danger">{{ $rapport['nb_surengagees'] }} ligne(s) surengagée(s)</x-filament::badge>
                    @endif
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($rapport['collectifs'] as $r)
                        <div class="px-4 py-3">
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <span class="font-semibold">{{ $r['numero'] }}</span>
                                <span class="text-gray-700 dark:text-gray-200">{{ $r['libelle'] }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $r['nb_mouvements'] }} mouvement(s) ·
                                    {{ $r['nb_lignes_crees'] }} ligne(s) créée(s) ·
                                    {{ $r['nb_virements'] }} virement(s) ·
                                    effet net {{ number_format($r['effet_total'], 0, ',', ' ') }} FCFA
                                </span>
                            </div>

                            @if (empty($r['blocages']))
                                @if (!empty($r['avertissements']))
                                    @foreach ($r['avertissements'] as $a)
                                        <div class="mt-2 ml-3 text-sm text-amber-700 dark:text-amber-300">⚠️ {{ $a['message'] }}</div>
                                    @endforeach
                                @else
                                    <div class="mt-1 ml-3 text-sm text-emerald-700 dark:text-emerald-400">
                                        Toutes les lignes concernées gardent un solde positif : la réinitialisation est sûre.
                                    </div>
                                @endif
                            @else
                                @foreach ($r['blocages'] as $b)
                                    <div class="mt-2 ml-3 text-sm text-red-700 dark:text-red-400">⛔ {{ $b['message'] }}</div>
                                @endforeach
                            @endif

                            @if ($r['impacts'] !== [])
                                <div class="mt-3 overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-50 dark:bg-gray-700/50 uppercase text-gray-500 dark:text-gray-400">
                                            <tr>
                                                <th class="px-3 py-1.5 text-left">Ligne</th>
                                                <th class="px-3 py-1.5 text-left">Nature</th>
                                                <th class="px-3 py-1.5 text-right">Crédit avant</th>
                                                <th class="px-3 py-1.5 text-right">Retiré</th>
                                                <th class="px-3 py-1.5 text-right">Crédit après</th>
                                                <th class="px-3 py-1.5 text-right">Déjà consommé</th>
                                                <th class="px-3 py-1.5 text-right">Solde après</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            @foreach ($r['impacts'] as $i)
                                                <tr>
                                                    <td class="px-3 py-1.5">
                                                        <span class="font-mono">{{ $i['code'] }}</span>
                                                        <span class="text-gray-500 dark:text-gray-400">— {{ $i['libelle'] }}</span>
                                                        @if ($i['creee'])
                                                            <x-filament::badge size="xs" color="warning">créée par ce collectif</x-filament::badge>
                                                        @endif
                                                        @if ($i['surengage'] && empty($r['blocages']))
                                                            <x-filament::badge size="xs" color="danger">surengagée</x-filament::badge>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-1.5">{{ $i['type'] }}</td>
                                                    <td class="px-3 py-1.5 text-right whitespace-nowrap">{{ number_format($i['avant'], 0, ',', ' ') }}</td>
                                                    <td class="px-3 py-1.5 text-right whitespace-nowrap {{ $i['retire'] < 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                                                        {{ $i['retire'] < 0 ? '+' : '−' }}{{ number_format(abs($i['retire']), 0, ',', ' ') }}
                                                    </td>
                                                    <td class="px-3 py-1.5 text-right font-medium whitespace-nowrap">{{ number_format($i['apres'], 0, ',', ' ') }}</td>
                                                    <td class="px-3 py-1.5 text-right whitespace-nowrap text-gray-500 dark:text-gray-400">{{ number_format($i['consomme'], 0, ',', ' ') }}</td>
                                                    <td class="px-3 py-1.5 text-right font-semibold whitespace-nowrap {{ $i['solde'] < -0.01 ? 'text-red-700 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                                                        {{ number_format($i['solde'], 0, ',', ' ') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Montants en FCFA. « Solde après » = disponible restant après réinitialisation
                                    (crédit après − déjà consommé) ; le « Retiré » n'impute que ce collectif, le
                                    « Crédit après » tient compte de toute la sélection.
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($rapport['nb_blocages'] > 0 && $rapport['nb_prets'] > 0)
                    <div class="px-4 py-3 bg-amber-50 dark:bg-amber-900/20 text-sm text-amber-900 dark:text-amber-100">
                        Seuls les {{ $rapport['nb_prets'] }} collectif(s) sans blocage seront réinitialisés.
                        Les {{ $rapport['nb_ecartes'] }} collectif(s) signalés resteront en place jusqu'à la levée
                        de leurs blocages.
                    </div>
                @elseif ($rapport['nb_blocages'] > 0)
                    <div class="px-4 py-3 bg-red-50 dark:bg-red-900/20 text-sm text-red-800 dark:text-red-200">
                        Aucun collectif de la sélection n'est réinitialisable : désengagez les lignes concernées,
                        ou retirez-les de la sélection.
                        @unless ($tolererSurengagement)
                            <br>
                            Si les blocages signalés portent tous sur un disponible devenu négatif, cochez
                            «&nbsp;Autoriser le surengagement&nbsp;» pour passer outre et remettre les lignes à leur
                            montant initial.
                        @endunless
                    </div>
                @endif
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Coche un ou plusieurs collectifs : l'aperçu de leurs effets et de leurs blocages s'affiche
                immédiatement. Le bouton «&nbsp;Réinitialiser la sélection&nbsp;» s'active dès qu'au moins un
                collectif est prêt&nbsp;; les collectifs encore bloqués ne sont pas touchés.
            </p>
        @endif

    </div>
</x-filament-panels::page>
