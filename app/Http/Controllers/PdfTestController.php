<?php

namespace App\Http\Controllers;

use App\Services\PdfGenerator\PdfGenerator;

class PdfTestController extends Controller
{
    public function certificat(PdfGenerator $generator)
    {
        $donnees = [
            'montant_total' => 47648407,
            'numero' => 'BE25-1542',
            'date_validation' => now(),
            'signataire' => ['nom' => 'Pr. ESSOMBA NOEL EMMANUEL'],
            'objet' => 'SALAIRE DE BASE NOVEMBRE 2025',
            'beneficiaire' => ['nom' => 'AGENT COMPTABLE'],
            'imputation' => [
                'chapitre' => '62',
                'article' => '620',
                'paragraphe' => '620000',
            ],
            'programme' => ['libelle' => 'GOUVERNANCE ET PILOTAGE STRATEGIQUE DU SYSTEME DE SANTE'],
            'objectif' => ['libelle' => 'AMELIORER LA COORDINATION DES SERVICES ET ASSURER LA BONNE MISE EN ŒUVRE DES PROGRAMMES AU MINISTERE'],
            'action' => ['libelle' => 'GESTION DES RESSOURCES HUMAINES ET SANTE'],
            'activite' => ['libelle' => 'AMELIORER LE FONCTIONNEMENT, LA PERFORMANCE DES SERVICES ET DU PERSONNEL'],
            'tache' => ['libelle' => 'SALAIRE BRUT DE BASE'],
        ];

        return $generator->afficher('certificat_engagement', $donnees);
    }

    public function bonCommande(PdfGenerator $generator)
    {
        $donnees = [
            'service' => ['libelle' => 'RESSOURCES HUMAINES'],
            'numero' => 'BE25-1542',
            'created_at' => now(),
            'fournisseur' => [
                'nom' => 'AGENT COMPTABLE',
                'adresse' => 'Yaoundé',
                'telephone' => '237 222 21 20 18',
                'numero_contribuable' => 'M012345678',
            ],
            'quantite' => 1,
            'designation' => 'SALAIRE DE BASE NOVEMBRE 2025',
            'prix_unitaire' => 47648407,
            'montant_total' => 47648407,
            'montant_ht' => 47648407,
            'montant_tva' => 0,
            'montant_ttc' => 47648407,
        ];

        return $generator->afficher('bon_commande', $donnees);
    }
}
