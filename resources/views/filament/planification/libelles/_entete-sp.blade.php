<table class="mx-table">
    <tr>
        <th style="width:20%">Sous-programme</th>
        <td @class(['mx-flag' => $bloc['sp_alertes']])>
            <strong>@include('filament.planification.libelles._libelle', ['item' => ['texte' => $bloc['sp_texte'], 'alertes' => $bloc['sp_alertes']]])</strong>
        </td>
    </tr>
    <tr>
        <th>Programme de rattachement</th>
        <td>
            @if ($bloc['programme']) {{ $bloc['programme'] }} @else <span class="mx-alerte">Non rattaché</span> @endif
        </td>
    </tr>
    <tr><th>Responsable</th><td>{{ $bloc['sp']->responsable?->name ?? '—' }}</td></tr>
    <tr><th>Objectif</th><td>{{ $bloc['sp']->objectif ?? '—' }}</td></tr>
    <tr>
        <th>Indicateur(s) du sous-programme</th>
        <td>
            @forelse ($bloc['indicateurs'] as $i)
                <div>• @include('filament.planification.libelles._libelle', ['item' => $i])</div>
            @empty
                <span class="mx-alerte">Aucun</span>
            @endforelse
        </td>
    </tr>
</table>

@php $c = $bloc['compteurs']; @endphp
<p class="mx-muted">
    {{ $c['actions'] }} action(s) · {{ $c['activites'] }} activité(s) · {{ $c['extrants'] }} extrant(s) ·
    {{ $c['indicateurs'] }} indicateur(s) · {{ $c['taches'] }} tâche(s) · {{ $c['sous_taches'] }} sous-tâche(s) ·
    <strong>{{ $bloc['observations']->count() }} observation(s)</strong>
</p>