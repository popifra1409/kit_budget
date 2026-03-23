<?php

namespace App\Filament\Budget\Resources\DecisionAdministrativeResource\Pages;

use App\Filament\Budget\Resources\DecisionAdministrativeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDecisionAdministrative extends CreateRecord
{
    protected static string $resource = DecisionAdministrativeResource::class;

    /**
     * ✅ Rediriger vers la page View après création
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * ✅ Message de succès personnalisé
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return '✅ Décision créée avec succès';
    }


    /**
     * ✅ MÉTHODE PRINCIPALE : Mutate avant création
     * 
     * Combine :
     * 1. Calcul des montants (via calculerMontants)
     * 2. Définition du bénéficiaire (personnel ou fournisseur)
     * 3. Garantie des valeurs par défaut
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 1️⃣ Calculer les montants (HT, taxes, retenues, net)
        $data = self::calculerMontants($data);

        // 2️⃣ Définir beneficiaire_type et beneficiaire_id selon le choix
        $data = self::definirBeneficiaire($data);

        // 3️⃣ Garantir les valeurs par défaut pour éviter les erreurs NULL
        $data = self::garantirValeursParDefaut($data);

        return $data;
    }



    /**
     * Calcul CNPS / IRNC / Taxes / Net
     */
    // protected static function calculerMontants(array $data): array
    // {
    //     $brut = (float) ($data['montant_brut'] ?? 0);
    //     $tauxCnps = (float) ($data['taux_cnps'] ?? 4.2);
    //     $tauxIrnc = (float) ($data['taux_irnc'] ?? 11);
    //     $autresRetenues = (float) ($data['autres_retenues'] ?? 0);

    //     $data['montant_cnps'] = $brut * ($tauxCnps / 100);
    //     $data['montant_irnc'] = $brut * ($tauxIrnc / 100);

    //     $data['total_taxes'] =
    //         $data['montant_cnps'] +
    //         $data['montant_irnc'] +
    //         $autresRetenues;

    //     $data['montant_net'] = $brut - $data['total_taxes'];

    //     return $data;
    // }

    /**
     * ✅ MÉTHODE 1 : Calculer les montants
     * 
     * Votre méthode existante (à garder telle quelle)
     */
    protected static function calculerMontants(array $data): array
    {
        // ✅ VOTRE CODE EXISTANT ICI
        // Cette méthode doit calculer :
        // - montant_ht (à partir de montant_brut et taux_tva)
        // - montant_tva
        // - montant_cnps
        // - montant_irnc
        // - montant_redevance_audiovisuelle_calcule
        // - montant_feicom_calcule
        // - total_taxes
        // - montant_net

        // Exemple de base (adaptez selon votre logique) :
        $brut = (float) ($data['montant_brut'] ?? 0);
        $tauxTva = (float) ($data['taux_tva'] ?? 19.25);

        if ($brut > 0) {
            // Calculer HT : HT = Brut / (1 + TVA/100)
            $data['montant_ht'] = $brut / (1 + ($tauxTva / 100));

            // Calculer montant TVA
            $data['montant_tva'] = $brut - $data['montant_ht'];

            // Calculer les retenues sur HT
            $ht = $data['montant_ht'];

            // CNPS
            $tauxCnps = (float) ($data['taux_cnps'] ?? 0);
            $data['montant_cnps'] = $ht * ($tauxCnps / 100);

            // IRNC
            $tauxIrnc = (float) ($data['taux_irnc'] ?? 0);
            $data['montant_irnc'] = $ht * ($tauxIrnc / 100);

            // Redevance audiovisuelle
            $typeRedevance = $data['type_redevance_audiovisuelle'] ?? 'forfait';
            if ($typeRedevance === 'taux') {
                $tauxRedevance = (float) ($data['taux_redevance_audiovisuelle'] ?? 0);
                $data['montant_redevance_audiovisuelle_calcule'] = $ht * ($tauxRedevance / 100);
            } else {
                $data['montant_redevance_audiovisuelle_calcule'] = (float) ($data['montant_redevance_audiovisuelle'] ?? 0);
            }

            // FEICOM
            $typeFeicom = $data['type_feicom'] ?? 'forfait';
            if ($typeFeicom === 'taux') {
                $tauxFeicom = (float) ($data['taux_feicom'] ?? 0);
                $data['montant_feicom_calcule'] = $ht * ($tauxFeicom / 100);
            } else {
                $data['montant_feicom_calcule'] = (float) ($data['montant_feicom'] ?? 0);
            }

            // Autres retenues
            $autresRetenues = (float) ($data['autres_retenues'] ?? 0);

            // Total taxes (retenues uniquement, pas la TVA)
            $data['total_taxes'] = $data['montant_cnps']
                + $data['montant_irnc']
                + $data['montant_redevance_audiovisuelle_calcule']
                + $data['montant_feicom_calcule']
                + $autresRetenues;

            // Montant net = HT - Total retenues
            $data['montant_net'] = $ht - $data['total_taxes'];
        }
        return $data;
    }

    /**
     * ✅ Définir beneficiaire_type et beneficiaire_id
     */
    protected static function definirBeneficiaire(array $data): array
    {
        if (isset($data['type_beneficiaire'])) {
            if ($data['type_beneficiaire'] === 'personnel' && isset($data['personnel_id'])) {
                // Bénéficiaire = Personnel
                $data['beneficiaire_type'] = \App\Models\Personnel::class;
                $data['beneficiaire_id'] = $data['personnel_id'];
                $data['fournisseur_id'] = null; // Nettoyer
            } elseif ($data['type_beneficiaire'] === 'fournisseur' && isset($data['fournisseur_id'])) {
                // Bénéficiaire = Fournisseur
                $data['beneficiaire_type'] = \App\Models\Fournisseur::class;
                $data['beneficiaire_id'] = $data['fournisseur_id'];
                $data['personnel_id'] = null; // Nettoyer
            }
        }

        return $data;
    }

    /**
     * ✅ Garantir les valeurs par défaut (évite erreurs NULL)
     */
    protected static function garantirValeursParDefaut(array $data): array
    {
        // Taux → 0 par défaut
        $data['taux_cnps'] = $data['taux_cnps'] ?? 0;
        $data['taux_irnc'] = $data['taux_irnc'] ?? 0;
        $data['taux_tva'] = $data['taux_tva'] ?? 19.25;
        $data['taux_redevance_audiovisuelle'] = $data['taux_redevance_audiovisuelle'] ?? 0;
        $data['taux_feicom'] = $data['taux_feicom'] ?? 0;

        // Montants calculés → 0 par défaut
        $data['montant_cnps'] = $data['montant_cnps'] ?? 0;
        $data['montant_irnc'] = $data['montant_irnc'] ?? 0;
        $data['montant_tva'] = $data['montant_tva'] ?? 0;
        $data['montant_redevance_audiovisuelle_calcule'] = $data['montant_redevance_audiovisuelle_calcule'] ?? 0;
        $data['montant_feicom_calcule'] = $data['montant_feicom_calcule'] ?? 0;
        $data['autres_retenues'] = $data['autres_retenues'] ?? 0;
        $data['total_taxes'] = $data['total_taxes'] ?? 0;

        // Textes → chaîne vide par défaut
        $data['reference_decision'] = $data['reference_decision'] ?? '';
        $data['signataire'] = $data['signataire'] ?? '';
        $data['observations'] = $data['observations'] ?? '';

        // Types → valeurs par défaut
        $data['type_redevance_audiovisuelle'] = $data['type_redevance_audiovisuelle'] ?? 'forfait';
        $data['type_feicom'] = $data['type_feicom'] ?? 'forfait';

        return $data;
    }
}
