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
            .etats-actions table { width: 100%; border-collapse: collapse; }
            .etats-actions th, .etats-actions td { border: 1px solid #999; padding: 4px; text-align: left; vertical-align: top; font-size: 11px; }
            .etats-actions th { background-color: #e8e8e8; text-align: center; }
            .etats-actions td.center { text-align: center; }
            .etats-actions td.num { text-align: right; white-space: nowrap; }
            .etats-actions tr.bloc-action td { background-color: #d8e4f0; }
            .etats-actions tr.bloc-action .titre { font-weight: 700; }
            .etats-actions tr.bloc-action .objectif { display: block; font-style: italic; color: #555; }
            .etats-actions tr.bloc-action .totaux { display: block; font-weight: 600; }
            .etats-actions tr.bloc-activite td { background-color: #efefef; }
            .etats-actions tr.bloc-activite .titre { font-weight: 600; }
            .etats-actions .meta { color: #666; }
            .etats-actions tr.vide td { font-style: italic; color: #888; }
            .etats-actions tr.detail td { background-color: #f7f7f7; font-size: 10px; }
            .etats-actions tr.detail .legende { font-weight: 700; text-decoration: underline; }
            .etats-actions tr.total td { font-weight: 700; background-color: #e8e8e8; }
            .etats-actions .palier + .palier { padding-left: 10px; }
            .dark .etats-actions th, .dark .etats-actions td { border-color: #555; }
            .dark .etats-actions { color: rgb(255 255 255 / .85); }
            .dark .etats-actions th { background-color: #333; }
            .dark .etats-actions tr.bloc-action td { background-color: #24344a; }
            .dark .etats-actions tr.bloc-activite td { background-color: #2e2e2e; }
            .dark .etats-actions tr.detail td { background-color: #262626; }
        </style>

        <div class="etats-actions mt-6 space-y-4">
            <div class="fi-section rounded-xl bg-white p-4 text-sm dark:bg-gray-800">
                <span class="font-semibold">{{ $rapport->titre() }}</span>
                — exercice {{ $rapport->exercice->annee }}
                · {{ $rapport->nbEtats() }} état(s)
                · AE {{ $rapport->formatMontant($rapport->totalAe()) }}
                · CP {{ $rapport->formatMontant($rapport->totalCp()) }}
            </div>

            @if ($rapport->estVide())
                <p class="text-sm italic">Aucune action rattachée à ce sous-programme pour cet exercice.</p>
            @else
                @php $anomalies = $rapport->anomalies(); @endphp
                @if ($anomalies['reclassees'] !== [] || $anomalies['absentes'] !== [])
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
                    </div>
                @endif

                @foreach ($rapport->etats() as $etat)
                    <div class="fi-section rounded-xl bg-white p-4 dark:bg-gray-800">
                        <h2 class="text-base font-bold mb-2">
                            État {{ $loop->iteration }} / {{ $rapport->nbEtats() }} — {{ $etat['titre'] }}
                        </h2>
                        <p class="text-xs mb-2">
                            {{ $etat['nb_activites'] }} activité(s) · {{ $etat['nb_lignes'] }} tâche(s) porteuse(s)
                            · programme {{ $etat['programme'] ?: '—' }}
                            · pilote {{ $etat['pilote'] ?: 'agent non désigné' }}
                        </p>

                        @include('filament.planification.pdf._cadre-logique-etendu-table', [
                            'blocs' => [$etat],
                            'totalAe' => $etat['ae'],
                            'totalCp' => $etat['cp'],
                            'libelleTotal' => "Total de l'action {$etat['code']}",
                        ])
                    </div>
                @endforeach
            @endif
        </div>
    @endif
</x-filament-panels::page>
