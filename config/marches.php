<?php

/*
 |--------------------------------------------------------------------------
 | Module Marchés publics — vocabulaires du paramétrage
 |--------------------------------------------------------------------------
 |
 | Ces listes font autorité pour les énumérations des référentiels du module :
 | les migrations en prennent les clés pour construire leurs contraintes CHECK
 | et les resources Filament en prennent les valeurs pour leurs Select.
 |
 | Ajouter une valeur ici impose une migration pour élargir la contrainte
 | correspondante (même mécanique que pour pieces_dossier.type_piece).
 */

return [

    // Familles de procédure de passation (types_procedure.famille)
    'familles_procedure' => [
        'appel_offre' => "Appel d'offres",
        'cotation'    => 'Demande de cotation',
        'gre_a_gre'   => 'Gré à gré',
    ],

    // Sens de comparaison du seuil (types_contrat.operateur)
    'operateurs_seuil' => [
        'gte' => 'Montant supérieur ou égal au seuil',
        'lt'  => 'Montant inférieur au seuil',
    ],

    // Volets du dossier d'appel d'offres évalués (elements_evaluation_dao.famille)
    'familles_evaluation' => [
        'administrative' => 'Pièce administrative',
        'financiere'     => 'Pièce financière',
        'technique'      => 'Description technique',
    ],

    // Qualités d'un membre de commission (commission_membres.titre)
    'titres_membre' => [
        'president'      => 'Président',
        'vice_president' => 'Vice-président',
        'rapporteur'     => 'Rapporteur',
        'secretaire'     => 'Secrétaire',
        'membre'         => 'Membre',
        'observateur'    => 'Observateur',
    ],

    /*
     |--------------------------------------------------------------------------
     | Cycle de vie du marché (marches.statut)
     |--------------------------------------------------------------------------
     |
     | Tous les statuts du cycle sont déclarés ici dès la création de la table,
     | y compris ceux qu'aucune action n'atteint encore (attribue, homologue,
     | en_execution, termine, resilie) : élargir la contrainte CHECK obligerait à
     | une migration sur la production. Une valeur sans action correspondante est
     | simplement inutilisable, jamais invalide.
     */
    'statuts_marche' => [
        'brouillon'     => 'Brouillon',
        'valide'        => 'Validé par le service des marchés',
        'publie'        => 'DAO publié — avis de lancement',
        'en_evaluation' => "Plis ouverts — évaluation en cours",
        'attribue'      => 'Attribué provisoirement',
        'homologue'     => 'Homologué',
        'en_execution'  => 'En cours d\'exécution',
        'termine'       => 'Terminé',
        'resilie'       => 'Résilié',
        'annule'        => 'Annulé',
    ],

    /*
     | Transitions autorisées. « brouillon » en cible n'est pas une marche du
     | cycle : c'est le retour pour correction du circuit de transmission
     | (HasWorkflow::retournerPourCorrection), qui remet le document chez
     | l'émetteur, quel que soit le statut atteint.
     |
     | Les retours en arrière (attribue → en_evaluation, homologue → attribue) sont
     | les mêmes mains qui ont pris la décision et qui la défont, pas des droits de
     | réécriture : chacun fige la trace antérieure dans le journal. Ils existent
     | parce que l'écran qui décide doit aussi pouvoir se corriger — sans eux, une
     | homologation prématurée ne se défait que par la suppression du marché, ce qui
     | effacerait la procédure au lieu de la reprendre.
     */
    'transitions_marche' => [
        'brouillon'     => ['valide', 'annule'],
        'valide'        => ['publie', 'brouillon', 'annule'],
        'publie'        => ['en_evaluation', 'brouillon', 'annule'],
        'en_evaluation' => ['attribue', 'brouillon', 'annule'],
        'attribue'      => ['homologue', 'en_evaluation', 'brouillon', 'annule'],
        // homologue → attribue (étape E) : le retour qui défait l'homologation, tant
        // qu'aucun ordre de service n'a été émis — passé la commande du service, il
        // n'y a plus de décision à défaire, il y a une exécution.
        'homologue'     => ['attribue', 'en_execution', 'brouillon', 'annule'],
        'en_execution'  => ['termine', 'resilie'],
        'termine'       => [],
        'resilie'       => [],
        'annule'        => [],
    ],

    /*
     |--------------------------------------------------------------------------
     | Réception des offres (offres.statut) — scan du 27/10 17.29
     |--------------------------------------------------------------------------
     |
     | « Renseigner les informations du soumissionnaire, choisir le marché
     | correspondant, date de réception, upload de la quittance (ou ses
     | informations), validation. » L'offre n'est pas un document du circuit de
     | transmission : elle est enregistrée puis décidée par le service des
     | marchés. D'où trois statuts, et une décision toujours reversible tant que
     | le marché n'est pas attribué — passé l'attribution, seule l'évaluation
     | (étape D) dira qui avait raison.
     */
    'statuts_offre' => [
        'recue'   => 'Reçue — en attente de décision',
        'validee' => 'Offre recevable (validée)',
        'rejetee' => 'Offre rejetée',
    ],

    'transitions_offre' => [
        'recue'   => ['validee', 'rejetee'],
        'validee' => ['recue'],
        'rejetee' => ['recue'],
    ],
];
