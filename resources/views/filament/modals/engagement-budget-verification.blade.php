{{-- resources/views/filament/modals/engagement-budget-verification.blade.php --}}

<div class="space-y-6">
    {{-- MESSAGE PRINCIPAL - CRÉDIT SUFFISANT OU INSUFFISANT --}}
    @if (!$verifications['peut_engager'])
        {{-- ❌ CRÉDIT INSUFFISANT --}}
        <div class="p-6 rounded-lg bg-red-50 dark:bg-red-900/20 border-2 border-red-300 dark:border-red-700">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <svg class="w-12 h-12 text-red-600 dark:text-red-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-xl font-bold text-red-900 dark:text-red-100 mb-2">
                        ❌ Crédit budgétaire insuffisant
                    </h3>
                    <p class="text-red-800 dark:text-red-200 mb-3">
                        L'engagement ne peut pas être créé. Une ou plusieurs lignes budgétaires n'ont pas assez de
                        crédit disponible.
                    </p>
                    <div class="bg-red-100 dark:bg-red-900/40 rounded p-3">
                        <p class="font-semibold text-red-900 dark:text-red-100 mb-2">⚠️ Actions possibles :</p>
                        <ul class="text-sm text-red-800 dark:text-red-200 space-y-1 list-disc list-inside">
                            <li>Réduire le montant du bon de commande</li>
                            <li>Effectuer un virement budgétaire</li>
                            <li>Utiliser une autre nomenclature budgétaire</li>
                            <li>Contacter le responsable budgétaire</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- ✅ CRÉDIT SUFFISANT --}}
        <div class="p-6 rounded-lg bg-green-50 dark:bg-green-900/20 border-2 border-green-300 dark:border-green-700">
            <div class="flex items-center gap-4">
                <svg class="w-12 h-12 text-green-600 dark:text-green-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <h3 class="text-xl font-bold text-green-900 dark:text-green-100">
                        ✅ Crédit suffisant
                    </h3>
                    <p class="text-green-700 dark:text-green-300">
                        L'engagement peut être créé sans problème. Toutes les lignes budgétaires ont un crédit
                        disponible suffisant.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- En-tête récapitulatif --}}
    <div
        class="grid grid-cols-3 gap-4 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
        <div class="text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">Montant HT</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ number_format($bonCommande->montant_ht, 0, ',', ' ') }}
            </p>
            <p class="text-xs text-gray-500">FCFA</p>
        </div>
        <div class="text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">Impôt/Retenue</p>
            <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                {{ number_format($bonCommande->montant_ir ?? 0, 0, ',', ' ') }}
            </p>
            <p class="text-xs text-gray-500">FCFA</p>
        </div>
        <div class="text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">Net à engager</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                {{ number_format($verifications['montant_total'], 0, ',', ' ') }}
            </p>
            <p class="text-xs text-gray-500">FCFA</p>
        </div>
    </div>

    {{-- Détail par ligne budgétaire --}}
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Détail par ligne budgétaire ({{ $verifications['nombre_nomenclatures'] }} ligne(s))
        </h3>

        @foreach ($verifications['lignes_budgetaires'] as $ligne)
            <div
                class="p-4 rounded-lg border {{ $ligne['suffisant'] ? 'border-green-200 dark:border-green-800 bg-white dark:bg-gray-800' : 'border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-900/10' }}">
                {{-- En-tête ligne budgétaire --}}
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">
                            {{ $ligne['nomenclature']->code }}
                        </h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $ligne['nomenclature']->libelle }}
                        </p>
                    </div>
                    @if ($ligne['suffisant'])
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                            ✓ OK
                        </span>
                    @else
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                            ✗ Insuffisant
                        </span>
                    @endif
                </div>

                {{-- Articles/Services concernés --}}
                @if (count($ligne['lignes_designation']) > 0)
                    <div class="mb-3 p-2 bg-gray-50 dark:bg-gray-900/50 rounded">
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Articles/Services :</p>
                        <ul class="text-sm text-gray-700 dark:text-gray-300 list-disc list-inside">
                            @foreach ($ligne['lignes_designation'] as $designation)
                                <li>{{ $designation }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Montants --}}
                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Provision totale</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ number_format($ligne['provision_totale'], 0, ',', ' ') }} FCFA
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Déjà engagé</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ number_format($ligne['deja_engage'], 0, ',', ' ') }} FCFA
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Disponible avant</p>
                        <p class="text-lg font-semibold text-blue-600 dark:text-blue-400">
                            {{ number_format($ligne['disponible_avant'], 0, ',', ' ') }} FCFA
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">À engager maintenant</p>
                        <p class="text-lg font-semibold text-purple-600 dark:text-purple-400">
                            {{ number_format($ligne['montant_a_engager'], 0, ',', ' ') }} FCFA
                        </p>
                    </div>
                </div>

                {{-- Résultat --}}
                <div
                    class="p-3 rounded {{ $ligne['suffisant'] ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20' }}">
                    @if ($ligne['suffisant'])
                        <p class="text-sm font-medium text-green-900 dark:text-green-100">
                            ✓ Disponible après : <span
                                class="font-bold">{{ number_format($ligne['disponible_apres'], 0, ',', ' ') }}
                                FCFA</span>
                        </p>
                    @else
                        <p class="text-sm font-medium text-red-900 dark:text-red-100">
                            ✗ Manque : <span class="font-bold">{{ number_format($ligne['manque'], 0, ',', ' ') }}
                                FCFA</span>
                        </p>
                    @endif
                </div>

                {{-- Jauge visuelle --}}
                <div class="mt-3">
                    <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 mb-1">
                        <span>Taux d'utilisation avant : {{ $ligne['taux_utilisation_avant'] }}%</span>
                        <span>Après : {{ $ligne['taux_utilisation'] }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                        <div class="h-3 rounded-full transition-all {{ $ligne['taux_utilisation'] < 70 ? 'bg-green-500' : ($ligne['taux_utilisation'] < 90 ? 'bg-orange-500' : 'bg-red-500') }}"
                            style="width: {{ min($ligne['taux_utilisation'], 100) }}%">
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Information sur l'engagement --}}
    <div class="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-200 dark:border-gray-700">
        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">ℹ️ Information sur l'engagement</h4>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-600 dark:text-gray-400">Numéro BC :</span>
                <span class="font-medium text-gray-900 dark:text-white ml-2">{{ $bonCommande->numero }}</span>
            </div>
            <div>
                <span class="text-gray-600 dark:text-gray-400">Fournisseur :</span>
                <span
                    class="font-medium text-gray-900 dark:text-white ml-2">{{ $bonCommande->fournisseur?->raison_sociale ?? 'N/A' }}</span>
            </div>
            <div>
                <span class="text-gray-600 dark:text-gray-400">Date :</span>
                <span class="font-medium text-gray-900 dark:text-white ml-2">{{ now()->format('d/m/Y') }}</span>
            </div>
            <div>
                <span class="text-gray-600 dark:text-gray-400">Exercice :</span>
                <span
                    class="font-medium text-gray-900 dark:text-white ml-2">{{ $bonCommande->exercice?->annee ?? 'N/A' }}</span>
            </div>
        </div>
    </div>
</div>
