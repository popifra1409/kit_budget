<?php
// database/seeders/TypesOrdreServiceSeeder.php

namespace Database\Seeders;

use App\Models\TypeOrdreService;
use Illuminate\Database\Seeder;

/**
 * Référentiel initial des ordres de service : les trois actes que la procédure
 * connaît — démarrer le service, l'arrêter, le reprendre.
 *
 * Additif, comme les autres semis du module : une ligne existante n'est jamais
 * modifiée, parce que l'EP qui a rebaptisé « OS de démarrage » en « Notification de
 * début de prestations » ne doit pas voir son réglage écrasé par un `db:seed`.
 *
 * `demarre_lexecution` est posée à true sur la ligne de démarrage, et sur elle
 * seule : c'est le sens ordinaire du mot, et le seul que le code ne peut pas
 * deviner. Cette case se relit et se change à l'écran du paramétrage — la règle
 * « tel type met le marché en exécution » appartient à l'EP, pas à ce fichier.
 */
class TypesOrdreServiceSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['demarrage',        'Ordre de service de démarrage', 'Met le marché en cours d\'exécution et ouvre son délai.', true],
            ['arret_service',    'Ordre de service d\'arrêt de service', 'Suspend le service pour une cause imputable à l\'administration.', false],
            ['reprise_service',  'Ordre de service de reprise de service', 'Remet le service en cours après un arrêt.', false],
        ];

        $ordre = 0;
        foreach ($types as [$code, $libelle, $description, $demarre]) {
            TypeOrdreService::firstOrCreate(['code' => $code], [
                'libelle'            => $libelle,
                'description'        => $description,
                'demarre_lexecution' => $demarre,
                'ordre'              => ++$ordre,
                'actif'              => true,
            ]);
        }

        $this->command?->info('✓ Types d\'ordre de service : ' . TypeOrdreService::count() . ' (aucun existant modifié)');
    }
}
