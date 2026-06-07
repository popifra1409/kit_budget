{{-- resources/views/filament/modals/ordonnances-list.blade.php --}}
<div class="space-y-4">

    {{-- En-tête engagement --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
        <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">
            📋 Engagement {{ $engagement->numero }}
        </h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-700 dark:text-gray-300">Type :</span>
                <span class="font-medium text-gray-900 dark:text-white ml-2">
                    {{ $engagement->type_engagement }}
                </span>
            </div>
            <div>
                <span class="text-gray-700 dark:text-gray-300">Montant engagé :</span>
                <span class="font-medium text-gray-900 dark:text-white ml-2">
                    {{ number_format($engagement->montant_engage, 0, ',', ' ') }} FCFA
                </span>
            </div>
            <div class="col-span-2">
                <span class="text-gray-700 dark:text-gray-300">Objet :</span>
                <span class="font-medium text-gray-900 dark:text-white ml-2">
                    {{ $engagement->objet }}
                </span>
            </div>
        </div>
    </div>

    {{-- Liste des ordonnances --}}
    <div class="space-y-3">
        @forelse($ordonnances as $ordonnance)
        <div class="bg-white dark:bg-gray-800 rounded-lg border overflow-hidden
                {{ $ordonnance->type_ordonnance === 'standard'
                    ? 'border-green-200 dark:border-green-800'
                    : 'border-orange-200 dark:border-orange-800' }}">

            {{-- En-tête ordonnance --}}
            <div class="p-4 border-b border-gray-200 dark:border-gray-700
                    {{ $ordonnance->type_ordonnance === 'standard'
                        ? 'bg-green-50 dark:bg-green-900/10'
                        : 'bg-orange-50 dark:bg-orange-900/10' }}">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">

                        {{-- Icône --}}
                        @if ($ordonnance->type_ordonnance === 'standard')
                        <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900
                                    flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        @else
                        <div class="w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900
                                    flex items-center justify-center">
                            <svg class="w-6 h-6 text-orange-600 dark:text-orange-400"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        @endif

                        <div>
                            <h4 class="font-semibold text-gray-900 dark:text-white">
                                {{ $ordonnance->numero }}
                            </h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $ordonnance->type_ordonnance === 'standard' ? 'OP Standard' : 'OP Impôt' }}
                            </p>
                        </div>
                    </div>

                    <div class="text-right">
                        <p class="text-2xl font-bold
                                {{ $ordonnance->type_ordonnance === 'standard'
                                    ? 'text-green-600 dark:text-green-400'
                                    : 'text-orange-600 dark:text-orange-400' }}">
                            {{ number_format($ordonnance->montant_net, 0, ',', ' ') }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">FCFA</p>
                    </div>
                </div>
            </div>

            {{-- Corps ordonnance --}}
            <div class="p-4 space-y-3">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Bénéficiaire :</span>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ $ordonnance->beneficiaire?->raison_sociale
                                ?? $ordonnance->beneficiaire?->nom_complet
                                ?? $ordonnance->beneficiaire?->name
                                ?? 'N/A' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Date d'émission :</span>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ $ordonnance->date_emission?->format('d/m/Y') ?? 'N/A' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Statut :</span>
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium
                                @switch($ordonnance->statut)
                                    @case('emise')   bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                    @case('visee')   bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 @break
                                    @case('payee')   bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                    @case('rejetee') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                                    @default         bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                @endswitch">
                            {{ ucfirst($ordonnance->statut) }}
                        </span>
                    </div>

                    @if ($ordonnance->periode)
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Période :</span>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ $ordonnance->periode }}
                        </p>
                    </div>
                    @endif
                </div>

                @if ($ordonnance->objet)
                <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                    <span class="text-gray-600 dark:text-gray-400 text-sm">Objet :</span>
                    <p class="text-sm text-gray-900 dark:text-white mt-1">
                        {{ $ordonnance->objet }}
                    </p>
                </div>
                @endif

                {{-- ✅ Détail impôts (OP Impôt uniquement) --}}
                @if ($ordonnance->type_ordonnance === 'impot')
                @php
                    $detailImpots = $ordonnance->getDetailImpots();

                    // ✅ Définition des lignes à afficher
                    $lignesImpots = [
                        'ir'        => 'IR',
                        'tva'       => 'TVA',
                        'tsr'       => 'TSR',
                        'cnps'      => 'CNPS',
                        'irnc'      => 'IRNC',
                        'redevance' => 'Redevance audiovisuelle',
                        'feicom'    => 'FEICOM',
                        'autres'    => 'Autres retenues',
                    ];

                    // ✅ Total exact = somme des lignes affichées (sans arrondi ni approximation)
                    $totalExact = 0;
                    foreach ($lignesImpots as $key => $label) {
                        $totalExact += (float) ($detailImpots[$key] ?? 0);
                    }
                @endphp

                <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Détail des retenues et impôts
                    </p>
                    <div class="space-y-1.5 text-sm">

                        {{-- ✅ Lignes affichées uniquement si > 0 --}}
                        @foreach ($lignesImpots as $key => $label)
                        @if (($detailImpots[$key] ?? 0) > 0)
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">
                                {{ $label }} :
                            </span>
                            <span class="font-medium text-gray-900 dark:text-white">
                                {{ number_format((float) ($detailImpots[$key]), 0, ',', ' ') }} FCFA
                            </span>
                        </div>
                        @endif
                        @endforeach

                        {{-- ✅ Total = somme exacte des lignes affichées --}}
                        <div class="flex justify-between pt-2 border-t border-gray-200
                                    dark:border-gray-700 font-semibold">
                            <span class="text-gray-900 dark:text-white">Total :</span>
                            <span class="text-orange-600 dark:text-orange-400">
                                {{ number_format($totalExact, 0, ',', ' ') }} FCFA
                            </span>
                        </div>
                    </div>
                </div>
                @endif

            </div>
        </div>
        @empty
        <div class="text-center py-8">
            <svg class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-600 mb-4"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="text-gray-500 dark:text-gray-400">Aucune ordonnance trouvée</p>
        </div>
        @endforelse
    </div>

    {{-- Récapitulatif global --}}
    @if ($ordonnances->count() > 0)
    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4
                border border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <span class="font-semibold text-gray-900 dark:text-white">
                Total des ordonnances :
            </span>
            <span class="text-xl font-bold text-blue-600 dark:text-blue-400">
                {{ number_format($ordonnances->sum('montant_net'), 0, ',', ' ') }} FCFA
            </span>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
            {{ $ordonnances->count() }} ordonnance(s) émise(s)
        </p>
    </div>
    @endif

</div>