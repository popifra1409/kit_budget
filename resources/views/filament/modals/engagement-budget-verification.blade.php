<div class="space-y-6">
    {{-- En-tête récapitulatif --}}
    <div
        class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900 p-6 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1 font-medium">Montant HT</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ number_format($bonCommande->montant_ht, 0, ',', ' ') }}
                    <span class="text-sm font-normal text-gray-500">FCFA</span>
                </p>
            </div>
            <div class="text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1 font-medium">IR à retenir</p>
                <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                    {{ number_format($bonCommande->montant_ir, 0, ',', ' ') }}
                    <span class="text-sm font-normal text-gray-500">FCFA</span>
                </p>
            </div>
            <div class="text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-1 font-medium">Net à engager</p>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                    {{ number_format($verifications['montant_total'], 0, ',', ' ') }}
                    <span class="text-sm font-normal text-gray-500">FCFA</span>
                </p>
            </div>
        </div>
    </div>

    {{-- Message de statut --}}
    <div
        class="rounded-lg p-4 {{ $verifications['peut_engager'] ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' }} shadow-sm">
        <div class="flex items-center gap-3">
            @if ($verifications['peut_engager'])
                <svg class="w-6 h-6 text-green-600 dark:text-green-400 flex-shrink-0" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-green-800 dark:text-green-200 font-semibold">
                    ✅ {{ $verifications['message'] }}
                </p>
            @else
                <svg class="w-6 h-6 text-red-600 dark:text-red-400 flex-shrink-0" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-red-800 dark:text-red-200 font-semibold">
                    ❌ {{ $verifications['message'] }}
                </p>
            @endif
        </div>
    </div>

    {{-- Détail par ligne budgétaire --}}
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                </path>
            </svg>
            Vérification par ligne budgétaire ({{ $verifications['nombre_nomenclatures'] }})
        </h3>

        @foreach ($verifications['lignes_budgetaires'] as $index => $verification)
            <div
                class="rounded-lg border {{ $verification['suffisant'] ? 'border-green-200 dark:border-green-800' : 'border-red-200 dark:border-red-800' }} bg-white dark:bg-gray-800 overflow-hidden shadow-sm">
                {{-- En-tête de la ligne --}}
                <div
                    class="p-4 {{ $verification['suffisant'] ? 'bg-green-50 dark:bg-green-900/10' : 'bg-red-50 dark:bg-red-900/10' }} border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center justify-center w-6 h-6 rounded-full {{ $verification['suffisant'] ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200' }} text-xs font-bold">
                                    {{ $index + 1 }}
                                </span>
                                <h4 class="font-semibold text-gray-900 dark:text-white">
                                    {{ $verification['nomenclature']->code }}
                                </h4>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                {{ $verification['nomenclature']->libelle }}
                            </p>
                        </div>

                        @if ($verification['suffisant'])
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                Crédit suffisant
                            </span>
                        @else
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                Crédit insuffisant
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Corps de la ligne --}}
                <div class="p-4 space-y-4">
                    {{-- Articles concernés --}}
                    <div
                        class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                </path>
                            </svg>
                            Articles/Services concernés :
                        </p>
                        <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                            @foreach ($verification['lignes_designation'] as $designation)
                                <li class="flex items-start gap-2">
                                    <span class="text-blue-500 dark:text-blue-400 mt-1">•</span>
                                    <span>{{ $designation }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Grille des montants --}}
                    <div class="grid grid-cols-2 gap-3">
                        {{-- Provision totale --}}
                        <div
                            class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 border border-blue-200 dark:border-blue-800">
                            <p class="text-xs text-blue-700 dark:text-blue-300 mb-1 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"></path>
                                    <path fill-rule="evenodd"
                                        d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                Provision totale
                            </p>
                            <p class="text-lg font-bold text-blue-900 dark:text-blue-100">
                                {{ number_format($verification['provision_totale'], 0, ',', ' ') }}
                            </p>
                        </div>

                        {{-- Déjà engagé --}}
                        <div
                            class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-3 border border-orange-200 dark:border-orange-800">
                            <p class="text-xs text-orange-700 dark:text-orange-300 mb-1">Déjà engagé</p>
                            <p class="text-lg font-bold text-orange-900 dark:text-orange-100">
                                {{ number_format($verification['deja_engage'], 0, ',', ' ') }}
                            </p>
                            <p class="text-xs text-orange-600 dark:text-orange-400 mt-1">
                                {{ $verification['taux_utilisation_avant'] }}% utilisé
                            </p>
                        </div>

                        {{-- Disponible avant --}}
                        <div
                            class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 border border-green-200 dark:border-green-800">
                            <p class="text-xs text-green-700 dark:text-green-300 mb-1">💰 Disponible avant</p>
                            <p class="text-lg font-bold text-green-900 dark:text-green-100">
                                {{ number_format($verification['disponible_avant'], 0, ',', ' ') }}
                            </p>
                        </div>

                        {{-- Montant à engager --}}
                        <div
                            class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3 border border-purple-200 dark:border-purple-800">
                            <p class="text-xs text-purple-700 dark:text-purple-300 mb-1">➖ À engager</p>
                            <p class="text-lg font-bold text-purple-900 dark:text-purple-100">
                                {{ number_format($verification['montant_a_engager'], 0, ',', ' ') }}
                            </p>
                        </div>
                    </div>

                    {{-- Résultat --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    {{ $verification['suffisant'] ? '✅ Disponible après engagement' : '❌ Manque' }}
                                </p>

                                @if ($verification['suffisant'])
                                    <div class="flex items-baseline gap-2">
                                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                                            {{ number_format($verification['disponible_apres'], 0, ',', ' ') }}
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">FCFA</p>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Taux d'utilisation après : {{ $verification['taux_utilisation'] }}%
                                    </p>
                                @else
                                    <div class="flex items-baseline gap-2">
                                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                                            {{ number_format($verification['manque'], 0, ',', ' ') }}
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">FCFA</p>
                                    </div>
                                    <p class="text-xs text-red-600 dark:text-red-400 mt-1 font-medium">
                                        Il manque {{ number_format($verification['manque'], 0, ',', ' ') }} FCFA pour
                                        engager cette ligne
                                    </p>
                                @endif
                            </div>

                            {{-- Jauge visuelle --}}
                            <div class="w-32">
                                <div class="relative">
                                    <div class="flex mb-2 items-center justify-between">
                                        <span
                                            class="text-xs font-semibold inline-block py-1 px-2 rounded {{ $verification['taux_utilisation'] > 90 ? 'text-red-600 bg-red-200 dark:bg-red-900' : ($verification['taux_utilisation'] > 70 ? 'text-orange-600 bg-orange-200 dark:bg-orange-900' : 'text-green-600 bg-green-200 dark:bg-green-900') }}">
                                            {{ min(100, $verification['taux_utilisation']) }}%
                                        </span>
                                    </div>
                                    <div
                                        class="overflow-hidden h-3 text-xs flex rounded-full bg-gray-200 dark:bg-gray-700">
                                        <div style="width:{{ min(100, $verification['taux_utilisation']) }}%"
                                            class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center transition-all duration-500 {{ $verification['taux_utilisation'] > 90 ? 'bg-red-500' : ($verification['taux_utilisation'] > 70 ? 'bg-orange-500' : 'bg-green-500') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Avertissement si crédit insuffisant --}}
    @if (!$verifications['peut_engager'])
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border-2 border-red-300 dark:border-red-800 p-4 shadow-sm">
            <div class="flex gap-3">
                <svg class="w-6 h-6 text-red-600 dark:text-red-400 flex-shrink-0" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
                <div class="flex-1">
                    <h4 class="text-sm font-semibold text-red-800 dark:text-red-200 mb-2">
                        💡 Actions recommandées :
                    </h4>
                    <ul class="text-sm text-red-700 dark:text-red-300 space-y-1.5">
                        <li class="flex items-start gap-2">
                            <span class="text-red-500 mt-0.5">•</span>
                            <span>Réduire le montant de la commande</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-red-500 mt-0.5">•</span>
                            <span>Demander un virement budgétaire vers cette ligne</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-red-500 mt-0.5">•</span>
                            <span>Utiliser une autre nomenclature budgétaire</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-red-500 mt-0.5">•</span>
                            <span>Solliciter un budget supplémentaire auprès de la direction</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Information supplémentaire --}}
    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-4">
        <div class="flex gap-2">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="currentColor"
                viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                    clip-rule="evenodd"></path>
            </svg>
            <p class="text-sm text-blue-800 dark:text-blue-200">
                <strong>Bon de commande :</strong> {{ $bonCommande->numero }} •
                <strong>Fournisseur :</strong> {{ $bonCommande->fournisseur->raison_sociale ?? 'N/A' }} •
                <strong>Date d'émission :</strong> {{ $bonCommande->date_emission->format('d/m/Y') }}
            </p>
        </div>
    </div>
</div>
