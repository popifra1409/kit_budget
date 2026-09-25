<x-filament-panels::page>
    @include('filament.suivi-evaluation.matrice._styles')

    <form wire:submit.prevent>{{ $this->form }}</form>

    @php $t = $this->getArborescence(); @endphp

    @if ($t)
        <div class="rounded-xl bg-white p-6 text-sm shadow-sm dark:bg-gray-800 space-y-1">
            <div><span class="font-semibold">CSP :</span> {{ $t['csp']?->libelle ?? '—' }}</div>
            <div><span class="font-semibold">PSP :</span> {{ $t['psp']->libelle }}</div>
            <div><span class="font-semibold">Objectif stratégique :</span> {{ $t['psp']->objectif_strategique ?? '—' }}</div>
            <div><span class="font-semibold">Exercice :</span> {{ $t['exercice']?->annee }}</div>
            <div class="text-gray-500">Les cellules surlignées ⚠ signalent un libellé à revoir (survolez le ⚠ pour le détail).</div>
        </div>

        @forelse ($t['sous_programmes'] as $bloc)
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800 space-y-3">
                @include('filament.planification.libelles._entete-sp', ['bloc' => $bloc])
                <div class="overflow-x-auto">@include('filament.planification.libelles._table-sp', ['bloc' => $bloc])</div>
                <h3 class="font-bold">Observations sur les libellés</h3>
                @include('filament.planification.libelles._observations', ['bloc' => $bloc])
            </div>
        @empty
            <div class="rounded-xl bg-white p-6 text-center text-gray-500 shadow-sm dark:bg-gray-800">
                Aucun sous-programme visible pour ces critères.
            </div>
        @endforelse
    @endif
</x-filament-panels::page>