{{-- Tableau partagé (écran) : matrice du cadre logique ÉTENDU et états isolés d'action.
     Entrées : $blocs (array — CadreLogiqueEtendu::blocs() ou un état de
     EtatsActionsSousProgramme, même forme). Une table par action : l'en-tête de l'action est
     HORS du tableau, et la première colonne porte l'activité sur une cellule fusionnée à
     toutes les lignes de ses tâches. La version PDF de ce gabarit est
     _cadre-logique-etendu-table-pdf.blade.php (dompdf ne coupe pas un rowspan entre deux pages).
     Les entetes viennent de CadreLogiqueEtendu::COLONNES : le PDF, l'écran et l'Excel n'ont pas
     chacun leur liste de colonnes. Les clés supplémentaires d'un état (objectif, zone, extrants,
     indicateurs) s'impriment quand elles existent. --}}
@php
    $cls = \App\Services\Planification\CadreLogiqueEtendu::class;
    $colonnes = $cls::COLONNES;
    // La colonne d'activité s'ajoute aux neuf de la tâche : dix largeurs pour 100 %.
    $largeurs = [
        'activite' => 18, 'designation' => 17, 'nomenclature' => 8, 'ae' => 8, 'cp' => 8,
        'delai' => 7, 'guichet' => 6, 'service' => 9, 'indicateur' => 9, 'resultat' => 10,
    ];
    $n = count($colonnes) + 1;
    // null = rien de saisi (ligne d'une activité sans tâche), 0 = un crédit effectivement nul.
    $montant = fn(?float $m) => $m === null ? '' : number_format($m, 0, ',', ' ');
    // Hauteur de la cellule d'activité : ses lignes de tâches, plus ses deux blocs de détail.
    $hauteur = fn(array $a) => count($a['lignes'])
        + (int) (($a['extrants'] ?? []) !== []) + (int) (($a['indicateurs'] ?? []) !== []);
@endphp

@foreach ($blocs as $bloc)
    @include('filament.planification.pdf._entete-action', ['bloc' => $bloc])

    <table class="cl-etendu">
        <thead>
            <tr>
                <th style="width: {{ $largeurs['activite'] }}%;">{{ $cls::ENTETE_ACTIVITE }}</th>
                @foreach ($colonnes as $cle => $entete)
                    <th style="width: {{ $largeurs[$cle] }}%;">{{ $entete }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($bloc['activites'] as $activite)
                @foreach ($activite['lignes'] as $i => $ligne)
                    <tr>
                        @if ($i === 0)
                            <td class="activite" rowspan="{{ $hauteur($activite) }}">
                                <div class="titre">Activité {{ $activite['code'] }} — {{ $activite['titre'] }}</div>
                                <div class="totaux">
                                    AE {{ $montant($activite['ae']) }} FCFA — CP {{ $montant($activite['cp']) }} FCFA
                                </div>
                                <div class="meta">
                                    Responsable de l'activité {{ $activite['responsable'] ?: '—' }}
                                    @if (filled($activite['zone'] ?? null))
                                        · Zone d'exécution {{ $activite['zone'] }}
                                    @endif
                                    @if (filled($activite['numero'] ?? null) || filled($activite['statut'] ?? null))
                                        · {{ collect([$activite['numero'] ?? null, $activite['statut'] ?? null])->filter()->implode(' · ') }}
                                    @endif
                                </div>
                                @if (filled($activite['objectif'] ?? null))
                                    <div class="objectif">{{ $activite['objectif'] }}</div>
                                @endif
                                @if ($activite['sans_tache'] ?? false)
                                    <div class="objectif">Aucune tâche saisie dans cette activité.</div>
                                @endif
                            </td>
                        @endif
                        <td class="designation">
                            @foreach ($ligne['designation'] as $palier)
                                <div class="palier">{{ $palier }}</div>
                            @endforeach
                        </td>
                        <td class="center">{{ $ligne['nomenclature'] ?: '—' }}</td>
                        <td class="num">{{ $montant($ligne['ae']) }}</td>
                        <td class="num">{{ $montant($ligne['cp']) }}</td>
                        <td>{{ $ligne['delai'] ?: '' }}</td>
                        <td>{{ $ligne['guichet'] ?: '' }}</td>
                        <td>{{ $ligne['service'] ?: '' }}</td>
                        <td>{{ $ligne['indicateur'] ?: '' }}</td>
                        <td>{{ $ligne['resultat'] ?: '' }}</td>
                    </tr>
                @endforeach

                @if (($activite['extrants'] ?? []) !== [])
                    <tr class="detail">
                        <td colspan="{{ $n - 1 }}">
                            <div class="legende">Extrants attendus</div>
                            @foreach ($activite['extrants'] as $extrant)
                                <div>
                                    {{ $extrant['libelle'] }}
                                    @if (filled($extrant['quantite']))
                                        — {{ $extrant['quantite'] }}{{ $extrant['unite'] ? ' '.$extrant['unite'] : '' }}
                                    @endif
                                    @if (filled($extrant['echeance']))
                                        (prévu le {{ $extrant['echeance'] }})
                                    @endif
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @endif

                @if (($activite['indicateurs'] ?? []) !== [])
                    <tr class="detail">
                        <td colspan="{{ $n - 1 }}">
                            <div class="legende">Indicateurs de résultat</div>
                            @foreach ($activite['indicateurs'] as $indicateur)
                                <div>
                                    {{ $indicateur['libelle'] }}
                                    @if (filled($indicateur['unite'])) ({{ $indicateur['unite'] }}) @endif
                                    — référence {{ filled($indicateur['reference']) ? $indicateur['reference'] : '—' }}, cible {{ filled($indicateur['cible']) ? $indicateur['cible'] : '—' }}
                                    @if (filled($indicateur['source'])) · source : {{ $indicateur['source'] }} @endif
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @endif
            @empty
                <tr class="vide">
                    <td colspan="{{ $n }}">Aucune activité rattachée à cette action.</td>
                </tr>
            @endforelse

            <tr class="total">
                <td colspan="2">{{ trim("Total de l'action {$bloc['action']->code}") }}</td>
                <td></td>
                <td class="num">{{ $montant($bloc['ae']) }}</td>
                <td class="num">{{ $montant($bloc['cp']) }}</td>
                <td colspan="{{ $n - 5 }}"></td>
            </tr>
        </tbody>
    </table>
@endforeach
