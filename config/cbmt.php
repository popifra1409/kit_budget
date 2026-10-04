<?php
// config/cbmt.php

/*
|--------------------------------------------------------------------------
| Classement des lignes de nomenclature par TITRE (CBMT)
|--------------------------------------------------------------------------
| Le titre proposé pour une ligne est déterminé par le DÉBUT de son code :
| le préfixe le plus long qui correspond l'emporte (« 67 » avant « 6 »).
| Chaque structure adapte ces règles à sa codification ; les exceptions se
| corrigent ligne par ligne dans l'écran de la nomenclature.
|
| Règles validées pour le CHUY (octobre 2026).
*/

return [

    'regles' => [

        'depense' => [
            // Classe 1 : remboursement de la dette
            '1'  => 1,
            '17' => 1,
            // Classe 2 : immobilisations
            '2'  => 5,
            // Classe 6 : fonctionnement
            '6'  => 6,   // par défaut : autres dépenses de fonctionnement
            '60' => 3,
            '61' => 3,
            '62' => 3,
            '63' => 3,
            '64' => 3,
            '65' => 3,
            '66' => 2,   // personnel
            '67' => 1,   // charges financières
        ],

        'recette' => [
            // Classe 1 : ressources en capital
            '1'  => 4,   // par défaut : autres recettes (ex. 11 fonds de réserve)
            '10' => 3,   // dons et legs d'investissement
            '14' => 3,   // subventions d'investissement
            // Classe 7 : ressources de fonctionnement
            '7'  => 4,   // par défaut : autres recettes (ex. 77 produits financiers)
            '70' => 2,
            '71' => 1,   // recettes fiscales affectées
            '72' => 2,   // produits de l'exploitation du domaine et des services
            '73' => 3,   // dotations et subventions
        ],
    ],

    // Niveau de nomenclature qui porte les montants (lignes budgétaires et de recettes)
    'niveau_ligne' => 'paragraphe',
];
