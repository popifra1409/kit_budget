<x-filament-panels::page>
    {{ $this->form }}

    @php
        $ca = $this->donnees();
        $f = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    @if (!$ca)
        <x-filament::section>Choisissez un exercice et un budget.</x-filament::section>
    @else
        <x-filament::section heading="Synthèse — exercice {{ $ca['exercice']->annee }}">
            @if (!$ca['cloture'])
                <p class="mb-3 text-sm text-warning-600">⚠️ La clôture des crédits de cet exercice n'est pas préparée : reports nuls, annulations estimées (crédits définitifs − payés).</p>
            @endif
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-lg border p-3 dark:border-gray-700">
                    <div class="text-xs text-gray-500">Recettes recouvrées / prévues</div>
                    <div class="text-lg font-bold">{{ $f($ca['resultat']['recettes_recouvrees']) }}</div>
                    <div class="text-xs text-gray-500">sur {{ $f($ca['recettes']['totaux']['definitif']) }} — {{ $ca['taux']['execution_recettes'] ?? '—' }} %</div>
                </div>
                <div class="rounded-lg border p-3 dark:border-gray-700">
                    <div class="text-xs text-gray-500">Dépenses payées / crédits définitifs</div>
                    <div class="text-lg font-bold">{{ $f($ca['resultat']['depenses_payees']) }}</div>
                    <div class="text-xs text-gray-500">sur {{ $f($ca['depenses']['totaux']['definitif']) }} — {{ $ca['taux']['paiement'] ?? '—' }} % (engagé : {{ $ca['taux']['engagement'] ?? '—' }} %)</div>
                </div>
                <div class="rounded-lg border p-3 dark:border-gray-700">
                    <div class="text-xs text-gray-500">Résultat de caisse / résultat corrigé</div>
                    <div class="text-lg font-bold {{ $ca['resultat']['resultat_caisse'] < 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $f($ca['resultat']['resultat_caisse']) }}</div>
                    <div class="text-xs text-gray-500">corrigé (RAR {{ $f($ca['resultat']['restes_a_recouvrer']) }}, restes à payer {{ $f($ca['resultat']['restes_a_payer']) }}) : <strong>{{ $f($ca['resultat']['resultat_corrige']) }}</strong></div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Dépenses par titre" collapsible>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left text-gray-500">
                        <th class="py-1 pr-3">Titre</th><th class="py-1 pr-3 text-right">Définitifs</th><th class="py-1 pr-3 text-right">Engagés</th>
                        <th class="py-1 pr-3 text-right">Payés</th><th class="py-1 pr-3 text-right">Restes à payer</th><th class="py-1 pr-3 text-right">Reportés</th><th class="py-1 text-right">Annulés</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($ca['depenses']['parTitre'] as $t)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-1 pr-3">{{ $t['libelle'] }}</td>
                                @foreach (['definitif', 'engage', 'paye', 'reste_a_payer', 'report', 'annule'] as $c)
                                    <td class="py-1 pr-3 text-right">{{ $f($t[$c]) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>