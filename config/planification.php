<?php

return [
    'libelles' => [
        // Niveaux dont le libelle doit commencer par un verbe a l'infinitif
        'niveaux_infinitif' => ['Activité', 'Tâche'],

        // Un extrant decrit un produit obtenu (« Équipements installés »), pas une action
        'extrant_produit' => true,

        // Nombre minimal de mots par niveau (niveaux absents = pas de controle)
        'min_mots' => ['Action' => 3, 'Activité' => 3, 'Tâche' => 2],

        'max_caracteres' => 200,

        // Mots finissant en -er/-ir/-re/-oir qui ne sont PAS des verbes (evite les faux positifs)
        'faux_infinitifs' => [
            'centre',
            'registre',
            'matiere',
            'lettre',
            'titre',
            'cadre',
            'ordre',
            'nombre',
            'membre',
            'livre',
            'filtre',
            'autre',
            'notre',
            'votre',
            'offre',
            'entre',
            'contre',
            'premier',
            'dernier',
            'fichier',
            'dossier',
            'atelier',
            'quartier',
            'chantier',
            'papier',
            'calendrier',
            'plaidoyer',
            'loyer',
            'foyer',
            'cahier',
            'soir',
            'espoir',
            'couloir',
            'laboratoire',
            'repertoire',
            'territoire',
            'itineraire',
            'formulaire',
            'inventaire',
            'salaire',
            'dispensaire',
            'annuaire',
            'partenaire',
            'beneficiaire',
            'prestataire',
            'secretaire',
            'budgetaire',
            'sanitaire',
            'primaire',
            'secondaire',
            'hospitalier',
            'infirmier',
            'dentaire',
        ],
    ],
];
