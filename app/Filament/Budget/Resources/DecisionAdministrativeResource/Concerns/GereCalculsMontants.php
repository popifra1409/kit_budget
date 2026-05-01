<?php

namespace App\Filament\Budget\Resources\DecisionAdministrativeResource\Concerns;

trait GereCalculsMontants
{
    public static function calculerMontants(array $data): array
    {
        $brut    = (float) ($data['montant_brut'] ?? 0);
        $tauxTva = (float) ($data['taux_tva']     ?? 19.25);

        if ($brut <= 0) return $data;

        $ht = $tauxTva > 0
            ? round($brut / (1 + $tauxTva / 100), 2)
            : $brut;

        $data['montant_ht']  = $ht;
        $data['montant_tva'] = round($brut - $ht, 2);

        $data['montant_cnps'] = round(
            $ht * ((float) ($data['taux_cnps'] ?? 0) / 100),
            2
        );

        $data['montant_irnc'] = round(
            $ht * ((float) ($data['taux_irnc'] ?? 0) / 100),
            2
        );

        if (($data['type_redevance_audiovisuelle'] ?? 'forfait') === 'taux') {
            $data['montant_redevance_audiovisuelle'] = round(
                $ht * ((float) ($data['taux_redevance_audiovisuelle'] ?? 0) / 100),
                2
            );
        } else {
            $data['montant_redevance_audiovisuelle'] = (float) ($data['montant_redevance_audiovisuelle'] ?? 0);
        }

        if (($data['type_feicom'] ?? 'forfait') === 'taux') {
            $data['montant_feicom'] = round(
                $ht * ((float) ($data['taux_feicom'] ?? 0) / 100),
                2
            );
        } else {
            $data['montant_feicom'] = (float) ($data['montant_feicom'] ?? 0);
        }

        $data['autres_retenues'] = (float) ($data['autres_retenues'] ?? 0);

        $data['total_taxes'] = $data['montant_cnps']
            + $data['montant_irnc']
            + $data['montant_redevance_audiovisuelle']
            + $data['montant_feicom']
            + $data['autres_retenues'];

        $data['montant_net'] = round($ht - $data['total_taxes'], 2);

        return $data;
    }

    public static function definirBeneficiaire(array $data): array
    {
        if (!isset($data['type_beneficiaire'])) return $data;

        if ($data['type_beneficiaire'] === 'personnel' && isset($data['personnel_id'])) {
            $data['beneficiaire_type'] = \App\Models\Personnel::class;
            $data['beneficiaire_id']   = $data['personnel_id'];
            $data['fournisseur_id']    = null;
        } elseif ($data['type_beneficiaire'] === 'fournisseur' && isset($data['fournisseur_id'])) {
            $data['beneficiaire_type'] = \App\Models\Fournisseur::class;
            $data['beneficiaire_id']   = $data['fournisseur_id'];
            $data['personnel_id']      = null;
        }

        return $data;
    }

    public static function garantirValeursParDefaut(array $data): array
    {
        $data['taux_cnps']                   = $data['taux_cnps']   ?? 0;
        $data['taux_irnc']                   = $data['taux_irnc']   ?? 0;
        $data['taux_tva']                    = $data['taux_tva']    ?? 19.25;
        $data['taux_redevance_audiovisuelle'] = $data['taux_redevance_audiovisuelle'] ?? 0;
        $data['taux_feicom']                 = $data['taux_feicom'] ?? 0;

        $data['montant_cnps']                   = $data['montant_cnps']   ?? 0;
        $data['montant_irnc']                   = $data['montant_irnc']   ?? 0;
        $data['montant_tva']                    = $data['montant_tva']    ?? 0;
        $data['montant_redevance_audiovisuelle'] = $data['montant_redevance_audiovisuelle'] ?? 0;
        $data['montant_feicom']                 = $data['montant_feicom'] ?? 0;
        $data['autres_retenues']                = $data['autres_retenues'] ?? 0;
        $data['total_taxes']                    = $data['total_taxes']    ?? 0;

        $data['reference_decision'] = $data['reference_decision'] ?? '';
        $data['signataire']         = $data['signataire']         ?? '';
        $data['observations']       = $data['observations']       ?? '';

        $data['type_redevance_audiovisuelle'] = $data['type_redevance_audiovisuelle'] ?? 'forfait';
        $data['type_feicom']                 = $data['type_feicom']                  ?? 'forfait';

        return $data;
    }

    protected function preparerDonnees(array $data): array
    {
        $mode = $data['mode_saisie'] ?? 'calcule';

        if ($mode === 'forfait') {
            // ✅ Neutraliser les taux pour bloquer l'observer
            $data['taux_tva']                    = 0;
            $data['taux_cnps']                   = 0;
            $data['taux_irnc']                   = 0;
            $data['taux_redevance_audiovisuelle'] = 0;
            $data['taux_feicom']                 = 0;
            $data['type_tva']                    = 'forfait';

            // ✅ Conserver les montants saisis — forcer leur présence
            $data['montant_cnps']                   = (float) ($data['montant_cnps']                   ?? 0);
            $data['montant_irnc']                   = (float) ($data['montant_irnc']                   ?? 0);
            $data['montant_redevance_audiovisuelle'] = (float) ($data['montant_redevance_audiovisuelle'] ?? 0);
            $data['montant_feicom']                 = (float) ($data['montant_feicom']                  ?? 0);
            $data['autres_retenues']                = (float) ($data['autres_retenues']                 ?? 0);
            $data['montant_brut']                   = (float) ($data['montant_brut']                    ?? 0);
            $data['montant_ht']                     = (float) ($data['montant_ht']                      ?? 0);
            $data['montant_tva']                    = (float) ($data['montant_tva']                     ?? 0);

            // ✅ Recalculer total et net pour cohérence
            $data['total_taxes'] = $data['montant_cnps']
                + $data['montant_irnc']
                + $data['montant_redevance_audiovisuelle']
                + $data['montant_feicom']
                + $data['autres_retenues'];

            // montant_net = saisi par l'utilisateur — ne pas écraser
            $data['montant_net'] = (float) ($data['montant_net'] ?? ($data['montant_ht'] - $data['total_taxes']));

            $data = static::definirBeneficiaire($data);
            $data = static::garantirValeursParDefaut($data);

            return $data;
        }

        // Mode calculé
        $data = static::calculerMontants($data);
        $data = static::definirBeneficiaire($data);
        $data = static::garantirValeursParDefaut($data);

        return $data;
    }
}
