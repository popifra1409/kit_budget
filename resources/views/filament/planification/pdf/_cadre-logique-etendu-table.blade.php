{{-- Tableau partagé : matrice du cadre logique ÉTENDU et états isolés d'action.
     Entrées : $blocs (array — CadreLogiqueEtendu::blocs() ou un état de
     EtatsActionsSousProgramme, même forme), $totalAe, $totalCp, $libelleTotal.
     Les entêtes viennent de CadreLogiqueEtendu::COLONNES : le PDF, l'écran et l'Excel
     n'ont pas chacun leur liste de colonnes. Les clés supplémentaires d'un état
     (objectif, zone, extrants, indicateurs) s'impriment quand elles existent. --}}
@php
    $cls = \App\Services\Planification\CadreLogiqueEtendu::class;
    $colonnes = $cls::COLONNES;
    $largeurs = [
        'designation' => 22, 'nomenclature' => 8, 'ae' => 9, 'cp' => 9, 'delai' => 8,
        'guichet' => 8, 'service' => 12, 'indicateur' => 12, 'resultat' => 12,
    ];
    $n = count($colonnes);
    $montant = fn(?float $m) => number_format((float) $m, 0, ',', ' ');
@endphp

<table class="cl-etendu">
    <thead>
        <tr>
            @foreach ($colonnes as $cle => $entete)
                <th style="width: {{ $largeurs[$cle] }}%;">{{ $entete }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($blocs as $bloc)
            <tr class="bloc-action">
                <td colspan="{{ $n }}">
                    <span class="titre">{{ $bloc['titre'] }}</span>
                    <span class="objectif">{{ $bloc['objectif'] ?: 'Objectif non renseigné' }}</span>
                    @if (filled($bloc['description'] ?? null))
                        <span class="objectif">{{ $bloc['description'] }}</span>
                    @endif
                    <span class="totaux">AE {{ $montant($bloc['ae']) }} FCFA — CP {{ $montant($bloc['cp']) }} FCFA</span>
                    <span class="meta">
                        Programme {{ $bloc['programme'] ?: '—' }}
                        · Pilote {{ $bloc['pilote'] ?: 'agent non désigné' }}
                        · Responsable de l'action {{ $bloc['responsable'] ?: '—' }}
                    </span>
                </td>
            </tr>

            @forelse ($bloc['activites'] as $activite)
                <tr class="bloc-activite">
                    <td colspan="{{ $n }}">
                        <span class="titre">Activité {{ $activite['code'] }} — {{ $activite['titre'] }}</span>
                        <span class="totaux">AE {{ $montant($activite['ae']) }} FCFA — CP {{ $montant($activite['cp']) }} FCFA</span>
                        <span class="meta">
                            Responsable de l'activité {{ $activite['responsable'] ?: '—' }}
                            @if (filled($activite['zone'] ?? null))
                                · Zone d'exécution {{ $activite['zone'] }}
                            @endif
                            @if (filled($activite['numero'] ?? null) || filled($activite['statut'] ?? null))
                                · {{ collect([$activite['numero'] ?? null, $activite['statut'] ?? null])->filter()->implode(' · ') }}
                            @endif
                        </span>
                        @if (filled($activite['objectif'] ?? null))
                            <span class="objectif">{{ $activite['objectif'] }}</span>
                        @endif
                    </td>
                </tr>

                @forelse ($activite['lignes'] as $ligne)
                    <tr>
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
                @empty
                    <tr class="vide">
                        <td colspan="{{ $n }}">Aucune tâche porteuse de crédit dans cette activité.</td>
                    </tr>
                @endforelse

                @if (($activite['extrants'] ?? []) !== [])
                    <tr class="detail">
                        <td colspan="{{ $n }}">
                            <span class="legende">Extrants attendus</span>
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
                        <td colspan="{{ $n }}">
                            <span class="legende">Indicateurs de résultat</span>
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
        @endforeach

        <tr class="total">
            <td>{{ $libelleTotal ?? 'Total' }}</td>
            <td></td>
            <td class="num">{{ $montant($totalAe) }}</td>
            <td class="num">{{ $montant($totalCp) }}</td>
            <td colspan="{{ $n - 4 }}"></td>
        </tr>
    </tbody>
</table>
