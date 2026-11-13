<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php
        $rapport = $this->getRapport();
    @endphp

    @if ($rapport)
        @php $anomalies = $rapport->anomalies(); @endphp

        {{-- Même structure que le PDF, habillée pour l'écran : une table par action, l'en-tête
             de l'action hors du tableau, l'activité en première colonne fusionnée à ses tâches.
             La version PDF se passe de rowspan (dompdf le coupe mal entre deux pages). --}}
        <style>
            .cadre-logique-etendu table { width: 100%; border-collapse: collapse; }
            .cadre-logique-etendu th, .cadre-logique-etendu td { border: 1px solid #999; padding: 4px; text-align: left; vertical-align: top; font-size: 11px; }
            .cadre-logique-etendu th { background-color: #e8e8e8; text-align: center; }
            .cadre-logique-etendu td.num { text-align: right; white-space: nowrap; }
            .cadre-logique-etendu .entete-action { background-color: #d8e4f0; border: 1px solid #999; padding: 8px; margin-top: 16px; }
            .cadre-logique-etendu .entete-action .titre { font-weight: 700; font-size: 12px; }
            .cadre-logique-etendu .entete-action .objectif, .cadre-logique-etendu .entete-action .description { font-style: italic; color: #555; }
            .cadre-logique-etendu .entete-action .totaux { font-weight: 600; }
            .cadre-logique-etendu td.activite { background-color: #efefef; }
            .cadre-logique-etendu td.activite .titre, .cadre-logique-etendu td.activite .totaux { font-weight: 600; }
            .cadre-logique-etendu .meta { color: #666; font-weight: 400; font-style: normal; }
            .cadre-logique-etendu tr.vide td { font-style: italic; color: #888; }
            .cadre-logique-etendu tr.detail td { background-color: #f7f7f7; font-size: 10px; }
            .cadre-logique-etendu tr.detail .legende { font-weight: 700; text-decoration: underline; }
            .cadre-logique-etendu tr.total td { font-weight: 700; background-color: #e8e8e8; }
            .cadre-logique-etendu .palier + .palier { padding-left: 10px; }
            .dark .cadre-logique-etendu th, .dark .cadre-logique-etendu td { border-color: #555; }
            .dark .cadre-logique-etendu { color: rgb(255 255 255 / .85); }
            .dark .cadre-logique-etendu th { background-color: #333; }
            .dark .cadre-logique-etendu .entete-action { background-color: #24344a; }
            .dark .cadre-logique-etendu td.activite { background-color: #2e2e2e; }
            .dark .cadre-logique-etendu .meta { color: #aaa; }
        </style>

        <div class="cadre-logique-etendu mt-6 space-y-4">
            <div class="fi-section rounded-xl bg-white p-4 text-sm dark:bg-gray-800">
                <span class="font-semibold">{{ $rapport->titre() }}</span>
                — exercice {{ $rapport->exercice->annee }}
                · {{ count($rapport->lignes()) }} ligne(s) de tâche
                @if ($anomalies['sans_budget'] !== [])
                    · {{ count($anomalies['sans_budget']) }} sans compte lié
                @endif
                · AE total {{ $rapport->formatMontant($rapport->totalAe()) }}
                · CP total {{ $rapport->formatMontant($rapport->totalCp()) }}
            </div>

            @if ($rapport->estVide())
                <p class="text-sm italic">Aucune action rattachée à ce sous-programme pour cet exercice.</p>
            @else
                <div class="fi-section rounded-xl bg-white p-4 text-sm dark:bg-gray-800">
                    <span class="font-semibold">Pilotage</span>
                    <ul class="mt-1 list-inside">
                        @foreach ($rapport->pilotage() as $ligne)
                            <li>{{ $ligne['programme'] }} — {{ $ligne['pilote'] ?: 'agent non désigné' }}</li>
                        @endforeach
                    </ul>

                    {{-- L'état descend sous ce titre : action, puis ses activités. --}}
                    @include('filament.planification.pdf._cadre-logique-etendu-table', [
                        'blocs' => $rapport->blocs(),
                    ])
                </div>

                @if ($anomalies['reclassees'] !== [] || $anomalies['absentes'] !== [] || $anomalies['sans_budget'] !== [])
                    <div class="fi-section rounded-xl bg-white p-4 text-xs dark:bg-gray-800">
                        <span class="font-semibold">Points de vigilance</span>
                        @if ($anomalies['reclassees'] !== [])
                            <p>{{ count($anomalies['reclassees']) }} sous-tâche(s) sont imprimées sous l'activité de
                                leur tâche mère alors que leur propre rattachement les donne à une autre activité
                                ({{ collect($anomalies['reclassees'])->pluck('code')->unique()->implode(', ') }}).
                                Les totaux suivent la hiérarchie de saisie, comme les agrégats du modèle.</p>
                        @endif
                        @if ($anomalies['absentes'] !== [])
                            <p>{{ count($anomalies['absentes']) }} tâche(s) porteuse(s) de ces activités ne descendent
                                d'aucune tâche de l'état.</p>
                        @endif
                        @if ($anomalies['sans_budget'] !== [])
                            @php $codesHorsCredit = collect($anomalies['sans_budget'])->pluck('code')->unique()->values(); @endphp
                            <p>{{ count($anomalies['sans_budget']) }} ligne(s) de ce sous-programme n'ont reçu jusqu'ici
                                aucune nomenclature : elles sont bien imprimées ci-dessus, leur montant reste compté dans
                                les totaux comme à l'écran de saisie, mais aucun compte du budget n'y correspond
                                ({{ $codesHorsCredit->take(10)->implode(', ') }}{{ $codesHorsCredit->count() > 10 ? ', …' : '' }}).
                                Reliez-leur un compte pour qu'elles aient une case de crédit.</p>
                        @endif
                    </div>
                @endif
            @endif
        </div>
    @endif
</x-filament-panels::page>
