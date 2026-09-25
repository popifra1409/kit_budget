<x-filament-panels::page>
    @include('filament.suivi-evaluation.matrice._styles')

    <form wire:submit.prevent>{{ $this->form }}</form>

    @php $m = $this->getMatrice(); @endphp

    @if ($m)
        {{-- Chaine d'arrimage ascendante --}}
        <div class="rounded-xl bg-white p-6 text-sm shadow-sm dark:bg-gray-800 space-y-1">
            <div><span class="font-semibold">CSP :</span> {{ $m['csp']?->libelle ?? '—' }}</div>
            <div><span class="font-semibold">PSP :</span> {{ $m['psp']->libelle }}</div>
            <div><span class="font-semibold">Objectif stratégique :</span> {{ $m['psp']->objectif_strategique ?? '—' }}</div>
            <div><span class="font-semibold">Exercice :</span> {{ $m['exercice']?->annee }}</div>
        </div>

        @if ($m['sous_programmes']->isEmpty())
            <div class="rounded-xl bg-white p-6 text-center text-gray-500 shadow-sm dark:bg-gray-800">
                Aucun sous-programme visible pour ces critères. Si vous êtes responsable de sous-programme,
                vérifiez que vous êtes bien désigné comme responsable dans le module Planification.
            </div>
        @else
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                <h2 class="mb-2 text-lg font-bold">Synthèse par sous-programme</h2>
                <div class="overflow-x-auto">@include('filament.suivi-evaluation.matrice._synthese', ['m' => $m])</div>
            </div>

            @foreach ($m['sous_programmes'] as $bloc)
                <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800 space-y-3">
                    @include('filament.suivi-evaluation.matrice._entete-sp', ['bloc' => $bloc])
                    <div class="overflow-x-auto">@include('filament.suivi-evaluation.matrice._table-sp', ['bloc' => $bloc])</div>
                </div>
            @endforeach
        @endif
    @endif
</x-filament-panels::page>