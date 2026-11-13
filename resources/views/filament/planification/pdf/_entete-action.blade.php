{{-- resources/views/filament/planification/pdf/_entete-action.blade.php

     L'en-tête d'une action, hors du tableau : c'est le premier niveau que le lecteur rencontre
     sous les titres du sous-programme et du pilotage. Entrée : $bloc — la forme de
     CadreLogiqueEtendu::blocs(), qui est aussi celle d'un état de EtatsActionsSousProgramme.
     Les deux états (cadre logique étendu, états isolés) passent par ici : l'action ne peut plus
     se dire d'un côté dans une ligne du tableau et de l'autre dans un titre de page. --}}
@php
    // null = rien de saisi, 0 = un crédit effectivement nul.
    $montant = fn(?float $m) => $m === null ? '' : number_format($m, 0, ',', ' ');
@endphp

<div class="entete-action">
    <div class="titre">{{ $bloc['titre'] }}</div>
    <div class="objectif">{{ $bloc['objectif'] ?: 'Objectif non renseigné' }}</div>
    @if (filled($bloc['description'] ?? null))
        <div class="description">{{ $bloc['description'] }}</div>
    @endif
    <div class="totaux">
        AE {{ $montant($bloc['ae']) }} FCFA — CP {{ $montant($bloc['cp']) }} FCFA
        · {{ count($bloc['activites']) }} activité(s)
        · {{ array_sum(array_map(fn($a) => count($a['lignes']), $bloc['activites'])) }} ligne(s)
    </div>
    <div class="meta">
        Programme {{ $bloc['programme'] ?: '—' }}
        · Pilote {{ $bloc['pilote'] ?: 'agent non désigné' }}
        · Responsable de l'action {{ $bloc['responsable'] ?: '—' }}
    </div>
</div>
