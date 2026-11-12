<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php
        $rapport = $this->getRapport();
    @endphp

    @if ($rapport)
        {{-- Même markup que le PDF, habillé pour l'écran --}}
        <style>
            .cadre-logique-etendu table { width: 100%; border-collapse: collapse; }
            .cadre-logique-etendu th, .cadre-logique-etendu td { border: 1px solid #999; padding: 4px; text-align: left; vertical-align: top; font-size: 11px; }
            .cadre-logique-etendu th { background-color: #e8e8e8; text-align: center; }
            .cadre-logique-etendu td.num { text-align: right; white-space: nowrap; }
            .cadre-logique-etendu tr.bloc-action td { background-color: #d8e4f0; }
            .cadre-logique-etendu tr.bloc-action .titre { font-weight: 700; }
            .cadre-logique-etendu tr.bloc-action .objectif { display: block; font-style: italic; color: #555; }
            .cadre-logique-etendu tr.bloc-action .totaux { display: block; font-weight: 600; }
            .cadre-logique-etendu tr.bloc-activite td { background-color: #efefef; }
            .cadre-logique-etendu tr.bloc-activite .titre { font-weight: 600; }
            .cadre-logique-etendu .meta { color: #666; }
            .cadre-logique-etendu tr.vide td { font-style: italic; color: #888; }
            .cadre-logique-etendu tr.detail td { background-color: #f7f7f7; font-size: 10px; }
            .cadre-logique-etendu tr.detail .legende { font-weight: 700; text-decoration: underline; }
            .dark .cadre-logique-etendu tr.detail td { background-color: #262626; }
            .cadre-logique-etendu tr.total td { font-weight: 700; background-color: #e8e8e8; }
            .cadre-logique-etendu .palier + .palier { padding-left: 10px; }
            .dark .cadre-logique-etendu th, .dark .cadre-logique-etendu td { border-color: #555; }
            .dark .cadre-logique-etendu { color: rgb(255 255 255 / .85); }
            .dark .cadre-logique-etendu th { background-color: #333; }
            .dark .cadre-logique-etendu tr.bloc-action td { background-color: #24344a; }
            .dark .cadre-logique-etendu tr.bloc-activite td { background-color: #2e2e2e; }
        </style>

        <div class="cadre-logique-etendu mt-6 space-y-4">
            <div class="fi-section rounded-xl bg-white p-4 text-sm dark:bg-gray-800">
                <span class="font-semibold">{{ $rapport->titre() }}</span>
                — exercice {{ $rapport->exercice->annee }}
                · {{ count($rapport->lignes()) }} tâche(s) porteuse(s) de crédit
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
                </div>

                @include('filament.planification.pdf._cadre-logique-etendu-table', [
                    'blocs' => $rapport->blocs(),
                    'totalAe' => $rapport->totalAe(),
                    'totalCp' => $rapport->totalCp(),
                    'libelleTotal' => 'Total du sous-programme',
                ])

                @php $anomalies = $rapport->anomalies(); @endphp
                @if ($anomalies['reclassees'] !== [] || $anomalies['absentes'] !== [])
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
                    </div>
                @endif
            @endif
        </div>
    @endif
</x-filament-panels::page>
