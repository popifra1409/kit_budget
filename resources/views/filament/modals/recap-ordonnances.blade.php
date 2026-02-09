{{-- resources/views/filament/modals/recap-ordonnances.blade.php --}}

<div class="space-y-4">
    {{-- Résumé de l'engagement --}}
    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">
            📋 Engagement : {{ $engagement->numero }}
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ $engagement->objet }}
        </p>
        <div class="mt-2 text-xs text-gray-500 dark:text-gray-500">
            Type : {{ $engagement->type_engagement }}
            @if ($engagement->estBonCommande())
                (Bon de Commande)
            @elseif($engagement->estDecision())
                (Décision Administrative)
            @else
                (Engagement manuel)
            @endif
        </div>
    </div>

    {{-- Tableau récapitulatif --}}
    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th
                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Description
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Montant
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                {{-- Montant HT (si disponible) --}}
                @if ($donnees['montant_brut'] > 0)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                            Montant Hors Taxe (HT)
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 text-right">
                            {{ number_format($donnees['montant_brut'], 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                @endif

                {{-- TVA (si disponible) --}}
                @if ($donnees['montant_tva'] > 0)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            TVA (19.25%)
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 text-right">
                            + {{ number_format($donnees['montant_tva'], 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                @endif

                {{-- Montant TTC --}}
                <tr class="bg-blue-50 dark:bg-blue-900/10">
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Montant Total TTC
                    </td>
                    <td class="px-4 py-3 text-sm font-bold text-blue-600 dark:text-blue-400 text-right">
                        {{ number_format($donnees['montant_ttc'], 0, ',', ' ') }} FCFA
                    </td>
                </tr>

                {{-- Séparateur --}}
                <tr class="bg-gray-100 dark:bg-gray-800">
                    <td colspan="2"
                        class="px-4 py-2 text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                        Retenues et Impôts
                    </td>
                </tr>

                {{-- Impôt sur Revenu --}}
                @if ($donnees['montant_ir'] > 0)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            Impôt sur Revenu (IR)
                        </td>
                        <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400 text-right">
                            - {{ number_format($donnees['montant_ir'], 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                @endif

                {{-- Pour Bon de Commande : TVA et TSR --}}
                @if ($engagement->estBonCommande())
                    @if ($donnees['montant_tva'] > 0)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                TVA à reverser
                            </td>
                            <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400 text-right">
                                - {{ number_format($donnees['montant_tva'], 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                    @endif

                    @if ($donnees['montant_tsr'] > 0)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                TSR (Taxe Spéciale sur le Revenu)
                            </td>
                            <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400 text-right">
                                - {{ number_format($donnees['montant_tsr'], 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                    @endif
                @else
                    {{-- Pour Décision Administrative : CNPS, IRNC, Autres --}}
                    @if ($donnees['montant_cnps'] > 0)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                Cotisations CNPS
                            </td>
                            <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400 text-right">
                                - {{ number_format($donnees['montant_cnps'], 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                    @endif

                    @if ($donnees['montant_irnc'] > 0)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                IRNC (Impôt sur Revenu Non Commercial)
                            </td>
                            <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400 text-right">
                                - {{ number_format($donnees['montant_irnc'], 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                    @endif

                    @if ($donnees['autres_retenues'] > 0)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                Autres retenues
                            </td>
                            <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400 text-right">
                                - {{ number_format($donnees['autres_retenues'], 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                    @endif
                @endif

                {{-- Montant Net --}}
                <tr class="bg-green-50 dark:bg-green-900/20">
                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                        <div>Net à payer au bénéficiaire</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 font-normal mt-1">
                            {{ $donnees['beneficiaire']?->raison_sociale ?? ($donnees['beneficiaire']?->name ?? 'Bénéficiaire principal') }}
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm font-bold text-green-600 dark:text-green-400 text-right">
                        {{ number_format($donnees['montant_net'], 0, ',', ' ') }} FCFA
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Ordonnances qui seront créées --}}
    @php
        // Calculer le total des retenues selon le type de document
        $totalRetenues = $donnees['montant_ir'];

        if ($engagement->estBonCommande()) {
            // Pour BC : IR + TVA + TSR
            $totalRetenues += $donnees['montant_tva'] + $donnees['montant_tsr'];
        } else {
            // Pour DA : IR + CNPS + IRNC + Autres
            $totalRetenues += $donnees['montant_cnps'] + $donnees['montant_irnc'] + $donnees['autres_retenues'];
        }
    @endphp

    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4">
        <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-3">
            📄 Ordonnances qui seront créées :
        </h4>

        <ul class="space-y-3 text-sm">
            {{-- OP Standard --}}
            <li
                class="flex items-start p-3 bg-white dark:bg-gray-800 rounded-lg border border-green-200 dark:border-green-800">
                <div
                    class="flex-shrink-0 w-10 h-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center mr-3">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                        </path>
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="font-semibold text-gray-900 dark:text-white">OP Standard</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $donnees['beneficiaire']?->raison_sociale ?? ($donnees['beneficiaire']?->name ?? 'Bénéficiaire principal') }}
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-lg font-bold text-green-600 dark:text-green-400">
                        {{ number_format($donnees['montant_net'], 0, ',', ' ') }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">FCFA</div>
                </div>
            </li>

            {{-- OP Impôt (TOTAL) --}}
            @if ($totalRetenues > 0)
                <li
                    class="flex items-start p-3 bg-white dark:bg-gray-800 rounded-lg border border-orange-200 dark:border-orange-800">
                    <div
                        class="flex-shrink-0 w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900 flex items-center justify-center mr-3">
                        <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                            </path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="font-semibold text-gray-900 dark:text-white">OP Impôts et Retenues</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Trésor Public
                        </div>

                        {{-- Détail des retenues selon le type --}}
                        <div class="mt-2 space-y-1 text-xs text-orange-700 dark:text-orange-300">
                            @if ($donnees['montant_ir'] > 0)
                                <div>• IR : {{ number_format($donnees['montant_ir'], 0, ',', ' ') }} FCFA</div>
                            @endif

                            @if ($engagement->estBonCommande())
                                {{-- Détails pour BC --}}
                                @if ($donnees['montant_tva'] > 0)
                                    <div>• TVA : {{ number_format($donnees['montant_tva'], 0, ',', ' ') }} FCFA</div>
                                @endif
                                @if ($donnees['montant_tsr'] > 0)
                                    <div>• TSR : {{ number_format($donnees['montant_tsr'], 0, ',', ' ') }} FCFA</div>
                                @endif
                            @else
                                {{-- Détails pour DA --}}
                                @if ($donnees['montant_cnps'] > 0)
                                    <div>• CNPS : {{ number_format($donnees['montant_cnps'], 0, ',', ' ') }} FCFA</div>
                                @endif
                                @if ($donnees['montant_irnc'] > 0)
                                    <div>• IRNC : {{ number_format($donnees['montant_irnc'], 0, ',', ' ') }} FCFA</div>
                                @endif
                                @if ($donnees['autres_retenues'] > 0)
                                    <div>• Autres retenues :
                                        {{ number_format($donnees['autres_retenues'], 0, ',', ' ') }} FCFA</div>
                                @endif
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-bold text-orange-600 dark:text-orange-400">
                            {{ number_format($totalRetenues, 0, ',', ' ') }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">FCFA</div>
                    </div>
                </li>
            @endif
        </ul>

        {{-- Total vérification --}}
        <div class="mt-4 pt-3 border-t border-blue-200 dark:border-blue-800">
            <div class="flex justify-between items-center">
                <span class="text-sm font-semibold text-blue-900 dark:text-blue-100">
                    Total des ordonnances :
                </span>
                <span class="text-lg font-bold text-blue-600 dark:text-blue-400">
                    {{ number_format($donnees['montant_ttc'], 0, ',', ' ') }} FCFA
                </span>
            </div>
            <p class="text-xs text-blue-600 dark:text-blue-300 mt-1">
                @if ($totalRetenues > 0)
                    2 ordonnances seront créées
                @else
                    1 ordonnance sera créée
                @endif
            </p>
        </div>
    </div>

    {{-- Avertissement --}}
    <div class="rounded-lg bg-yellow-50 dark:bg-yellow-900/20 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                        clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                    <strong>Cette action est irréversible.</strong> Les ordonnances seront créées avec les montants
                    ci-dessus et seront prêtes à être visées par le contrôle financier.
                </p>
            </div>
        </div>
    </div>
</div>
