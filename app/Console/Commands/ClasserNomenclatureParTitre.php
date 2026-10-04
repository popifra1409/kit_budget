<?php
// app/Console/Commands/ClasserNomenclatureParTitre.php

namespace App\Console\Commands;

use App\Services\Programmation\TitreNomenclatureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Renseigne le titre CBMT des lignes de nomenclature d'après config/cbmt.php.
 *
 *   php artisan nomenclature:titres                       → simulation
 *   php artisan nomenclature:titres --appliquer           → lignes SANS titre uniquement
 *   php artisan nomenclature:titres --appliquer --forcer  → toutes les lignes (écrase les corrections manuelles)
 */
class ClasserNomenclatureParTitre extends Command
{
    protected $signature = 'nomenclature:titres
                            {--appliquer : Enregistrer (sinon simulation)}
                            {--forcer : Reclasser aussi les lignes qui ont déjà un titre}';

    protected $description = 'Classe les lignes de nomenclature par titre CBMT (règles de config/cbmt.php)';

    public function handle(TitreNomenclatureService $service): int
    {
        $appliquer = (bool) $this->option('appliquer');
        $forcer = (bool) $this->option('forcer');

        $lignes = DB::table('nomenclature_budgetaire')
            ->where('niveau', config('cbmt.niveau_ligne', 'paragraphe'))
            ->whereNull('deleted_at')
            ->orderBy('type')->orderBy('code')
            ->get(['id', 'code', 'libelle', 'type', 'titre']);

        $aTraiter = $lignes->filter(fn($l) => $forcer || $l->titre === null)
            ->map(fn($l) => (object) array_merge((array) $l, ['propose' => $service->titrePropose($l->code, $l->type)]));

        // Synthèse par type et par titre proposé
        $synthese = $aTraiter->groupBy(fn($l) => $l->type . '|' . ($l->propose ?? '—'))
            ->map(fn($g, $k) => [...explode('|', $k), $g->count(), $g->first()->code . ' ' . mb_strimwidth($g->first()->libelle, 0, 45, '…')])
            ->sortKeys()->values()->all();

        $this->table(['Type', 'Titre proposé', 'Lignes', 'Exemple'], $synthese);

        $sansRegle = $aTraiter->whereNull('propose');
        if ($sansRegle->isNotEmpty()) {
            $this->warn("{$sansRegle->count()} ligne(s) sans règle : elles resteront sans titre (à classer à la main).");
            $sansRegle->take(10)->each(fn($l) => $this->line("   {$l->type} {$l->code} {$l->libelle}"));
        }

        if (!$appliquer) {
            $this->info('Simulation : relancez avec --appliquer pour enregistrer.');
            return self::SUCCESS;
        }

        $n = 0;
        DB::transaction(function () use ($aTraiter, &$n) {
            foreach ($aTraiter->whereNotNull('propose') as $l) {
                DB::table('nomenclature_budgetaire')->where('id', $l->id)->update(['titre' => $l->propose, 'updated_at' => now()]);
                $n++;
            }
        });

        $this->info("✅ {$n} ligne(s) classée(s).");
        return self::SUCCESS;
    }
}
