<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php
        $rapport = $this->getRapport();
        $avecCadre = $rapport && ! $rapport->filtreUnSousProgramme() && $rapport->matrices()->isNotEmpty();
    @endphp

    @if ($rapport)
        {{-- Même markup que le PDF, habillé pour l'écran --}}
        <style>
            .cadre-logique table { width: 100%; border-collapse: collapse; }
            .cadre-logique th, .cadre-logique td { border: 1px solid #999; padding: 4px; text-align: left; vertical-align: top; font-size: 11px; }
            .cadre-logique th { background-color: #e8e8e8; text-align: center; }
            .cadre-logique td.bloc { font-weight: bold; }
            .cadre-logique td.center { text-align: center; }
            .cadre-logique .italic { font-style: italic; color: #777; }
            .cadre-logique .page-break { display: none; }
            .dark .cadre-logique th, .dark .cadre-logique td { border-color: #555; }
            .dark .cadre-logique { color: rgb(255 255 255 / .85); }
            .dark .cadre-logique th { background-color: #333; }
        </style>

        <div class="cadre-logique mt-6 space-y-6">
            <div class="fi-section rounded-xl bg-white p-4 text-sm dark:bg-gray-800">
                <span class="font-semibold">{{ $rapport->psp->libelle }}</span>
                — exercice de référence {{ $rapport->anneeReference() }}
                · réalisation {{ $rapport->annees()[0] }}, estimation {{ $rapport->annees()[1] }},
                valeurs cibles {{ $rapport->annees()[2] }} à {{ $rapport->annees()[4] }}
            </div>

            @if ($avecCadre)
                <div>
                    <h2 class="text-base font-bold mb-1">Tableau 1 : Cadre Stratégique des Sous-programmes</h2>
                    @include('filament.planification.pdf._cadre-logique-table', [
                        'premiereColonne' => 'Sous-programmes',
                        'blocs' => $rapport->cadreStrategique()->all(),
                        'annees' => $rapport->annees(),
                    ])
                </div>
            @endif

            @foreach ($rapport->matrices() as $matrice)
                <div>
                    <h2 class="text-base font-bold mb-1">
                        Matrice du Cadre Logique — {{ $rapport->titreSousProgramme($matrice['sous_programme']) }}
                    </h2>
                    @include('filament.planification.pdf._cadre-logique-table', [
                        'premiereColonne' => 'Sous-programme/Actions',
                        'blocs' => array_merge([$matrice['bloc']], $matrice['actions']->all()),
                        'annees' => $rapport->annees(),
                    ])
                </div>
            @endforeach

            @if ($rapport->matrices()->isEmpty())
                <p class="text-sm italic">Aucun sous-programme rattaché à ce plan stratégique.</p>
            @endif
        </div>
    @endif
</x-filament-panels::page>
