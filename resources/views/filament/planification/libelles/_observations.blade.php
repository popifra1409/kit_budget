@if ($bloc['observations']->isNotEmpty())
    <table class="mx-table">
        <thead>
            <tr><th style="width:12%">Niveau</th><th style="width:40%">Libellé</th><th>Observation</th></tr>
        </thead>
        <tbody>
            @foreach ($bloc['observations'] as $o)
                <tr><td>{{ $o['niveau'] }}</td><td>{{ $o['libelle'] }}</td><td>{{ $o['constat'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="mx-muted">Aucune observation : les libellés respectent les règles de contrôle.</p>
@endif