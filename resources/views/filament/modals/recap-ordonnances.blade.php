{{-- resources/views/filament/modals/recap-ordonnances.blade.php --}}

@php
    $beneficiaire = $donnees['beneficiaire'] ?? null;

    // ✅ Fallback : essayer de récupérer depuis l'engagement
    if (!$beneficiaire && $engagement->beneficiaire) {
        $beneficiaire = $engagement->beneficiaire;
    }

    // ✅ Fallback : essayer depuis le document source
    if (!$beneficiaire && $engagement->engageable) {
        if ($engagement->estBonCommande()) {
            $beneficiaire = $engagement->engageable->fournisseur;
        } elseif ($engagement->estDecision()) {
            $beneficiaire = $engagement->engageable->personnel;
        }
    }
@endphp

@if (!$beneficiaire)
    <div class="rounded-lg bg-red-50 dark:bg-red-900/20 p-4 text-sm text-red-700 dark:text-red-300">
        ❌ Impossible de continuer : aucun bénéficiaire valide n'est associé à cet engagement.
        <div class="mt-2 text-xs">
            Debug: Engagement ID: {{ $engagement->id }}, Type: {{ $engagement->beneficiaire_type }}, ID Bénéf:
            {{ $engagement->beneficiaire_id }}
        </div>
    </div>
    @php return; @endphp
@endif

@php
    // Sécurisation des montants
    $montantBrut = $donnees['montant_brut'] ?? 0;
    $montantTVA = $donnees['montant_tva'] ?? 0;
    $montantTSR = $donnees['montant_tsr'] ?? 0;
    $montantTTC = $donnees['montant_ttc'] ?? 0;
    $montantIR = $donnees['montant_ir'] ?? 0;
    $montantCNPS = $donnees['montant_cnps'] ?? 0;
    $montantIRNC = $donnees['montant_irnc'] ?? 0;
    $autresRetenues = $donnees['autres_retenues'] ?? 0;
    $montantNet = $donnees['montant_net'] ?? 0;

    // Calcul du total des retenues selon le type
    if ($engagement->estBonCommande()) {
        $totalRetenues = $montantIR + $montantTVA + $montantTSR;
    } else {
        $totalRetenues = $montantIR + $montantCNPS + $montantIRNC + $autresRetenues;
    }

    $nomBeneficiaire =
        $beneficiaire->raison_sociale ??
        ($beneficiaire->nom_complet ?? ($beneficiaire->name ?? 'Bénéficiaire principal'));
@endphp

<div class="space-y-4">

    {{-- En-tête Engagement --}}
    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">
            📋 Engagement : {{ $engagement->numero }}
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ $engagement->objet }}
        </p>
        <div class="mt-1 text-xs text-gray-500">
            Type :
            @if ($engagement->estBonCommande())
                Bon de Commande
            @elseif ($engagement->estDecision())
                Décision Administrative
            @else
                Engagement manuel
            @endif
        </div>
    </div>

    {{-- Tableau récapitulatif --}}
    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Description</th>
                    <th class="px-4 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Montant</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">

                {{-- Montant HT (si applicable) --}}
                @if ($montantBrut > 0)
                    <tr>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">Montant Hors Taxe (HT)</td>
                        <td class="px-4 py-2 text-right text-gray-900 dark:text-gray-100">
                            {{ number_format($montantBrut, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                @endif

                {{-- TVA (si applicable pour BC) --}}
                @if ($engagement->estBonCommande() && $montantTVA > 0)
                    <tr>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">TVA (19.25%)</td>
                        <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">
                            + {{ number_format($montantTVA, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                @endif

                {{-- Total TTC --}}
                <tr class="bg-blue-50 dark:bg-blue-900/20">
                    <td class="px-4 py-2 font-semibold text-gray-900 dark:text-gray-100">Montant Total TTC</td>
                    <td class="px-4 py-2 text-right font-bold text-blue-600 dark:text-blue-400">
                        {{ number_format($montantTTC, 0, ',', ' ') }} FCFA
                    </td>
                </tr>

                {{-- Séparateur Retenues --}}
                <tr class="bg-gray-100 dark:bg-gray-800">
                    <td colspan="2"
                        class="px-4 py-2 text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">
                        Retenues et Impôts
                    </td>
                </tr>

                {{-- ✅ Pour Bon de Commande : TOUJOURS afficher IR, TVA, TSR --}}
                @if ($engagement->estBonCommande())
                    <tr class="{{ $montantIR == 0 ? 'opacity-60' : '' }}">
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                            Impôt sur Revenu (IR)
                            @if ($montantIR == 0)
                                <span class="text-xs italic text-gray-400">(exonéré)</span>
                            @endif
                        </td>
                        <td
                            class="px-4 py-2 text-right {{ $montantIR > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                            - {{ number_format($montantIR, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>

                    <tr class="{{ $montantTVA == 0 ? 'opacity-60' : '' }}">
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                            TVA à reverser
                            @if ($montantTVA == 0)
                                <span class="text-xs italic text-gray-400">(non applicable)</span>
                            @endif
                        </td>
                        <td
                            class="px-4 py-2 text-right {{ $montantTVA > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                            - {{ number_format($montantTVA, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>

                    <tr class="{{ $montantTSR == 0 ? 'opacity-60' : '' }}">
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                            TSR (Taxe Spéciale sur le Revenu)
                            @if ($montantTSR == 0)
                                <span class="text-xs italic text-gray-400">(exonéré)</span>
                            @endif
                        </td>
                        <td
                            class="px-4 py-2 text-right {{ $montantTSR > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                            - {{ number_format($montantTSR, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                @else
                    {{-- ✅ Pour Décision Administrative : TOUJOURS afficher IR, CNPS, IRNC, Autres --}}
                    <tr class="{{ $montantIR == 0 ? 'opacity-60' : '' }}">
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                            Impôt sur Revenu (IR)
                            @if ($montantIR == 0)
                                <span class="text-xs italic text-gray-400">(exonéré)</span>
                            @endif
                        </td>
                        <td
                            class="px-4 py-2 text-right {{ $montantIR > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                            - {{ number_format($montantIR, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>

                    <tr class="{{ $montantCNPS == 0 ? 'opacity-60' : '' }}">
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                            Cotisations CNPS ({{ $donnees['taux_cnps'] ?? 4.2 }}%)
                            @if ($montantCNPS == 0)
                                <span class="text-xs italic text-gray-400">(exonéré)</span>
                            @endif
                        </td>
                        <td
                            class="px-4 py-2 text-right {{ $montantCNPS > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                            - {{ number_format($montantCNPS, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>

                    <tr class="{{ $montantIRNC == 0 ? 'opacity-60' : '' }}">
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                            IRNC ({{ $donnees['taux_irnc'] ?? 11 }}%)
                            @if ($montantIRNC == 0)
                                <span class="text-xs italic text-gray-400">(exonéré)</span>
                            @endif
                        </td>
                        <td
                            class="px-4 py-2 text-right {{ $montantIRNC > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                            - {{ number_format($montantIRNC, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>

                    <tr class="{{ $autresRetenues == 0 ? 'opacity-60' : '' }}">
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                            Autres retenues
                            @if ($autresRetenues == 0)
                                <span class="text-xs italic text-gray-400">(aucune)</span>
                            @endif
                        </td>
                        <td
                            class="px-4 py-2 text-right {{ $autresRetenues > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                            - {{ number_format($autresRetenues, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                @endif

                {{-- Total retenues --}}
                <tr class="bg-orange-50 dark:bg-orange-900/20">
                    <td class="px-4 py-2 font-semibold text-gray-900 dark:text-gray-100">Total Retenues</td>
                    <td class="px-4 py-2 text-right font-bold text-orange-600 dark:text-orange-400">
                        {{ number_format($totalRetenues, 0, ',', ' ') }} FCFA
                    </td>
                </tr>

                {{-- Montant Net --}}
                <tr class="bg-green-50 dark:bg-green-900/20">
                    <td class="px-4 py-2">
                        <div class="font-semibold text-gray-900 dark:text-gray-100">Net à payer au bénéficiaire</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $nomBeneficiaire }}</div>
                    </td>
                    <td class="px-4 py-2 text-right font-bold text-green-600 dark:text-green-400">
                        {{ number_format($montantNet, 0, ',', ' ') }} FCFA
                    </td>
                </tr>

            </tbody>
        </table>
    </div>

    {{-- Ordonnances qui seront créées --}}
    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4">
        <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-3">
            📄 Ordonnances qui seront créées :
        </h4>

        <ul class="space-y-3">
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
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $nomBeneficiaire }}</div>
                </div>
                <div class="text-right">
                    <div class="text-lg font-bold text-green-600 dark:text-green-400">
                        {{ number_format($montantNet, 0, ',', ' ') }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">FCFA</div>
                </div>
            </li>

            {{-- ✅ OP Impôt : Créée SEULEMENT si total retenues > 0 --}}
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
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Trésor Public</div>

                        {{-- ✅ Détail : Afficher TOUTES les retenues (même celles à 0 pour transparence) --}}
                        <div class="mt-2 space-y-1 text-xs">
                            @if ($engagement->estBonCommande())
                                <div
                                    class="{{ $montantIR > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-gray-400' }}">
                                    • IR : {{ number_format($montantIR, 0, ',', ' ') }} FCFA
                                    @if ($montantIR == 0)
                                        <span class="italic">(exonéré)</span>
                                    @endif
                                </div>
                                <div
                                    class="{{ $montantTVA > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-gray-400' }}">
                                    • TVA : {{ number_format($montantTVA, 0, ',', ' ') }} FCFA
                                    @if ($montantTVA == 0)
                                        <span class="italic">(non applicable)</span>
                                    @endif
                                </div>
                                <div
                                    class="{{ $montantTSR > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-gray-400' }}">
                                    • TSR : {{ number_format($montantTSR, 0, ',', ' ') }} FCFA
                                    @if ($montantTSR == 0)
                                        <span class="italic">(exonéré)</span>
                                    @endif
                                </div>
                            @else
                                <div
                                    class="{{ $montantIR > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-gray-400' }}">
                                    • IR : {{ number_format($montantIR, 0, ',', ' ') }} FCFA
                                    @if ($montantIR == 0)
                                        <span class="italic">(exonéré)</span>
                                    @endif
                                </div>
                                <div
                                    class="{{ $montantCNPS > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-gray-400' }}">
                                    • CNPS : {{ number_format($montantCNPS, 0, ',', ' ') }} FCFA
                                    @if ($montantCNPS == 0)
                                        <span class="italic">(exonéré)</span>
                                    @endif
                                </div>
                                <div
                                    class="{{ $montantIRNC > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-gray-400' }}">
                                    • IRNC : {{ number_format($montantIRNC, 0, ',', ' ') }} FCFA
                                    @if ($montantIRNC == 0)
                                        <span class="italic">(exonéré)</span>
                                    @endif
                                </div>
                                <div
                                    class="{{ $autresRetenues > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-gray-400' }}">
                                    • Autres : {{ number_format($autresRetenues, 0, ',', ' ') }} FCFA
                                    @if ($autresRetenues == 0)
                                        <span class="italic">(aucune)</span>
                                    @endif
                                </div>
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
            @else
                {{-- Si total retenues = 0, afficher un message --}}
                <li
                    class="flex items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div
                        class="flex-shrink-0 w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mr-3">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="text-sm text-gray-500 dark:text-gray-400 italic">
                            Pas d'OP Impôt (toutes les retenues sont exonérées)
                        </div>
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
                    {{ number_format($montantTTC, 0, ',', ' ') }} FCFA
                </span>
            </div>
            <p class="text-xs text-blue-600 dark:text-blue-300 mt-1">
                {{ $totalRetenues > 0 ? '2 ordonnances seront créées' : '1 ordonnance sera créée (aucune retenue)' }}
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
