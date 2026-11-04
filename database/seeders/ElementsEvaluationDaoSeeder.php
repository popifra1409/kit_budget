<?php
// database/seeders/ElementsEvaluationDaoSeeder.php

namespace Database\Seeders;

use App\Models\ElementEvaluationDao;
use Illuminate\Database\Seeder;

/**
 * Référentiel initial des éléments d'évaluation du DAO : les trois volets du schéma
 * (pièce administrative, pièce financière, description technique).
 *
 * Additif, et volontairement réduit aux volets : les « étape 1, étape 2 » du schéma
 * sont des lignes à ajouter à l'écran dans le volet correspondant, parce que les
 * pièces exigées dépendent de l'EP et ne doivent pas être inventées par un semis.
 */
class ElementsEvaluationDaoSeeder extends Seeder
{
    public function run(): void
    {
        $elements = [
            ['administrative', 'piece_administrative', 'Pièce administrative'],
            ['financiere',     'piece_financiere',     'Pièce financière'],
            ['technique',      'description_technique', 'Description technique'],
        ];

        $ordre = 0;
        foreach ($elements as [$famille, $code, $libelle]) {
            ElementEvaluationDao::firstOrCreate(['code' => $code], [
                'famille'     => $famille,
                'libelle'     => $libelle,
                'bareme_max'  => 20,
                'obligatoire' => true,
                'ordre'       => ++$ordre,
                'actif'       => true,
            ]);
        }

        $this->command?->info('✓ Éléments d\'évaluation du DAO : ' . ElementEvaluationDao::count() . ' (aucun existant modifié)');
    }
}
