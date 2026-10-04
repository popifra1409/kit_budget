<?php

/*
|--------------------------------------------------------------------------
| Paramètres d'exécution budgétaire — DÉFINITIONS et valeurs par défaut
|--------------------------------------------------------------------------
| Les valeurs effectives sont saisies dans « Administration > Paramètres
| d'exécution budgétaire » (avec date d'effet et historique). Les valeurs
| ci-dessous ne servent que tant qu'aucune valeur n'a été enregistrée.
|
| type : entier | decimal | booleen | choix
*/

$decideurs = [
    'directeur_general'      => 'Directeur général (ordonnateur)',
    'conseil_administration' => "Conseil d'administration",
    'ordonnateur_avis_ca'    => "Ordonnateur, après avis conforme du conseil d'administration",
    'tutelle'                => 'Autorité de tutelle (ministère)',
];

return [

    'groupes' => [
        'liquidation'   => 'Liquidation et délais de paiement',
        'plafonds'      => 'Plafonds des mouvements de crédits',
        'decideurs'     => 'Décideurs des mouvements de crédits',
        'cloture'       => "Clôture d'exercice et reports",
        'exceptionnel'  => 'Procédure exceptionnelle',
    ],

    'parametres' => [

        // ── Liquidation et délais de paiement ───────────────────
        'liquidation_obligatoire_des' => [
            'groupe' => 'liquidation',
            'type' => 'entier',
            'defaut' => 2027,
            'unite' => 'exercice',
            'libelle' => "Liquidation obligatoire avant ordonnancement à partir de l'exercice",
            'aide' => "Avant cet exercice, l'ordonnancement direct à partir de l'engagement reste possible.",
        ],
        'visa_cf_obligatoire' => [
            'groupe' => 'liquidation',
            'type' => 'booleen',
            'defaut' => true,
            'libelle' => 'Visa du contrôleur financier obligatoire sur la liquidation avant ordonnancement',
        ],
        'delai_paiement_jours' => [
            'groupe' => 'liquidation',
            'type' => 'entier',
            'defaut' => 90,
            'unite' => 'jours',
            'libelle' => 'Délai de paiement à compter de la liquidation',
            'aide' => 'Au-delà, la dépense constitue un arriéré (instruction 2026 : 90 jours).',
        ],
        'alerte_paiement_1_jours' => [
            'groupe' => 'liquidation',
            'type' => 'entier',
            'defaut' => 30,
            'unite' => 'jours',
            'libelle' => "Première alerte avant l'échéance de paiement",
        ],
        'alerte_paiement_2_jours' => [
            'groupe' => 'liquidation',
            'type' => 'entier',
            'defaut' => 15,
            'unite' => 'jours',
            'libelle' => "Seconde alerte avant l'échéance de paiement",
        ],
        'taux_interets_moratoires' => [
            'groupe' => 'liquidation',
            'type' => 'decimal',
            'defaut' => 0,
            'unite' => '% par an',
            'libelle' => 'Taux des intérêts moratoires (estimation des arriérés)',
            'aide' => '0 = pas d\'estimation des intérêts moratoires.',
        ],

        // ── Plafonds des mouvements de crédits ──────────────────
        'base_plafonds' => [
            'groupe' => 'plafonds',
            'type' => 'choix',
            'defaut' => 'initial',
            'options' => ['initial' => 'Budget initial voté', 'actualise' => 'Budget actualisé (après collectifs)'],
            'libelle' => 'Base de calcul des « crédits ouverts » pour les plafonds',
        ],
        'plafond_virements_pct' => [
            'groupe' => 'plafonds',
            'type' => 'decimal',
            'defaut' => 2.0,
            'unite' => '%',
            'libelle' => 'Plafond du cumul annuel des virements',
        ],
        'plafond_annulations_pct' => [
            'groupe' => 'plafonds',
            'type' => 'decimal',
            'defaut' => 1.5,
            'unite' => '%',
            'libelle' => 'Plafond du cumul annuel des annulations',
        ],
        'depassement_plafond' => [
            'groupe' => 'plafonds',
            'type' => 'choix',
            'defaut' => 'bloquant',
            'options' => ['bloquant' => 'Bloquant', 'avertissement' => 'Avertissement seulement'],
            'libelle' => "En cas de dépassement d'un plafond",
        ],

        // ── Décideurs ────────────────────────────────────────────
        'decideur_fongibilite' => [
            'groupe' => 'decideurs',
            'type' => 'choix',
            'defaut' => 'directeur_general',
            'options' => $decideurs,
            'libelle' => 'Fongibilité (dans un même sous-programme)',
        ],
        'decideur_virement' => [
            'groupe' => 'decideurs',
            'type' => 'choix',
            'defaut' => 'directeur_general',
            'options' => $decideurs,
            'libelle' => 'Virement (entre sous-programmes ou programmes)',
        ],
        'decideur_transfert' => [
            'groupe' => 'decideurs',
            'type' => 'choix',
            'defaut' => 'tutelle',
            'options' => $decideurs,
            'libelle' => 'Transfert (entre ministères)',
        ],
        'decideur_annulation' => [
            'groupe' => 'decideurs',
            'type' => 'choix',
            'defaut' => 'directeur_general',
            'options' => $decideurs,
            'libelle' => 'Annulation de crédits',
        ],
        'decideur_report' => [
            'groupe' => 'decideurs',
            'type' => 'choix',
            'defaut' => 'ordonnateur_avis_ca',
            'options' => $decideurs,
            'libelle' => 'Report de crédits',
        ],

        // ── Clôture et reports ───────────────────────────────────
        'report_cp_fonctionnement' => [
            'groupe' => 'cloture',
            'type' => 'booleen',
            'defaut' => false,
            'libelle' => 'Autoriser le report de CP sur les dépenses de fonctionnement',
            'aide' => "Par défaut non : les bons de commande de fonctionnement doivent être exécutés avant le 31 décembre.",
        ],

        // ── Procédure exceptionnelle ─────────────────────────────
        'delai_regularisation_jours' => [
            'groupe' => 'exceptionnel',
            'type' => 'entier',
            'defaut' => 30,
            'unite' => 'jours',
            'libelle' => "Délai de régularisation d'un paiement sans ordonnancement préalable",
        ],
    ],
];
