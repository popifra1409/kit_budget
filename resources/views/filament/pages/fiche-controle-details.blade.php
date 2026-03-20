<div class="space-y-6">
    {{-- En-tête avec informations principales --}}
    <div
        class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
        <h2 class="text-xl font-bold text-blue-900 dark:text-blue-100 mb-4">
            📊 Ligne Budgétaire : {{ $record->nomenclature?->code ?? 'N/A' }}
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Dotation Initiale --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">
                    Dotation Initiale
                </div>
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    {{ number_format($record->dotation_initiale ?? 0, 0, ',', ' ') }} FCFA
                </div>
            </div>

            {{-- Total Engagé --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">
                    Total Engagé
                </div>
                <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                    {{ number_format($totalEngage ?? 0, 0, ',', ' ') }} FCFA
                </div>
            </div>

            {{-- Disponible --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">
                    Crédits Disponibles
                </div>
                <div
                    class="text-2xl font-bold {{ ($record->disponible_engagement ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ number_format($record->disponible_engagement ?? 0, 0, ',', ' ') }} FCFA
                </div>
            </div>

            {{-- Taux de Consommation --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">
                    Taux de Consommation
                </div>
                <div
                    class="text-2xl font-bold {{ ($tauxConso ?? 0) < 80 ? 'text-green-600' : (($tauxConso ?? 0) < 100 ? 'text-orange-600' : 'text-red-600') }}">
                    {{ number_format($tauxConso ?? 0, 1) }}%
                </div>
            </div>
        </div>
    </div>

    {{-- Liste des Engagements --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="bg-gray-50 dark:bg-gray-900/50 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                📋 Liste des Engagements ({{ $engagements->count() ?? 0 }})
            </h3>
        </div>

        @if (isset($engagements) && $engagements->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                N°
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Date
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Type
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Référence
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Bénéficiaire
                            </th>
                            <th
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Montant Engagé
                            </th>
                            <th
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Statut
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($engagements as $index => $engagement)
                            @php
                                $engageable = $engagement->engageable;
                                $type = $engageable ? class_basename(get_class($engageable)) : 'Inconnu';
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    {{ $index + 1 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $engagement->date_engagement ? $engagement->date_engagement->format('d/m/Y') : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $type === 'BonCommande' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300' }}">
                                        {{ $type === 'BonCommande' ? 'BC' : ($type === 'DecisionAdministrative' ? 'DA' : $type) }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $engageable->numero ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    @if ($engageable && method_exists($engageable, 'fournisseur') && $engageable->fournisseur)
                                        {{ $engageable->fournisseur->raison_sociale ?? ($engageable->fournisseur->nom ?? '-') }}
                                    @elseif($engageable && method_exists($engageable, 'beneficiaire') && $engageable->beneficiaire)
                                        {{ $engageable->beneficiaire->nom_complet ?? ($engageable->beneficiaire->name ?? '-') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-right text-gray-900 dark:text-gray-100">
                                    {{ number_format($engagement->montant_engage ?? 0, 0, ',', ' ') }} FCFA
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ ($engagement->statut ?? 'valide') === 'valide' ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-900/50 dark:text-gray-300' }}">
                                        {{ ucfirst($engagement->statut ?? 'valide') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <td colspan="5"
                                class="px-6 py-4 text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                                TOTAL :
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-right text-orange-600 dark:text-orange-400">
                                {{ number_format($totalEngage ?? 0, 0, ',', ' ') }} FCFA
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <div class="text-gray-400 dark:text-gray-500 text-6xl mb-4">📭</div>
                <p class="text-gray-500 dark:text-gray-400 text-lg">
                    Aucun engagement enregistré sur cette ligne budgétaire
                </p>
            </div>
        @endif
    </div>

    {{-- Informations Complémentaires --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                    fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                        clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                    Nomenclature Budgétaire Complète
                </h3>
                <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                    <p><strong>Code :</strong> {{ $record->nomenclature?->code ?? 'N/A' }}</p>
                    <p><strong>Libellé :</strong> {{ $record->nomenclature?->libelle ?? 'N/A' }}</p>
                    <p><strong>Budget :</strong> {{ $record->budget?->libelle ?? 'N/A' }}</p>
                    <p><strong>Exercice :</strong> {{ $record->budget?->exercice?->annee ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
