{{-- resources/views/filament/planification/libelles/_table-sp-pdf.blade.php

     Version PDF du tableau : AUCUN rowspan, une table par action.
     dompdf 3.1.5 ne sait pas couper une cellule à rowspan entre deux pages : les lignes de la page
     suivante perdaient leurs colonnes (tableau « désorganisé »). Le <thead> est en revanche répété
     sur chaque page (vérifié) : le titre de l'action reste donc visible, et le regroupement est
     rendu par les bordures + le renvoi ↳ au code de l'activité ou de la tâche. --}}
@php
    $blocs = [];
    $i = -1;
    foreach ($bloc['lignes'] as $row) {
        if ($row['action']) { $i++; }
        $blocs[$i][] = $row;
    }

    /** « 614300 - Honoraires… » → « ↳ 614300 » : renvoi court sur les lignes de suite, vide sans code. */
    $suite = fn(?string $texte) => $texte ? '↳ ' . trim(explode(' - ', $texte)[0]) : '';
@endphp

@forelse ($blocs as $rows)
    @php $action = $rows[0]['action']; @endphp
    <table class="mx-table">
        <thead>
            <tr>
                <th style="width:17%">Activité</th>
                <th style="width:17%">Extrant(s)</th>
                <th style="width:17%">Indicateur(s)</th>
                <th style="width:17%">Tâche</th>
                <th style="width:16%">Sous-tâche</th>
                <th style="width:15%">Ligne budgétaire</th>
            </tr>
            <tr>
                <th colspan="6" @class(['mx-action', 'mx-flag' => $action['alertes'] ?? []])>
                    <strong>@include('filament.planification.libelles._libelle', ['item' => $action])</strong>
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr @class(['mx-debut' => $row['activite'] ?? $row['message'] ?? false])>
                    @if ($row['activite'])
                        @php $a = $row['activite']; @endphp
                        <td @class(['mx-flag' => $a['alertes']])><strong>@include('filament.planification.libelles._libelle', ['item' => $a])</strong></td>
                        <td>
                            @forelse ($a['extrants'] as $e)
                                <div @class(['mx-flag' => $e['alertes']])>• @include('filament.planification.libelles._libelle', ['item' => $e])</div>
                            @empty
                                <span class="mx-alerte">Aucun</span>
                            @endforelse
                        </td>
                        <td>
                            @forelse ($a['indicateurs'] as $ind)
                                <div @class(['mx-flag' => $ind['alertes']])>• @include('filament.planification.libelles._libelle', ['item' => $ind])</div>
                            @empty
                                <span class="mx-alerte">Aucun</span>
                            @endforelse
                        </td>
                    @elseif ($row['message'])
                        {{-- Pas d'activité : le message reprend les colonnes concernées. --}}
                    @else
                        <td class="mx-suite">{{ $suite($row['ctx']['activite'] ?? null) }}</td>
                        <td></td>
                        <td></td>
                    @endif

                    @if ($row['message'])
                        <td colspan="{{ $row['message_colspan'] }}" class="mx-vide">{{ $row['message'] }}</td>
                    @else
                        @if ($row['tache'])
                            <td @class(['mx-flag' => $row['tache']['alertes']])>@include('filament.planification.libelles._libelle', ['item' => $row['tache']])</td>
                        @else
                            <td class="mx-suite">{{ $suite($row['ctx']['tache'] ?? null) }}</td>
                        @endif
                        <td>{{ $row['ligne']['sous_tache'] ?? '—' }}</td>
                        <td>
                            @if ($row['ligne']['nomenclature'])
                                {{ $row['ligne']['nomenclature'] }}
                            @else
                                <span class="mx-alerte">Non imputée</span>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@empty
    <p class="mx-vide">Aucune action rattachée à ce sous-programme.</p>
@endforelse
