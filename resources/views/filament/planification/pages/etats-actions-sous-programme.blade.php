<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php
        $rapport = $this->getRapport();
    @endphp

    @if ($rapport)
        @php $anomalies = $rapport->anomalies(); @endphp

        {{-- Même structure que le PDF, habillée pour l'écran : l'en-tête de l'action est hors du
             tableau (il suit le titre de l'état), l'activité occupe la première colonne fusionnée
             à toutes les lignes de ses tâches. La version PDF se passe de rowspan. --}}
        <style>
            .etats-actions table { width: 100%; border-collapse: collapse; }
            .etats-actions th, .etats-actions td { border: 1px solid #999; padding: 4px; text-align: left; vertical-align: top; font-size: 11px; }
            .etats-actions th { background-color: #e8e8e8; text-align: center; }
            .etats-actions td.center { text-align: center; }
            .etats-actions td.num { text-align: right; white-space: nowrap; }
            .etats-actions .entete-action { background-color: #d8e4f0; border: 1px solid #999; padding: 8px; margin: 8px 0; }
            .etats-actions .entete-action .titre { font-weight: 700; font-size: 12px; }
            .etats-actions .entete-action .objectif, .etats-actions .entete-action .description { font-style: italic; color: #555; }
            .etats-actions .entete-action .totaux { font-weight: 600; }
            .etats-actions td.activite { background-color: #efefef; }
            .etats-actions td.activite .titre, .etats-actions td.activite .totaux { font-weight: 600; }
            .etats-actions .meta { color: #666; font-weight: 400; font-style: normal; }
            .etats-actions tr.vide td { font-style: italic; color: #888; }
            .etats-actions tr.detail td { background-color: #f7f7f7; font-size: 10px; }
            .etats-actions tr.detail .legende { font-weight: 700; text-decoration: underline; }
            .etats-actions tr.total td { font-weight: 700; background-color: #e8e8e8; }
            .etats-actions .palier + .palier { padding-left: 10px; }
            .dark .etats-actions th, .dark .etats-actions td { border-color: #555; }
            .dark .etats-actions { color: rgb(255 255 255 / .85); }
            .dark .etats-actions th { background-color: #333; }
            .dark .etats-actions .entete-action { background-color: #24344a; }
            .dark .etats-actions td.activite { background-color: #2e2e2e; }
            .dark .etats-actions .meta { color: #aaa; }
            .dark .etats-actions tr.detail td { background-color: #262626; }
        </style>

        <div class="etats-actions mt-6 space-y-4">
            <div class="fi-section rounded-xl bg-white p-4 text-sm dark:bg-gray-800">
                <span class="font-semibold">{{ $rapport->titre() }}</span>
                — exercice {{ $rapport->exercice->annee }}
                · {{ $rapport->nbEtats() }} état(s)
                · {{ count($rapport->lignes()) }} ligne(s) de tâche
                @if ($anomalies['sans_budget'] !== [])
                    · {{ count($anomalies['sans_budget']) }} sans compte lié
                @endif
                · AE {{ $rapport->formatMontant($rapport->totalAe()) }}
                · CP {{ $rapport->formatMontant($rapport->totalCp()) }}
            </div>

            @if ($rapport->estVide())
                <p class="text-sm italic">Aucune action rattachée à ce sous-programme pour cet exercice.</p>
            @else
                @if ($anomalies['reclassees'] !== [] || $anomalies['absentes'] !== [] || $anomalies['sans_budget'] !== [])
                    <div class="fi-section rounded-xl bg-white p-4 text-xs dark:bg-gray-800">
                        <span class="font-semibold">Points de vigilance</span>
                        @if ($anomalies['reclassees'] !== [])
                            <p>{{ count($anomalies['reclassees']) }} sous-tâche(s) sont imprimées sous l'activité de
                                leur tâche mère alors que leur propre rattachement les donne à une autre activité
                                ({{ collect($anomalies['reclassees'])->pluck('code')->unique()->implode(', ') }}).</p>
                        @endif
                        @if ($anomalies['absentes'] !== [])
                            <p>{{ count($anomalies['absentes']) }} tâche(s) porteuse(s) de ces activités ne descendent
                                d'aucune tâche de l'état.</p>
                        @endif
                        @if ($anomalies['sans_budget'] !== [])
                            @php $codesHorsCredit = collect($anomalies['sans_budget'])->pluck('code')->unique()->values(); @endphp
                            <p>{{ count($anomalies['sans_budget']) }} ligne(s) de ce sous-programme n'ont reçu jusqu'ici
                                aucune nomenclature : elles sont bien imprimées ci-dessous, leur montant reste compté dans
                                les totaux comme à l'écran de saisie, mais aucun compte du budget n'y correspond
                                ({{ $codesHorsCredit->take(10)->implode(', ') }}{{ $codesHorsCredit->count() > 10 ? ', …' : '' }}).
                                Reliez-leur un compte pour qu'elles aient une case de crédit.</p>
                        @endif
                    </div>
                @endif

                @foreach ($rapport->etats() as $etat)
                    <div class="fi-section rounded-xl bg-white p-4 dark:bg-gray-800">
                        {{-- L'action se lit dans l'en-tête que rend le gabarit partagé : le titre de
                             section ne numérote l'état que pour la navigation. --}}
                        <h2 class="text-base font-bold mb-2">
                            État {{ $loop->iteration }} / {{ $rapport->nbEtats() }}
                        </h2>

                        @include('filament.planification.pdf._cadre-logique-etendu-table', [
                            'blocs' => [$etat],
                        ])
                    </div>
                @endforeach
            @endif
        </div>
    @endif
</x-filament-panels::page>
