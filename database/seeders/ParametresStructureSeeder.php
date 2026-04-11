<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ParametresStructure;
use Illuminate\Support\Facades\DB;

class ParametresStructureSeeder extends Seeder
{
    /**
     * Seed des paramètres de structure par défaut
     */
    public function run(): void
    {
        // Désactiver tous les paramétrages existants
        DB::table('parametres_structure')->update(['actif' => false]);

        // Créer le paramétrage du CHU Yaoundé (exemple basé sur votre fichier)
        ParametresStructure::create([
            // Informations principales
            'nom_structure' => 'HOPITAL GENERAL DE YAOUNDE',
            'sigle' => 'HGY',
            'logo' => null, // À uploader via l'interface

            // Coordonnées
            'adresse' => 'NGOUSSO - YAOUNDE',
            'ville' => 'Yaoundé',
            'pays' => 'Cameroun',
            'telephone' => '+237 222 23 40 20',
            'fax' => '+237 222 23 13 80',
            'email' => 'contact@hopitalgeneraldeyaounde.cm',
            'site_web' => 'https://www.hopitalgeneraldeyaounde.cm',
            'boite_postale' => 'BP 1364',

            // Informations officielles
            'ministere_tutelle' => 'MINISTERE DE LA SANTE PUBLIQUE',
            'numero_contribuable' => 'M000000000000X',
            'rccm' => null,

            // En-tête bilingue
            'pays_gauche' => 'REPUBLIQUE DU CAMEROUN',
            'pays_droite' => 'REPUBLIC OF CAMEROON',
            'devise_gauche' => 'Paix – Travail - Patrie',
            'devise_droite' => 'Peace – Work - Fatherland',

            // Hiérarchie administrative
            'direction_generale' => 'DIRECTION GENERALE',
            'direction_generale_en' => 'DIRECTORATE GENERAL',
            'sous_direction' => 'SOUS-DIRECTION DES FINANCES ET DE LA COMPTABILITE',
            'sous_direction_en' => 'SUB-DEPARTMENT OF FINANCES AND ACCOUNTING',

            // Signatures
            'nom_ordonnateur' => 'Prof. Noël Emmanuel ESSOMBA',
            'fonction_ordonnateur' => 'LE DIRECTEUR GENERAL DE HGY',
            'nom_comptable' => 'Mme EBOUA Nadine',
            'fonction_comptable' => 'LE CHEF DE L\'AGENCE COMPTABLE',

            // Paramètres
            'taux_tva_defaut' => 19.25,
            'monnaie' => 'FCFA',
            'actif' => true,
            'exercice_courant' => now()->year,
        ]);

        $this->command->info('✅ Paramètres structure créés avec succès (HGY)');
    }
}
