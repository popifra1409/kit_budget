<?php

return [
    // Délai max pour rappeler une transmission (en minutes)
    'delai_annulation_minutes' => (int) env('WORKFLOW_DELAI_ANNULATION', 30),

    // Délai max pour traiter une transmission (en jours)
    'delai_traitement_jours' => (int) env('WORKFLOW_DELAI_TRAITEMENT', 3),

    // Envoyer des rappels automatiques
    'rappels_actifs' => (int) env('WORKFLOW_RAPPELS', true),
];
