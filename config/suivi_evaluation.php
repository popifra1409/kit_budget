<?php

return [
    'engagements' => [
        // Passer a true une fois les colonnes ci-dessous verifiees
        'enabled' => env('SE_ENGAGEMENTS_DATES', false),

        'model'    => \App\Models\Engagement::class,
        'ligne_fk' => 'ligne_budgetaire_id',
        'montant'  => 'montant',
        'date'     => 'date_engagement',

        // Seuls les engagements effectifs comptent (pas les brouillons ni les annules).
        // Mettre 'statut' => null pour ne pas filtrer.
        'statut'          => 'statut',
        'statuts_retenus' => ['valide', 'engage'],
    ],

    'matrice' => [
        'aligne_completude'  => 90,
        'aligne_performance' => 75,
        'partiel_completude' => 70,
    ],
];
